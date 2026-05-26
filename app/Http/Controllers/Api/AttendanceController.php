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
    protected $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

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

                // If absent or late, send push notification to parents
                if (in_array($status, ['absent', 'late'])) {
                    $eleve = Eleve::find($eleveId);
                    
                    if ($eleve) {
                        // Find parents via eleve_parents table
                        $parentLinks = DB::table('eleve_parents')->where('eleve_id', $eleveId)->get();
                        
                        foreach ($parentLinks as $parentLink) {
                            $parent = ParentUser::find($parentLink->parent_id);
                            if ($parent && !empty($parent->fcm_token)) {
                                $title = "Alerte de présence - " . $eleve->prenom;
                                $statusFr = $status === 'absent' ? 'absent' : 'en retard';
                                $body = "Votre enfant {$eleve->prenom} {$eleve->nom} a été marqué $statusFr aujourd'hui.";
                                
                                $this->notificationService->sendToToken($parent->fcm_token, $title, $body, [
                                    'eleve_id' => $eleveId,
                                    'type' => 'attendance_alert',
                                    'status' => $status
                                ]);
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
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation de l\'appel.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
