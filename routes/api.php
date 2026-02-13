<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\LeccionesController;
use App\Http\Controllers\ProgresoController;
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

/*
|--------------------------------------------------------------------------
| RECUPERACIÓN DE CONTRASEÑA
|--------------------------------------------------------------------------
*/
Route::post('/password/forgot', [AuthController::class, 'recoverPassword'])
    ->middleware('throttle:email-resend');

Route::get('/reset-password/{token}', function ($token) {
    return response()->json(['token' => $token]);
});

Route::post('/password/reset', [AuthController::class, 'resetPassword']);

Route::get('/password/reset', function () {
    return response()->json([
        'message' => 'Token válido, envía email, token y nueva contraseña por POST'
    ]);
});

/*
|--------------------------------------------------------------------------
| RUTAS AUTENTICADAS (TODAS NECESITAN TOKEN)
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
    Route::post('/email/resend', [EmailVerificationController::class, 'resend']);

    /*
    |--------------------------------------------------------------------------
    | MÓDULOS
    |--------------------------------------------------------------------------
    */
    Route::get('/modulos', [ModuloController::class, 'index']);
    Route::get('/modulos/{slug}', [ModuloController::class, 'show']);           // Por slug
    Route::get('/modulos/id/{moduloId}', [ModuloController::class, 'showById']); // Por ID
    Route::get('/modulos/{slug}/intro-completa', [ModuloController::class, 'getIntroCompleta']);
    /*
    |--------------------------------------------------------------------------
    | LECCIONES
    |--------------------------------------------------------------------------
    */
    Route::get('/modulos/{moduloSlug}/lecciones', [LeccionesController::class, 'index']);
    Route::get('/modulos/{moduloSlug}/lecciones/{leccionSlug}', [LeccionesController::class, 'show']);
    Route::get('/modulos/{moduloId}/lecciones/id/{leccionId}', [LeccionesController::class, 'showById']);

    /*
    |--------------------------------------------------------------------------
    | PROGRESO Y NAVEGACIÓN
    |--------------------------------------------------------------------------
    */
    Route::get('/modulos-con-progreso', [ProgresoController::class, 'getModulosConProgreso']);
    Route::get('/modulos/{moduloId}/lecciones/{leccionId}/navegacion', [ProgresoController::class, 'getNavegacionLeccion']);
    Route::get('/modulos/{moduloId}/evaluacion/estado-desbloqueo', [ProgresoController::class, 'getEstadoEvaluacion']);
    Route::post('/modulos/{moduloId}/lecciones/{leccionId}/marcar-vista', [ProgresoController::class, 'marcarLeccionVista']);
    Route::get('/modulos/{moduloId}/continuar', [ProgresoController::class, 'getLeccionParaContinuar']);
    Route::post('/modulos/{moduloId}/actualizar-evaluacion-aprobada', [ProgresoController::class, 'actualizarEvaluacionAprobada']);

    /*
    |--------------------------------------------------------------------------
    | EJERCICIOS INTERACTIVOS
    |--------------------------------------------------------------------------
    */
    Route::get('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios', [EjercicioController::class, 'getEjercicios']);
    Route::post('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/{ejercicioId}/intento', [EjercicioController::class, 'enviarIntento']);
    Route::get('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/resultados', [EjercicioController::class, 'getResultados']);

    // Eliminar cuenta
    Route::delete('/account', [AuthController::class, 'deleteAccount']);


    /*
    |--------------------------------------------------------------------------
    | EVALUACIONES
    |--------------------------------------------------------------------------
    */
    // Sincronización manual
    Route::post(
        '/modulos/{moduloId}/sincronizar-evaluacion',
        [ProgresoController::class, 'sincronizarEvaluacion']
    );

    // Forzar actualización de progreso
    Route::post(
        '/modulos/{moduloId}/forzar-actualizacion',
        [ProgresoController::class, 'forzarActualizacionProgreso']
    );
    // Información y estado de evaluación
    Route::get('/modulos/{moduloId}/evaluacion', [EvaluacionController::class, 'getEvaluacion']);
    Route::get('/modulos/{moduloId}/evaluacion/estado', [EvaluacionController::class, 'getEvaluacion']); // Alias

    // Gestión de intentos
    Route::post('/modulos/{moduloId}/evaluacion/iniciar', [EvaluacionController::class, 'iniciarEvaluacion']);
    Route::get('/modulos/{moduloId}/evaluacion/en-progreso', [EvaluacionController::class, 'getIntentoEnProgreso']);
    Route::post('/modulos/{moduloId}/evaluacion/{intentoId}/respuesta', [EvaluacionController::class, 'guardarRespuesta']);
    Route::post('/modulos/{moduloId}/evaluacion/{intentoId}/finalizar', [EvaluacionController::class, 'finalizarEvaluacion']);

    // Resultados e historial
    Route::get('/modulos/{moduloId}/evaluacion/{intentoId}/resultado', [EvaluacionController::class, 'getResultadosIntento']);
    Route::get('/modulos/{moduloId}/evaluacion/intentos', [EvaluacionController::class, 'getHistorialIntentos']);

    /*
    |--------------------------------------------------------------------------
    | RANKING
    |--------------------------------------------------------------------------
    */
    Route::get('/ranking/modulo/{moduloId}/top5', [RankingController::class, 'getTop5Modulo']);
    Route::get('/ranking/pantalla-principal', [RankingController::class, 'getPantallaPrincipal']);

    /*
    |--------------------------------------------------------------------------
    | CERTIFICACIONES
    |--------------------------------------------------------------------------
    */
    Route::get('/certificaciones', [CertificacionController::class, 'getMisCertificaciones']);
    Route::post('/modulos/{moduloId}/certificacion/generar', [CertificacionController::class, 'generarCertificacion']);
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
Route::get('/test/modulos/{moduloSlug}/lecciones', [LeccionesController::class, 'index']);





