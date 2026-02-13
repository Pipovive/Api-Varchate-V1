<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\Evaluacion;
use App\Models\PreguntaEvaluacion;
use App\Models\OpcionesEvaluacion;
use Illuminate\Http\Request;

class EvaluacionController extends Controller
{
    /**
     * Obtener evaluación de un módulo (admin)
     */
    public function show($moduloId)
    {
        $evaluacion = Evaluacion::with(['preguntas' => function ($q) {
            $q->orderBy('orden')->with('opciones');
        }])->where('modulo_id', $moduloId)->first();

        if (!$evaluacion) {
            return response()->json([
                'success' => false,
                'message' => 'Este módulo no tiene evaluación configurada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $evaluacion
        ]);
    }

    /**
     * Crear o actualizar configuración de evaluación
     */
    public function updateConfig(Request $request, $moduloId)
    {
        $modulo = Modulo::findOrFail($moduloId);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'numero_preguntas' => 'required|integer|min:1|max:50',
            'tiempo_limite' => 'required|integer|min:5|max:180',
            'puntaje_minimo' => 'required|numeric|min:0|max:100',
            'max_intentos' => 'required|integer|min:1|max:10',
            'estado' => 'sometimes|in:activo,inactivo'
        ]);

        $validated['created_by'] = auth()->id();

