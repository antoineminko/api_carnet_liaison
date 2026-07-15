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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
        $ecole = $request->attributes->get('school');
        $ecoleId = $ecole->id;

        $validator = Validator::make($request->all(), [
            'type'            => 'required|string',
            'content'         => 'required|string',
            'parent_id'       => 'nullable|integer',
            'classe_id'       => 'nullable',
            'niveaux'         => 'nullable',
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
        $fichierUrl = null;

        if ($request->hasFile('fichier')) {
            $path = $request->file('fichier')->store('communications', 'public');
            $fichierUrl = (env('APP_URL') == 'http://localhost' ? 'https://sirh.alwaysdata.net/api_carnet_liaison' : env('APP_URL', 'https://sirh.alwaysdata.net/api_carnet_liaison')) . '/storage/' . $path;
        }

        // Enregistrer le broadcast global
        $cibles = [];
        if ($request->filled('tous_etablissement')) $cibles['tous_etablissement'] = true;
        if ($request->filled('tous_enseignants')) $cibles['tous_enseignants'] = true;
        if ($request->filled('classe_id')) $cibles['classe_id'] = is_array($request->classe_id) ? $request->classe_id : explode(',', $request->classe_id);
        if ($request->filled('niveaux')) $cibles['niveaux'] = is_array($request->niveaux) ? $request->niveaux : explode(',', $request->niveaux);
        if ($request->filled('eleve_id')) $cibles['eleve_id'] = $request->eleve_id;
        if ($request->filled('parent_id')) $cibles['parent_id'] = $request->parent_id;

        \App\Models\AdminBroadcast::create([
            'ecole_id' => $ecoleId,
            'type' => $request->type ?? 'textual',
            'titre' => $request->titre ?? 'Information Administration',
            'contenu' => $content,
            'fichier_url' => $fichierUrl,
            'cibles' => $cibles,
        ]);

        // Cas des enseignants
        $isTeacherMessage = $request->filled('enseignant_id') || $request->tous_enseignants;
        
        if ($isTeacherMessage) {
            $enseignantsList = [];
            if ($request->tous_enseignants) {
                // Get all teachers from this school
                $enseignantsList = DB::table('classe_enseignant')
                    ->join('classes', 'classe_enseignant.classe_id', '=', 'classes.id')
                    ->where('classes.ecole_id', $ecoleId)
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
                        'ecole_id'      => $ecoleId,
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
                    'sender_id'       => $ecoleId,
                    'content'         => $content,
                    'fichier_url'     => $fichierUrl,
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

        // Cas 1 : Envoi à un élève spécifique
        if ($request->filled('eleve_id')) {
            $elevesList = [$request->eleve_id];
        }
        // Cas 2 : Envoi à une classe entière ou plusieurs classes, ou par niveaux
        elseif ($request->filled('classe_id') || $request->filled('niveaux')) {
            $query = DB::table('eleves')
                ->join('classes', 'eleves.classe_id', '=', 'classes.id')
                ->where('classes.ecole_id', $ecoleId);
            
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
        // Cas 3 : Envoi à un parent unique (on récupère tous ses enfants dans l'école)
        elseif ($request->filled('parent_id')) {
            $elevesList = DB::table('eleve_parents')
                ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
                ->join('classes', 'eleves.classe_id', '=', 'classes.id')
                ->where('eleve_parents.parent_id', $request->parent_id)
                ->where('classes.ecole_id', $ecoleId)
                ->pluck('eleves.id')
                ->unique()
                ->toArray();
        }
        // Cas 4 : Envoi global à toute l'école
        else {
            $elevesList = DB::table('eleves')
                ->join('classes', 'eleves.classe_id', '=', 'classes.id')
                ->where('classes.ecole_id', $ecoleId)
                ->pluck('eleves.id')
                ->toArray();
        }

        if (empty($elevesList)) {
            return response()->json(['error' => 'Aucun élève/destinataire trouvé.'], 400);
        }

        $sentCount = 0;
        $parentIdsSet = [];

        // Précharger les relations pour éviter les requêtes N+1
        $elevesParents = DB::table('eleve_parents')
            ->whereIn('eleve_id', $elevesList)
            ->get()
            ->groupBy('eleve_id');

        $allParentIds = $elevesParents->flatten()->pluck('parent_id')->unique()->toArray();
        $parentsData = ParentUser::whereIn('id', $allParentIds)->get()->keyBy('id');

        $existingConversations = Conversation::where('ecole_id', $ecoleId)
            ->whereNull('enseignant_id')
            ->whereIn('parent_id', $allParentIds)
            ->get()
            ->keyBy('parent_id');

        $messagesData = [];
        $conversationsToUpdate = [];
        $now = now();
        $processedParentsForTextual = [];

        foreach ($elevesList as $eleveId) {
            $adminInfo = null;

            if ($request->type !== 'textual') {
                $adminInfo = \App\Models\AdminInformation::create([
                    'eleve_id'        => $eleveId,
                    'type'            => $request->type,
                    'titre'           => $request->titre ?? 'Information Administration',
                    'contenu'         => $content,
                    'fichier_url'     => $fichierUrl,
                    'montant'         => $request->montant,
                    'montant_paye'    => $request->montant_paye,
                    'montant_restant' => $request->montant_restant,
                    'is_read'         => false,
                ]);
            }

            $parents = $elevesParents->get($eleveId, collect());

            foreach ($parents as $pivot) {
                $parentId = $pivot->parent_id;

                if ($request->type === 'textual') {
                    if (!in_array($parentId, $processedParentsForTextual)) {
                        if ($existingConversations->has($parentId)) {
                            $conversation = $existingConversations->get($parentId);
                            if ($conversation->status !== 'accepted') {
                                $conversationsToUpdate[] = $conversation->id;
                                $conversation->status = 'accepted';
                            }
                        } else {
                            $conversation = Conversation::create([
                                'ecole_id'      => $ecoleId,
                                'enseignant_id' => null,
                                'parent_id'     => $parentId,
                                'status'        => 'accepted'
                            ]);
                            $existingConversations->put($parentId, $conversation);
                        }

                        $messagesData[] = [
                            'conversation_id' => $conversation->id,
                            'sender_type'     => 'admin',
                            'sender_id'       => $ecoleId,
                            'content'         => $content,
                            'fichier_url'     => $fichierUrl,
                            'is_read'         => false,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                        
                        $processedParentsForTextual[] = $parentId;
                    }
                }

                if (!in_array($parentId, $parentIdsSet)) {
                    $parentIdsSet[] = $parentId;
                    $parent = $parentsData->get($parentId);
                    
                    if ($parent) {
                        $title = $request->type === 'finance' ? "Nouvelle information financière" : "Nouveau message de l'Administration";
                        $body  = substr($content, 0, 100) . (strlen($content) > 100 ? '...' : '');

                        $notificationData = [
                            'type' => $request->type === 'textual' ? 'admin_message' : 'admin_info',
                        ];

                        if ($request->type !== 'textual') {
                            $notificationData['eleve_id'] = (string) $eleveId;
                        }

                        if ($fichierUrl) {
                            $notificationData['fichier_url'] = $fichierUrl;
                        }

                        if ($request->type === 'textual' && isset($existingConversations[$parentId])) {
                            $notificationData['conversation_id'] = (string) $existingConversations[$parentId]->id;
                        } elseif (isset($adminInfo)) {
                            $notificationData['admin_info_id'] = (string) $adminInfo->id;
                        }

                        $this->notificationService->sendAndSave('parent', $parentId, $parent->fcm_token, $title, $body, $notificationData);
                    }

                    if ($parent && !empty($parent->email)) {
                        try {
                            $emailTitle = $request->type === 'finance' ? "Nouvelle information financière" : "Nouveau message de l'Administration";
                            $emailContent = "Bonjour {$parent->prenom} {$parent->nom},\n\n" . $content . "\n\nCordialement,\nL'Administration";
                            
                            Mail::raw($emailContent, function($msg) use ($parent, $emailTitle, $request) {
                                $msg->to($parent->email)
                                    ->subject($emailTitle);
                                
                                if ($request->hasFile('fichier')) {
                                    $file = $request->file('fichier');
                                    $msg->attach($file->getRealPath(), [
                                        'as' => $file->getClientOriginalName(),
                                        'mime' => $file->getClientMimeType(),
                                    ]);
                                }
                            });
                        } catch (\Exception $e) {
                            Log::error("Erreur envoi email au parent {$parent->id}: " . $e->getMessage());
                        }
                    }

                    $sentCount++;
                }
            }
        }

        if (!empty($conversationsToUpdate)) {
            Conversation::whereIn('id', $conversationsToUpdate)->update(['status' => 'accepted']);
        }

        if (!empty($messagesData)) {
            Message::insert($messagesData);
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
        $ecole_id = $request->attributes->get('school')->id;

        $conversations = DB::table('conversations')
            ->join('parent_users', 'conversations.parent_id', '=', 'parent_users.id')
            ->join('enseignants', 'conversations.enseignant_id', '=', 'enseignants.id')
            ->join('eleve_parents', 'parent_users.id', '=', 'eleve_parents.parent_id')
            ->join('eleves', 'eleve_parents.eleve_id', '=', 'eleves.id')
            ->join('classes', 'eleves.classe_id', '=', 'classes.id')
            ->join('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->whereNotNull('conversations.enseignant_id')
            ->where('ecoles.id', $ecole_id)
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

    // Récupérer les broadcasts (annonces) de l'Admin
    public function getAdminBroadcasts(Request $request)
    {
        $ecole_id = $request->attributes->get('school')->id;
        
        $broadcasts = \App\Models\AdminBroadcast::where('ecole_id', $ecole_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function($b) {
                $cibles = is_string($b->cibles) ? json_decode($b->cibles, true) : $b->cibles;
                if (!$cibles) return false;
                
                // Exclure les messages envoyés individuellement (à un seul élève ou parent)
                if (isset($cibles['eleve_id']) && count($cibles) === 1) return false;
                if (isset($cibles['parent_id']) && count($cibles) === 1) return false;
                
                return true;
            })
            ->values()
            ->map(function($b) {
                $cibles = is_string($b->cibles) ? json_decode($b->cibles, true) : $b->cibles;
                $targetLabel = 'Non spécifié';
                
                if (isset($cibles['tous_etablissement'])) {
                    $targetLabel = 'Tout l\'établissement';
                } elseif (isset($cibles['tous_enseignants'])) {
                    $targetLabel = 'Tous les enseignants';
                } elseif (isset($cibles['classe_id'])) {
                    $classes = is_array($cibles['classe_id']) ? $cibles['classe_id'] : explode(',', $cibles['classe_id']);
                    $targetLabel = count($classes) > 1 ? count($classes) . ' classes' : 'Classe spécifique';
                    
                    // Optionnel : on pourrait aller chercher le nom des classes, mais simple "X classes" ou "Classe" suffit
                    $classesNoms = DB::table('classes')->whereIn('id', $classes)->pluck('nom')->toArray();
                    if (!empty($classesNoms)) {
                        $targetLabel = implode(', ', $classesNoms);
                    }
                } elseif (isset($cibles['niveaux'])) {
                    $niveaux = is_array($cibles['niveaux']) ? $cibles['niveaux'] : explode(',', $cibles['niveaux']);
                    $targetLabel = 'Niveaux : ' . implode(', ', $niveaux);
                }

                return [
                    'id' => $b->id,
                    'type' => $b->type,
                    'titre' => $b->titre,
                    'contenu' => $b->contenu,
                    'fichier_url' => $b->fichier_url,
                    'created_at' => $b->created_at,
                    'cibles' => $cibles,
                    'target_label' => $targetLabel
                ];
            });

        return response()->json(['broadcasts' => $broadcasts]);
    }

    // Récupérer les conversations où l'Admin est impliqué (Admin <-> Parent ou Admin <-> Enseignant)
    public function getAdminConversations(Request $request)
    {
        $ecole_id = $request->attributes->get('school')->id;

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
        ]);

        $conversation = Conversation::find($conversation_id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation introuvable'], 404);
        }

        $ecoleId = $request->attributes->get('school')->id;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => 'admin',
            'sender_id'       => $ecoleId,
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
