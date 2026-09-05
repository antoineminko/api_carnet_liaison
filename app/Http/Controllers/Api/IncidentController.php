<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\StoreIncidentRequest;
use App\Services\IncidentService;

class IncidentController extends Controller
{
    protected $incidentService;

    public function __construct(IncidentService $incidentService)
    {
        $this->incidentService = $incidentService;
    }
    /**
     * Créer un nouvel incident et notifier les parents
     */
    public function store(StoreIncidentRequest $request)
    {
        try {
            DB::beginTransaction();

            $result = $this->incidentService->createIncidents($request->validated());

            DB::commit();

            $count = $result['count'];
            return response()->json([
                'success' => true,
                'message' => $count > 1 
                    ? "$count incidents signalés avec succès" 
                    : 'Incident signalé avec succès',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création incident: ' . $e->getMessage());
            $message = $e->getMessage() == 'Aucun élève sélectionné' ? $e->getMessage() : 'Erreur lors de la création de l\'incident';
            return response()->json([
                'success' => false,
                'message' => $message
            ], $e->getMessage() == 'Aucun élève sélectionné' ? 422 : 500);
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
            $parentsToNotify = [];

            foreach ($elevesAndIncidents as $item) {
                $eleve = $item['eleve'];
                $incident = $item['incident'];

                $parentLinks = DB::table('eleve_parents')->where('eleve_id', $eleve->id)->get();

                if ($parentLinks->isEmpty()) {
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

                    /* 
                     * Traçabilité : Une notification individuelle par élève est créée en base 
                     * pour garantir l'incrémentation des compteurs et l'affichage des badges côté client. 
                     */
                    $notif = \App\Models\Notification::create([
                        'user_type' => 'parent',
                        'user_id'   => $parentId,
                        'type'      => 'incident',
                        'title'     => $title,
                        'message'   => $body,
                        'data'      => $dataPayload,
                    ]);

                    /* 
                     * Optimisation : Groupement des notifications Push par parent 
                     * pour éviter le spam en cas d'incidents multiples simultanés. 
                     */
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
                        }
                    }
                }
            }

            foreach ($parentsToNotify as $parentId => $data) {
                $token   = $data['token'];
                $payload = $data['payload'];

                $notificationService->sendPushOnly(
                    $token,
                    "Nouveaux signalements",
                    "De nouveaux signalements de comportement ont été enregistrés.",
                    $payload
                );
            }

        } catch (\Exception $e) {
            \Log::error('[IncidentController] Erreur notification groupée incident: ' . $e->getMessage());
        }
    }
}

