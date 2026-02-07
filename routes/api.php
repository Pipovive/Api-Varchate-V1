<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\LeccionesController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\EjercicioController;
use App\Http\Controllers\EvaluacionController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\CertificacionController;

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
    Route::get(
        '/modulos/{moduloSlug}/lecciones',
        [LeccionesController::class, 'index']
    );

    Route::get(
        '/modulos/{moduloSlug}/lecciones/{leccionSlug}',
        [LeccionesController::class, 'show']
    );

    Route::get(
        '/modulos/{moduloId}/lecciones/id/{leccionId}',
        [LeccionesController::class, 'showById']
    );

    /*
    |--------------------------------------------------------------------------
    | PROGRESO Y REANUDACIÓN
    |--------------------------------------------------------------------------
    */
    Route::prefix('progreso')->group(function () {

        Route::put(
            '/modulo/{moduloId}/ultima-leccion',
            [ProgressController::class, 'saveLastLesson']
        );

        Route::get(
            '/modulo/{moduloId}/ultima-leccion',
            [ProgressController::class, 'getLastLesson']
        );
    });

    /*
    |--------------------------------------------------------------------------
    | EJERCICIOS INTERACTIVOS
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/modulos/{moduloId}/lecciones/{leccionId}/ejercicios',
        [EjercicioController::class, 'getEjercicios']
    );

    Route::post(
        '/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/{ejercicioId}/intento',
        [EjercicioController::class, 'enviarIntento']
    );

    Route::get(
        '/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/resultados',
        [EjercicioController::class, 'getResultados']
    );
});

// Evaluaciones
Route::middleware(['auth:sanctum'])->group(function () {
    // Información y estado de evaluación
    Route::get(
        '/modulos/{moduloId}/evaluacion',
        [EvaluacionController::class, 'getEvaluacion']
    );

    Route::get(
        '/modulos/{moduloId}/evaluacion/estado',
        [EvaluacionController::class, 'getEvaluacion']
    ); // Alias

    // Gestión de intentos
    Route::post(
        '/modulos/{moduloId}/evaluacion/iniciar',
        [EvaluacionController::class, 'iniciarEvaluacion']
    );

    Route::get(
        '/modulos/{moduloId}/evaluacion/en-progreso',
        [EvaluacionController::class, 'getIntentoEnProgreso']
    );

    Route::post(
        '/modulos/{moduloId}/evaluacion/{intentoId}/respuesta',
        [EvaluacionController::class, 'guardarRespuesta']
    );

    Route::post(
        '/modulos/{moduloId}/evaluacion/{intentoId}/finalizar',
        [EvaluacionController::class, 'finalizarEvaluacion']
    );

    // Resultados e historial
    Route::get(
        '/modulos/{moduloId}/evaluacion/{intentoId}/resultado',
        [EvaluacionController::class, 'getResultadosIntento']
    );

    Route::get(
        '/modulos/{moduloId}/evaluacion/intentos',
        [EvaluacionController::class, 'getHistorialIntentos']
    );
});
// Ranking para pantalla principal
Route::middleware(['auth:sanctum'])->group(function () {
    // Top 5 de un módulo específico
    Route::get(
        '/ranking/modulo/{moduloId}/top5',
        [RankingController::class, 'getTop5Modulo']
    );

    // Para pantalla principal - Top 5 de TODOS los módulos
    Route::get(
        '/ranking/pantalla-principal',
        [RankingController::class, 'getPantallaPrincipal']
    );
});
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // Certificaciones del usuario
    Route::get('/certificaciones', [CertificacionController::class, 'getMisCertificaciones']);

    // Generar nueva certificación
    Route::post('/modulos/{moduloId}/certificacion/generar', [CertificacionController::class, 'generarCertificacion']);

    // Ver, descargar y verificar certificado específico
    Route::get('/certificaciones/{codigo}/ver', [CertificacionController::class, 'verCertificado']);
    Route::get('/certificaciones/{codigo}/descargar', [CertificacionController::class, 'descargarCertificado']);
    Route::get('/certificaciones/{codigo}/verificar', [CertificacionController::class, 'verificarCertificado']);

    // Herramientas de desarrollo
    Route::get('/certificaciones/preview', [CertificacionController::class, 'previewCertificado']);
    Route::get('/certificaciones/info-imagen', [CertificacionController::class, 'getInfoImagenBase']);
});
/*
|--------------------------------------------------------------------------
| RUTAS DE PRUEBA (TEMPORALES - SIN AUTH)
|--------------------------------------------------------------------------
*/
Route::get(
    '/test/modulos/{moduloSlug}/lecciones',
    [LeccionesController::class, 'index']
);

// Rutas públicas deshabilitadas
// Route::get('/modulos/id/{moduloId}', [ModuloController::class, 'showById']);
// Route::get('/modulos/{moduloId}/lecciones/id/{leccionId}', [LeccionesController::class, 'showById']);
