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

        // Simuler notification push
        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'message' => $message
        ]);
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
            $firstMessage = $conversation->messages()->orderBy('created_at', 'asc')->first();
            if ($firstMessage) {
                try {
                    // If teacher sent first message, parent is rejecting -> notify teacher
                    if ($firstMessage->sender_type === 'enseignant') {
                        $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')->where('id', $conversation->enseignant_id)->first();
                        if ($enseignant && !empty($enseignant->fcm_token)) {
                            $parent = \App\Models\ParentUser::find($conversation->parent_id);
                            $parentName = $parent ? "{$parent->prenom} {$parent->nom}" : "Le parent";
                            $this->notificationService->sendAndSave('enseignant', $enseignant->id, $enseignant->fcm_token, "Discussion refusée", "{$parentName} a refusé d'établir une discussion avec vous.", [
                                'type' => 'chat_rejected',
                                'conversation_id' => (string)$conversation->id
                            ]);
                        }
                    } else {
                        // C'est l'enseignant qui avait envoyé le 1er message, on notifie le parent
                        $parent = \App\Models\ParentUser::find($conversation->parent_id);
                        if ($parent && !empty($parent->fcm_token)) {
                            $enseignant = \Illuminate\Support\Facades\DB::table('enseignants')->where('id', $conversation->enseignant_id)->first();
                            $enseignantName = $enseignant ? "{$enseignant->prenom} {$enseignant->nom}" : "L'enseignant";
                            $this->notificationService->sendAndSave('parent', $parent->id, $parent->fcm_token, "Discussion refusée", "{$enseignantName} a refusé d'établir une discussion avec vous.", [
                                'type' => 'chat_rejected',
                                'conversation_id' => (string)$conversation->id
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::error('Erreur Firebase (rejet conversation) : ' . $e->getMessage());
                }
            }
        }

        return response()->json(['success' => true, 'conversation' => $conversation]);
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

        // Envoyer une notification push au parent si l'expéditeur n'est pas le parent lui-même
        if ($request->sender_type !== 'parent') {
            if ($conversation && $conversation->parent_id) {
                $parent = ParentUser::find($conversation->parent_id);
                if ($parent && !empty($parent->fcm_token)) {
                    $senderName = $request->sender_type === 'enseignant' ? "Un enseignant" : "L'École";
                    $title = "Nouveau message de {$senderName}";
                    $body = substr($request->content, 0, 100) . (strlen($request->content) > 100 ? '...' : '');

                    $this->notificationService->sendAndSave('parent', $parent->id, $parent->fcm_token, $title, $body, [
                        'conversation_id' => (string) $conversation->id,
                        'type' => 'teacher_message'
                    ]);
                }
            }
        }

        return response()->json($message, 201);
    }

    // Obtenir toutes les conversations pour un parent
    public function getConversationsForParent($parent_id)
    {
        $conversations = \Illuminate\Support\Facades\DB::table('conversations')
            ->leftJoin('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->leftJoin('ecoles', 'conversations.ecole_id', '=', 'ecoles.id')
            ->where('conversations.parent_id', $parent_id)
            ->select(
                'conversations.id as conversation_id',
                'conversations.ecole_id',
                'conversations.enseignant_id',
                'conversations.status',
                'conversations.subject',
                'enseignants.nom as enseignant_nom',
                'enseignants.prenom as enseignant_prenom',
                'ecoles.nom as admin_name',
                \Illuminate\Support\Facades\DB::raw('(SELECT COUNT(*) FROM messages WHERE messages.conversation_id = conversations.id AND messages.sender_type != "parent" AND messages.is_read = false) as unread_count')
            )
            ->get();

        // Récupérer le nom de l'élève pour le contexte
        $eleve = \Illuminate\Support\Facades\DB::table('eleve_parents')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->leftJoin('classes', 'eleves.classe_id', '=', 'classes.id')
            ->where('eleve_parents.parent_id', $parent_id)
            ->select('eleves.nom', 'eleves.prenom', 'classes.nom as classe_nom')
            ->first();

        foreach ($conversations as $conv) {
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
}
