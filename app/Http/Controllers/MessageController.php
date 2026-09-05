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

    /* Extraction du fil de discussion ou initialisation d'une nouvelle session de messagerie */
    public function getConversation(Request $request)
    {
        $conversation_id = $request->input('conversation_id');
        $enseignant_id = $request->input('enseignant_id');
        $parent_id = $request->input('parent_id');

        $ecoleId = $request->attributes->get('school')?->id;

        if ($conversation_id) {
            $conversationQuery = Conversation::where('id', $conversation_id);
        } else if ($enseignant_id && $parent_id) {
            $conversationQuery = Conversation::where('enseignant_id', $enseignant_id)->where('parent_id', $parent_id);
        } else {
            return response()->json(['error' => 'conversation_id ou (enseignant_id et parent_id) requis'], 400);
        }

        if ($ecoleId) {
            $conversationQuery->where('ecole_id', $ecoleId);
        }

        $conversation = $conversationQuery->first();

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

        $is_online = false;
        if ($viewer_type === 'parent' && $conversation->enseignant_id) {
            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')->where('id', $conversation->enseignant_id)->first();
            if ($enseignant && $enseignant->last_seen_at) {
                $is_online = \Carbon\Carbon::parse($enseignant->last_seen_at)->diffInMinutes(\Carbon\Carbon::now()) <= 2;
            }
        } elseif ($viewer_type === 'enseignant' && $conversation->parent_id) {
            $parent = \App\Models\ParentUser::find($conversation->parent_id);
            if ($parent && $parent->last_seen_at) {
                $is_online = \Carbon\Carbon::parse($parent->last_seen_at)->diffInMinutes(\Carbon\Carbon::now()) <= 2;
            }
        }

        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'subject' => $conversation->subject,
            'is_online' => $is_online,
            'messages' => $messages
        ]);
    }

    /* Processus d'initialisation sécurisée d'une conversation avec demande d'opt-in */
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

        $ecoleId = $request->attributes->get('school')?->id;

        $conversation = Conversation::firstOrCreate(
            ['enseignant_id' => $request->enseignant_id, 'parent_id' => $request->parent_id, 'ecole_id' => $ecoleId],
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

        /* Routage de la demande d'ouverture de canal vers l'interlocuteur cible */
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
                /* Scénario d'initialisation par le corps professoral vers les familles */
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
                            'action' => 'validate_conversation',
                            'sent_at' => now()->timestamp
                        ]
                    );
                }
            } else {
                /* Scénario d'initialisation par les familles vers le corps professoral */
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
                            'action' => 'validate_conversation',
                            'sent_at' => now()->timestamp
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur notification initiation conversation : ' . $e->getMessage());
        }
    }

    /* Traitement de l'opt-in : Validation ou rejet de la demande de liaison */
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
            $this->sendConversationStatusNotification($conversation, 'rejected', $request->user());
        } elseif ($request->status === 'accepted') {
            $this->sendConversationStatusNotification($conversation, 'accepted', $request->user());
        }

        return response()->json(['success' => true, 'conversation' => $conversation]);
    }

    /**
     * Envoyer une notification lors du changement de statut d'une conversation
     */
    private function sendConversationStatusNotification($conversation, $status, $actionUser = null)
    {
        try {
            $parent = ParentUser::find($conversation->parent_id);
            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                ->where('id', $conversation->enseignant_id)
                ->first();

            if (!$parent || !$enseignant) return;

            $parentName = trim("{$parent->prenom} {$parent->nom}");
            $enseignantName = trim("{$enseignant->prenom} {$enseignant->nom}");

            if ($status === 'rejected') {
                $title = "❌ Liaison refusée";
                $body = "{$parentName} a refusé la demande de discussion.";

                $payload = [
                    'type'            => 'chat_rejected',
                    'conversation_id' => (string)$conversation->id,
                    'status'          => 'rejected',
                    'sent_at'         => now()->timestamp
                ];

                $isParentAction = $actionUser && get_class($actionUser) === 'App\Models\ParentUser';

                /* Routage du refus vers l'enseignant lorsque la famille décline la liaison */
                if ($isParentAction && !empty($enseignant->fcm_token)) {
                    $this->notificationService->sendAndSave('enseignant', $enseignant->id, $enseignant->fcm_token, $title, $body, $payload);
                } 
                /* Routage du refus vers la famille lorsque l'enseignant décline la liaison */
                elseif (!$isParentAction && !empty($parent->fcm_token)) {
                    $this->notificationService->sendAndSave('parent', $parent->id, $parent->fcm_token, $title, $body, $payload);
                }

            } elseif ($status === 'accepted') {

                $isParentAction = $actionUser && get_class($actionUser) === 'App\Models\ParentUser';


                $titleForTeacher = "✅ Liaison acceptée";
                $bodyForTeacher  = "{$parentName} a accepté la demande de discussion.";

                $titleForParent = "✅ Liaison établie";
                $bodyForParent  = "Vous êtes désormais autorisé à communiquer avec {$enseignantName}. Vos échanges sont sécurisés et une copie est conservée par l'administration de l'établissement conformément au règlement interne.";

                /* Résolution du contexte élèves rattaché à cette liaison parent-enseignant */
                $enfantsIds = \Illuminate\Support\Facades\DB::table('eleve_parents')
                    ->where('parent_id', $parent->id)
                    ->pluck('eleve_id');

                $enseignantClasses = \Illuminate\Support\Facades\DB::table('classe_enseignant')
                    ->where('enseignant_id', $enseignant->id)
                    ->pluck('classe_id');


                $elevesCibles = \App\Models\Eleve::whereIn('id', $enfantsIds)
                    ->whereIn('classe_id', $enseignantClasses)
                    ->get();

                /* Fallback de sécurité : Sélection du premier enfant rattaché au compte parent en l'absence de correspondance stricte */
                if ($elevesCibles->isEmpty()) {
                    $firstEleve = \App\Models\Eleve::find($enfantsIds->first());
                    if ($firstEleve) $elevesCibles = collect([$firstEleve]);
                }

                /* Déclenchement de la notification d'acceptation de liaison */
                $payloadPush = [
                    'type'            => 'chat_accepted',
                    'conversation_id' => (string)$conversation->id,
                    'status'          => 'accepted',
                    'action'          => 'open_chat',
                    'sent_at'         => now()->timestamp
                ];

                if ($isParentAction) {
                    if (!empty($enseignant->fcm_token)) {
                        try {
                            $this->notificationService->sendAndSave(
                                'enseignant',
                                $enseignant->id,
                                $enseignant->fcm_token,
                                $titleForTeacher,
                                $bodyForTeacher,
                                $payloadPush
                            );
                        } catch (\Exception $e) {
                            \Log::error("[LIAISON] Erreur sendAndSave pour enseignant: " . $e->getMessage());
                        }
                    }
                } else {
                    if (!empty($parent->fcm_token)) {
                        $this->notificationService->sendPushOnly(
                            $parent->fcm_token,
                            $titleForParent,
                            $bodyForParent,
                            $payloadPush
                        );
                    }
                }

                /* Génération des traces d'audit et des informations administratives pour chaque enfant lié */
                foreach ($elevesCibles as $eleve) {
                    $enfantPrenom = $eleve->prenom ?? 'votre enfant';
                    $infoMessage = "Vous êtes désormais autorisé à communiquer avec {$enseignantName} concernant {$enfantPrenom}. Vos échanges sont sécurisés et une copie est conservée par l'administration de l'établissement conformément au règlement interne.";


                    \App\Models\AdminInformation::create([
                        'eleve_id' => $eleve->id,
                        'type'     => 'info',
                        'titre'    => 'Liaison de discussion établie',
                        'contenu'  => $infoMessage,
                        'is_read'  => false
                    ]);


                    $notifParentPayload = [
                        'type'            => 'chat_accepted',
                        'conversation_id' => (string)$conversation->id,
                        'status'          => 'accepted',
                        'enseignant_nom'  => $enseignantName,
                        'eleve_id'        => (string)$eleve->id,
                        'child_name'      => $enfantPrenom,
                    ];
                    
                    \App\Models\Notification::create([
                        'user_type' => 'parent',
                        'user_id'   => $parent->id,
                        'type'      => 'chat_accepted',
                        'title'     => $titleForParent,
                        'message'   => $infoMessage,
                        'data'      => $notifParentPayload,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur notification statut conversation : ' . $e->getMessage());
        }
    }


    /* Traitement et persistance d'un nouveau message dans une conversation établie */
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

        $fichierUrl = null;
        if ($request->hasFile('fichier')) {
            $path = $request->file('fichier')->store('communications', 'public');
            $fichierUrl = (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $path;
        }

        $message = Message::create([
            'conversation_id' => $request->conversation_id,
            'sender_type' => $request->sender_type,
            'sender_id' => $request->sender_id,
            'content' => $request->content,
            'fichier_url' => $fichierUrl,
            'is_read' => false
        ]);

        /* Déclenchement conditionnel des alertes Push pour les nouveaux messages */
        $senderName = $request->sender_type === 'enseignant' ? "Un enseignant" : ($request->sender_type === 'parent' ? "Un parent" : "L'École");
        $title = "Nouveau message de {$senderName}";
        $body = substr($request->content, 0, 100) . (strlen($request->content) > 100 ? '...' : '');

        /* Scénario : Message émis par l'établissement (Enseignant/Admin) vers une famille */
        if ($request->sender_type !== 'parent') {
            if ($conversation && $conversation->parent_id) {
                $parent = ParentUser::find($conversation->parent_id);
                if ($parent && !empty($parent->fcm_token)) {
                    $this->notificationService->sendPushOnly(
                        $parent->fcm_token,
                        $title,
                        $body,
                        [
                            'conversation_id' => (string) $conversation->id,
                            'type' => 'teacher_message'
                        ]
                    );
                }
            }
        }

        /* Scénario : Message émis par une famille vers le corps professoral */
        if ($request->sender_type === 'parent' && $conversation && $conversation->enseignant_id) {
            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')
                ->where('id', $conversation->enseignant_id)
                ->first();
            if ($enseignant && !empty($enseignant->fcm_token)) {
                $this->notificationService->sendPushOnly(
                    $enseignant->fcm_token,
                    $title,
                    $body,
                    [
                        'conversation_id' => (string) $conversation->id,
                        'type' => 'parent_message'
                    ]
                );
            }
        }

        return response()->json($message, 201);
    }

    public function getConversationsForParent($parent_id)
    {
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->leftJoin('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
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
                'ecoles.nom as admin_name'
            )
            ->get();

        /* Optimisation: Récupération en une seule fois des métadonnées (N+1 évité) */
        $conversationIds = $conversations->pluck('conversation_id')->toArray();
        
        $unreadCounts = \Illuminate\Support\Facades\DB::table('messages')
            ->select('conversation_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->whereIn('conversation_id', $conversationIds)
            ->where('sender_type', '!=', 'parent')
            ->where('is_read', false)
            ->groupBy('conversation_id')
            ->pluck('count', 'conversation_id');

        /* Optionnel: Prendre le premier élève du parent pour l'affichage UI */
        $eleve = \Illuminate\Support\Facades\DB::table('eleve_parents')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
            ->where('eleve_parents.parent_id', $parent_id)
            ->select('eleves.nom', 'eleves.prenom', 'classes.nom as classe_nom')
            ->first();

        foreach ($conversations as $conv) {
            $conv->unread_count = $unreadCounts->get($conv->conversation_id, 0);
            if ($eleve) {
                $conv->eleve_nom = $eleve->nom;
                $conv->eleve_prenom = $eleve->prenom;
                $conv->classe_nom = $eleve->classe_nom;
            } else {
                $conv->eleve_nom = null;
                $conv->eleve_prenom = null;
                $conv->classe_nom = null;
            }
        }

        return response()->json([
            'success'       => true,
            'conversations' => $conversations,
        ]);
    }

    /* Extraction des canaux de communication rattachés à un enseignant spécifique */
    public function getConversationsForTeacher($enseignant_id)
    {
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->leftJoin('parent_users', 'conversations.parent_id', '=', 'parent_users.id')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
            ->where('conversations.enseignant_id', $enseignant_id)
            ->select(
                'conversations.id as conversation_id',
                'conversations.parent_id',
                'conversations.status',
                'conversations.subject',
                'parent_users.nom as parent_nom',
                'parent_users.prenom as parent_prenom',
                'ecoles.nom as admin_name'
            )
            ->get();

        /* Optimisation (N+1) : Récupérer en masse les derniers messages et compteurs */
        $conversationIds = $conversations->pluck('conversation_id')->toArray();
        $parentIds = $conversations->pluck('parent_id')->filter()->unique()->toArray();

        $unreadCounts = \Illuminate\Support\Facades\DB::table('messages')
            ->select('conversation_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->whereIn('conversation_id', $conversationIds)
            ->where('sender_type', '!=', 'enseignant')
            ->where('is_read', false)
            ->groupBy('conversation_id')
            ->pluck('count', 'conversation_id');

        /* Dernière version du message par conversation en utilisant ROW_NUMBER ou une sous-requête (optimisation simple) */
        $lastMessages = \Illuminate\Support\Facades\DB::table('messages')
            ->whereIn('id', function($q) use ($conversationIds) {
                $q->select(\Illuminate\Support\Facades\DB::raw('MAX(id)'))->from('messages')->whereIn('conversation_id', $conversationIds)->groupBy('conversation_id');
            })->get()->keyBy('conversation_id');

        /* Précharger le premier élève de chaque parent pour affichage */
        $eleves = collect();
        if (!empty($parentIds)) {
            $eleves = \Illuminate\Support\Facades\DB::table('eleve_parents')
                ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
                ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
                ->whereIn('eleve_parents.parent_id', $parentIds)
                ->select('eleve_parents.parent_id', 'eleves.nom', 'eleves.prenom', 'classes.nom as classe_nom')
                ->get()
                ->groupBy('parent_id')
                ->map(function($items) { return $items->first(); });
        }

        /* Hydratation des métadonnées sans requêtes dans la boucle */
        foreach ($conversations as $conv) {
            $lastMessage = $lastMessages->get($conv->conversation_id);
            $conv->last_message = $lastMessage ? $lastMessage->content : 'Aucun message';
            $conv->last_message_time = $lastMessage ? \Carbon\Carbon::parse($lastMessage->created_at)->format('H:i') : '';
            $conv->unread_count = $unreadCounts->get($conv->conversation_id, 0);

            if ($conv->parent_id && $eleves->has($conv->parent_id)) {
                $eleve = $eleves->get($conv->parent_id);
                $conv->eleve_nom = $eleve->nom;
                $conv->eleve_prenom = $eleve->prenom;
                $conv->classe_nom = $eleve->classe_nom;
            } else {
                $conv->eleve_nom = null;
                $conv->eleve_prenom = null;
                $conv->classe_nom = null;
            }
        }

        return response()->json([
            'success' => true,
            'conversations' => $conversations
        ]);
    }

    /* Extraction des canaux de communication administratifs rattachés à un enseignant */
    public function getAdminConversationsForTeacher($enseignant_id)
    {
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
            ->where('conversations.enseignant_id', $enseignant_id)
            ->whereNull('conversations.parent_id')
            ->select(
                'conversations.id as conversation_id',
                'conversations.ecole_id',
                'conversations.status',
                'conversations.subject',
                'ecoles.nom as admin_name',
                'ecoles.id as admin_id'
            )
            ->get();

        $conversationIds = $conversations->pluck('conversation_id')->toArray();

        $unreadCounts = \Illuminate\Support\Facades\DB::table('messages')
            ->select('conversation_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->whereIn('conversation_id', $conversationIds)
            ->where('sender_type', '!=', 'enseignant')
            ->where('is_read', false)
            ->groupBy('conversation_id')
            ->pluck('count', 'conversation_id');

        $lastMessages = \Illuminate\Support\Facades\DB::table('messages')
            ->whereIn('id', function($q) use ($conversationIds) {
                $q->select(\Illuminate\Support\Facades\DB::raw('MAX(id)'))->from('messages')->whereIn('conversation_id', $conversationIds)->groupBy('conversation_id');
            })->get()->keyBy('conversation_id');

        /* Hydratation des métadonnées des conversations administratives */
        foreach ($conversations as $conv) {
            $lastMessage = $lastMessages->get($conv->conversation_id);
                
            $conv->last_message = $lastMessage ? $lastMessage->content : 'Aucun message';
            $conv->last_message_time = $lastMessage ? \Carbon\Carbon::parse($lastMessage->created_at)->format('H:i') : '';
            $conv->unread_count = $unreadCounts->get($conv->conversation_id, 0);
        }

        return response()->json([
            'success' => true,
            'conversations' => $conversations
        ]);
    }
}
