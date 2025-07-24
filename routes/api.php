<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\RutaController;
use App\Http\Controllers\Api\ComunidadController;
use Illuminate\Support\Facades\Route;

// Ruta pública para login con Google
Route::post('/auth/google', [AuthController::class, 'googleLogin']);

// Rutas protegidas que requieren autenticación
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::apiResource('usuarios', UsuarioController::class);
    Route::apiResource('rutas', RutaController::class);
    Route::apiResource('comunidades', ComunidadController::class);
});
