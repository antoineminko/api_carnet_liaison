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

// Parents
Route::get('/parents', [ParentController::class, 'index']);
Route::post('/parents', [ParentController::class, 'store']);
Route::get('/parents/{id}/children', [ParentController::class, 'getChildren']);

// Matieres
Route::get('/matieres', [MatiereController::class, 'index']);
Route::post('/matieres', [MatiereController::class, 'store']);

// Enseignants
Route::get('/enseignants', [EnseignantController::class, 'index']);
Route::post('/enseignants', [EnseignantController::class, 'store']);

// Protected routes
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
