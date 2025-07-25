<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\RutaController;
use App\Http\Controllers\Api\ComunidadController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/auth/login', [AuthController::class, 'login']);                    // ← AGREGAR ESTA
Route::post('/auth/send-code', [AuthController::class, 'sendVerificationCode']);
Route::post('/auth/verify-code', [AuthController::class, 'verifyCodeAndLogin']);    // ← Nueva

// Rutas protegidas que requieren autenticación
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::apiResource('usuarios', UsuarioController::class);
    Route::apiResource('rutas', RutaController::class);
    Route::apiResource('comunidades', ComunidadController::class);

    // Rutas de comunidades ✅ AGREGAR ESTAS LÍNEAS
    Route::prefix('comunidades')->group(function () {
        Route::post('/crear', [ComunidadController::class, 'crearComunidad']);
        Route::post('/unirse', [ComunidadController::class, 'unirseAComunidad']);
        Route::get('/mis-comunidades', [ComunidadController::class, 'misComunidades']);
        Route::get('/{id}', [ComunidadController::class, 'detallesComunidad']);
        Route::delete('/{id}/salir', [ComunidadController::class, 'salirDeComunidad']);
    });
});
