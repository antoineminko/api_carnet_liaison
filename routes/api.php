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
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\AdminMessageController;

// Auth
Route::post('/login/parent', [AuthController::class, 'loginParent']);

// Liaison
Route::post('/liaison/qr', [LiaisonController::class, 'linkWithQrCode']);
Route::post('/liaison/code', [LiaisonController::class, 'linkWithSecretCode']);

// Ecoles
Route::get('/ecoles', [EcoleController::class, 'index']);
Route::post('/ecoles', [EcoleController::class, 'store']);

// Classes
Route::get('/classes', [ClasseController::class, 'index']);
Route::post('/classes', [ClasseController::class, 'store']);

// Eleves
Route::get('/eleves', [EleveController::class, 'index']);
Route::post('/eleves', [EleveController::class, 'store']);
Route::get('/classes/{classeId}/eleves', [EleveController::class, 'getByClasse']);

// Appel (Présences)
Route::post('/attendances', [AttendanceController::class, 'submitAttendance']);

// Parents
Route::get('/parents', [ParentController::class, 'index']);
Route::post('/parents', [ParentController::class, 'store']);
Route::get('/parents/{id}/children', [ParentController::class, 'getChildren']);
Route::get('/parents/{id}/conversations', [MessageController::class, 'getConversationsForParent']);

// Matieres
Route::get('/matieres', [MatiereController::class, 'index']);
Route::post('/matieres', [MatiereController::class, 'store']);

// Enseignants
Route::get('/enseignants', [EnseignantController::class, 'index']);
Route::post('/enseignants', [EnseignantController::class, 'store']);

// Notifications
Route::post('/notifications/register-token', [NotificationController::class, 'registerToken']);

// Messagerie
Route::get('/messages/conversation', [MessageController::class, 'getConversation']);
Route::post('/messages', [MessageController::class, 'sendMessage']);

// Administration Messages
Route::post('/admin/messages/send', [AdminMessageController::class, 'sendMessageToParent']);
Route::get('/admin/conversations/monitoring', [AdminMessageController::class, 'getCommunications']);

// Protected routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
