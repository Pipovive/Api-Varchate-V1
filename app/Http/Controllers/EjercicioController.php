<?php

namespace App\Http\Controllers;

use App\Models\Ejercicio;
use App\Models\IntentoEjercicio;
use App\Models\Leccion;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EjercicioController extends Controller
{
    /**
     * Obtener ejercicios de una lección
     * GET api/modulos/{moduloId}/lecciones/{leccionId}/ejercicios
     */
    public function getEjercicios($moduloId, $leccionId)
    {
        try {
            // Verificar que el módulo y lección existen
            $modulo = Modulo::findOrFail($moduloId);
            $leccion = Leccion::where('id', $leccionId)
                ->where('modulo_id', $moduloId)
                ->firstOrFail();

            // Verificar que la lección tiene ejercicios
            if (!$leccion->tiene_ejercicios) {
                return response()->json([
                    'success' => true,
                    'message' => 'Esta lección no contiene ejercicios',
                    'data' => [
                        'tiene_ejercicios' => false,
                        'ejercicios' => []
                    ]
                ], 200);
            }

            // Obtener ejercicios activos de la lección
            $ejercicios = Ejercicio::with(['opciones' => function ($query) {
                $query->orderBy('orden', 'asc');
            }])
                ->where('leccion_id', $leccionId)
                ->where('estado', 'activo')
                ->orderBy('orden', 'asc')
                ->get()
                ->map(function ($ejercicio) {
                    // Para evitar mostrar respuestas correctas al frontend
                    $ejercicio->opciones = $ejercicio->opciones->map(function ($opcion) {
                        return [
                            'id' => $opcion->id,
                            'texto' => $opcion->texto,
                            'orden' => $opcion->orden,
                            'pareja_arrastre' => $opcion->pareja_arrastre
                        ];
                    });

                    return [
                        'id' => $ejercicio->id,
                        'pregunta' => $ejercicio->pregunta,
                        'tipo' => $ejercicio->tipo,
                        'orden' => $ejercicio->orden,
                        'opciones' => $ejercicio->opciones,
                        'instrucciones' => $this->getInstruccionesPorTipo($ejercicio->tipo)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'tiene_ejercicios' => true,
                    'cantidad_ejercicios' => $ejercicios->count(),
                    'leccion' => [
                        'id' => $leccion->id,
                        'titulo' => $leccion->titulo,
                        'orden' => $leccion->orden
                    ],
                    'modulo' => [
                        'id' => $modulo->id,
                        'titulo' => $modulo->titulo,
                        'slug' => $modulo->slug
                    ],
                    'ejercicios' => $ejercicios
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo o lección no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ejercicios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar intento de ejercicio
     * POST api/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/{ejercicioId}/intento
     */
    public function enviarIntento(Request $request, $moduloId, $leccionId, $ejercicioId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'opcion_id' => 'nullable|integer|exists:opciones_ejercicio,id',
                'respuesta_texto' => 'nullable|string',
                'parejas' => 'nullable|array', // Para ejercicios de arrastrar y soltar
                'parejas.*.id_opcion' => 'nullable|integer',
                'parejas.*.pareja' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validación fallida',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar existencia
            $modulo = Modulo::findOrFail($moduloId);
            $leccion = Leccion::where('id', $leccionId)
                ->where('modulo_id', $moduloId)
                ->firstOrFail();
            $ejercicio = Ejercicio::where('id', $ejercicioId)
                ->where('leccion_id', $leccionId)
                ->where('estado', 'activo')
                ->firstOrFail();

            $usuario = Auth::user();
            $esCorrecta = false;
            $feedback = '';
            $opcionSeleccionada = null;
            $respuestaTexto = null;

            // Validar respuesta según tipo de ejercicio
            switch ($ejercicio->tipo) {
                case 'seleccion_multiple':
                    $opcionId = $request->opcion_id;
                    $opcionSeleccionada = $ejercicio->opciones()->find($opcionId);

                    if ($opcionSeleccionada) {
                        $esCorrecta = $opcionSeleccionada->es_correcta;
                        $feedback = $esCorrecta
                            ? '¡Correcto! Has seleccionado la respuesta correcta.'
                            : 'Respuesta incorrecta. Revisa el contenido de la lección.';
                    }
                    break;

                case 'verdadero_falso':
                    $opcionId = $request->opcion_id;
                    $opcionSeleccionada = $ejercicio->opciones()->find($opcionId);

                    if ($opcionSeleccionada) {
                        $esCorrecta = $opcionSeleccionada->es_correcta;
                        $feedback = $esCorrecta
                            ? '¡Correcto! Tu respuesta es verdadera.'
                            : 'Incorrecto. La afirmación es falsa.';
                    }
                    break;

                case 'arrastrar_soltar':
                    $parejas = $request->parejas;
                    $respuestaTexto = json_encode($parejas);

                    // Validar todas las parejas (simplificado - puedes mejorar esta lógica)
                    $todasCorrectas = true;
                    foreach ($parejas as $pareja) {
                        $opcion = $ejercicio->opciones()
                            ->where('id', $pareja['id_opcion'])
                            ->where('pareja_arrastre', $pareja['pareja'])
                            ->first();

                        if (!$opcion || !$opcion->es_correcta) {
                            $todasCorrectas = false;
                            break;
                        }
                    }

                    $esCorrecta = $todasCorrectas;
                    $feedback = $esCorrecta
                        ? '¡Excelente! Has relacionado correctamente todos los elementos.'
                        : 'Algunas relaciones son incorrectas. Intenta nuevamente.';
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de ejercicio no soportado'
                    ], 400);
            }

            // Registrar intento
            $intento = IntentoEjercicio::create([
                'usuario_id' => $usuario->id,
                'ejercicio_id' => $ejercicio->id,
                'opcion_seleccionada_id' => $opcionSeleccionada ? $opcionSeleccionada->id : null,
                'respuesta_texto' => $respuestaTexto,
                'es_correcta' => $esCorrecta
            ]);

            // Si es correcto, marcar progreso (implementarás esto después)
            if ($esCorrecta) {
                // $this->marcarProgresoEjercicio($usuario->id, $leccionId, $ejercicioId);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'ejercicio_id' => $ejercicio->id,
                    'es_correcta' => $esCorrecta,
                    'feedback' => $feedback,
                    'intento_id' => $intento->id,
                    'opcion_correcta' => $this->getOpcionCorrecta($ejercicio), // Solo para feedback educativo
                    'explicacion' => $this->getExplicacionPorTipo($ejercicio->tipo)
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ejercicio no encontrado o no disponible'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el intento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resultados de ejercicios de una lección
     * GET api/modulos/{moduloId}/lecciones/{leccionId}/ejercicios/resultados
     */
    public function getResultados($moduloId, $leccionId)
    {
        try {
            $usuario = Auth::user();

            $modulo = Modulo::findOrFail($moduloId);
            $leccion = Leccion::where('id', $leccionId)
                ->where('modulo_id', $moduloId)
                ->firstOrFail();

            // Obtener todos los ejercicios de la lección
            $ejercicios = Ejercicio::with(['opciones'])
                ->where('leccion_id', $leccionId)
                ->where('estado', 'activo')
                ->orderBy('orden', 'asc')
                ->get();

            // Obtener intentos del usuario
            $intentos = IntentoEjercicio::where('usuario_id', $usuario->id)
                ->whereIn('ejercicio_id', $ejercicios->pluck('id'))
                ->get()
                ->groupBy('ejercicio_id');

            // Calcular estadísticas
            $ejerciciosCompletados = 0;
            $ejerciciosCorrectos = 0;
            $resultadosDetallados = [];

            foreach ($ejercicios as $ejercicio) {
                $ultimoIntento = $intentos->get($ejercicio->id) ?
                    $intentos[$ejercicio->id]->sortByDesc('created_at')->first() : null;

                $completado = !is_null($ultimoIntento);
                $correcto = $completado && $ultimoIntento->es_correcta;

                if ($completado) {
                    $ejerciciosCompletados++;
                }
                if ($correcto) {
                    $ejerciciosCorrectos++;
                }

                $resultadosDetallados[] = [
                    'ejercicio_id' => $ejercicio->id,
                    'pregunta' => $ejercicio->pregunta,
                    'tipo' => $ejercicio->tipo,
                    'orden' => $ejercicio->orden,
                    'completado' => $completado,
                    'correcto' => $correcto,
                    'ultimo_intento' => $ultimoIntento ? [
                        'fecha' => $ultimoIntento->created_at,
                        'es_correcta' => $ultimoIntento->es_correcta
                    ] : null,
                    'intentos_totales' => $intentos->get($ejercicio->id) ?
                        count($intentos[$ejercicio->id]) : 0
                ];
            }

            $porcentajeCompletado = $ejercicios->count() > 0 ?
                round(($ejerciciosCompletados / $ejercicios->count()) * 100, 2) : 0;
            $porcentajeCorrecto = $ejerciciosCompletados > 0 ?
                round(($ejerciciosCorrectos / $ejerciciosCompletados) * 100, 2) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'leccion' => [
                        'id' => $leccion->id,
                        'titulo' => $leccion->titulo,
                        'total_ejercicios' => $ejercicios->count(),
                        'ejercicios_con_ejercicios' => $leccion->tiene_ejercicios
                    ],
                    'estadisticas' => [
                        'ejercicios_completados' => $ejerciciosCompletados,
                        'ejercicios_correctos' => $ejerciciosCorrectos,
                        'porcentaje_completado' => $porcentajeCompletado,
                        'porcentaje_correcto' => $porcentajeCorrecto,
                        'ejercicios_pendientes' => $ejercicios->count() - $ejerciciosCompletados
                    ],
                    'resultados' => $resultadosDetallados,
                    'recomendacion' => $this->getRecomendacion($porcentajeCorrecto)
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo o lección no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resultados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Métodos helper privados
     */
    private function getInstruccionesPorTipo($tipo)
    {
        $instrucciones = [
            'seleccion_multiple' => 'Selecciona la respuesta correcta entre las opciones disponibles.',
            'verdadero_falso' => 'Indica si la afirmación es verdadera o falsa.',
            'arrastrar_soltar' => 'Arrastra cada elemento a su definición correspondiente.'
        ];

        return $instrucciones[$tipo] ?? 'Responde la siguiente pregunta:';
    }

    private function getExplicacionPorTipo($tipo)
    {
        $explicaciones = [
            'seleccion_multiple' => 'Recuerda leer todas las opciones antes de seleccionar.',
            'verdadero_falso' => 'Analiza cuidadosamente la afirmación antes de responder.',
            'arrastrar_soltar' => 'Relaciona cada concepto con su definición precisa.'
        ];

        return $explicaciones[$tipo] ?? '¡Buen trabajo! Continúa aprendiendo.';
    }

    private function getOpcionCorrecta($ejercicio)
    {
        if ($ejercicio->tipo === 'arrastrar_soltar') {
            return $ejercicio->opciones()
                ->where('es_correcta', true)
                ->get()
                ->map(function ($opcion) {
                    return [
                        'texto' => $opcion->texto,
                        'pareja_correcta' => $opcion->pareja_arrastre
                    ];
                });
        }

        $opcionCorrecta = $ejercicio->opciones()->where('es_correcta', true)->first();

        if (!$opcionCorrecta) {
            return null;
        }

        return [
            'id' => $opcionCorrecta->id,
            'texto' => $opcionCorrecta->texto
        ];
    }

    private function getRecomendacion($porcentajeCorrecto)
    {
        if ($porcentajeCorrecto >= 80) {
            return '¡Excelente! Has dominado los conceptos de esta lección.';
        } elseif ($porcentajeCorrecto >= 60) {
            return 'Buen trabajo. Revisa los ejercicios incorrectos para mejorar.';
        } elseif ($porcentajeCorrecto > 0) {
            return 'Te recomiendo repasar la lección antes de continuar.';
        } else {
            return 'Aún no has completado ejercicios. ¡Comienza ahora!';
        }
    }
}
