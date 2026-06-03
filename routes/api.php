<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LiaisonController;
use App\Http\Controllers\Api\EcoleController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\EleveController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\MatiereController;
use App\Http\Controllers\Api\EnseignantController;
use App\Http\Controllers\Api\EnseignantDashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DevoirController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AdminMessageController;

// Auth
Route::post('/login/parent', [AuthController::class, 'loginParent']);
Route::post('/login/teacher', [AuthController::class, 'loginTeacher']);

// Liaison
Route::post('/liaison/qr', [LiaisonController::class, 'linkWithQrCode']);
Route::post('/liaison/code', [LiaisonController::class, 'linkWithSecretCode']);
Route::post('/admin/parents/link-child', [LiaisonController::class, 'adminLinkChild']);

// Ecoles
Route::get('/ecoles', [EcoleController::class, 'index']);
Route::post('/ecoles', [EcoleController::class, 'store']);

// Classes
Route::get('/classes', [ClasseController::class, 'index']);
Route::post('/classes', [ClasseController::class, 'store']);
Route::put('/classes/{id}', [ClasseController::class, 'update']);
Route::delete('/classes/{id}', [ClasseController::class, 'destroy']);

// Eleves
Route::get('/eleves', [EleveController::class, 'index']);
Route::post('/eleves', [EleveController::class, 'store']);
Route::put('/eleves/{id}', [EleveController::class, 'update']);
Route::delete('/eleves/{id}', [EleveController::class, 'destroy']);
Route::get('/classes/{classeId}/eleves', [EleveController::class, 'getByClasse']);

// Appel (Présences)
Route::post('/attendances', [AttendanceController::class, 'submitAttendance']);
Route::post('/attendances/reset', [AttendanceController::class, 'resetAttendance']);

// Incidents (Signalements enseignants)
Route::post('/incidents', [IncidentController::class, 'store']);
Route::get('/eleves/{eleveId}/incidents', [IncidentController::class, 'getByEleve']);
Route::put('/incidents/{id}/read', [IncidentController::class, 'markAsRead']);

Route::get('/test-attendance', function (\Illuminate\Http\Request $request) {
    $req = new \Illuminate\Http\Request();
    // On teste avec la classe 1 et l'eleve 1. 
    $req->merge([
        "classe_id" => 1,
        "date" => date('Y-m-d'),
        "attendances" => [
            ["eleve_id" => 1, "status" => "absent"]
        ]
    ]);
    $controller = app()->make(\App\Http\Controllers\Api\AttendanceController::class);
    return $controller->submitAttendance($req);
});

// Devoirs
Route::post('/devoirs', [DevoirController::class, 'store']);
Route::get('/enseignants/{teacherId}/classes', [DevoirController::class, 'getTeacherClasses']);
Route::get('/classes/{classeId}/eleves-devoirs', [DevoirController::class, 'getClassStudents']);