/*
|--------------------------------------------------------------------------
| RUTAS DE ADMINISTRACIÓN (SOLO ADMINISTRADORES)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'is_admin'])->prefix('admin')->group(function () {

    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index']);
    Route::get('/dashboard/charts', [App\Http\Controllers\Admin\DashboardController::class, 'charts']);
    Route::get('/dashboard/recent-activity', [App\Http\Controllers\Admin\DashboardController::class, 'recentActivity']);

    // Gestión de Usuarios
    Route::get('/usuarios', [App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::get('/usuarios/statistics', [App\Http\Controllers\Admin\UserController::class, 'statistics']);
    Route::get('/usuarios/avatars', [App\Http\Controllers\Admin\UserController::class, 'getAvatars']);
    Route::post('/usuarios', [App\Http\Controllers\Admin\UserController::class, 'store']);
    Route::get('/usuarios/{id}', [App\Http\Controllers\Admin\UserController::class, 'show']);
    Route::put('/usuarios/{id}', [App\Http\Controllers\Admin\UserController::class, 'update']);
    Route::delete('/usuarios/{id}', [App\Http\Controllers\Admin\UserController::class, 'destroy']);
    Route::patch('/usuarios/{id}/toggle-status', [App\Http\Controllers\Admin\UserController::class, 'toggleStatus']);

    // Gestión de Módulos
    Route::get('/modulos', [App\Http\Controllers\Admin\ModuloController::class, 'index']);
    Route::get('/modulos/statistics', [App\Http\Controllers\Admin\ModuloController::class, 'statistics']);
    Route::post('/modulos', [App\Http\Controllers\Admin\ModuloController::class, 'store']);
    Route::get('/modulos/{id}', [App\Http\Controllers\Admin\ModuloController::class, 'show']);
    Route::put('/modulos/{id}', [App\Http\Controllers\Admin\ModuloController::class, 'update']);
    Route::delete('/modulos/{id}', [App\Http\Controllers\Admin\ModuloController::class, 'destroy']);
    Route::post('/modulos/reorder', [App\Http\Controllers\Admin\ModuloController::class, 'reorder']);

    // Gestión de Lecciones (dentro de módulos)
    Route::get('/modulos/{moduloId}/lecciones', [App\Http\Controllers\Admin\LeccionController::class, 'index']);
    Route::post('/modulos/{moduloId}/lecciones', [App\Http\Controllers\Admin\LeccionController::class, 'store']);
    Route::get('/modulos/{moduloId}/lecciones/{leccionId}', [App\Http\Controllers\Admin\LeccionController::class, 'show']);
    Route::put('/modulos/{moduloId}/lecciones/{leccionId}', [App\Http\Controllers\Admin\LeccionController::class, 'update']);
    Route::delete('/modulos/{moduloId}/lecciones/{leccionId}', [App\Http\Controllers\Admin\LeccionController::class, 'destroy']);
    Route::post('/modulos/{moduloId}/lecciones/reorder', [App\Http\Controllers\Admin\LeccionController::class, 'reorder']);

    // Gestión de Ejercicios (dentro de lecciones)
    Route::get('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios', [App\Http\Controllers\Admin\EjercicioController::class, 'index']);
    Route::post('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios', [App\Http\Controllers\Admin\EjercicioController::class, 'store']);
    Route::get('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/{ejercicioId}', [App\Http\Controllers\Admin\EjercicioController::class, 'show']);
    Route::put('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/{ejercicioId}', [App\Http\Controllers\Admin\EjercicioController::class, 'update']);
    Route::delete('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/{ejercicioId}', [App\Http\Controllers\Admin\EjercicioController::class, 'destroy']);
    Route::put('/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/{ejercicioId}/opciones', [App\Http\Controllers\Admin\EjercicioController::class, 'updateOpciones']);

    // Gestión de Evaluaciones
    Route::get('/modulos/{moduloId}/evaluacion', [App\Http\Controllers\Admin\EvaluacionController::class, 'show']);
    Route::put('/modulos/{moduloId}/evaluacion/config', [App\Http\Controllers\Admin\EvaluacionController::class, 'updateConfig']);
    Route::get('/evaluaciones/statistics', [App\Http\Controllers\Admin\EvaluacionController::class, 'statistics']);

    // Gestión de Preguntas de Evaluación
    Route::post('/modulos/{moduloId}/evaluacion/{evaluacionId}/preguntas', [App\Http\Controllers\Admin\EvaluacionController::class, 'storePregunta']);
    Route::put('/modulos/{moduloId}/evaluacion/{evaluacionId}/preguntas/{preguntaId}', [App\Http\Controllers\Admin\EvaluacionController::class, 'updatePregunta']);
    Route::delete('/modulos/{moduloId}/evaluacion/{evaluacionId}/preguntas/{preguntaId}', [App\Http\Controllers\Admin\EvaluacionController::class, 'destroyPregunta']);
    Route::put('/modulos/{moduloId}/evaluacion/{evaluacionId}/preguntas/{preguntaId}/opciones', [App\Http\Controllers\Admin\EvaluacionController::class, 'updateOpcionesPregunta']);
});
