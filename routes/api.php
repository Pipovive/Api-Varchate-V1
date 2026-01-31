<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ModuloController;

/*
|--------------------------------------------------------------------------
| RUTAS DE PRUEBA / DEBUG
|--------------------------------------------------------------------------
*/

Route::get('/test', [AuthController::class, 'test']);


/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN (PUBLICAS)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Login con Google (token enviado desde frontend)
Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);

/*
|--------------------------------------------------------------------------
| VERIFICACIÓN DE EMAIL
|--------------------------------------------------------------------------
*/

// Link que llega por correo
Route::get(
    '/email/verify/{id}/{hash}',
    [EmailVerificationController::class, 'verify']
)->name('verification.verify');

// Reenviar email de verificación (usuario logueado)
Route::middleware('auth:sanctum')->post(
    '/email/resend',
    [EmailVerificationController::class, 'resend']
);

/*
|--------------------------------------------------------------------------
| RECUPERACION DE CONTRASEÑA
|--------------------------------------------------------------------------
*/

Route::post('/password/forgot', [AuthController::class, 'recoverPassword']);
Route::get('/reset-password/{token}', function ($token) {
    return response()->json([
        'token' => $token
    ]);
});
Route::post('/password/reset', [AuthController::class, 'resetPassword']);
Route::get('/password/reset', function () {
    return response()->json([
        'message' => 'Token válido, envía email, token y nueva contraseña por POST'
    ]);
});
/*
|--------------------------------------------------------------------------
| USUARIO AUTENTICADO
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Obtener usuario autenticado
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    // Logout (revoca token actual)
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | MODULOS (USUARIO)
    |--------------------------------------------------------------------------
    */

    // Ver módulos disponibles
    Route::get('/modulos', [ModuloController::class, 'index']);

    // Ver detalle de un módulo
    Route::get('/modulos/{slug}', [ModuloController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| ADMINISTRADOR (auth + rol)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {

    // Crear módulo
    Route::post('/admin/modulos', [ModuloController::class, 'store']);

    // Editar módulo
    Route::put('/admin/modulos/{id}', [ModuloController::class, 'update']);

    // Eliminar módulo
    Route::delete('/admin/modulos/{id}', [ModuloController::class, 'destroy']);
});
