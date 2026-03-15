<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Leccion;
use App\Models\Ejercicio;
use App\Models\OpcionesEjercicio;
use Illuminate\Http\Request;

class EjercicioController extends Controller
{
    /**
     * Listar ejercicios de una lección
     */
    public function index($moduloId, $leccionId)
    {
        $leccion = Leccion::where('modulo_id', $moduloId)->findOrFail($leccionId);

        $ejercicios = $leccion->ejercicios()
                             ->with('opciones')
                             ->orderBy('orden')
                             ->get();

        return response()->json([
            'success' => true,
            'data' => $ejercicios
        ]);
    }

    /**
     * Crear un nuevo ejercicio
     */
    public function store(Request $request, $moduloId, $leccionId)
    {
        $leccion = Leccion::where('modulo_id', $moduloId)->findOrFail($leccionId);

        $validated = $request->validate([
            'pregunta' => 'required|string',
            'tipo' => 'required|in:seleccion_multiple,verdadero_falso,arrastrar_soltar',
            'orden' => 'sometimes|integer',
            'estado' => 'sometimes|in:activo,inactivo',
            'opciones' => 'required_if:tipo,seleccion_multiple,arrastrar_soltar|array',
            'opciones.*.texto' => 'required_with:opciones|string',
            'opciones.*.es_correcta' => 'required_if:tipo,seleccion_multiple|boolean',
            'opciones.*.pareja_arrastre' => 'required_if:tipo,arrastrar_soltar|string|nullable'
        ]);

        $validated['leccion_id'] = $leccionId;
        $validated['created_by'] = auth()->id();

        // Si no se especifica orden, poner al final
        if (!isset($validated['orden'])) {
            $maxOrden = Ejercicio::where('leccion_id', $leccionId)->max('orden') ?? 0;
            $validated['orden'] = $maxOrden + 1;
        }

        // Para verdadero/falso, crear opciones automáticamente
        if ($validated['tipo'] === 'verdadero_falso') {
            $ejercicio = Ejercicio::create($validated);

            // Crear opciones
            $ejercicio->opciones()->createMany([
                ['texto' => 'Verdadero', 'es_correcta' => $request->respuesta_correcta === 'verdadero', 'orden' => 1],
                ['texto' => 'Falso', 'es_correcta' => $request->respuesta_correcta === 'falso', 'orden' => 2]
            ]);
        } else {
            $ejercicio = Ejercicio::create($validated);

            // Crear opciones si se proporcionaron
            if ($request->has('opciones')) {
                $opciones = collect($request->opciones)->map(function ($opcion, $index) {
                    return [
                        'texto' => $opcion['texto'],
                        'es_correcta' => $opcion['es_correcta'] ?? false,
                        'pareja_arrastre' => $opcion['pareja_arrastre'] ?? null,
                        'orden' => $opcion['orden'] ?? $index + 1
                    ];
                })->toArray();

                $ejercicio->opciones()->createMany($opciones);
            }
        }

        // Actualizar tiene_ejercicios de la lección
        $leccion->tiene_ejercicios = true;
        $leccion->cantidad_ejercicios = $leccion->ejercicios()->count();
        $leccion->save();

        return response()->json([
            'success' => true,
            'message' => 'Ejercicio creado exitosamente',
            'data' => $ejercicio->load('opciones')
        ], 201);
    }

    /**
     * Obtener un ejercicio específico
     */
    public function show($moduloId, $leccionId, $ejercicioId)
    {
        $ejercicio = Ejercicio::with('opciones')
                             ->where('leccion_id', $leccionId)
                             ->findOrFail($ejercicioId);

        return response()->json([
            'success' => true,
            'data' => $ejercicio
        ]);
    }

    /**
     * Actualizar un ejercicio
     */
    public function update(Request $request, $moduloId, $leccionId, $ejercicioId)
    {
        $ejercicio = Ejercicio::where('leccion_id', $leccionId)->findOrFail($ejercicioId);

        $validated = $request->validate([
            'pregunta' => 'sometimes|string',
            'tipo' => 'sometimes|in:seleccion_multiple,verdadero_falso,arrastrar_soltar',
            'orden' => 'sometimes|integer',
            'estado' => 'sometimes|in:activo,inactivo'
        ]);

        $ejercicio->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ejercicio actualizado exitosamente',
            'data' => $ejercicio
        ]);
    }

    /**
     * Eliminar un ejercicio
     */
    public function destroy($moduloId, $leccionId, $ejercicioId)
    {
        $ejercicio = Ejercicio::where('leccion_id', $leccionId)->findOrFail($ejercicioId);

        $leccion = Leccion::find($leccionId);

        $ejercicio->delete();

        // Actualizar tiene_ejercicios de la lección
        $leccion->cantidad_ejercicios = $leccion->ejercicios()->count();
        $leccion->tiene_ejercicios = $leccion->cantidad_ejercicios > 0;
        $leccion->save();

        // Reordenar ejercicios restantes
        $this->reordenarEjercicios($leccionId);

        return response()->json([
            'success' => true,
            'message' => 'Ejercicio eliminado exitosamente'
        ]);
    }

    /**
     * Actualizar opciones de un ejercicio
     */
    public function updateOpciones(Request $request, $moduloId, $leccionId, $ejercicioId)
    {
        $ejercicio = Ejercicio::where('leccion_id', $leccionId)->findOrFail($ejercicioId);

        $request->validate([
            'opciones' => 'required|array',
            'opciones.*.id' => 'sometimes|exists:opciones_ejercicio,id',
            'opciones.*.texto' => 'required|string',
            'opciones.*.es_correcta' => 'boolean',
            'opciones.*.pareja_arrastre' => 'nullable|string'
        ]);

        $opcionesIds = collect($request->opciones)->pluck('id')->filter();

        // Eliminar opciones que ya no existen
        $ejercicio->opciones()->whereNotIn('id', $opcionesIds)->delete();

        // Actualizar o crear opciones
        foreach ($request->opciones as $index => $opcionData) {
            if (isset($opcionData['id'])) {
                // Actualizar existente
                $opcion = OpcionesEjercicio::find($opcionData['id']);
                $opcion->update([
                    'texto' => $opcionData['texto'],
                    'es_correcta' => $opcionData['es_correcta'] ?? false,
                    'pareja_arrastre' => $opcionData['pareja_arrastre'] ?? null,
                    'orden' => $opcionData['orden'] ?? $index + 1
                ]);
            } else {
                // Crear nueva
                $ejercicio->opciones()->create([
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
            'data' => $ejercicio->load('opciones')
        ]);
    }

    /**
     * Reordenar ejercicios
     */
    private function reordenarEjercicios($leccionId)
    {
        $ejercicios = Ejercicio::where('leccion_id', $leccionId)
                              ->orderBy('orden')
                              ->get();

        $orden = 1;
        foreach ($ejercicios as $ejercicio) {
            $ejercicio->update(['orden' => $orden++]);
        }
    }
}
