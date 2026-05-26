<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ParentUser; // Assuming parent model
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminMessageController extends Controller
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    // 1. Envoyer un message depuis l'Admin (appweb) vers un Parent
    public function sendMessageToParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ecole_id' => 'required|integer',
            'parent_id' => 'required|integer',
            'type' => 'required|string', // ex: "frais impayés", "convocation", "réunion", "personnalisé"
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // On cherche une conversation Admin ↔ Parent pour cette école
        $conversation = Conversation::firstOrCreate([
            'ecole_id' => $request->ecole_id,
            'enseignant_id' => null, // C'est l'administration qui parle
            'parent_id' => $request->parent_id,
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'admin',
            'sender_id' => $request->ecole_id, // L'identifiant de l'admin/école
            'content' => $request->content,
            'is_read' => false
        ]);

        // Push notification logic
        $parent = ParentUser::find($request->parent_id);
        if ($parent && !empty($parent->fcm_token)) {
            $title = "Nouveau message de l'Administration";
            // For example, display a preview
            $body = substr($request->content, 0, 50) . (strlen($request->content) > 50 ? '...' : '');

            $this->notificationService->sendToToken($parent->fcm_token, $title, $body, [
                'conversation_id' => $conversation->id,
                'type' => 'admin_message'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé au parent avec succès.',
            'data' => $message
        ], 201);
    }

    // 2. Supervision des échanges (Admin web -> /admin/signalements)
    public function getCommunications(Request $request)
    {
        $ecole_id = $request->query('ecole_id'); // Idéalement via auth()
        
        // On récupère toutes les conversations Parent ↔ Enseignant (enseignant_id != NULL)
        $conversations = DB::table('conversations')
            ->join('parents', 'conversations.parent_id', '=', 'parents.id')
            ->join('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            // Jointure pour récupérer l'enfant associé au parent
            ->join('eleve_parents', 'parents.id', '=', 'eleve_parents.parent_id')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->join('ecoles', 'eleves.ecole_id', '=', 'ecoles.id')
            ->whereNotNull('conversations.enseignant_id')
            ->where('ecoles.id', $ecole_id)
            ->select(
                'conversations.id as conversation_id',
                'parents.nom as parent_nom',
                'parents.prenom as parent_prenom',
                'enseignants.nom as enseignant_nom',
                'enseignants.prenom as enseignant_prenom',
                'eleves.id as eleve_id',
                'eleves.nom as eleve_nom',
                'eleves.prenom as eleve_prenom',
                'eleves.photo_url as eleve_photo',
                'eleves.code_secret as eleve_code_secret',
                'ecoles.nom as ecole_nom'
            )
            ->groupBy(
                'conversations.id', 'parents.nom', 'parents.prenom', 'enseignants.nom', 'enseignants.prenom',
                'eleves.id', 'eleves.nom', 'eleves.prenom', 'eleves.photo_url', 'eleves.code_secret', 'ecoles.nom'
            )
            ->get();

        return response()->json([
            'success' => true,
            'communications' => $conversations
        ]);
    }
}
