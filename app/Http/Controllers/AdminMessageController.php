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
            'classe_id'       => 'nullable',
            'niveaux'         => 'nullable|array',
            'eleve_id'        => 'nullable|integer',
            'enseignant_id'   => 'nullable|integer',
            'tous_enseignants'=> 'nullable|boolean',
            'montant'         => 'nullable|numeric',
            'montant_paye'    => 'nullable|numeric',
            'montant_restant' => 'nullable|numeric',
            'titre'           => 'nullable|string',
            'fichier'         => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $content = $request->content;

        if ($request->hasFile('fichier')) {
            $path = $request->file('fichier')->store('communications', 'public');
            $fileUrl = (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $path;
            
            $content .= "\n\nPièce jointe : " . $fileUrl;
        }

        // Cas des enseignants
        $isTeacherMessage = $request->filled('enseignant_id') || $request->tous_enseignants;
        
        if ($isTeacherMessage) {
            $enseignantsList = [];
            if ($request->tous_enseignants) {
                // Get all teachers from this school
                $enseignantsList = DB::table('classe_enseignant')
                    ->join('classes', 'classe_enseignant.classe_id', '=', 'classes.id')
                    ->where('classes.ecole_id', $request->ecole_id)
                    ->pluck('classe_enseignant.enseignant_id')
                    ->unique()
                    ->toArray();
            } else {
                $enseignantsList = [$request->enseignant_id];
            }

            if (empty($enseignantsList)) {
                return response()->json(['error' => 'Aucun enseignant destinataire trouvé.'], 400);
            }

            $sentCount = 0;
            foreach ($enseignantsList as $ensId) {
                $conversation = Conversation::firstOrCreate(
                    [
                        'ecole_id'      => $request->ecole_id,
                        'enseignant_id' => $ensId,
                        'parent_id'     => null, // Conversation Admin ↔ Enseignant
                    ],
                    ['status' => 'accepted']
                );

                if ($conversation->status !== 'accepted') {
                    $conversation->update(['status' => 'accepted']);
                }

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type'     => 'admin',
                    'sender_id'       => $request->ecole_id,
                    'content'         => $content,
                    'is_read'         => false,
                ]);

                // Ajouter ici notification Push pour Enseignant si implémenté

                $sentCount++;
            }

            return response()->json([
                'success'    => true,
                'message'    => "Message envoyé à {$sentCount} enseignant(s) avec succès.",
                'sent_count' => $sentCount,
            ], 201);
        }

        // Récupérer les élèves concernés au lieu de juste les parents, pour les admin_informations
        $elevesList = [];

        // Cas 1 : Envoi à une classe entière ou plusieurs classes, ou par niveaux
        if ($request->filled('classe_id') || $request->filled('niveaux')) {
            $query = DB::table('eleves')
                ->join('classes', 'eleves.classe_id', '=', 'classes.id')
                ->where('classes.ecole_id', $request->ecole_id);
            
            $query->where(function($q) use ($request) {
                if ($request->filled('classe_id')) {
                    // classe_id peut être un int ou un array (ex: [1, 2])
                    $classes = is_array($request->classe_id) ? $request->classe_id : explode(',', $request->classe_id);
                    $q->whereIn('classe_id', $classes);
                }
                if ($request->filled('niveaux')) {
                    $niveaux = is_array($request->niveaux) ? $request->niveaux : explode(',', $request->niveaux);
                    $q->orWhere(function($subQ) use ($niveaux) {
                        foreach ($niveaux as $niveau) {
                            $subQ->orWhere('classes.nom', 'LIKE', $niveau . ' %')
                                 ->orWhere('classes.nom', 'LIKE', $niveau . '-%')
                                 ->orWhere('classes.nom', $niveau);
                        }
                    });
                }
            });

            $elevesList = $query->pluck('eleves.id')->unique()->toArray();
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
            $adminInfo = null;

            if ($request->type !== 'textual') {
                $adminInfo = \App\Models\AdminInformation::create([
                    'eleve_id'        => $eleveId,
                    'type'            => $request->type,
                    'titre'           => $request->titre ?? 'Information Administration',
                    'contenu'         => $content,
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
                    $conversation = Conversation::firstOrCreate(
                        [
                            'ecole_id'      => $request->ecole_id,
                            'enseignant_id' => null,
                            'parent_id'     => $parentId,
                        ],
                        [
                            'status' => 'accepted'
                        ]
                    );

                    // If it already existed but wasn't accepted, update it
                    if ($conversation->status !== 'accepted') {
                        $conversation->update(['status' => 'accepted']);
                    }

                    Message::create([
                        'conversation_id' => $conversation->id,
                        'sender_type'     => 'admin',
                        'sender_id'       => $request->ecole_id,
                        'content'         => $content,
                        'is_read'         => false,
                    ]);
                }

                if (!in_array($parentId, $parentIdsSet)) {
                    $parentIdsSet[] = $parentId;
                    $parent = ParentUser::find($parentId);
                    
                    if ($parent && !empty($parent->fcm_token)) {
                        $title = $request->type === 'finance' ? "Nouvelle information financière" : "Nouveau message de l'Administration";
                        $body  = substr($content, 0, 100) . (strlen($content) > 100 ? '...' : '');

                        $notificationData = [
                            'type' => $request->type === 'textual' ? 'admin_message' : 'admin_info',
                            'eleve_id' => (string) $eleveId,
                        ];

                        if ($request->type === 'textual' && isset($conversation)) {
                            $notificationData['conversation_id'] = (string) $conversation->id;
                        } elseif ($adminInfo) {
                            $notificationData['admin_info_id'] = (string) $adminInfo->id;
                        }

                        $this->notificationService->sendAndSave('parent', $parentId, $parent->fcm_token, $title, $body, $notificationData);
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

    // Récupérer les conversations où l'Admin est impliqué (Admin <-> Parent ou Admin <-> Enseignant)
    public function getAdminConversations(Request $request)
    {
        $ecole_id = $request->query('ecole_id');

        // Admin <-> Parents
        $parentConversations = DB::table('conversations')
            ->join('parent_users', 'conversations.parent_id', '=', 'parent_users.id')
            ->leftJoin('messages', function ($join) {
                $join->on('messages.conversation_id', '=', 'conversations.id')
                     ->whereRaw('messages.id = (select max(id) from messages where conversation_id = conversations.id)');
            })
            ->whereNull('conversations.enseignant_id')
            ->where('conversations.ecole_id', $ecole_id)
            ->select(
                'conversations.id as conversation_id',
                'parent_users.nom as interlocuteur_nom',
                'parent_users.prenom as interlocuteur_prenom',
                DB::raw("'Parent' as interlocuteur_type"),
                'messages.content as last_message',
                'messages.created_at as last_message_date'
            );

        // Admin <-> Enseignants
        $enseignantConversations = DB::table('conversations')
            ->join('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->leftJoin('messages', function ($join) {
                $join->on('messages.conversation_id', '=', 'conversations.id')
                     ->whereRaw('messages.id = (select max(id) from messages where conversation_id = conversations.id)');
            })
            ->whereNull('conversations.parent_id')
            ->where('conversations.ecole_id', $ecole_id)
            ->select(
                'conversations.id as conversation_id',
                'enseignants.nom as interlocuteur_nom',
                'enseignants.prenom as interlocuteur_prenom',
                DB::raw("'Enseignant' as interlocuteur_type"),
                'messages.content as last_message',
                'messages.created_at as last_message_date'
            );

        $conversations = $parentConversations->union($enseignantConversations)
            ->orderBy('last_message_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'conversations' => $conversations
        ]);
    }

    public function getAdminMessages($conversation_id)
    {
        $messages = Message::where('conversation_id', $conversation_id)
            ->orderBy('created_at', 'asc')
            ->get();
            
        // Marquer les messages entrants comme lus
        Message::where('conversation_id', $conversation_id)
            ->where('sender_type', '!=', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    // Récupérer les messages d'une conversation pour la supervision (sans marquer comme lu)
    public function getMonitoringMessages($conversation_id)
    {
        $messages = Message::where('conversation_id', $conversation_id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    public function replyAdminMessage(Request $request, $conversation_id)
    {
        $request->validate([
            'content' => 'required|string',
            'ecole_id'=> 'required|integer',
        ]);

        $conversation = Conversation::find($conversation_id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation introuvable'], 404);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => 'admin',
            'sender_id'       => $request->ecole_id,
            'content'         => $request->content,
            'is_read'         => false,
        ]);

        // Push notification logic could be added here
        
        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }
}
