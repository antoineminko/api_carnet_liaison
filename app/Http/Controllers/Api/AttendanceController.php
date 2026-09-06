<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Eleve;
use App\Models\ParentUser;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;

use App\Http\Requests\Api\SubmitAttendanceRequest;
use App\Services\AttendanceService;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }
    public function submitAttendance(SubmitAttendanceRequest $request)
    {
        DB::beginTransaction();

        try {
            $this->attendanceService->submitAttendance($request->validated(), $request->user());

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

    /* Suppression de l'appel validé du jour pour permettre une nouvelle soumission */
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