// Rendez-vous
Route::post('/appointments', [AppointmentController::class, 'store']);
Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
Route::put('/appointments/{id}/accept-postponed', [AppointmentController::class, 'acceptPostponedDate']);
Route::put('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
Route::get('/appointments', [AppointmentController::class, 'index']);
Route::get('/appointments/{id}', [AppointmentController::class, 'show']);

// Parents
Route::get('/parents', [ParentController::class, 'index']);
Route::post('/parents', [ParentController::class, 'store']);
Route::put('/parents/{id}', [ParentController::class, 'update']);
Route::delete('/parents/{id}', [ParentController::class, 'destroy']);
Route::get('/parents/{id}/children', [ParentController::class, 'getChildren']);
Route::get('/parents/{id}/events', [ParentController::class, 'getEvents']);
Route::get('/parents/{id}/conversations', [MessageController::class, 'getConversationsForParent']);
Route::get('/enseignants/{id}/conversations', [MessageController::class, 'getConversationsForTeacher']);
Route::get('/enseignants/{id}/admin-conversations', [MessageController::class, 'getAdminConversationsForTeacher']);

// Matieres
Route::get('/matieres', [MatiereController::class, 'index']);
Route::post('/matieres', [MatiereController::class, 'store']);
Route::put('/matieres/{id}', [MatiereController::class, 'update']);
Route::delete('/matieres/{id}', [MatiereController::class, 'destroy']);

// Enseignants
Route::get('/enseignants', [EnseignantController::class, 'index']);
Route::post('/enseignants', [EnseignantController::class, 'store']);
Route::put('/enseignants/{id}', [EnseignantController::class, 'update']);
Route::delete('/enseignants/{id}', [EnseignantController::class, 'destroy']);
Route::get('/enseignants/{id}/dashboard', [EnseignantDashboardController::class, 'getDashboard']);
Route::get('/enseignants/{id}/events', [EnseignantDashboardController::class, 'getEvents']);
Route::get('/enseignants/{id}/classes/{classId}', [EnseignantDashboardController::class, 'getClassDetails']);
Route::get('/enseignants/student/{studentId}/info', [EnseignantDashboardController::class, 'getStudentInfo']);

// Notifications
Route::post('/notifications/register-token', [NotificationController::class, 'registerToken']);
Route::get('/users/{role}/{user_id}/notifications', [NotificationController::class, 'index']);
Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

// Messagerie
Route::get('/messages/conversation', [MessageController::class, 'getConversation']);
Route::post('/messages/conversation/initiate', [MessageController::class, 'initiateConversation']);
Route::put('/messages/conversation/{id}/status', [MessageController::class, 'updateConversationStatus']);
Route::post('/messages', [MessageController::class, 'sendMessage']);

// Appels (Calls)
Route::post('/calls', [\App\Http\Controllers\Api\CallController::class, 'initiate']);
Route::put('/calls/{id}/accept', [\App\Http\Controllers\Api\CallController::class, 'accept']);
Route::put('/calls/{id}/reject', [\App\Http\Controllers\Api\CallController::class, 'reject']);
Route::put('/calls/{id}/end', [\App\Http\Controllers\Api\CallController::class, 'end']);
Route::put('/calls/{id}/missed', [\App\Http\Controllers\Api\CallController::class, 'markAsMissed']);
Route::get('/calls', [\App\Http\Controllers\Api\CallController::class, 'index']);

// Signaling WebRTC
Route::post('/calls/{callId}/offer', [\App\Http\Controllers\Api\CallController::class, 'storeOffer']);
Route::post('/calls/{callId}/answer', [\App\Http\Controllers\Api\CallController::class, 'storeAnswer']);
Route::post('/calls/{callId}/ice-candidate', [\App\Http\Controllers\Api\CallController::class, 'storeIceCandidate']);
Route::get('/calls/{callId}/signaling', [\App\Http\Controllers\Api\CallController::class, 'getSignaling']);

// Signalements (Reports)
Route::post('/reports', [\App\Http\Controllers\Api\ReportController::class, 'store']);
Route::get('/reports', [\App\Http\Controllers\Api\ReportController::class, 'index']);
Route::get('/reports/{id}', [\App\Http\Controllers\Api\ReportController::class, 'show']);
Route::put('/reports/{id}/status', [\App\Http\Controllers\Api\ReportController::class, 'updateStatus']);
Route::get('/reports/user', [\App\Http\Controllers\Api\ReportController::class, 'getUserReports']);
Route::get('/reports/against', [\App\Http\Controllers\Api\ReportController::class, 'getReportsAgainstUser']);
Route::get('/reports/eleve/{eleve_id}', [\App\Http\Controllers\Api\ReportController::class, 'getReportsForEleve']);

// Administration Messages
Route::post('/admin/messages/send', [AdminMessageController::class, 'sendMessageToParent']);
Route::get('/admin/conversations/monitoring', [AdminMessageController::class, 'getCommunications']);
Route::get('/admin/informations/{eleve_id}', [AdminMessageController::class, 'getAdminInformations']);

// Protected routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\EleveDashboardController;
Route::get('/eleves/{id}/dashboard', [EleveDashboardController::class, 'getDashboard']);

Route::get('/test-push', function(\Illuminate\Http\Request $request) {
    // 1. Clear config cache
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    
    // 2. Check all parents tokens in DB
    $parents = \App\Models\ParentUser::select('id', 'nom', 'fcm_token')->get();
    $links = \Illuminate\Support\Facades\DB::table('eleve_parents')->get();
    
    // 3. Test Firebase
    $token = $request->query('token');
    if (!$token) {
        return response()->json([
            'error' => 'Veuillez fournir un ?token= dans l\'url',
            'database_parents' => $parents,
            'eleve_parents' => $links
        ]);
    }
    
    try {
        $notificationService = app(\App\Services\PushNotificationService::class);
        $success = $notificationService->sendToToken($token, 'Test Alwaysdata', 'Ceci est un test direct depuis le serveur !', ['eleve_id' => 1]);
        return response()->json([
            'success' => $success,
            'message' => 'Cache vidé, et notification envoyée !',
            'token_used' => $token,
            'database_parents' => $parents,
            'eleve_parents' => $links
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Test simple pour vérifier la création d'incident
Route::post('/test-incident', function(\Illuminate\Http\Request $request) {
    try {
        $eleveId = $request->input('eleve_id', 1);
        $enseignantId = $request->input('enseignant_id', 1);
        
        // Vérifier que les modèles existent
        $eleve = \App\Models\Eleve::find($eleveId);
        $enseignant = \App\Models\Enseignant::find($enseignantId);
        
        if (!$eleve) {
            return response()->json(['error' => 'Eleve not found'], 404);
        }
        if (!$enseignant) {
            return response()->json(['error' => 'Enseignant not found'], 404);
        }
        
        // Créer un incident de test
        $incident = \App\Models\Incident::create([
            'eleve_id' => $eleveId,
            'enseignant_id' => $enseignantId,
            'classe_id' => $eleve->classe_id,
            'type' => 'test',
            'description' => 'Test incident',
            'date' => now(),
            'is_read' => false
        ]);
        
        // Essayer d'envoyer la notification
        $controller = new \App\Http\Controllers\Api\IncidentController();
        $typeLabel = \App\Models\Incident::getTypeLabel('test');
        $controller->notifyParents($eleve, $enseignant, $incident, $typeLabel);
        
        return response()->json([
            'success' => true,
            'incident_id' => $incident->id,
            'eleve' => $eleve->prenom . ' ' . $eleve->nom,
            'enseignant' => $enseignant->prenom . ' ' . $enseignant->nom,
            'message' => 'Incident créé et notification tentée. Vérifiez les logs.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
