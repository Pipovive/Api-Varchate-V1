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

                // RECALCULAR con lógica CORREGIDA
                $totalLecciones = $modulo->lecciones()->count();
                $leccionesVistas = $this->contarLeccionesVistas($modulo->id, $usuario->id);

                // ===== LÓGICA CORREGIDA =====
                // Si aprobó evaluación: 100%
                // Si no: solo lecciones completadas
                if ($progreso->evaluacion_aprobada) {
                    $porcentajeTotal = 100.00;
                    $porcentajeLecciones = ($totalLecciones > 0)
                        ? ($leccionesVistas / $totalLecciones) * 100
                        : 0;
                    $porcentajeEvaluacion = 100 - $porcentajeLecciones;
                } else {
                    $porcentajeLecciones = ($totalLecciones > 0)
                        ? ($leccionesVistas / $totalLecciones) * 100
                        : 0;
                    $porcentajeTotal = $porcentajeLecciones;
                    $porcentajeEvaluacion = 0;
                }
                // ===== FIN LÓGICA CORREGIDA =====

                // Actualizar si es necesario
                if ($progreso->porcentaje_completado != $porcentajeTotal) {
                    $ultimaLeccion = $this->obtenerUltimaLeccionVista($modulo->id, $usuario->id);

                    $progreso->update([
                        'porcentaje_completado' => $porcentajeTotal,
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
                    'evaluacion_aprobada' => (bool) $progreso->evaluacion_aprobada,
                    'certificado_disponible' => (bool) $progreso->certificado_disponible,
                    'desglose' => [
                        'lecciones' => $porcentajeLecciones,
                        'evaluacion' => $porcentajeEvaluacion
                    ]
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
     * Sincronizar evaluación aprobada con progreso
     * POST /modulos/{moduloId}/sincronizar-evaluacion
     */
    public function sincronizarEvaluacion($moduloId)
    {
        try {
            $usuario = Auth::user();

            \Log::info('Sincronizando evaluación', [
                'usuario_id' => $usuario->id,
                'modulo_id' => $moduloId
            ]);

            // 1. Buscar último intento aprobado
            $ultimoIntentoAprobado = \App\Models\IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->whereHas('evaluacion', function ($q) use ($moduloId) {
                    $q->where('modulo_id', $moduloId);
                })
                ->where('aprobado', true)
                ->latest()
                ->first();

            \Log::info('Último intento encontrado', [
                'existe' => !is_null($ultimoIntentoAprobado),
                'intento_id' => $ultimoIntentoAprobado ? $ultimoIntentoAprobado->id : null,
                'aprobado' => $ultimoIntentoAprobado ? $ultimoIntentoAprobado->aprobado : false
            ]);

            // 2. Buscar o crear progreso
            $progreso = ProgresoModulo::where('usuario_id', $usuario->id)
                ->where('modulo_id', $moduloId)
                ->first();

            if (!$progreso) {
                // Si no existe, crear uno nuevo
                $totalLecciones = Leccion::where('modulo_id', $moduloId)
                    ->where('estado', 'activo')
                    ->count();

                $leccionesVistas = $this->contarLeccionesVistas($moduloId, $usuario->id);

                $progreso = ProgresoModulo::create([
                    'usuario_id' => $usuario->id,
                    'modulo_id' => $moduloId,
                    'porcentaje_completado' => !is_null($ultimoIntentoAprobado) ? 100.00 : 0,
                    'lecciones_vistas' => $leccionesVistas,
                    'total_lecciones' => $totalLecciones,
                    'evaluacion_aprobada' => !is_null($ultimoIntentoAprobado),
                    'certificado_disponible' => !is_null($ultimoIntentoAprobado),
                    'fecha_ultimo_progreso' => now()
                ]);
            } else {
                // Si existe, actualizar
                $progreso->update([
                    'evaluacion_aprobada' => !is_null($ultimoIntentoAprobado),
                    'certificado_disponible' => !is_null($ultimoIntentoAprobado),
                    'porcentaje_completado' => !is_null($ultimoIntentoAprobado) ? 100.00 : $progreso->porcentaje_completado,
                    'fecha_ultimo_progreso' => now()
                ]);
            }

            // 3. Actualizar ranking
            $this->actualizarRanking($moduloId, $usuario->id, $progreso->porcentaje_completado);

            \Log::info('Progreso sincronizado', [
                'evaluacion_aprobada' => $progreso->evaluacion_aprobada,
                'certificado_disponible' => $progreso->certificado_disponible,
                'porcentaje_completado' => $progreso->porcentaje_completado
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Evaluación sincronizada correctamente',
                'data' => [
                    'progreso' => [
                        'porcentaje_completado' => (float) $progreso->porcentaje_completado,
                        'evaluacion_aprobada' => (bool) $progreso->evaluacion_aprobada,
                        'certificado_disponible' => (bool) $progreso->certificado_disponible,
                        'lecciones_vistas' => $progreso->lecciones_vistas,
                        'total_lecciones' => $progreso->total_lecciones
                    ],
                    'intento_aprobado' => !is_null($ultimoIntentoAprobado) ? [
                        'id' => $ultimoIntentoAprobado->id,
                        'porcentaje' => (float) $ultimoIntentoAprobado->porcentaje_obtenido,
                        'fecha' => $ultimoIntentoAprobado->fecha_fin
                    ] : null
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error sincronizando evaluación', [
                'error' => $e->getMessage(),
                'modulo_id' => $moduloId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
 * Forzar actualización de progreso
 * POST /modulos/{moduloId}/forzar-actualizacion
 */
    public function forzarActualizacionProgreso($moduloId)
    {
        try {
            $usuario = Auth::user();

            // Forzar actualización completa
            $this->actualizarProgresoModulo($moduloId, $usuario->id);

            // Verificar progreso actualizado
            $progreso = ProgresoModulo::where('usuario_id', $usuario->id)
                ->where('modulo_id', $moduloId)
                ->first();

            // Verificar intentos aprobados
            $intentoAprobado = \App\Models\IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->whereHas('evaluacion', function ($q) use ($moduloId) {
                    $q->where('modulo_id', $moduloId);
                })
                ->where('aprobado', true)
                ->exists();

            return response()->json([
                'success' => true,
                'message' => 'Progreso forzado correctamente',
                'data' => [
                    'progreso_actualizado' => $progreso ? [
                        'porcentaje_completado' => (float) $progreso->porcentaje_completado,
                        'evaluacion_aprobada' => (bool) $progreso->evaluacion_aprobada,
                        'certificado_disponible' => (bool) $progreso->certificado_disponible
                    ] : null,
                    'tiene_intento_aprobado' => $intentoAprobado,
                    'consistencia' => $progreso ?
                        ((bool)$progreso->evaluacion_aprobada === $intentoAprobado) : false
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al forzar actualización',
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

    public function actualizarProgresoModulo($moduloId, $usuarioId)
    {
        $totalLecciones = Leccion::where('modulo_id', $moduloId)
            ->where('estado', 'activo')
            ->count();

        $leccionesVistas = $this->contarLeccionesVistas($moduloId, $usuarioId);

        // ===== LÓGICA CORREGIDA Y SIMPLIFICADA =====
        // 1. Verificar si ya aprobó evaluación
        $ultimoIntentoAprobado = \App\Models\IntentoEvaluacion::where('usuario_id', $usuarioId)
            ->whereHas('evaluacion', function ($q) use ($moduloId) {
                $q->where('modulo_id', $moduloId);
            })
            ->where('aprobado', true)
            ->latest()
            ->first();

        $evaluacionAprobada = !is_null($ultimoIntentoAprobado);

        // 2. Determinar porcentaje
        if ($evaluacionAprobada) {
            // Si aprobó evaluación: 100%
            $porcentajeTotal = 100.00;
            $certificadoDisponible = true;
        } else {
            // Si no aprobó: solo lecciones completadas
            $porcentajeTotal = $totalLecciones > 0
                ? ($leccionesVistas / $totalLecciones) * 100
                : 0;
            $certificadoDisponible = false;
        }
        // ===== FIN LÓGICA CORREGIDA =====

        // Obtener última lección vista
        $ultimaLeccion = $this->obtenerUltimaLeccionVista($moduloId, $usuarioId);

        $progresoModulo = ProgresoModulo::updateOrCreate(
            [
                'usuario_id' => $usuarioId,
                'modulo_id' => $moduloId
            ],
            [
                'porcentaje_completado' => $porcentajeTotal,
                'lecciones_vistas' => $leccionesVistas,
                'total_lecciones' => $totalLecciones,
                'evaluacion_aprobada' => $evaluacionAprobada,
                'certificado_disponible' => $certificadoDisponible,
                'ultima_leccion_vista_id' => $ultimaLeccion ? $ultimaLeccion->id : null,
                'fecha_ultimo_progreso' => now()
            ]
        );

        // Actualizar ranking si cambió
        $this->actualizarRanking($moduloId, $usuarioId, $porcentajeTotal);

        return $progresoModulo;
    }


    private function actualizarRanking($moduloId, $usuarioId, $porcentaje)
    {
        // Actualizar tabla ranking
        \App\Models\Ranking::updateOrCreate(
            [
                'modulo_id' => $moduloId,
                'usuario_id' => $usuarioId
            ],
            [
                'porcentaje_progreso' => $porcentaje,
                'fecha_ultima_actualizacion' => now()
            ]
        );
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
