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
            'ecole_id'        => 'required|integer',
            'type'            => 'required|string',
            'content'         => 'required|string',
            'parent_id'       => 'nullable|integer',
            'classe_id'       => 'nullable|integer',
            'eleve_id'        => 'nullable|integer',
            'montant'         => 'nullable|numeric',
            'montant_paye'    => 'nullable|numeric',
            'montant_restant' => 'nullable|numeric',
            'titre'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Récupérer les élèves concernés au lieu de juste les parents, pour les admin_informations
        $elevesList = [];

        // Cas 1 : Envoi à une classe entière
        if ($request->filled('classe_id')) {
            $elevesList = DB::table('eleves')
                ->where('classe_id', $request->classe_id)
                ->pluck('id')
                ->toArray();
        }
        // Cas 2 : Envoi à un élève spécifique
        elseif ($request->filled('eleve_id')) {
            $elevesList = [$request->eleve_id];
        }
        // Cas 3 : Envoi à un parent unique (on récupère tous ses enfants dans l'école)
        elseif ($request->filled('parent_id')) {
            $elevesList = DB::table('eleve_parents')
                ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
                ->join('classes', 'eleves.classe_id', '=', 'classes.id')
                ->where('eleve_parents.parent_id', $request->parent_id)
                ->where('classes.ecole_id', $request->ecole_id)
                ->pluck('eleves.id')
                ->unique()
                ->toArray();
        }
        // Cas 4 : Envoi global à toute l'école
        else {
            $elevesList = DB::table('eleves')
                ->join('classes', 'eleves.classe_id', '=', 'classes.id')
                ->where('classes.ecole_id', $request->ecole_id)
                ->pluck('eleves.id')
                ->toArray();
        }

        if (empty($elevesList)) {
            return response()->json(['error' => 'Aucun élève/destinataire trouvé.'], 400);
        }

        $sentCount = 0;
        $parentIdsSet = [];

        foreach ($elevesList as $eleveId) {
            // Créer l'AdminInformation si ce n'est pas textuel
            if ($request->type !== 'textual') {
                \App\Models\AdminInformation::create([
                    'eleve_id'        => $eleveId,
                    'type'            => $request->type, // finance, convocation, info
                    'titre'           => $request->titre ?? 'Information Administration',
                    'contenu'         => $request->content,
                    'montant'         => $request->montant,
                    'montant_paye'    => $request->montant_paye,
                    'montant_restant' => $request->montant_restant,
                    'is_read'         => false,
                ]);
            }

            // Récupérer les parents de cet élève
            $parents = DB::table('eleve_parents')
                ->where('eleve_id', $eleveId)
                ->pluck('parent_id');

            foreach ($parents as $parentId) {
                // Créer Conversation & Message uniquement pour textual
                if ($request->type === 'textual') {
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
                }

                // Notification push unique par parent pour cet envoi
                if (!in_array($parentId, $parentIdsSet)) {
                    $parentIdsSet[] = $parentId;
                    $parent = ParentUser::find($parentId);
                    
                    if ($parent && !empty($parent->fcm_token)) {
                        $title = $request->type === 'finance' ? "Nouvelle information financière" : "Nouveau message de l'Administration";
                        $body  = substr($request->content, 0, 100) . (strlen($request->content) > 100 ? '...' : '');

                        $this->notificationService->sendToToken($parent->fcm_token, $title, $body, [
                            'type' => $request->type === 'textual' ? 'admin_message' : 'admin_info',
                            'eleve_id' => (string) $eleveId,
                        ]);
                    }
                    $sentCount++;
                }
            }
        }

        return response()->json([
            'success'    => true,
            'message'    => "Message traité et envoyé à {$sentCount} parent(s) avec succès.",
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
            ->join('classes', 'eleves.classe_id', '=', 'classes.id')
            ->join('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->whereNotNull('conversations.enseignant_id')
            ->when($ecole_id, fn($q) => $q->where('ecoles.id', $ecole_id))
            ->where('conversations.status', 'accepted')
            ->select(
                'conversations.id as conversation_id',
                'conversations.status',
                'conversations.subject',
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
                'conversations.status',
                'conversations.subject',
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

    public function getAdminInformations($eleve_id)
    {
        $infos = DB::table('admin_informations')
            ->where('eleve_id', $eleve_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success'      => true,
            'informations' => $infos,
        ]);
    }
}
