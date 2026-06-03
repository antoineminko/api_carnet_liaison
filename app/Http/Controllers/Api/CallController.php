<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Call;
use App\Models\CallSignaling;
use App\Models\Conversation;
use App\Models\ParentUser;
use App\Services\PushNotificationService;

class CallController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Initier un appel (audio ou vidéo)
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'caller_id' => 'required|integer',
            'caller_type' => 'required|in:enseignant,parent',
            'type' => 'required|in:audio,video',
        ]);

        $conversation = Conversation::find($request->conversation_id);

        if ($conversation->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'La conversation doit être acceptée pour passer un appel.'
            ], 403);
        }

        // Déterminer le receveur
        $receiverId = $request->caller_type === 'enseignant'
            ? $conversation->parent_id
            : $conversation->enseignant_id;
        $receiverType = $request->caller_type === 'enseignant' ? 'parent' : 'enseignant';

        // Créer l'appel
        $call = Call::create([
            'conversation_id' => $request->conversation_id,
            'caller_id' => $request->caller_id,
            'caller_type' => $request->caller_type,
            'receiver_id' => $receiverId,
            'receiver_type' => $receiverType,
            'type' => $request->type,
            'status' => 'ringing',
        ]);

        // Envoyer notification push au receveur
        $this->sendCallNotification($call, 'ringing');

        return response()->json([
            'success' => true,
            'call' => $call,
            'message' => 'Appel initié.',
        ], 201);
    }

    /**
     * Accepter un appel
     */
    public function accept($id)
    {
        $call = Call::findOrFail($id);

        if ($call->status !== 'ringing') {
            return response()->json([
                'success' => false,
                'message' => 'L\'appel n\'est plus en cours.'
            ], 400);
        }

        $call->update([
            'status' => 'accepted',
            'started_at' => now(),
        ]);

        // Notifier l'appelant
        $this->sendCallNotification($call, 'accepted');

        return response()->json([
            'success' => true,
            'call' => $call,
            'message' => 'Appel accepté.',
        ]);
    }

    /**
     * Rejeter un appel
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string',
        ]);

        $call = Call::findOrFail($id);

        if ($call->status !== 'ringing') {
            return response()->json([
                'success' => false,
                'message' => 'L\'appel n\'est plus en cours.'
            ], 400);
        }

        $call->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        // Notifier l'appelant
        $this->sendCallNotification($call, 'rejected', $request->reason);

        return response()->json([
            'success' => true,
            'call' => $call,
            'message' => 'Appel rejeté.',
        ]);
    }

    /**
     * Terminer un appel
     */
    public function end($id)
    {
        $call = Call::findOrFail($id);

        if ($call->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'L\'appel n\'est pas en cours.'
            ], 400);
        }

        $endedAt = now();
        $duration = $call->started_at ? $endedAt->diffInSeconds($call->started_at) : 0;

        $call->update([
            'status' => 'ended',
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
        ]);

        return response()->json([
            'success' => true,
            'call' => $call,
            'message' => 'Appel terminé.',
        ]);
    }

    /**
     * Marquer un appel comme manqué (appelé par un cron ou après un timeout)
     */
    public function markAsMissed($id)
    {
        $call = Call::findOrFail($id);

        if ($call->status === 'ringing') {
            $call->update(['status' => 'missed']);

            // Notifier l'appelant
            $this->sendCallNotification($call, 'missed');
        }

        return response()->json([
            'success' => true,
            'call' => $call,
        ]);
    }

    /**
     * Liste des appels pour une conversation
     */
    public function index(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
        ]);

        $calls = Call::where('conversation_id', $request->conversation_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'calls' => $calls,
        ]);
    }

    /**
     * Envoyer une notification d'appel
     */
    private function sendCallNotification(Call $call, string $status, string $reason = null)
    {
        try {
            if ($status === 'ringing') {
                // Notifier le receveur
                if ($call->receiver_type === 'parent') {
                    $parent = ParentUser::find($call->receiver_id);
                    if ($parent && !empty($parent->fcm_token)) {
                        $callerName = $this->getCallerName($call);
                        $callType = $call->type === 'video' ? 'vidéo' : 'audio';

                        $this->notificationService->sendAndSave(
                            'parent',
                            $parent->id,
                            $parent->fcm_token,
                            "📞 Appel {$callType} entrant",
                            "{$callerName} vous appelle...",
                            [
                                'type' => 'incoming_call',
                                'call_id' => (string)$call->id,
                                'conversation_id' => (string)$call->conversation_id,
                                'caller_type' => $call->caller_type,
                                'caller_id' => (string)$call->caller_id,
                                'call_type' => $call->type,
                            ]
                        );
                    }
                } else {
                    // Notifier l'enseignant
                    $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                        ->where('id', $call->receiver_id)
                        ->first();
                    if ($enseignant && !empty($enseignant->fcm_token)) {
                        $callerName = $this->getCallerName($call);
                        $callType = $call->type === 'video' ? 'vidéo' : 'audio';

                        $this->notificationService->sendAndSave(
                            'enseignant',
                            $enseignant->id,
                            $enseignant->fcm_token,
                            "📞 Appel {$callType} entrant",
                            "{$callerName} vous appelle...",
                            [
                                'type' => 'incoming_call',
                                'call_id' => (string)$call->id,
                                'conversation_id' => (string)$call->conversation_id,
                                'caller_type' => $call->caller_type,
                                'caller_id' => (string)$call->caller_id,
                                'call_type' => $call->type,
                            ]
                        );
                    }
                }
            } elseif ($status === 'rejected') {
                // Notifier l'appelant du rejet
                $this->notifyCaller($call, 'rejected', $reason);
            } elseif ($status === 'missed') {
                // Notifier l'appelant de l'appel manqué
                $this->notifyCaller($call, 'missed');
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur notification appel : ' . $e->getMessage());
        }
    }

    private function getCallerName(Call $call): string
    {
        if ($call->caller_type === 'enseignant') {
            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                ->where('id', $call->caller_id)
                ->first();
            return $enseignant ? trim("{$enseignant->prenom} {$enseignant->nom}") : 'Enseignant';
        } else {
            $parent = ParentUser::find($call->caller_id);
            return $parent ? trim("{$parent->prenom} {$parent->nom}") : 'Parent';
        }
    }

    private function notifyCaller(Call $call, string $status, string $reason = null)
    {
        if ($call->caller_type === 'parent') {
            $parent = ParentUser::find($call->caller_id);
            if ($parent && !empty($parent->fcm_token)) {
                $title = $status === 'rejected' ? '❌ Appel rejeté' : 'Appel manqué';
                $body = $status === 'rejected'
                    ? ($reason ?? 'L\'appel a été rejeté.')
                    : 'L\'appel n\'a pas été décroché.';

                $this->notificationService->sendAndSave(
                    'parent',
                    $parent->id,
                    $parent->fcm_token,
                    $title,
                    $body,
                    [
                        'type' => $status === 'rejected' ? 'call_rejected' : 'call_missed',
                        'call_id' => (string)$call->id,
                        'status' => $status,
                    ]
                );
            }
        } else {
            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                ->where('id', $call->caller_id)
                ->first();
            if ($enseignant && !empty($enseignant->fcm_token)) {
                $title = $status === 'rejected' ? '❌ Appel rejeté' : 'Appel manqué';
                $body = $status === 'rejected'
                    ? ($reason ?? 'L\'appel a été rejeté.')
                    : 'L\'appel n\'a pas été décroché.';

                $this->notificationService->sendAndSave(
                    'enseignant',
                    $enseignant->id,
                    $enseignant->fcm_token,
                    $title,
                    $body,
                    [
                        'type' => $status === 'rejected' ? 'call_rejected' : 'call_missed',
                        'call_id' => (string)$call->id,
                        'status' => $status,
                    ]
                );
            }
        }
    }

    // ==================== SIGNALING WEBRTC ====================

    /**
     * Stocker une offre SDP
     */
    public function storeOffer(Request $request, $callId)
    {
        $request->validate([
            'sdp' => 'required|string',
            'type' => 'required|string',
        ]);

        CallSignaling::create([
            'call_id' => $callId,
            'type' => 'offer',
            'sdp' => $request->sdp,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Stocker une réponse SDP
     */
    public function storeAnswer(Request $request, $callId)
    {
        $request->validate([
            'sdp' => 'required|string',
            'type' => 'required|string',
        ]);

        CallSignaling::create([
            'call_id' => $callId,
            'type' => 'answer',
            'sdp' => $request->sdp,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Stocker un candidat ICE
     */
    public function storeIceCandidate(Request $request, $callId)
    {
        $request->validate([
            'candidate' => 'required|string',
            'sdpMid' => 'nullable|string',
            'sdpMLineIndex' => 'nullable|integer',
        ]);

        CallSignaling::create([
            'call_id' => $callId,
            'type' => 'ice_candidate',
            'candidate' => $request->candidate,
            'sdp_mid' => $request->sdpMid,
            'sdp_m_line_index' => $request->sdpMLineIndex,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Récupérer les données de signaling non traitées
     */
    public function getSignaling($callId)
    {
        $call = Call::findOrFail($callId);

        // Récupérer les données non traitées
        $signaling = CallSignaling::where('call_id', $callId)
            ->where('processed', false)
            ->orderBy('created_at', 'asc')
            ->get();

        // Marquer comme traitées
        CallSignaling::where('call_id', $callId)
            ->where('processed', false)
            ->update(['processed' => true]);

        // Organiser les données
        $result = [
            'offer' => null,
            'answer' => null,
            'ice_candidates' => [],
        ];

        foreach ($signaling as $item) {
            if ($item->type === 'offer') {
                $result['offer'] = [
                    'sdp' => $item->sdp,
                    'type' => 'offer',
                ];
            } elseif ($item->type === 'answer') {
                $result['answer'] = [
                    'sdp' => $item->sdp,
                    'type' => 'answer',
                ];
            } elseif ($item->type === 'ice_candidate') {
                $result['ice_candidates'][] = [
                    'candidate' => $item->candidate,
                    'sdpMid' => $item->sdp_mid,
                    'sdpMLineIndex' => $item->sdp_m_line_index,
                ];
            }
        }

        return response()->json($result);
    }
}
