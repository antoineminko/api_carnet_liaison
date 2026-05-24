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
