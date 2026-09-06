<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Eleve;
use App\Models\ParentUser;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Valide et persiste l'appel pour une classe.
     *
     * @param array $data Données validées
     * @return void
     */
    public function submitAttendance(array $data)
    {
        $classeId = $data['classe_id'];
        $date = $data['date'];

        $targets = collect();
        $ecoleNom = DB::table('classes')
            ->join('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->where('classes.id', $classeId)
            ->value('ecoles.nom');

        // Récupération du nom complet du professeur pour l'afficher dans la notification
        $enseignant = DB::table('classe_enseignant')
            ->join('enseignants', 'classe_enseignant.enseignant_id', '=', 'enseignants.id')
            ->where('classe_enseignant.classe_id', $classeId)
            ->select('enseignants.prenom', 'enseignants.nom', 'enseignants.matiere')
            ->first();
        $matiere = $enseignant->matiere ?? '';
        $teacherName = $enseignant ? trim(($enseignant->prenom ?? '') . ' ' . ($enseignant->nom ?? '')) : '';

        $eleveIds = collect($data['attendances'])->pluck('eleve_id')->toArray();
        $eleves = Eleve::whereIn('id', $eleveIds)->get()->keyBy('id');
        $eleveParents = DB::table('eleve_parents')->whereIn('eleve_id', $eleveIds)->get()->groupBy('eleve_id');

        foreach ($data['attendances'] as $attData) {
            $eleveId = $attData['eleve_id'];
            $status = $attData['status'];

            /* Upsert de l'enregistrement de présence journalier */
            Attendance::updateOrCreate(
                [
                    'eleve_id' => $eleveId,
                    'date' => $date,
                    'classe_id' => $classeId,
                ],
                [
                    'status' => $status
                ]
            );

            $eleve = $eleves->get($eleveId);
            
            if ($eleve) {
                $parentsForChild = $eleveParents->get($eleveId, collect());
                
                foreach ($parentsForChild as $pivot) {
                    $targets->push([
                        'parent_id' => $pivot->parent_id,
                        'eleve_id' => $eleveId,
                        'eleve_nom' => trim($eleve->prenom . ' ' . $eleve->nom),
                        'status' => $status,
                    ]);
                }
            }
        }

        $this->notifyParents($targets, $ecoleNom, $matiere, $teacherName);
    }

    /**
     * Notifie les parents concernés par l'appel.
     */
    protected function notifyParents($targets, $ecoleNom, $matiere, $teacherName = '')
    {
        /* Agrégation des notifications par parent pour éviter les envois multiples */
        $groupedTargets = $targets->groupBy('parent_id');

        foreach ($groupedTargets as $parentId => $childrenTargets) {
            $parent = ParentUser::find($parentId);
            if (!$parent) continue;

            /* Persistance individuelle des notifications */
            foreach ($childrenTargets as $childTarget) {
                $statusFr = 'présent';
                if ($childTarget['status'] === 'absent') $statusFr = 'absent';
                if ($childTarget['status'] === 'late') $statusFr = 'en retard';

                $title = "{$childTarget['eleve_nom']} - Appel de présence";
                $ecoleStr = $ecoleNom ? " ({$ecoleNom})" : '';
                $body = "{$childTarget['eleve_nom']} a été marqué {$statusFr} aujourd'hui{$ecoleStr}.";

                \App\Models\Notification::create([
                    'user_type' => 'parent',
                    'user_id'   => $parentId,
                    'type'      => 'attendance_alert',
                    'title'     => $title,
                    'message'   => $body,
                    'data'      => [
                        'eleve_id'    => (string)$childTarget['eleve_id'],
                        'eleve_nom'   => $childTarget['eleve_nom'],     // nom complet de l'enfant
                        'child_name'  => $childTarget['eleve_nom'],     // alias pour compatibilité
                        'school_name' => $ecoleNom ?? '',               // clé standard Flutter
                        'ecole_nom'   => $ecoleNom ?? '',               // alias legacy
                        'sender_name' => $teacherName,                  // nom du professeur
                        'matiere'     => $matiere,
                        'type'        => 'attendance_alert',
                        'status'      => (string)$childTarget['status'],
                    ],
                    'is_read' => false,
                ]);
            }

            /* Déclenchement d'une notification Push unique résumant les appels pour tous les enfants */
            if (!empty($parent->fcm_token) && count($childrenTargets) > 0) {
                $title = "Présences mises à jour";
                $pushBody = "Les informations de présence de vos enfants sont disponibles.";

                try {
                    $this->notificationService->sendPushOnly(
                        $parent->fcm_token,
                        $title,
                        $pushBody,
                        [
                            'type'       => 'attendance_group_alert',
                            'matiere'    => $matiere,
                        ]
                    );
                } catch (\Throwable $e) {
                    \Log::error('Erreur Firebase push : ' . $e->getMessage());
                }
            }
        }
    }
}
