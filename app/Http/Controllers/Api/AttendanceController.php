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
                // Send push notification to parents for all statuses (present, absent, late)
                $eleve = Eleve::find($eleveId);
                
                if ($eleve) {
                    // Récupérer le nom de l'école via la classe
                    $ecoleNom = DB::table('classes')
                        ->join('ecoles', 'classes.ecole_id', '=', 'ecoles.id')
                        ->where('classes.id', $classeId)
                        ->value('ecoles.nom');

                    // Find parents via eleve_parents table
                    $parentLinks = DB::table('eleve_parents')->where('eleve_id', $eleveId)->get();
                    
                    foreach ($parentLinks as $parentLink) {
                        $parent = ParentUser::find($parentLink->parent_id);
                        if ($parent && !empty($parent->fcm_token)) {
                            $prenomNom = trim($eleve->prenom . ' ' . $eleve->nom);
                            $statusFr = 'présent';
                            if ($status === 'absent') $statusFr = 'absent';
                            if ($status === 'late') $statusFr = 'en retard';

                            // Titre avec nom de l'élève
                            $title = "{$prenomNom} - Appel de présence";
                            // Corps avec nom de l'école si disponible
                            $ecoleStr = $ecoleNom ? " ({$ecoleNom})" : '';
                            $body = "{$prenomNom} a été marqué {$statusFr} aujourd'hui{$ecoleStr}.";
                                
                            try {
                                $notificationService = app(PushNotificationService::class);
                                $notificationService->sendToToken($parent->fcm_token, $title, $body, [
                                    'eleve_id'   => (string)$eleveId,
                                    'child_name' => $prenomNom,
                                    'ecole_nom'  => $ecoleNom ?? '',
                                    'type'       => 'attendance_alert',
                                    'status'     => (string)$status,
                                ]);
                            } catch (\Throwable $e) {
                                \Log::error('Erreur Firebase non configuré : ' . $e->getMessage());
                            }
                        }
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
