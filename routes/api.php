<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\LiaisonController;

Route::post('/liaison/qr', [LiaisonController::class, 'linkWithQrCode']);
Route::post('/liaison/code', [LiaisonController::class, 'linkWithSecretCode']);

use App\Http\Controllers\Api\AuthController;

Route::post('/login/parent', [AuthController::class, 'loginParent']);

use App\Http\Controllers\Api\ParentController;
Route::get('/parents/{id}/children', [ParentController::class, 'getChildren']);

use App\Http\Controllers\Api\EcoleController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\EleveController;

Route::get('/ecoles', [EcoleController::class, 'index']);
Route::post('/ecoles', [EcoleController::class, 'store']);

Route::get('/classes', [ClasseController::class, 'index']);
Route::post('/classes', [ClasseController::class, 'store']);

Route::get('/eleves', [EleveController::class, 'index']);
Route::post('/eleves', [EleveController::class, 'store']);

use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\MatiereController;
use App\Http\Controllers\Api\EnseignantController;

Route::get('/parents', [ParentController::class, 'index']);
Route::post('/parents', [ParentController::class, 'store']);

Route::get('/matieres', [MatiereController::class, 'index']);
Route::post('/matieres', [MatiereController::class, 'store']);

Route::get('/enseignants', [EnseignantController::class, 'index']);
Route::post('/enseignants', [EnseignantController::class, 'store']);