        $evaluacion = Evaluacion::updateOrCreate(
            ['modulo_id' => $moduloId],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Configuración de evaluación guardada exitosamente',
            'data' => $evaluacion
        ]);
    }

    /**
     * Crear una nueva pregunta
     */
    public function storePregunta(Request $request, $moduloId, $evaluacionId)
    {
        $evaluacion = Evaluacion::where('modulo_id', $moduloId)->findOrFail($evaluacionId);

        $validated = $request->validate([
            'pregunta' => 'required|string',
            'tipo' => 'required|in:seleccion_multiple,verdadero_falso,arrastrar_soltar',
            'puntos' => 'required|numeric|min:0.5|max:100',
            'orden' => 'sometimes|integer',
            'opciones' => 'required_if:tipo,seleccion_multiple,arrastrar_soltar|array',
            'opciones.*.texto' => 'required_with:opciones|string',
            'opciones.*.es_correcta' => 'required_if:tipo,seleccion_multiple|boolean',
            'opciones.*.pareja_arrastre' => 'required_if:tipo,arrastrar_soltar|string|nullable'
        ]);

        $validated['evaluacion_id'] = $evaluacionId;
        $validated['created_by'] = auth()->id();

        // Si no se especifica orden, poner al final
        if (!isset($validated['orden'])) {
            $maxOrden = PreguntaEvaluacion::where('evaluacion_id', $evaluacionId)->max('orden') ?? 0;
            $validated['orden'] = $maxOrden + 1;
        }

        // Para verdadero/falso, crear opciones automáticamente
        if ($validated['tipo'] === 'verdadero_falso') {
            $pregunta = PreguntaEvaluacion::create($validated);

            $pregunta->opciones()->createMany([
                ['texto' => 'Verdadero', 'es_correcta' => $request->respuesta_correcta === 'verdadero', 'orden' => 1],
                ['texto' => 'Falso', 'es_correcta' => $request->respuesta_correcta === 'falso', 'orden' => 2]
            ]);
        } else {
            $pregunta = PreguntaEvaluacion::create($validated);

            if ($request->has('opciones')) {
                $opciones = collect($request->opciones)->map(function ($opcion, $index) {
                    return [
                        'texto' => $opcion['texto'],
                        'es_correcta' => $opcion['es_correcta'] ?? false,
                        'pareja_arrastre' => $opcion['pareja_arrastre'] ?? null,
                        'orden' => $opcion['orden'] ?? $index + 1
                    ];
                })->toArray();

                $pregunta->opciones()->createMany($opciones);
            }
        }

        // Actualizar numero_preguntas de la evaluación
        $evaluacion->numero_preguntas = $evaluacion->preguntas()->count();
        $evaluacion->save();

        return response()->json([
            'success' => true,
            'message' => 'Pregunta creada exitosamente',
            'data' => $pregunta->load('opciones')
        ], 201);
    }

    /**
     * Actualizar una pregunta
     */
    public function updatePregunta(Request $request, $moduloId, $evaluacionId, $preguntaId)
    {
        $pregunta = PreguntaEvaluacion::where('evaluacion_id', $evaluacionId)->findOrFail($preguntaId);

        $validated = $request->validate([
            'pregunta' => 'sometimes|string',
            'tipo' => 'sometimes|in:seleccion_multiple,verdadero_falso,arrastrar_soltar',
            'puntos' => 'sometimes|numeric|min:0.5|max:100',
            'orden' => 'sometimes|integer'
        ]);

        $pregunta->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pregunta actualizada exitosamente',
            'data' => $pregunta
        ]);
    }

    /**
     * Eliminar una pregunta
     */
    public function destroyPregunta($moduloId, $evaluacionId, $preguntaId)
    {
        $pregunta = PreguntaEvaluacion::where('evaluacion_id', $evaluacionId)->findOrFail($preguntaId);

        $evaluacion = Evaluacion::find($evaluacionId);

        $pregunta->delete();

        // Actualizar numero_preguntas de la evaluación
        $evaluacion->numero_preguntas = $evaluacion->preguntas()->count();
        $evaluacion->save();

        // Reordenar preguntas restantes
        $this->reordenarPreguntas($evaluacionId);

        return response()->json([
            'success' => true,
            'message' => 'Pregunta eliminada exitosamente'
        ]);
    }

    /**
     * Actualizar opciones de una pregunta
     */
    public function updateOpcionesPregunta(Request $request, $moduloId, $evaluacionId, $preguntaId)
    {
        $pregunta = PreguntaEvaluacion::where('evaluacion_id', $evaluacionId)->findOrFail($preguntaId);

        $request->validate([
            'opciones' => 'required|array',
            'opciones.*.id' => 'sometimes|exists:opciones_evaluacion,id',
            'opciones.*.texto' => 'required|string',
            'opciones.*.es_correcta' => 'boolean',
            'opciones.*.pareja_arrastre' => 'nullable|string'
        ]);

        $opcionesIds = collect($request->opciones)->pluck('id')->filter();

        // Eliminar opciones que ya no existen
        $pregunta->opciones()->whereNotIn('id', $opcionesIds)->delete();

        // Actualizar o crear opciones
        foreach ($request->opciones as $index => $opcionData) {
            if (isset($opcionData['id'])) {
                $opcion = OpcionesEvaluacion::find($opcionData['id']);
                $opcion->update([
                    'texto' => $opcionData['texto'],
                    'es_correcta' => $opcionData['es_correcta'] ?? false,
                    'pareja_arrastre' => $opcionData['pareja_arrastre'] ?? null,
                    'orden' => $opcionData['orden'] ?? $index + 1
                ]);
            } else {
                $pregunta->opciones()->create([
                    'texto' => $opcionData['texto'],
                    'es_correcta' => $opcionData['es_correcta'] ?? false,
                    'pareja_arrastre' => $opcionData['pareja_arrastre'] ?? null,
                    'orden' => $opcionData['orden'] ?? $index + 1
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Opciones actualizadas exitosamente',
            'data' => $pregunta->load('opciones')
        ]);
    }

    /**
     * Reordenar preguntas
     */
    private function reordenarPreguntas($evaluacionId)
    {
        $preguntas = PreguntaEvaluacion::where('evaluacion_id', $evaluacionId)
                                      ->orderBy('orden')
                                      ->get();

        $orden = 1;
        foreach ($preguntas as $pregunta) {
            $pregunta->update(['orden' => $orden++]);
        }
    }

    /**
     * Obtener estadísticas de evaluaciones
     */
    public function statistics()
    {
        $stats = [
            'total_evaluaciones' => Evaluacion::count(),
            'total_preguntas' => PreguntaEvaluacion::count(),
            'promedio_preguntas' => PreguntaEvaluacion::count() / max(Evaluacion::count(), 1),
            'por_tipo_pregunta' => PreguntaEvaluacion::selectRaw('tipo, count(*) as total')
                                                    ->groupBy('tipo')
                                                    ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
