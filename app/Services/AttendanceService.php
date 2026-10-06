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
    public function submitAttendance(array $data, $enseignant = null)
    {
        $classeId = $data['classe_id'];
        $date = $data['date'];

        $targets = collect();
        $classeInfo = DB::table('classes')
            ->join('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
            ->where('classes.id', $classeId)
            ->select('classes.nom as classe_nom', 'classes.ecole_id', 'ecoles.nom as ecole_nom')
            ->first();

        $ecoleNom = $classeInfo->ecole_nom ?? null;
        $classeNom = $classeInfo->classe_nom ?? 'Classe';
        $ecoleId = $classeInfo->ecole_id ?? null;

        // Récupération du nom complet du professeur pour l'afficher dans la notification
        $enseignantId = null;
        if ($enseignant) {
            $matiere = $enseignant->matiere ?? '';
            $teacherName = trim(($enseignant->prenom ?? '') . ' ' . ($enseignant->nom ?? ''));
            $enseignantId = $enseignant->id ?? null;
        } else {
            $enseignantFallback = DB::table('classe_enseignant')
                ->join('enseignants', 'classe_enseignant.enseignant_id', '=', 'enseignants.id')
                ->where('classe_enseignant.classe_id', $classeId)
                ->select('enseignants.id', 'enseignants.prenom', 'enseignants.nom', 'enseignants.matiere')
                ->first();
            $matiere = $enseignantFallback->matiere ?? '';
            $teacherName = $enseignantFallback ? trim(($enseignantFallback->prenom ?? '') . ' ' . ($enseignantFallback->nom ?? '')) : '';
            $enseignantId = $enseignantFallback->id ?? null;
        }

        $eleveIds = collect($data['attendances'])->pluck('eleve_id')->toArray();
        $eleves = Eleve::whereIn('id', $eleveIds)->get()->keyBy('id');
        $eleveParents = DB::table('eleve_parents')->whereIn('eleve_id', $eleveIds)->get()->groupBy('eleve_id');

        foreach ($data['attendances'] as $attData) {
            $eleveId = $attData['eleve_id'];
            $status = $attData['status'];

            /* Upsert de l'enregistrement de présence journalier */
            $payload = [
                'status'  => $status,
                'matiere' => $matiere,
            ];
            if ($enseignantId) {
                $payload['enseignant_id'] = $enseignantId;
            }

            Attendance::updateOrCreate(
                [
                    'eleve_id'  => $eleveId,
                    'date'      => $date,
                    'classe_id' => $classeId,
                ],
                $payload
            );

            $eleve = $eleves->get($eleveId);

            if ($eleve) {
                $parentsForChild = $eleveParents->get($eleveId, collect());

                foreach ($parentsForChild as $pivot) {
                    $targets->push([
                        'parent_id' => $pivot->parent_id,
                        'eleve_id'  => $eleveId,
                        'eleve_nom' => trim($eleve->prenom . ' ' . $eleve->nom),
                        'status'    => $status,
                    ]);
                }
            }
        }

        $this->notifyParents($targets, $ecoleNom, $matiere, $teacherName);
        $this->notifyAdmin($ecoleId, $classeId, $classeNom, $teacherName, $matiere, $date, count($data['attendances']));
    }

    /**
     * Notifie l'administration de l'établissement qu'un appel a été validé.
     */
    protected function notifyAdmin($ecoleId, $classeId, $classeNom, $teacherName, $matiere, $date, int $count)
    {
        if (!$ecoleId) {
            return;
        }

        $cours = $matiere ?: 'Non précisé';
        $prof = $teacherName ?: 'Un enseignant';
        $title = 'Appel de présence effectué';
        $body = "La présence de la classe {$classeNom} a été effectuée par {$prof} (Cours {$cours}).";

        try {
            \App\Models\Notification::create([
                'user_type' => 'admin',
                'user_id'   => $ecoleId,
                'type'      => 'attendance_submitted',
                'title'     => $title,
                'message'   => $body,
                'data'      => [
                    'type'           => 'attendance_submitted',
                    'classe_id'      => (string) $classeId,
                    'classe_nom'     => $classeNom,
                    'enseignant_nom' => $prof,
                    'matiere'        => $cours,
                    'date'           => $date,
                    'count'          => $count,
                ],
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[Attendance] Erreur notif admin: ' . $e->getMessage());
        }
    }

    /**
     * Notifie les parents concernés par l'appel.
     */
    protected function notifyParents($targets, $ecoleNom, $matiere, $teacherName = '')
    {
        /* Agrégation des notifications par parent pour éviter les envois multiples */
        $groupedTargets = $targets->groupBy('parent_id');
        $parentsData = ParentUser::whereIn('id', $groupedTargets->keys()->all())->get()->keyBy('id');

        foreach ($groupedTargets as $parentId => $childrenTargets) {
            $parent = $parentsData->get($parentId);
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
                    $firstChild = $childrenTargets->first();
                    $this->notificationService->sendPushOnly(
                        $parent->fcm_token,
                        $title,
                        $pushBody,
                        [
                            'type'       => 'attendance_alert',
                            'eleve_id'   => (string) $firstChild['eleve_id'],
                            'eleve_nom'  => $firstChild['eleve_nom'],
                            'child_name' => $firstChild['eleve_nom'],
                            'status'     => (string) $firstChild['status'],
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
