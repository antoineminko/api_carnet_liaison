<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\ParentUser;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Crée des incidents pour un ou plusieurs élèves.
     *
     * @param array $data Données validées
     * @return array Résultat avec les incidents créés
     */
    public function createIncidents(array $data): array
    {
        /* Détermination des cibles */
        $elevesIds = [];
        if (!empty($data['eleves_ids']) && is_array($data['eleves_ids'])) {
            $elevesIds = $data['eleves_ids'];
        } elseif (!empty($data['eleve_id'])) {
            $elevesIds = [$data['eleve_id']];
        }

        if (empty($elevesIds)) {
            throw new \Exception('Aucun élève sélectionné');
        }

        $createdIncidents = [];
        $enseignant = Enseignant::find($data['enseignant_id']);
        $typeLabel = Incident::getTypeLabel($data['type']);

        /* Sécurisation du type d'incident pour prévenir les erreurs d'insertion SQL en production */
        $allowedEnumTypes = ['desordre', 'bavardage', 'bagarre', 'injure', 'retenu', 'autre'];
        $dbType = in_array($data['type'], $allowedEnumTypes) ? $data['type'] : 'autre';
        $descriptionPrefix = !in_array($data['type'], $allowedEnumTypes) ? "[$typeLabel] " : "";

        $elevesAndIncidents = [];
        $eleves = Eleve::whereIn('id', $elevesIds)->get()->keyBy('id');

        foreach ($elevesIds as $eleveId) {
            $incident = Incident::create([
                'eleve_id' => $eleveId,
                'enseignant_id' => $data['enseignant_id'],
                'classe_id' => $data['classe_id'] ?? null,
                'type' => $dbType,
                'description' => trim($descriptionPrefix . ($data['description'] ?? '')),
                'date' => $data['date'],
                'is_read' => false
            ]);

            $eleve = $eleves->get($eleveId);

            if ($eleve) {
                $elevesAndIncidents[] = [
                    'eleve' => $eleve,
                    'incident' => $incident
                ];

                $createdIncidents[] = [
                    'id' => $incident->id,
                    'eleve' => trim($eleve->prenom . ' ' . $eleve->nom),
                ];
            }
        }

        if (!empty($elevesAndIncidents)) {
            $this->notifyParentsGroup($elevesAndIncidents, $enseignant, $typeLabel);
        }

        return [
            'count' => count($createdIncidents),
            'type' => $data['type'],
            'type_label' => $typeLabel,
            'enseignant' => $enseignant ? trim($enseignant->prenom . ' ' . $enseignant->nom) : '',
            'matiere' => $enseignant ? $enseignant->matiere : '',
            'date' => $data['date'],
            'incidents' => $createdIncidents
        ];
    }

    /**
     * Envoie une notification Push groupée aux parents
     */
    protected function notifyParentsGroup(array $elevesAndIncidents, $enseignant, $typeLabel)
    {
        $enseignantNom = $enseignant ? trim($enseignant->prenom . ' ' . $enseignant->nom) : 'Un enseignant';
        
        $elevesIds = array_map(function($item) {
            return $item['eleve']->id;
        }, $elevesAndIncidents);

        $parentsRelations = DB::table('eleve_parents')
            ->whereIn('eleve_id', $elevesIds)
            ->get()
            ->groupBy('parent_id');

        foreach ($parentsRelations as $parentId => $relations) {
            $parent = ParentUser::find($parentId);
            if (!$parent) continue;

            $childrenIncidents = [];
            foreach ($elevesAndIncidents as $item) {
                foreach ($relations as $rel) {
                    if ($rel->eleve_id == $item['eleve']->id) {
                        $childrenIncidents[] = $item;
                        break;
                    }
                }
            }

            if (empty($childrenIncidents)) continue;

            foreach ($childrenIncidents as $item) {
                $eleve = $item['eleve'];
                $incident = $item['incident'];
                $eleveNom = trim($eleve->prenom . ' ' . $eleve->nom);
                
                $title = "Signalement : {$eleveNom}";
                $body = "Un incident de type '{$typeLabel}' a été signalé par {$enseignantNom}.";

                \App\Models\Notification::create([
                    'user_type' => 'parent',
                    'user_id' => $parentId,
                    'type' => 'new_incident',
                    'title' => $title,
                    'message' => $body,
                    'data' => [
                        'incident_id' => (string) $incident->id,
                        'eleve_id'    => (string) $eleve->id,
                        'child_name'  => $eleveNom,
                        'type_label'  => $typeLabel,
                        'enseignant'  => $enseignantNom,
                        'type'        => 'new_incident',
                    ],
                    'is_read' => false,
                ]);
            }

            if (!empty($parent->fcm_token)) {
                $firstItem = reset($childrenIncidents);
                $eleve = $firstItem['eleve'];
                $eleveNom = trim($eleve->prenom . ' ' . $eleve->nom);
                
                $pushTitle = count($childrenIncidents) > 1 
                    ? "Signalements concernant vos enfants" 
                    : "Signalement : {$eleveNom}";
                
                $pushBody = count($childrenIncidents) > 1
                    ? "De nouveaux incidents ont été signalés."
                    : "Un incident de type '{$typeLabel}' a été signalé.";

                try {
                    $this->notificationService->sendPushOnly(
                        $parent->fcm_token,
                        $pushTitle,
                        $pushBody,
                        [
                            'type'        => 'new_incident',
                            'incident_id' => (string) $firstItem['incident']->id,
                            'eleve_id'    => (string) $eleve->id,
                            'child_name'  => $eleveNom,
                        ]
                    );
                } catch (\Throwable $e) {
                    \Log::error('Erreur Firebase push Incident : ' . $e->getMessage());
                }
            }
        }
    }
}
