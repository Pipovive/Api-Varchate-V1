<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Leccion;
use App\Models\ProgresoModulo;
use App\Models\ProgresoLeccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgresoController extends Controller
{
    /**
     * Obtener módulos con progreso para el menú
     * GET /modulos-con-progreso
     */
    public function getModulosConProgreso()
    {
        try {
            $usuario = Auth::user();
            $modulos = Modulo::where('estado', 'activo')
                ->orderBy('orden_global')
                ->get();

            $resultado = [];

            foreach ($modulos as $modulo) {
                // Obtener progreso del módulo
                $progreso = ProgresoModulo::firstOrCreate([
                    'usuario_id' => $usuario->id,
                    'modulo_id' => $modulo->id
                ]);

                // Calcular porcentaje basado en lecciones vistas
                $totalLecciones = $modulo->lecciones()->count();
                $leccionesVistas = $this->contarLeccionesVistas($modulo->id, $usuario->id);
                $porcentaje = $totalLecciones > 0 ? ($leccionesVistas / $totalLecciones) * 100 : 0;

                // Actualizar si es necesario
                if ($progreso->porcentaje_completado != $porcentaje) {
                    $ultimaLeccion = $this->obtenerUltimaLeccionVista($modulo->id, $usuario->id);

                    $progreso->update([
                        'porcentaje_completado' => $porcentaje,
                        'lecciones_vistas' => $leccionesVistas,
                        'total_lecciones' => $totalLecciones,
                        'ultima_leccion_vista_id' => $ultimaLeccion ? $ultimaLeccion->id : null,
                        'fecha_ultimo_progreso' => now()
                    ]);
                }

                $resultado[] = [
                    'id' => $modulo->id,
                    'titulo' => $modulo->titulo,
                    'slug' => $modulo->slug,
                    'icono' => $this->getIconoModulo($modulo->modulo),
                    'progreso' => (float) $progreso->porcentaje_completado,
                    'lecciones_vistas' => $progreso->lecciones_vistas,
                    'total_lecciones' => $progreso->total_lecciones,
                    'evaluacion_aprobada' => (bool) $progreso->evaluacion_aprobada, // AÑADIDO
                    'certificado_disponible' => (bool) $progreso->certificado_disponible
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $resultado
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener navegación para una lección (anterior/siguiente)
     * GET /modulos/{moduloId}/lecciones/{leccionId}/navegacion
     */
    public function getNavegacionLeccion($moduloId, $leccionId)
    {
        try {
            $usuario = Auth::user();

            // Lección actual
            $leccionActual = Leccion::where('id', $leccionId)
                ->where('modulo_id', $moduloId)
                ->where('estado', 'activo')
                ->firstOrFail();

            // Lección anterior (siempre disponible para navegar hacia atrás)
            $leccionAnterior = Leccion::where('modulo_id', $moduloId)
                ->where('orden', '<', $leccionActual->orden)
                ->where('estado', 'activo')
                ->orderBy('orden', 'desc')
                ->first();

            // Lección siguiente
            $leccionSiguiente = Leccion::where('modulo_id', $moduloId)
                ->where('orden', '>', $leccionActual->orden)
                ->where('estado', 'activo')
                ->orderBy('orden')
                ->first();

            // ===== VALIDAR SI SIGUIENTE ESTÁ DESBLOQUEADA =====
            $siguienteDesbloqueada = false;

            if ($leccionSiguiente) {
                // Si es la introducción (orden 1), siguiente siempre desbloqueada
                if ($leccionActual->orden == 1) {
                    $siguienteDesbloqueada = true;
                } else {
                    // Verificar si ya completó la lección actual
                    $progresoActual = ProgresoLeccion::where('usuario_id', $usuario->id)
                        ->where('leccion_id', $leccionActual->id)
                        ->where('vista', true)
                        ->exists();

                    $siguienteDesbloqueada = $progresoActual;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'actual' => [
                        'id' => $leccionActual->id,
                        'titulo' => $leccionActual->titulo,
                        'orden' => $leccionActual->orden,
                        'completada' => ProgresoLeccion::where('usuario_id', $usuario->id)
                            ->where('leccion_id', $leccionActual->id)
                            ->where('vista', true)
                            ->exists()
                    ],
                    'anterior' => $leccionAnterior ? [
                        'id' => $leccionAnterior->id,
                        'titulo' => $leccionAnterior->titulo,
                        'orden' => $leccionAnterior->orden
                    ] : null,
                    'siguiente' => $leccionSiguiente ? [
                        'id' => $leccionSiguiente->id,
                        'titulo' => $leccionSiguiente->titulo,
                        'orden' => $leccionSiguiente->orden,
                        'desbloqueada' => $siguienteDesbloqueada,
                        'mensaje' => $siguienteDesbloqueada
                            ? 'Disponible'
                            : 'Completa esta lección primero'
                    ] : null,
                    'es_ultima_leccion' => !$leccionSiguiente,
                    'evaluacion_disponible' => !$leccionSiguiente &&
                        $this->estaEvaluacionDesbloqueada($moduloId, $usuario->id)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener navegación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function estaEvaluacionDesbloqueada($moduloId, $usuarioId)
    {
        $totalLecciones = Leccion::where('modulo_id', $moduloId)
            ->where('estado', 'activo')
            ->count();

        $leccionesVistas = ProgresoLeccion::whereHas('leccion', function ($q) use ($moduloId) {
            $q->where('modulo_id', $moduloId);
        })
        ->where('usuario_id', $usuarioId)
        ->where('vista', true)
        ->count();

        return $totalLecciones > 0 && $leccionesVistas >= $totalLecciones;
    }

    /**
     * Obtener estado de desbloqueo de evaluación
     * GET /modulos/{moduloId}/evaluacion/estado-desbloqueo
     */
    public function getEstadoEvaluacion($moduloId)
    {
        try {
            $usuario = Auth::user();

            // Contar lecciones totales vs vistas
            $totalLecciones = Leccion::where('modulo_id', $moduloId)
                ->where('estado', 'activo')
                ->count();

            $leccionesVistas = ProgresoLeccion::whereHas('leccion', function ($q) use ($moduloId) {
                $q->where('modulo_id', $moduloId);
            })
            ->where('usuario_id', $usuario->id)
            ->where('vista', true)
            ->count();

            // REGLA: La evaluación se desbloquea cuando se ven TODAS las lecciones
            $evaluacionDesbloqueada = $totalLecciones > 0 && $leccionesVistas >= $totalLecciones;

            return response()->json([
                'success' => true,
                'data' => [
                    'evaluacion_desbloqueada' => $evaluacionDesbloqueada,
                    'requisitos' => [
                        'lecciones_requeridas' => $totalLecciones,
                        'lecciones_vistas' => $leccionesVistas,
                        'completado' => $evaluacionDesbloqueada
                    ],
                    'mensaje' => $evaluacionDesbloqueada
                        ? '¡Felicidades! Has desbloqueado la evaluación final.'
                        : 'Completa todas las lecciones para desbloquear la evaluación.'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar lección como vista (para cuando el usuario da "Siguiente")
     * POST /modulos/{moduloId}/lecciones/{leccionId}/marcar-vista
     */
    public function marcarLeccionVista($moduloId, $leccionId)
    {
        try {
            $usuario = Auth::user();

            // Verificar que la lección existe
            $leccion = Leccion::where('id', $leccionId)
                ->where('modulo_id', $moduloId)
                ->firstOrFail();

            // Marcar como vista
            $progreso = ProgresoLeccion::updateOrCreate(
                [
                    'usuario_id' => $usuario->id,
                    'leccion_id' => $leccionId
                ],
                [
                    'vista' => true,
                    'fecha_vista' => now()
                ]
            );

            // Actualizar progreso del módulo
            $this->actualizarProgresoModulo($moduloId, $usuario->id);

            return response()->json([
                'success' => true,
                'message' => 'Progreso guardado',
                'data' => [
                    'leccion_id' => $leccionId,
                    'vista' => true,
                    'fecha_vista' => $progreso->fecha_vista
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar progreso',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lección para continuar (última vista o primera)
     * GET /modulos/{moduloId}/continuar
     */
    public function getLeccionParaContinuar($moduloId)
    {
        try {
            $usuario = Auth::user();

            // Buscar última lección vista
            $ultimaLeccionVista = Leccion::whereHas('progresos', function ($q) use ($usuario) {
                $q->where('usuario_id', $usuario->id)
                  ->where('vista', true);
            })
            ->where('modulo_id', $moduloId)
            ->orderBy('orden', 'desc')
            ->first();

            if ($ultimaLeccionVista) {
                // Verificar si hay siguiente lección
                $siguienteLeccion = Leccion::where('modulo_id', $moduloId)
                    ->where('orden', '>', $ultimaLeccionVista->orden)
                    ->orderBy('orden')
                    ->first();

                if ($siguienteLeccion) {
                    // Ir a la siguiente lección
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'tipo' => 'siguiente',
                            'leccion' => [
                                'id' => $siguienteLeccion->id,
                                'titulo' => $siguienteLeccion->titulo,
                                'orden' => $siguienteLeccion->orden
                            ]
                        ]
                    ]);
                } else {
                    // Ya está en la última lección
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'tipo' => 'ultima',
                            'leccion' => [
                                'id' => $ultimaLeccionVista->id,
                                'titulo' => $ultimaLeccionVista->titulo,
                                'orden' => $ultimaLeccionVista->orden
                            ],
                            'mensaje' => 'Has completado todas las lecciones. ¡Accede a la evaluación!'
                        ]
                    ]);
                }
            }

            // Si no ha visto ninguna, comenzar con la primera
            $primeraLeccion = Leccion::where('modulo_id', $moduloId)
                ->where('estado', 'activo')
                ->orderBy('orden')
                ->first();

            if (!$primeraLeccion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay lecciones disponibles'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tipo' => 'primera',
                    'leccion' => [
                        'id' => $primeraLeccion->id,
                        'titulo' => $primeraLeccion->titulo,
                        'orden' => $primeraLeccion->orden
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al buscar lección',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estado de evaluación aprobada
     * POST /modulos/{moduloId}/actualizar-evaluacion-aprobada
     */
    public function actualizarEvaluacionAprobada($moduloId)
    {
        try {
            $usuario = Auth::user();

            // Buscar progreso del módulo
            $progreso = ProgresoModulo::where('usuario_id', $usuario->id)
                ->where('modulo_id', $moduloId)
                ->firstOrFail();

            // Marcar evaluación como aprobada
            $progreso->update([
                'evaluacion_aprobada' => true,
                'fecha_ultimo_progreso' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Evaluación marcada como aprobada',
                'data' => [
                    'evaluacion_aprobada' => true,
                    'certificacion_disponible' => $progreso->porcentaje_completado >= 100
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================
     * MÉTODOS PRIVADOS / HELPERS
     * ============================================
     */

    private function contarLeccionesVistas($moduloId, $usuarioId)
    {
        return ProgresoLeccion::whereHas('leccion', function ($q) use ($moduloId) {
            $q->where('modulo_id', $moduloId);
        })
        ->where('usuario_id', $usuarioId)
        ->where('vista', true)
        ->count();
    }

    private function actualizarProgresoModulo($moduloId, $usuarioId)
    {
        $totalLecciones = Leccion::where('modulo_id', $moduloId)
            ->where('estado', 'activo')
            ->count();

        $leccionesVistas = $this->contarLeccionesVistas($moduloId, $usuarioId);
        $porcentaje = $totalLecciones > 0 ? ($leccionesVistas / $totalLecciones) * 100 : 0;

        // Obtener última lección vista
        $ultimaLeccion = $this->obtenerUltimaLeccionVista($moduloId, $usuarioId);

        $progresoModulo = ProgresoModulo::updateOrCreate(
            [
                'usuario_id' => $usuarioId,
                'modulo_id' => $moduloId
            ],
            [
                'porcentaje_completado' => $porcentaje,
                'lecciones_vistas' => $leccionesVistas,
                'total_lecciones' => $totalLecciones,
                'ultima_leccion_vista_id' => $ultimaLeccion ? $ultimaLeccion->id : null,
                'fecha_ultimo_progreso' => now()
            ]
        );

        return $progresoModulo;
    }

    private function obtenerUltimaLeccionVista($moduloId, $usuarioId)
    {
        return Leccion::whereHas('progresos', function ($q) use ($usuarioId) {
            $q->where('usuario_id', $usuarioId)
              ->where('vista', true);
        })
        ->where('modulo_id', $moduloId)
        ->orderBy('orden', 'desc')
        ->first();
    }

    private function getIconoModulo($tipo)
    {
        $iconos = [
            'html' => '📄',
            'css' => '🎨',
            'javascript' => '⚡',
            'php' => '🐘',
            'sql' => '🗃️',
            'introduccion' => '🚀'
        ];

        return $iconos[$tipo] ?? '📚';
    }
}
