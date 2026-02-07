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
    Route::get('/modulos/{slug}', [ModuloController::class, 'show']);
    Route::get('/modulos/id/{moduloId}', [ModuloController::class, 'showById']);

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
    | PROGRESO (DETALLE Y COMPLETADO)
    |--------------------------------------------------------------------------
    */
    Route::get(
        '/progreso/modulos',
        [ProgressController::class, 'getProgresoModulos']
    );

    Route::get(
        '/progreso/modulo/{moduloId}/detalle',
        [ProgressController::class, 'getProgresoDetalle']
    );

    Route::post(
        '/progreso/leccion/{leccionId}/completar',
        [ProgressController::class, 'completarLeccion']
    );

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

/*
|--------------------------------------------------------------------------
| EVALUACIONES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/modulos/{moduloId}/evaluacion',
        [EvaluacionController::class, 'getEvaluacion']
    );

    Route::get(
        '/modulos/{moduloId}/evaluacion/estado',
        [EvaluacionController::class, 'getEvaluacion']
    );

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

    Route::get(
        '/modulos/{moduloId}/evaluacion/{intentoId}/resultado',
        [EvaluacionController::class, 'getResultadosIntento']
    );

    Route::get(
        '/modulos/{moduloId}/evaluacion/intentos',
        [EvaluacionController::class, 'getHistorialIntentos']
    );
});

/*
|--------------------------------------------------------------------------
| RANKING
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/ranking/modulo/{moduloId}/top5',
        [RankingController::class, 'getTop5Modulo']
    );

    Route::get(
        '/ranking/pantalla-principal',
        [RankingController::class, 'getPantallaPrincipal']
    );

    Route::get(
        '/ranking/modulo/{moduloId}/usuario',
        [RankingController::class, 'getPosicionUsuario']
    );

    Route::post(
        '/ranking/actualizar',
        [RankingController::class, 'webhookActualizarRanking']
    );
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
