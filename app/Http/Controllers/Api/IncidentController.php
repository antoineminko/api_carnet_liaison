<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IncidentController extends Controller
{
    /**
     * Créer un nouvel incident et notifier les parents
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eleve_id' => 'nullable|exists:eleves,id',
            'eleves_ids' => 'nullable|array',
            'eleves_ids.*' => 'exists:eleves,id',
            'enseignant_id' => 'required|exists:enseignants,id',
            'classe_id' => 'nullable|exists:classes,id',
            'type' => 'required|in:retard_repete,absence_injustifiee,indiscipline,violence,insolence,non_respect,devoirs_non_faits,telephone,degradation,perturbation,autre',
            'description' => 'nullable|string|max:500',
            'date' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        // Déterminer les élèves concernés (un seul ou plusieurs)
        $elevesIds = [];
        if ($request->has('eleves_ids') && is_array($request->eleves_ids)) {
            $elevesIds = $request->eleves_ids;
        } elseif ($request->has('eleve_id')) {
            $elevesIds = [$request->eleve_id];
        }

        if (empty($elevesIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun élève sélectionné'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $createdIncidents = [];
            $enseignant = Enseignant::find($request->enseignant_id);
            $typeLabel = Incident::getTypeLabel($request->type);

            // FIX: Map the request type to allowed enum types to prevent DB error in production
            $allowedEnumTypes = ['desordre', 'bavardage', 'bagarre', 'injure', 'retenu', 'autre'];
            $dbType = in_array($request->type, $allowedEnumTypes) ? $request->type : 'autre';
            $descriptionPrefix = !in_array($request->type, $allowedEnumTypes) ? "[$typeLabel] " : "";

            $elevesAndIncidents = [];

            foreach ($elevesIds as $eleveId) {
                // Créer l'incident pour chaque élève
                $incident = Incident::create([
                    'eleve_id' => $eleveId,
                    'enseignant_id' => $request->enseignant_id,
                    'classe_id' => $request->classe_id,
                    'type' => $dbType,
                    'description' => trim($descriptionPrefix . $request->description),
                    'date' => $request->date,
                    'is_read' => false
                ]);

                // Récupérer l'élève
                $eleve = Eleve::find($eleveId);

                if ($eleve) {
                    $elevesAndIncidents[] = [
                        'eleve' => $eleve,
                        'incident' => $incident
                    ];

                    $createdIncidents[] = [
                        'id' => $incident->id,
                        'eleve' => $eleve->prenom . ' ' . $eleve->nom,
                    ];
                }
            }

            // Envoyer notification aux parents
            if (!empty($elevesAndIncidents)) {
                $this->notifyParentsGroup($elevesAndIncidents, $enseignant, $typeLabel);
            }

            DB::commit();

            $count = count($createdIncidents);
            return response()->json([
                'success' => true,
                'message' => $count > 1 
                    ? "$count incidents signalés avec succès" 
                    : 'Incident signalé avec succès',
                'data' => [
                    'count' => $count,
                    'type' => $request->type,
                    'type_label' => $typeLabel,
                    'enseignant' => $enseignant->prenom . ' ' . $enseignant->nom,
                    'matiere' => $enseignant->matiere,
                    'date' => $request->date,
                    'incidents' => $createdIncidents
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création incident: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'incident'
            ], 500);
        }
    }

    /**
     * Liste des incidents d'un élève
     */
    public function getByEleve($eleveId)
    {
        $eleve = Eleve::find($eleveId);
        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        $incidents = Incident::with(['enseignant'])
            ->where('eleve_id', $eleveId)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($incident) {
                return [
                    'id' => $incident->id,
                    'type' => $incident->type,
                    'type_label' => Incident::getTypeLabel($incident->type),
                    'description' => $incident->description,
                    'date' => $incident->date->format('Y-m-d'),
                    'is_read' => $incident->is_read,
                    'read_at' => $incident->read_at ? $incident->read_at->format('Y-m-d H:i:s') : null,
                    'enseignant_nom' => $incident->enseignant->prenom . ' ' . $incident->enseignant->nom,
                    'matiere' => $incident->enseignant->matiere,
                    'created_at' => $incident->created_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $incidents
        ]);
    }

    /**
     * Marquer un incident comme lu
     */
    public function markAsRead($id)
    {
        $incident = Incident::find($id);
        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident non trouvé'
            ], 404);
        }

        $incident->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Incident marqué comme lu'
        ]);
    }

    /**
     * Notifier les parents d'un groupe d'incidents (un seul push par parent)
     */
    public function notifyParentsGroup($elevesAndIncidents, $enseignant, $typeLabel)
    {
        try {
            $notificationService = app(PushNotificationService::class);
            // $parentsToNotify[parentId] = ['token' => ..., 'payload' => ..., 'notification_id' => ...]
            $parentsToNotify = [];

            \Log::info('[IncidentController] notifyParentsGroup démarré - ' . count($elevesAndIncidents) . ' incident(s)');

            foreach ($elevesAndIncidents as $item) {
                $eleve = $item['eleve'];
                $incident = $item['incident'];

                \Log::info('[IncidentController] Traitement incident #' . $incident->id . ' pour élève #' . $eleve->id . ' (' . $eleve->prenom . ' ' . $eleve->nom . ')');

                $parentLinks = DB::table('eleve_parents')->where('eleve_id', $eleve->id)->get();

                if ($parentLinks->isEmpty()) {
                    \Log::info('[IncidentController] Aucun parent lié pour l\'élève ' . $eleve->id);
                    continue;
                }

                $title = "Incident signalé - " . $eleve->prenom;
                $body = $typeLabel . " signalé par " . $enseignant->prenom . " " . $enseignant->nom . " (" . $enseignant->matiere . ")";

                $dataPayload = [
                    'eleve_id'       => (string)$eleve->id,
                    'child_name'     => trim($eleve->prenom . ' ' . $eleve->nom),
                    'type'           => 'incident',
                    'incident_id'    => (string)$incident->id,
                    'incident_type'  => $incident->type,
                    'enseignant_nom' => trim($enseignant->prenom . ' ' . $enseignant->nom),
                    'matiere'        => $enseignant->matiere ?? '',
                    'date'           => $incident->date->format('Y-m-d')
                ];

                foreach ($parentLinks as $parentLink) {
                    $parentId = $parentLink->parent_id;

                    \Log::info('[IncidentController] Création notification BDD pour parent #' . $parentId . ' - incident #' . $incident->id);

                    // Créer la notification individuelle en BDD pour le badge par élève
                    $notif = \App\Models\Notification::create([
                        'user_type' => 'parent',
                        'user_id'   => $parentId,
                        'type'      => 'incident',
                        'title'     => $title,
                        'message'   => $body,
                        'data'      => $dataPayload,
                    ]);

                    \Log::info('[IncidentController] Notification #' . $notif->id . ' créée en BDD pour parent #' . $parentId);

                    // Stocker le token et le payload (avec notification_id) pour le push unique
                    // Si ce parent a déjà un incident dans ce batch, on NE remplace PAS (1 seul push)
                    if (!isset($parentsToNotify[$parentId])) {
                        $parent = \App\Models\ParentUser::find($parentId);
                        if ($parent && !empty($parent->fcm_token)) {
                            $parentsToNotify[$parentId] = [
                                'token'           => $parent->fcm_token,
                                'payload'         => array_merge($dataPayload, [
                                    'notification_id' => (string)$notif->id,
                                ]),
                                'title'           => $title,
                                'body'            => $body,
                            ];
                            \Log::info('[IncidentController] Parent #' . $parentId . ' ajouté à la liste push (token: ' . substr($parent->fcm_token, 0, 20) . '...)');
                        } else {
                            \Log::warning('[IncidentController] Parent #' . $parentId . ' sans token FCM, pas de push');
                        }
                    } else {
                        \Log::info('[IncidentController] Parent #' . $parentId . ' déjà dans la liste push - pas de doublon');
                    }
                }
            }

            \Log::info('[IncidentController] Envoi push pour ' . count($parentsToNotify) . ' parent(s) unique(s)');

            // Envoyer UN SEUL push par parent concerné
            foreach ($parentsToNotify as $parentId => $data) {
                $token   = $data['token'];
                $payload = $data['payload'];

                \Log::info('[IncidentController] → Envoi push au parent #' . $parentId . ' - Token: ' . substr($token, 0, 20) . '... - notification_id: ' . ($payload['notification_id'] ?? 'N/A'));

                $notificationService->sendPushOnly(
                    $token,
                    "Nouveaux signalements",
                    "De nouveaux signalements de comportement ont été enregistrés.",
                    $payload
                );
            }

            \Log::info('[IncidentController] notifyParentsGroup terminé');

        } catch (\Exception $e) {
            \Log::error('[IncidentController] Erreur notification groupée incident: ' . $e->getMessage());
        }
    }
}

