<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // Récupérer les messages d'une conversation (ou la créer si elle n'existe pas)
    public function getConversation(Request $request)
    {
        $conversation_id = $request->input('conversation_id');
        $enseignant_id = $request->input('enseignant_id');
        $parent_id = $request->input('parent_id');

        if ($conversation_id) {
            $conversation = Conversation::find($conversation_id);
        } else if ($enseignant_id && $parent_id) {
            $conversation = Conversation::where('enseignant_id', $enseignant_id)->where('parent_id', $parent_id)->first();
        } else {
            return response()->json(['error' => 'conversation_id ou (enseignant_id et parent_id) requis'], 400);
        }

        if (!$conversation) {
            return response()->json(['error' => 'Conversation non trouvée'], 404);
        }

        $viewer_type = $request->input('viewer_type');
        if ($viewer_type) {
            $conversation->messages()
                ->where('sender_type', '!=', $viewer_type)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'subject' => $conversation->subject,
            'messages' => $messages
        ]);
    }

    // Initier une conversation (Opt-in)
    public function initiateConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enseignant_id' => 'required|integer',
            'parent_id' => 'required|integer',
            'subject' => 'required|string',
            'initial_message' => 'required|string',
            'sender_type' => 'required|in:enseignant,parent'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $conversation = Conversation::firstOrCreate(
            ['enseignant_id' => $request->enseignant_id, 'parent_id' => $request->parent_id],
            ['status' => 'pending', 'subject' => $request->subject]
        );

        if ($conversation->status !== 'pending' && $conversation->status !== 'accepted') {
            $conversation->status = 'pending';
            $conversation->subject = $request->subject;
            $conversation->save();
        }

        $sender_id = $request->sender_type === 'enseignant' ? $request->enseignant_id : $request->parent_id;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $request->sender_type,
            'sender_id' => $sender_id,
            'content' => $request->initial_message,
            'is_read' => false
        ]);

        // Envoyer notification push à l'autre partie
        $this->sendConversationInitiationNotification($conversation, $request->sender_type, $request->initial_message);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'message' => $message
        ]);
    }

    /**
     * Envoyer une notification lors de l'initiation d'une conversation
     */
    private function sendConversationInitiationNotification($conversation, $senderType, $messageContent)
    {
        try {
            if ($senderType === 'enseignant') {
                // L'enseignant initie, on notifie le parent
                $parent = ParentUser::find($conversation->parent_id);
                $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                    ->where('id', $conversation->enseignant_id)
                    ->first();

                if ($parent && !empty($parent->fcm_token) && $enseignant) {
                    $enseignantName = trim("{$enseignant->prenom} {$enseignant->nom}");
                    $title = " Nouveau message de {$enseignantName}";
                    $body = substr($messageContent, 0, 100) . (strlen($messageContent) > 100 ? '...' : '');

                    $this->notificationService->sendAndSave(
                        'parent',
                        $parent->id,
                        $parent->fcm_token,
                        $title,
                        $body,
                        [
                            'type' => 'new_conversation_request',
                            'conversation_id' => (string)$conversation->id,
                            'enseignant_id' => (string)$conversation->enseignant_id,
                            'enseignant_nom' => $enseignantName,
                            'subject' => $conversation->subject,
                            'status' => 'pending',
                            'action' => 'validate_conversation' // Le parent doit valider la liaison
                        ]
                    );
                }
            } else {
                // Le parent initie, on notifie l'enseignant
                $parent = ParentUser::find($conversation->parent_id);
                $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                    ->where('id', $conversation->enseignant_id)
                    ->first();

                if ($enseignant && !empty($enseignant->fcm_token) && $parent) {
                    $parentName = trim("{$parent->prenom} {$parent->nom}");
                    $title = "💬 Nouveau message de {$parentName}";
                    $body = substr($messageContent, 0, 100) . (strlen($messageContent) > 100 ? '...' : '');

                    $this->notificationService->sendAndSave(
                        'enseignant',
                        $enseignant->id,
                        $enseignant->fcm_token,
                        $title,
                        $body,
                        [
                            'type' => 'new_conversation_request',
                            'conversation_id' => (string)$conversation->id,
                            'parent_id' => (string)$conversation->parent_id,
                            'parent_nom' => $parentName,
                            'subject' => $conversation->subject,
                            'status' => 'pending',
                            'action' => 'validate_conversation' // L'enseignant doit valider la liaison
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur notification initiation conversation : ' . $e->getMessage());
        }
    }

    // Accepter ou Refuser la conversation
    public function updateConversationStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $conversation = Conversation::find($id);
        if (!$conversation) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $conversation->status = $request->status;
        $conversation->save();

        if ($request->status === 'rejected') {
            $this->sendConversationStatusNotification($conversation, 'rejected');
        } elseif ($request->status === 'accepted') {
            $this->sendConversationStatusNotification($conversation, 'accepted');
        }

        return response()->json(['success' => true, 'conversation' => $conversation]);
    }

    /**
     * Envoyer une notification lors du changement de statut d'une conversation
     */
    private function sendConversationStatusNotification($conversation, $status)
    {
        try {
            $firstMessage = $conversation->messages()->orderBy('created_at', 'asc')->first();
            if (!$firstMessage) return;

            $senderWasEnseignant = $firstMessage->sender_type === 'enseignant';

            if ($status === 'rejected') {
                // Notifier l'expéditeur du premier message que sa demande a été refusée
                if ($senderWasEnseignant) {
                    // L'enseignant avait initié, on notifie l'enseignant que le parent a refusé
                    $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                        ->where('id', $conversation->enseignant_id)
                        ->first();
                    if ($enseignant && !empty($enseignant->fcm_token)) {
                        $parent = ParentUser::find($conversation->parent_id);
                        $parentName = $parent ? trim("{$parent->prenom} {$parent->nom}") : "Le parent";
                        $this->notificationService->sendAndSave(
                            'enseignant',
                            $enseignant->id,
                            $enseignant->fcm_token,
                            "❌ Liaison refusée",
                            "{$parentName} a refusé d'établir une discussion avec vous.",
                            [
                                'type' => 'chat_rejected',
                                'conversation_id' => (string)$conversation->id,
                                'status' => 'rejected'
                            ]
                        );
                    }
                } else {
                    // Le parent avait initié, on notifie le parent que l'enseignant a refusé
                    $parent = ParentUser::find($conversation->parent_id);
                    $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                        ->where('id', $conversation->enseignant_id)
                        ->first();
                    if ($parent && !empty($parent->fcm_token) && $enseignant) {
                        $enseignantName = trim("{$enseignant->prenom} {$enseignant->nom}");
                        $this->notificationService->sendAndSave(
                            'parent',
                            $parent->id,
                            $parent->fcm_token,
                            "❌ Liaison refusée",
                            "{$enseignantName} a refusé d'établir une discussion avec vous.",
                            [
                                'type' => 'chat_rejected',
                                'conversation_id' => (string)$conversation->id,
                                'status' => 'rejected'
                            ]
                        );
                    }
                }
            } elseif ($status === 'accepted') {
                // Notifier l'expéditeur du premier message que sa demande a été acceptée
                if ($senderWasEnseignant) {
                    // L'enseignant avait initié, on notifie l'enseignant que le parent a accepté
                    // (mais l'enseignant sait déjà qu'il a envoyé le message, donc on pourrait ne pas notifier)
                    // On notifie plutôt le parent que la conversation est active
                    $parent = ParentUser::find($conversation->parent_id);
                    $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                        ->where('id', $conversation->enseignant_id)
                        ->first();
                    if ($parent && !empty($parent->fcm_token) && $enseignant) {
                        $enseignantName = trim("{$enseignant->prenom} {$enseignant->nom}");
                        $this->notificationService->sendAndSave(
                            'parent',
                            $parent->id,
                            $parent->fcm_token,
                            "✅ Liaison acceptée",
                            "Vous pouvez maintenant discuter avec {$enseignantName}.",
                            [
                                'type' => 'chat_accepted',
                                'conversation_id' => (string)$conversation->id,
                                'status' => 'accepted',
                                'action' => 'open_chat'
                            ]
                        );
                    }
                } else {
                    // Le parent avait initié, on notifie le parent que l'enseignant a accepté
                    $parent = ParentUser::find($conversation->parent_id);
                    $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                        ->where('id', $conversation->enseignant_id)
                        ->first();
                    if ($parent && !empty($parent->fcm_token) && $enseignant) {
                        $enseignantName = trim("{$enseignant->prenom} {$enseignant->nom}");
                        $this->notificationService->sendAndSave(
                            'parent',
                            $parent->id,
                            $parent->fcm_token,
                            "✅ Liaison acceptée",
                            "{$enseignantName} a accepté votre demande de discussion.",
                            [
                                'type' => 'chat_accepted',
                                'conversation_id' => (string)$conversation->id,
                                'status' => 'accepted',
                                'action' => 'open_chat'
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur notification statut conversation : ' . $e->getMessage());
        }
    }

    // Envoyer un nouveau message
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:conversations,id',
            'sender_type' => 'required|in:enseignant,parent,admin',
            'sender_id' => 'required|integer',
            'content' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $conversation = Conversation::find($request->conversation_id);
        if ($conversation->status !== 'accepted' && $conversation->enseignant_id !== null) {
            return response()->json(['error' => 'Conversation non acceptée'], 403);
        }

        $message = Message::create([
            'conversation_id' => $request->conversation_id,
            'sender_type' => $request->sender_type,
            'sender_id' => $request->sender_id,
            'content' => $request->content,
            'is_read' => false
        ]);

        // Envoyer une notification push au destinataire
        $senderName = $request->sender_type === 'enseignant' ? "Un enseignant" : ($request->sender_type === 'parent' ? "Un parent" : "L'École");
        $title = "Nouveau message de {$senderName}";
        $body = substr($request->content, 0, 100) . (strlen($request->content) > 100 ? '...' : '');

        // Notifier le parent si l'expéditeur est l'enseignant ou admin
        if ($request->sender_type !== 'parent') {
            if ($conversation && $conversation->parent_id) {
                $parent = ParentUser::find($conversation->parent_id);
                if ($parent && !empty($parent->fcm_token)) {
                    $this->notificationService->sendAndSave('parent', $parent->id, $parent->fcm_token, $title, $body, [
                        'conversation_id' => (string) $conversation->id,
                        'type' => 'teacher_message'
                    ]);
                }
            }
        }

        // Notifier l'enseignant si l'expéditeur est le parent
        if ($request->sender_type === 'parent' && $conversation && $conversation->enseignant_id) {
            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                ->where('id', $conversation->enseignant_id)
                ->first();
            if ($enseignant && !empty($enseignant->fcm_token)) {
                $this->notificationService->sendAndSave('enseignant', $enseignant->id, $enseignant->fcm_token, $title, $body, [
                    'conversation_id' => (string) $conversation->id,
                    'type' => 'parent_message'
                ]);
            }
        }

        return response()->json($message, 201);
    }

    public function getConversationsForParent($parent_id)
    {
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->leftJoin('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
            ->leftJoin('eleve_parents', 'conversations.parent_id', '=', 'eleve_parents.parent_id')
            ->leftJoin('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
            ->where('conversations.parent_id', $parent_id)
            ->select(
                'conversations.id as conversation_id',
                'conversations.id',
                'conversations.ecole_id',
                'conversations.enseignant_id',
                'conversations.status',
                'conversations.subject',
                'enseignants.nom as enseignant_nom',
                'enseignants.prenom as enseignant_prenom',
                'enseignants.matiere as enseignant_matiere',
                'ecoles.nom as admin_name',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom',
                'classes.nom as classe_nom',
                \Illuminate\Support\Facades\DB::raw('(SELECT COUNT(*) FROM messages WHERE messages.conversation_id = conversations.id AND messages.sender_type != "parent" AND messages.is_read = false) as unread_count')
            )
            ->groupBy(
                'conversations.id', 'conversations.ecole_id', 'conversations.enseignant_id',
                'conversations.status', 'conversations.subject',
                'enseignants.nom', 'enseignants.prenom', 'enseignants.matiere',
                'ecoles.nom', 'eleves.nom', 'eleves.prenom', 'classes.nom'
            )
            ->get();

        return response()->json([
            'success'       => true,
            'conversations' => $conversations,
        ]);
    }

    // Obtenir toutes les conversations pour un enseignant
    public function getConversationsForTeacher($enseignant_id)
    {
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->leftJoin('parent_users', 'conversations.parent_id', '=', 'parent_users.id')
            ->where('conversations.enseignant_id', $enseignant_id)
            ->select(
                'conversations.id as conversation_id',
                'conversations.parent_id',
                'conversations.status',
                'conversations.subject',
                'parent_users.nom as parent_nom',
                'parent_users.prenom as parent_prenom'
            )
            ->get();

        // Pour chaque conversation, récupérer le dernier message et le contexte de l'élève
        foreach ($conversations as $conv) {
            $lastMessage = Message::where('conversation_id', $conv->conversation_id)
                ->orderBy('created_at', 'desc')
                ->first();
                
            $conv->last_message = $lastMessage ? $lastMessage->content : 'Aucun message';
            $conv->last_message_time = $lastMessage ? $lastMessage->created_at->format('H:i') : '';

            $eleve = \Illuminate\Support\Facades\DB::table('eleve_parents')
                ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->where('eleve_parents.parent_id', $conv->parent_id)
                ->select('eleves.nom', 'eleves.prenom', 'classes.nom as classe_nom')
                ->first();

            if ($eleve) {
                $conv->eleve_nom = $eleve->nom;
                $conv->eleve_prenom = $eleve->prenom;
                $conv->classe_nom = $eleve->classe_nom;
            }
        }

        return response()->json([
            'success' => true,
            'conversations' => $conversations
        ]);
    }

    // Obtenir les conversations admin-enseignant pour un enseignant
    public function getAdminConversationsForTeacher($enseignant_id)
    {
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
            ->where('conversations.enseignant_id', $enseignant_id)
            ->whereNull('conversations.parent_id') // Conversations sans parent = admin
            ->select(
                'conversations.id as conversation_id',
                'conversations.ecole_id',
                'conversations.status',
                'conversations.subject',
                'ecoles.nom as admin_name',
                'ecoles.id as admin_id'
            )
            ->get();

        // Pour chaque conversation, récupérer le dernier message
        foreach ($conversations as $conv) {
            $lastMessage = Message::where('conversation_id', $conv->conversation_id)
                ->orderBy('created_at', 'desc')
                ->first();
                
            $conv->last_message = $lastMessage ? $lastMessage->content : 'Aucun message';
            $conv->last_message_time = $lastMessage ? $lastMessage->created_at->format('H:i') : '';

            // Compter les messages non lus
            $unreadCount = Message::where('conversation_id', $conv->conversation_id)
                ->where('sender_type', '!=', 'enseignant')
                ->where('is_read', false)
                ->count();
            $conv->unread_count = $unreadCount;
        }

        return response()->json([
            'success' => true,
            'conversations' => $conversations
        ]);
    }
}
