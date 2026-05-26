<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ParentUser;
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

    // 1. Envoyer un message depuis l'Admin (appweb) vers un Parent, une Classe ou les parents d'un Élève
    public function sendMessageToParent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ecole_id'  => 'required|integer',
            'type'      => 'required|string',
            'content'   => 'required|string',
            'parent_id' => 'nullable|integer',
            'classe_id' => 'nullable|integer',
            'eleve_id'  => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $parentIds = [];

        // Cas 1 : Envoi à une classe entière
        if ($request->filled('classe_id')) {
            $parentIds = DB::table('eleve_parents')
                ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
                ->where('eleves.classe_id', $request->classe_id)
                ->pluck('eleve_parents.parent_id')
                ->unique()
                ->toArray();
        }
        // Cas 2 : Envoi aux parents d'un élève spécifique
        elseif ($request->filled('eleve_id')) {
            $parentIds = DB::table('eleve_parents')
                ->where('eleve_id', $request->eleve_id)
                ->pluck('parent_id')
                ->unique()
                ->toArray();
        }
        // Cas 3 : Envoi à un parent unique
        elseif ($request->filled('parent_id')) {
            $parentIds = [$request->parent_id];
        }

        if (empty($parentIds)) {
            return response()->json(['error' => 'Aucun destinataire trouvé.'], 400);
        }

        $sentCount = 0;

        foreach ($parentIds as $parentId) {
            $conversation = Conversation::firstOrCreate([
                'ecole_id'      => $request->ecole_id,
                'enseignant_id' => null,
                'parent_id'     => $parentId,
            ]);

            Message::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => 'admin',
                'sender_id'       => $request->ecole_id,
                'content'         => $request->content,
                'is_read'         => false,
            ]);

            // Push notification
            $parent = ParentUser::find($parentId);
            if ($parent && !empty($parent->fcm_token)) {
                $title = "Nouveau message de l'Administration";
                $body  = substr($request->content, 0, 100) . (strlen($request->content) > 100 ? '...' : '');

                $this->notificationService->sendToToken($parent->fcm_token, $title, $body, [
                    'conversation_id' => (string) $conversation->id,
                    'type'            => 'admin_message',
                ]);
            }

            $sentCount++;
        }

        return response()->json([
            'success'    => true,
            'message'    => "Message envoyé à {$sentCount} parent(s) avec succès.",
            'sent_count' => $sentCount,
        ], 201);
    }

    // 2. Supervision des échanges Parent ↔ Enseignant
    public function getCommunications(Request $request)
    {
        $ecole_id = $request->query('ecole_id');

        $conversations = DB::table('conversations')
            ->join('parent_users', 'conversations.parent_id', '=', 'parent_users.id')
            ->join('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->join('eleve_parents', 'parent_users.id', '=', 'eleve_parents.parent_id')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->join('ecoles', 'eleves.ecole_id', '=', 'ecoles.id')
            ->whereNotNull('conversations.enseignant_id')
            ->when($ecole_id, fn($q) => $q->where('ecoles.id', $ecole_id))
            ->select(
                'conversations.id as conversation_id',
                'parent_users.nom as parent_nom',
                'parent_users.prenom as parent_prenom',
                'enseignants.nom as enseignant_nom',
                'enseignants.prenom as enseignant_prenom',
                'eleves.id as eleve_id',
                'eleves.nom as eleve_nom',
                'ecoles.nom as ecole_nom'
            )
            ->groupBy(
                'conversations.id',
                'parent_users.nom', 'parent_users.prenom',
                'enseignants.nom', 'enseignants.prenom',
                'eleves.id', 'eleves.nom', 'ecoles.nom'
            )
            ->get();

        return response()->json([
            'success'        => true,
            'communications' => $conversations,
        ]);
    }
}
