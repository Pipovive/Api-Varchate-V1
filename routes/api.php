<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\LeccionesController;
use App\Http\Controllers\ProgressController;

/*
|--------------------------------------------------------------------------
| RUTAS DE PRUEBA / DEBUG
|--------------------------------------------------------------------------
*/
Route::get('/test', [AuthController::class, 'test']);

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN (PÚBLICAS)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);

/*
|--------------------------------------------------------------------------
| VERIFICACIÓN DE EMAIL
|--------------------------------------------------------------------------
*/
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify');

Route::middleware('auth:sanctum')->post(
    '/email/resend',
    [EmailVerificationController::class, 'resend']
);

/*
|--------------------------------------------------------------------------
| RECUPERACIÓN DE CONTRASEÑA
|--------------------------------------------------------------------------
*/
Route::post('/password/forgot', [AuthController::class, 'recoverPassword'])
    ->middleware('throttle:email-resend');

Route::get('/reset-password/{token}', fn ($token) => response()->json([
    'token' => $token
]));

Route::post('/password/reset', [AuthController::class, 'resetPassword']);

Route::get('/password/reset', fn () => response()->json([
    'message' => 'Token válido, envía email, token y nueva contraseña por POST'
]));

/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | USUARIO
    |--------------------------------------------------------------------------
    */
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'updateProfile']);
    Route::put('/me/password', [UserController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | MÓDULOS
    |--------------------------------------------------------------------------
    */
    Route::get('/modulos', [ModuloController::class, 'index']);
    Route::get('/modulos/{slug}', [ModuloController::class, 'show']); // Por slug
    Route::get('/modulos/id/{moduloId}', [ModuloController::class, 'showById']); // Por ID

    /*
    |--------------------------------------------------------------------------
    | LECCIONES
    |--------------------------------------------------------------------------
    */
    Route::get('/modulos/{moduloSlug}/lecciones', [LeccionesController::class, 'index']); // Por slug
    Route::get('/modulos/{moduloSlug}/lecciones/{leccionSlug}', [LeccionesController::class, 'show']); // Por slugs

    // Lecciones por IDs
    Route::get('/modulos/{moduloId}/lecciones/id/{leccionId}', [LeccionesController::class, 'showById']);

    /*
    |--------------------------------------------------------------------------
    | PROGRESO Y REANUDACIÓN
    |--------------------------------------------------------------------------
    */
    Route::prefix('progreso')->group(function () {
        // Guardar última lección vista
        Route::put('/modulo/{moduloId}/ultima-leccion', [ProgressController::class, 'saveLastLesson']);

        // Obtener última lección para reanudar
        Route::get('/modulo/{moduloId}/ultima-leccion', [ProgressController::class, 'getLastLesson']);
    });
});

/*
|--------------------------------------------------------------------------
| RUTAS DE PRUEBA (TEMPORALES - SIN AUTH)
|--------------------------------------------------------------------------
*/
Route::get('/test/modulos/{moduloSlug}/lecciones', [LeccionesController::class, 'index']);

// Quitar estas rutas públicas (están fuera de auth:sanctum)
// Route::get('/modulos/id/{moduloId}', [ModuloController::class, 'showById']);
// Route::get('/modulos/{moduloId}/lecciones/id/{leccionId}', [LeccionesController::class, 'showById']);
