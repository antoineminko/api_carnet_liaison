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
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AdminMessageController;

// Auth
Route::post('/login/parent', [AuthController::class, 'loginParent']);
Route::post('/login/teacher', [AuthController::class, 'loginTeacher']);

// Liaison
Route::post('/liaison/qr', [LiaisonController::class, 'linkWithQrCode']);
Route::post('/liaison/code', [LiaisonController::class, 'linkWithSecretCode']);

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

// Devoirs
Route::post('/devoirs', [DevoirController::class, 'store']);

// Rendez-vous
Route::post('/appointments', [AppointmentController::class, 'store']);
Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
Route::get('/appointments', [AppointmentController::class, 'index']);

// Parents
Route::get('/parents', [ParentController::class, 'index']);
Route::post('/parents', [ParentController::class, 'store']);
Route::put('/parents/{id}', [ParentController::class, 'update']);
Route::delete('/parents/{id}', [ParentController::class, 'destroy']);
Route::get('/parents/{id}/children', [ParentController::class, 'getChildren']);
Route::get('/parents/{id}/events', [ParentController::class, 'getEvents']);
Route::get('/parents/{id}/conversations', [MessageController::class, 'getConversationsForParent']);

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

// Messagerie
Route::get('/messages/conversation', [MessageController::class, 'getConversation']);
Route::post('/messages/conversation/initiate', [MessageController::class, 'initiateConversation']);
Route::put('/messages/conversation/{id}/status', [MessageController::class, 'updateConversationStatus']);
Route::post('/messages', [MessageController::class, 'sendMessage']);

// Administration Messages
Route::post('/admin/messages/send', [AdminMessageController::class, 'sendMessageToParent']);
Route::get('/admin/conversations/monitoring', [AdminMessageController::class, 'getCommunications']);

// Protected routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\EleveDashboardController;
Route::get('/eleves/{id}/dashboard', [EleveDashboardController::class, 'getDashboard']);
