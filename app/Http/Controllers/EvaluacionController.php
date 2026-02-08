<?php

namespace App\Http\Controllers;

use App\Models\Evaluacion;
use App\Models\IntentoEvaluacion;
use App\Models\Modulo;
use App\Models\PreguntaEvaluacion;
use App\Models\RespuestaEvaluacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EvaluacionController extends Controller
{
    /**
     * Obtener información de evaluación de un módulo
     * GET /modulos/{moduloId}/evaluacion
     */
    public function getEvaluacion($moduloId)
    {
        try {
            $usuario = Auth::user();
            $modulo = Modulo::findOrFail($moduloId);

            // Verificar si el módulo tiene evaluación
            $evaluacion = Evaluacion::with('modulo')
                ->where('modulo_id', $moduloId)
                ->where('estado', 'activo')
                ->first();

            if (!$evaluacion) {
                return response()->json([
                    'success' => true,
                    'message' => 'Este módulo no tiene evaluación configurada',
                    'data' => [
                        'tiene_evaluacion' => false,
                        'modulo' => [
                            'id' => $modulo->id,
                            'titulo' => $modulo->titulo
                        ]
                    ]
                ], 200);
            }

            // Obtener intentos del usuario
            $intentosUsuario = IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->where('evaluacion_id', $evaluacion->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $intentoEnProgreso = $intentosUsuario->first(function ($intento) {
                return $intento->estaEnProgreso();
            });

            $intentosCompletados = $intentosUsuario->where('estado', 'completado')->count();
            $ultimoIntentoAprobado = $intentosUsuario->first(function ($intento) {
                return $intento->aprobado;
            });

            // Verificar si puede realizar nuevo intento
            $puedeIntentar = $this->puedeRealizarIntento($usuario, $evaluacion, $intentosCompletados);

            return response()->json([
                'success' => true,
                'data' => [
                    'tiene_evaluacion' => true,
                    'evaluacion' => [
                        'id' => $evaluacion->id,
                        'titulo' => $evaluacion->titulo,
                        'descripcion' => $evaluacion->descripcion,
                        'numero_preguntas' => $evaluacion->numero_preguntas,
                        'tiempo_limite' => $evaluacion->tiempo_limite,
                        'puntaje_minimo' => (float) $evaluacion->puntaje_minimo,
                        'max_intentos' => $evaluacion->max_intentos,
                        'estado' => $evaluacion->estado
                    ],
                    'modulo' => [
                        'id' => $modulo->id,
                        'titulo' => $modulo->titulo,
                        'slug' => $modulo->slug
                    ],
                    'estado_usuario' => [
                        'puede_intentar' => $puedeIntentar['puede'],
                        'mensaje' => $puedeIntentar['mensaje'],
                        'intentos_completados' => $intentosCompletados,
                        'intentos_disponibles' => max(0, $evaluacion->max_intentos - $intentosCompletados),
                        'tiene_intento_en_progreso' => !is_null($intentoEnProgreso),
                        'intento_en_progreso_id' => $intentoEnProgreso ? $intentoEnProgreso->id : null,
                        'ya_aprobo' => !is_null($ultimoIntentoAprobado),
                        'mejor_porcentaje' => $intentosUsuario->max('porcentaje_obtenido')
                    ],
                    'intentos' => $intentosUsuario->map(function ($intento) {
                        return [
                            'id' => $intento->id,
                            'intento_numero' => $intento->intento_numero,
                            'fecha_inicio' => $intento->fecha_inicio,
                            'fecha_fin' => $intento->fecha_fin,
                            'porcentaje_obtenido' => (float) $intento->porcentaje_obtenido,
                            'preguntas_correctas' => $intento->preguntas_correctas,
                            'preguntas_incorrectas' => $intento->preguntas_incorrectas,
                            'aprobado' => $intento->aprobado,
                            'estado' => $intento->estado,
                            'tiempo_utilizado' => $intento->tiempo_utilizado
                        ];
                    })
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Iniciar nuevo intento de evaluación
     * POST /modulos/{moduloId}/evaluacion/iniciar
     */
    public function iniciarEvaluacion(Request $request, $moduloId)
    {
        try {
            $usuario = Auth::user();
            $modulo = Modulo::findOrFail($moduloId);

            $evaluacion = Evaluacion::where('modulo_id', $moduloId)
                ->where('estado', 'activo')
                ->firstOrFail();

            // Verificar intentos previos
            $intentosUsuario = IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->where('evaluacion_id', $evaluacion->id)
                ->get();

            $intentosCompletados = $intentosUsuario->where('estado', 'completado')->count();
            $intentoEnProgreso = $intentosUsuario->first(function ($intento) {
                return $intento->estaEnProgreso();
            });

            // Validar si puede iniciar
            if ($intentoEnProgreso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes un intento en progreso',
                    'data' => [
                        'intento_en_progreso_id' => $intentoEnProgreso->id
                    ]
                ], 400);
            }

            if ($intentosCompletados >= $evaluacion->max_intentos) {
                // Verificar si ha pasado 24 horas desde el último intento
                $ultimoIntento = $intentosUsuario->where('estado', 'completado')
                    ->sortByDesc('created_at')
                    ->first();

                if ($ultimoIntento && now()->diffInHours($ultimoIntento->created_at) < 24) {
                    $horasRestantes = 24 - now()->diffInHours($ultimoIntento->created_at);
                    return response()->json([
                        'success' => false,
                        'message' => "Has alcanzado el límite de intentos. Podrás intentar nuevamente en {$horasRestantes} horas.",
                        'code' => 'LIMITE_INTENTOS_ALCANZADO'
                    ], 429);
                }
            }

            // Obtener preguntas aleatorias
            $preguntas = $evaluacion->getPreguntasAleatorias();

            if ($preguntas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay preguntas disponibles para esta evaluación'
                ], 404);
            }

            // Crear nuevo intento
            $nuevoIntentoNumero = $intentosCompletados + 1;

            $intento = IntentoEvaluacion::create([
                'usuario_id' => $usuario->id,
                'evaluacion_id' => $evaluacion->id,
                'intento_numero' => $nuevoIntentoNumero,
                'fecha_inicio' => now(),
                'estado' => 'en_progreso',
                'puntuacion_total' => 0,
                'porcentaje_obtenido' => 0,
                'preguntas_correctas' => 0,
                'preguntas_incorrectas' => 0,
                'aprobado' => false
            ]);

            // Preparar preguntas para el frontend (sin respuestas correctas)
            $preguntasFormateadas = $preguntas->map(function ($pregunta) {
                $opciones = $pregunta->getOpcionesAleatorias()->map(function ($opcion) {
                    return [
                        'id' => $opcion->id,
                        'texto' => $opcion->texto,
                        'orden' => $opcion->orden,
                        'pareja_arrastre' => $opcion->pareja_arrastre
                    ];
                });

                return [
                    'id' => $pregunta->id,
                    'pregunta' => $pregunta->pregunta,
                    'tipo' => $pregunta->tipo,
                    'puntos' => (float) $pregunta->puntos,
                    'orden' => $pregunta->orden,
                    'opciones' => $opciones,
                    'instrucciones' => $this->getInstruccionesPorTipo($pregunta->tipo)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'intento_id' => $intento->id,
                    'evaluacion_id' => $evaluacion->id,
                    'modulo_id' => $modulo->id,
                    'fecha_inicio' => $intento->fecha_inicio,
                    'tiempo_limite_minutos' => $evaluacion->tiempo_limite,
                    'tiempo_limite_segundos' => $evaluacion->tiempo_limite * 60,
                    'puntaje_minimo' => (float) $evaluacion->puntaje_minimo,
                    'numero_preguntas' => $preguntasFormateadas->count(),
                    'preguntas' => $preguntasFormateadas,
                    'intento_numero' => $intento->intento_numero
                ]
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Evaluación no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener intento en progreso
     * GET /modulos/{moduloId}/evaluacion/en-progreso
     */
    public function getIntentoEnProgreso($moduloId)
    {
        try {
            $usuario = Auth::user();

            $evaluacion = Evaluacion::where('modulo_id', $moduloId)
                ->where('estado', 'activo')
                ->firstOrFail();

            $intento = IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->where('evaluacion_id', $evaluacion->id)
                ->where('estado', 'en_progreso')
                ->first();

            if (!$intento) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tienes intentos en progreso',
                    'data' => null
                ], 200);
            }

            // Verificar si el intento ha expirado
            if ($intento->estaExpirado()) {
                $intento->update([
                    'estado' => 'expirado',
                    'fecha_fin' => now(),
                    'tiempo_utilizado' => $evaluacion->tiempo_limite * 60
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'El tiempo para esta evaluación ha expirado',
                    'code' => 'TIEMPO_EXPIRADO'
                ], 400);
            }

            // Obtener preguntas ya respondidas
            $respuestas = $intento->respuestas()->with('pregunta')->get();
            $preguntasRespondidas = $respuestas->pluck('pregunta_evaluacion_id')->toArray();

            // Obtener todas las preguntas de la evaluación
            $preguntas = PreguntaEvaluacion::where('evaluacion_id', $evaluacion->id)
                ->with(['opciones' => function ($query) {
                    $query->orderBy('orden', 'asc');
                }])
                ->orderBy('orden', 'asc')
                ->get()
                ->map(function ($pregunta) use ($preguntasRespondidas, $respuestas) {
                    $respondida = in_array($pregunta->id, $preguntasRespondidas);
                    $respuestaUsuario = $respondida ?
                        $respuestas->firstWhere('pregunta_evaluacion_id', $pregunta->id) : null;

                    return [
                        'id' => $pregunta->id,
                        'pregunta' => $pregunta->pregunta,
                        'tipo' => $pregunta->tipo,
                        'puntos' => (float) $pregunta->puntos,
                        'orden' => $pregunta->orden,
                        'respondida' => $respondida,
                        'respuesta_usuario' => $respuestaUsuario ? [
                            'opcion_id' => $respuestaUsuario->opcion_seleccionada_id,
                            'respuesta_texto' => $respuestaUsuario->respuesta_texto
                        ] : null,
                        'opciones' => $pregunta->opciones->map(function ($opcion) {
                            return [
                                'id' => $opcion->id,
                                'texto' => $opcion->texto,
                                'orden' => $opcion->orden,
                                'pareja_arrastre' => $opcion->pareja_arrastre
                            ];
                        })
                    ];
                });

            // Calcular tiempo restante
            $tiempoTranscurrido = now()->diffInSeconds($intento->fecha_inicio);
            $tiempoLimiteSegundos = $evaluacion->tiempo_limite * 60;
            $tiempoRestante = max(0, $tiempoLimiteSegundos - $tiempoTranscurrido);

            return response()->json([
                'success' => true,
                'data' => [
                    'intento_id' => $intento->id,
                    'evaluacion_id' => $evaluacion->id,
                    'modulo_id' => $moduloId,
                    'fecha_inicio' => $intento->fecha_inicio,
                    'tiempo_transcurrido_segundos' => $tiempoTranscurrido,
                    'tiempo_restante_segundos' => $tiempoRestante,
                    'preguntas_respondidas' => count($preguntasRespondidas),
                    'preguntas_totales' => $preguntas->count(),
                    'preguntas' => $preguntas
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener intento en progreso',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar respuesta durante la evaluación
     * POST /modulos/{moduloId}/evaluacion/{intentoId}/respuesta
     */
    public function guardarRespuesta(Request $request, $moduloId, $intentoId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pregunta_id' => 'required|integer|exists:preguntas_evaluacion,id',
                'opcion_id' => 'nullable|integer|exists:opciones_evaluacion,id',
                'respuesta_texto' => 'nullable|string',
                'parejas' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida',
                    'errors' => $validator->errors()
                ], 422);
            }

            $usuario = Auth::user();

            // Verificar intento
            $intento = IntentoEvaluacion::where('id', $intentoId)
                ->where('estado', 'en_progreso')
                ->firstOrFail();

            if ($intento->usuario_id !== $usuario->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para modificar este intento'
                ], 403);
            }

            // Verificar que la pregunta pertenece a esta evaluación
            $pregunta = PreguntaEvaluacion::where('id', $request->pregunta_id)
                ->where('evaluacion_id', $intento->evaluacion_id)
                ->firstOrFail();

            // Verificar si ya existe respuesta para esta pregunta
            $respuestaExistente = RespuestaEvaluacion::where('intento_id', $intento->id)
                ->where('pregunta_evaluacion_id', $pregunta->id)
                ->first();

            // Determinar si la respuesta es correcta
            $esCorrecta = false;
            $puntosObtenidos = 0;

            switch ($pregunta->tipo) {
                case 'seleccion_multiple':
                case 'verdadero_falso':
                    if ($request->opcion_id) {
                        $opcionSeleccionada = $pregunta->opciones()->find($request->opcion_id);
                        if ($opcionSeleccionada) {
                            $esCorrecta = $opcionSeleccionada->es_correcta;
                            $puntosObtenidos = $esCorrecta ? $pregunta->puntos : 0;
                        }
                    }
                    break;

                case 'arrastrar_soltar':
                    $parejas = $request->parejas;
                    if (is_array($parejas)) {
                        // Validar todas las parejas (simplificado)
                        $todasCorrectas = true;
                        foreach ($parejas as $pareja) {
                            $opcion = $pregunta->opciones()
                                ->where('id', $pareja['id_opcion'])
                                ->where('pareja_arrastre', $pareja['pareja'])
                                ->first();

                            if (!$opcion || !$opcion->es_correcta) {
                                $todasCorrectas = false;
                                break;
                            }
                        }
                        $esCorrecta = $todasCorrectas;
                        $puntosObtenidos = $esCorrecta ? $pregunta->puntos : 0;
                        $request->respuesta_texto = json_encode($parejas);
                    }
                    break;
            }

            // Guardar o actualizar respuesta
            $respuestaData = [
                'intento_id' => $intento->id,
                'pregunta_evaluacion_id' => $pregunta->id,
                'opcion_seleccionada_id' => $request->opcion_id,
                'respuesta_texto' => $request->respuesta_texto,
                'es_correcta' => $esCorrecta,
                'puntos_obtenidos' => $puntosObtenidos
            ];

            if ($respuestaExistente) {
                $respuestaExistente->update($respuestaData);
                $respuesta = $respuestaExistente;
            } else {
                $respuesta = RespuestaEvaluacion::create($respuestaData);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'respuesta_id' => $respuesta->id,
                    'pregunta_id' => $pregunta->id,
                    'es_correcta' => $esCorrecta,
                    'puntos_obtenidos' => $puntosObtenidos,
                    'mensaje' => $esCorrecta ? 'Respuesta correcta' : 'Respuesta guardada'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar respuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalizar evaluación
     * POST /modulos/{moduloId}/evaluacion/{intentoId}/finalizar
     */
    /**
 * Finalizar evaluación
 * POST /modulos/{moduloId}/evaluacion/{intentoId}/finalizar
 */
    public function finalizarEvaluacion(Request $request, $moduloId, $intentoId)
    {
        try {
            $usuario = Auth::user();

            $intento = IntentoEvaluacion::where('id', $intentoId)
                ->where('estado', 'en_progreso')
                ->firstOrFail();

            if ($intento->usuario_id !== $usuario->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para finalizar este intento'
                ], 403);
            }

            // Calcular resultados
            $intento->calcularResultado();

            // ===== ¡IMPORTANTE! LLAMAR AL PROGRESO CONTROLLER =====
            // Esto actualiza automáticamente la base de datos
            $progresoController = new ProgresoController();
            $progresoController->actualizarProgresoModulo($moduloId, $usuario->id);

            // También llamar explícitamente al método de evaluación aprobada
            $progresoController->actualizarEvaluacionAprobada($moduloId);

            // Verificar si aprobó
            $aprobado = $intento->aprobado;

            // Verificar progreso actualizado
            $progresoActualizado = \App\Models\ProgresoModulo::where('usuario_id', $usuario->id)
                ->where('modulo_id', $moduloId)
                ->first();

            $responseData = [
                'intento_id' => $intento->id,
                'evaluacion_id' => $intento->evaluacion_id,
                'fecha_fin' => $intento->fecha_fin,
                'tiempo_utilizado_segundos' => $intento->tiempo_utilizado,
                'tiempo_utilizado_minutos' => round($intento->tiempo_utilizado / 60, 2),
                'puntuacion_total' => (float) $intento->puntuacion_total,
                'porcentaje_obtenido' => (float) $intento->porcentaje_obtenido,
                'preguntas_correctas' => $intento->preguntas_correctas,
                'preguntas_incorrectas' => $intento->preguntas_incorrectas,
                'preguntas_totales' => $intento->preguntas_correctas + $intento->preguntas_incorrectas,
                'aprobado' => $aprobado,
                'puntaje_minimo' => (float) $intento->evaluacion->puntaje_minimo,
                'mensaje' => $aprobado ?
                    '¡Felicidades! Has aprobado la evaluación.' :
                    'No has alcanzado el puntaje mínimo. Puedes intentarlo nuevamente.'
            ];

            // Si aprobó, agregar información de certificación
            if ($aprobado && $progresoActualizado) {
                $responseData['certificacion'] = [
                    'disponible' => $progresoActualizado->certificado_disponible,
                    'modulo_id' => $moduloId,
                    'mensaje' => $progresoActualizado->certificado_disponible
                        ? '¡Ya puedes generar tu certificado!'
                        : 'Completa el 100% del módulo para certificar.'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $responseData
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error al finalizar evaluación', [
                'error' => $e->getMessage(),
                'modulo_id' => $moduloId,
                'intento_id' => $intentoId
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar evaluación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resultados detallados de un intento
     * GET /modulos/{moduloId}/evaluacion/{intentoId}/resultado
     */
    public function getResultadosIntento($moduloId, $intentoId)
    {
        try {
            $usuario = Auth::user();

            $intento = IntentoEvaluacion::with(['evaluacion', 'respuestas.pregunta', 'respuestas.opcionSeleccionada'])
                ->where('id', $intentoId)
                ->firstOrFail();

            if ($intento->usuario_id !== $usuario->id && $usuario->rol !== 'administrador') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para ver estos resultados'
                ], 403);
            }

            if (!$intento->estaCompletado() && !$intento->estaExpirado()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta evaluación aún no ha sido finalizada'
                ], 400);
            }

            // Obtener respuestas con información detallada
            $respuestasDetalladas = [];
            foreach ($intento->respuestas as $respuesta) {
                $pregunta = $respuesta->pregunta;
                $opcionCorrecta = $pregunta->getOpcionCorrecta();

                $respuestasDetalladas[] = [
                    'pregunta_id' => $pregunta->id,
                    'pregunta_texto' => $pregunta->pregunta,
                    'tipo' => $pregunta->tipo,
                    'puntos' => (float) $pregunta->puntos,
                    'respuesta_usuario' => [
                        'opcion_id' => $respuesta->opcion_seleccionada_id,
                        'opcion_texto' => $respuesta->opcionSeleccionada ? $respuesta->opcionSeleccionada->texto : null,
                        'respuesta_texto' => $respuesta->respuesta_texto,
                        'es_correcta' => $respuesta->es_correcta,
                        'puntos_obtenidos' => (float) $respuesta->puntos_obtenidos
                    ],
                    'respuesta_correcta' => $opcionCorrecta ? [
                        'id' => $opcionCorrecta->id,
                        'texto' => $opcionCorrecta->texto,
                        'pareja_arrastre' => $opcionCorrecta->pareja_arrastre
                    ] : null,
                    'explicacion' => $this->getExplicacionPregunta($pregunta->tipo, $respuesta->es_correcta)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'intento' => [
                        'id' => $intento->id,
                        'intento_numero' => $intento->intento_numero,
                        'fecha_inicio' => $intento->fecha_inicio,
                        'fecha_fin' => $intento->fecha_fin,
                        'tiempo_utilizado_segundos' => $intento->tiempo_utilizado,
                        'tiempo_utilizado_minutos' => round($intento->tiempo_utilizado / 60, 2),
                        'estado' => $intento->estado
                    ],
                    'resultados' => [
                        'puntuacion_total' => (float) $intento->puntuacion_total,
                        'porcentaje_obtenido' => (float) $intento->porcentaje_obtenido,
                        'preguntas_correctas' => $intento->preguntas_correctas,
                        'preguntas_incorrectas' => $intento->preguntas_incorrectas,
                        'preguntas_totales' => $intento->preguntas_correctas + $intento->preguntas_incorrectas,
                        'aprobado' => $intento->aprobado,
                        'puntaje_minimo_requerido' => (float) $intento->evaluacion->puntaje_minimo
                    ],
                    'evaluacion' => [
                        'id' => $intento->evaluacion->id,
                        'titulo' => $intento->evaluacion->titulo,
                        'descripcion' => $intento->evaluacion->descripcion
                    ],
                    'modulo' => [
                        'id' => $moduloId,
                        'titulo' => $intento->evaluacion->modulo->titulo ?? 'Módulo'
                    ],
                    'respuestas_detalladas' => $respuestasDetalladas,
                    'recomendaciones' => $this->getRecomendacionesResultado($intento)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resultados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de intentos
     * GET /modulos/{moduloId}/evaluacion/intentos
     */
    public function getHistorialIntentos($moduloId)
    {
        try {
            $usuario = Auth::user();

            $evaluacion = Evaluacion::where('modulo_id', $moduloId)
                ->where('estado', 'activo')
                ->firstOrFail();

            $intentos = IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->where('evaluacion_id', $evaluacion->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($intento) {
                    return [
                        'id' => $intento->id,
                        'intento_numero' => $intento->intento_numero,
                        'fecha_inicio' => $intento->fecha_inicio,
                        'fecha_fin' => $intento->fecha_fin,
                        'porcentaje_obtenido' => (float) $intento->porcentaje_obtenido,
                        'preguntas_correctas' => $intento->preguntas_correctas,
                        'preguntas_incorrectas' => $intento->preguntas_incorrectas,
                        'aprobado' => $intento->aprobado,
                        'estado' => $intento->estado,
                        'tiempo_utilizado' => $intento->tiempo_utilizado,
                        'puede_revisar' => $intento->estaCompletado() || $intento->estaExpirado()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'evaluacion' => [
                        'id' => $evaluacion->id,
                        'titulo' => $evaluacion->titulo,
                        'max_intentos' => $evaluacion->max_intentos
                    ],
                    'total_intentos' => $intentos->count(),
                    'intentos_aprobados' => $intentos->where('aprobado', true)->count(),
                    'mejor_porcentaje' => $intentos->max('porcentaje_obtenido') ?? 0,
                    'intentos' => $intentos
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Métodos helper privados
     */
    private function puedeRealizarIntento($usuario, $evaluacion, $intentosCompletados)
    {
        // Si ya aprobó
        $yaAprobo = IntentoEvaluacion::where('usuario_id', $usuario->id)
            ->where('evaluacion_id', $evaluacion->id)
            ->where('aprobado', true)
            ->exists();

        if ($yaAprobo) {
            return [
                'puede' => false,
                'mensaje' => 'Ya has aprobado esta evaluación'
            ];
        }

        // Si tiene intento en progreso
        $intentoEnProgreso = IntentoEvaluacion::where('usuario_id', $usuario->id)
            ->where('evaluacion_id', $evaluacion->id)
            ->where('estado', 'en_progreso')
            ->exists();

        if ($intentoEnProgreso) {
            return [
                'puede' => true,
                'mensaje' => 'Tienes un intento en progreso'
            ];
        }

        // Verificar límite de intentos
        if ($intentosCompletados >= $evaluacion->max_intentos) {
            $ultimoIntento = IntentoEvaluacion::where('usuario_id', $usuario->id)
                ->where('evaluacion_id', $evaluacion->id)
                ->where('estado', 'completado')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($ultimoIntento && now()->diffInHours($ultimoIntento->created_at) < 24) {
                $horasRestantes = 24 - now()->diffInHours($ultimoIntento->created_at);
                return [
                    'puede' => false,
                    'mensaje' => "Podrás intentar nuevamente en {$horasRestantes} horas"
                ];
            }
        }

        return [
            'puede' => true,
            'mensaje' => 'Puedes iniciar una nueva evaluación'
        ];
    }

    private function getInstruccionesPorTipo($tipo)
    {
        $instrucciones = [
            'seleccion_multiple' => 'Selecciona la respuesta correcta.',
            'verdadero_falso' => 'Indica si la afirmación es verdadera o falsa.',
            'arrastrar_soltar' => 'Relaciona cada elemento con su definición correspondiente.'
        ];

        return $instrucciones[$tipo] ?? 'Responde la siguiente pregunta:';
    }

    private function getExplicacionPregunta($tipo, $esCorrecta)
    {
        if ($esCorrecta) {
            $explicaciones = [
                'seleccion_multiple' => '¡Correcto! Has seleccionado la opción adecuada.',
                'verdadero_falso' => '¡Correcto! Tu evaluación es precisa.',
                'arrastrar_soltar' => '¡Excelente! Has realizado las relaciones correctamente.'
            ];
        } else {
            $explicaciones = [
                'seleccion_multiple' => 'Revisa los conceptos relacionados con esta pregunta.',
                'verdadero_falso' => 'Analiza nuevamente la afirmación planteada.',
                'arrastrar_soltar' => 'Verifica las relaciones entre conceptos y definiciones.'
            ];
        }

        return $explicaciones[$tipo] ?? ($esCorrecta ? '¡Bien hecho!' : 'Sigue practicando.');
    }

    private function getRecomendacionesResultado($intento)
    {
        $porcentaje = (float) $intento->porcentaje_obtenido;

        if ($intento->aprobado) {
            if ($porcentaje >= 90) {
                return [
                    'mensaje' => '¡Excelente desempeño! Has dominado los conceptos.',
                    'siguiente_paso' => 'Continúa con el siguiente módulo o repasa temas avanzados.'
                ];
            } elseif ($porcentaje >= 80) {
                return [
                    'mensaje' => 'Muy buen trabajo. Tienes un sólido entendimiento.',
                    'siguiente_paso' => 'Refuerza los temas donde tuviste dificultades.'
                ];
            } else {
                return [
                    'mensaje' => 'Has aprobado. Buen esfuerzo.',
                    'siguiente_paso' => 'Revisa las preguntas incorrectas para mejorar.'
                ];
            }
        } else {
            if ($porcentaje >= 60) {
                return [
                    'mensaje' => 'Estás cerca de aprobar. Un poco más de esfuerzo.',
                    'siguiente_paso' => 'Repasa específicamente los temas de las preguntas falladas.'
                ];
            } else {
                return [
                    'mensaje' => 'Necesitas reforzar los conceptos principales.',
                    'siguiente_paso' => 'Revisa las lecciones del módulo antes de intentar nuevamente.'
                ];
            }
        }
    }
}
