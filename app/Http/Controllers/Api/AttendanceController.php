<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Eleve;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Valider l'appel pour une classe
     */
    public function submitAttendance(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|integer',
            'date'      => 'required|date',
            'attendances' => 'required|array',
            'attendances.*.eleve_id' => 'required|integer',
            'attendances.*.status'   => 'required|in:present,absent,late'
        ]);

        $classeId = $request->classe_id;
        $date = $request->date;

        DB::beginTransaction();

        try {
            $targets = collect();
            $ecoleNom = DB::table('classes')
                ->join('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
                ->where('classes.id', $classeId)
                ->value('ecoles.nom');

            $matiere = DB::table('classe_enseignant')
                ->join('enseignants', 'classe_enseignant.enseignant_id', '=', 'enseignants.id')
                ->where('classe_enseignant.classe_id', $classeId)
                ->value('enseignants.matiere') ?? '';

            foreach ($request->attendances as $attData) {
                $eleveId = $attData['eleve_id'];
                $status = $attData['status'];

                // Update or create attendance record
                $attendance = Attendance::updateOrCreate(
                    [
                        'eleve_id' => $eleveId,
                        'date' => $date,
                    ],
                    [
                        'classe_id' => $classeId,
                        'status' => $status
                    ]
                );

                $eleve = Eleve::find($eleveId);
                
                if ($eleve) {
                    $parentIds = DB::table('eleve_parents')->where('eleve_id', $eleveId)->pluck('parent_id');
                    
                    foreach ($parentIds as $parentId) {
                        $targets->push([
                            'parent_id' => $parentId,
                            'eleve_id' => $eleveId,
                            'eleve_nom' => trim($eleve->prenom . ' ' . $eleve->nom),
                            'status' => $status,
                        ]);
                    }
                }
            }

            // Group by parent
            $groupedTargets = $targets->groupBy('parent_id');
            $notificationService = app(PushNotificationService::class);

            foreach ($groupedTargets as $parentId => $childrenTargets) {
                $parent = ParentUser::find($parentId);
                if (!$parent) continue;

                // Save individual notifications in DB for each child
                foreach ($childrenTargets as $childTarget) {
                    $statusFr = 'présent';
                    if ($childTarget['status'] === 'absent') $statusFr = 'absent';
                    if ($childTarget['status'] === 'late') $statusFr = 'en retard';

                    $title = "{$childTarget['eleve_nom']} - Appel de présence";
                    $ecoleStr = $ecoleNom ? " ({$ecoleNom})" : '';
                    $body = "{$childTarget['eleve_nom']} a été marqué {$statusFr} aujourd'hui{$ecoleStr}.";

                    \App\Models\Notification::create([
                        'user_type' => 'parent',
                        'user_id' => $parentId,
                        'type' => 'attendance_alert',
                        'title' => $title,
                        'message' => $body,
                        'data' => [
                            'eleve_id'   => (string)$childTarget['eleve_id'],
                            'child_name' => $childTarget['eleve_nom'],
                            'ecole_nom'  => $ecoleNom ?? '',
                            'type'       => 'attendance_alert',
                            'status'     => (string)$childTarget['status'],
                            'matiere'    => $matiere,
                        ],
                        'is_read' => false,
                    ]);
                }

                // Send 1 push notification per parent
                if (!empty($parent->fcm_token)) {
                    $title = "Appel de présence";
                    $pushBody = "Les présences de vos enfants ont été mises à jour.";

                    try {
                        $notificationService->sendPushOnly(
                            $parent->fcm_token,
                            $title,
                            $pushBody,
                            [
                                'type' => 'attendance_group',
                                'classe_id' => (string)$classeId,
                                'date' => $date,
                            ]
                        );
                    } catch (\Throwable $e) {
                        \Log::error('Erreur Firebase non configuré : ' . $e->getMessage());
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Appel validé avec succès.'
            ]);
            
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation de l\'appel.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Réinitialiser l'appel pour une classe (supprime les présences du jour)
     */
    public function resetAttendance(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|integer',
            'date'      => 'required|date'
        ]);

        try {
            DB::table('attendances')
                ->where('classe_id', $request->classe_id)
                ->where('date', $request->date)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'L\'appel du jour a été réinitialisé.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation de l\'appel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
