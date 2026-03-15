<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\Leccion;
use App\Models\Ejercicio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeccionController extends Controller
{
    /**
     * Listar lecciones de un módulo (admin)
     */
    public function index(Request $request, $moduloId)
    {
        $modulo = Modulo::findOrFail($moduloId);

        $lecciones = $modulo->lecciones()
                          ->withCount('ejercicios')
                          ->orderBy('orden')
                          ->get();

        return response()->json([
            'success' => true,
            'data' => $lecciones
        ]);
    }

    /**
     * Crear una nueva lección
     */
    public function store(Request $request, $moduloId)
    {
        $modulo = Modulo::findOrFail($moduloId);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'contenido' => 'required|string',
            'orden' => 'sometimes|integer',
            'tiene_editor_codigo' => 'sometimes|boolean',
            'tiene_ejercicios' => 'sometimes|boolean',
            'estado' => 'sometimes|in:activo,inactivo'
        ]);

        $validated['slug'] = Str::slug($validated['titulo']);
        $validated['modulo_id'] = $moduloId;
        $validated['created_by'] = auth()->id();

        // Verificar slug único en el módulo
        if (Leccion::where('modulo_id', $moduloId)->where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $validated['slug'] . '-' . uniqid();
        }

        // Si no se especifica orden, poner al final
        if (!isset($validated['orden'])) {
            $maxOrden = Leccion::where('modulo_id', $moduloId)->max('orden') ?? 0;
            $validated['orden'] = $maxOrden + 1;
        }

        $leccion = Leccion::create($validated);

        // Actualizar total_lecciones del módulo
        $modulo->total_lecciones = $modulo->lecciones()->count();
        $modulo->save();

        return response()->json([
            'success' => true,
            'message' => 'Lección creada exitosamente',
            'data' => $leccion
        ], 201);
    }

    /**
     * Obtener una lección específica para editar
     */
    public function show($moduloId, $leccionId)
    {
        $leccion = Leccion::with(['ejercicios' => function ($q) {
            $q->orderBy('orden');
        }])->where('modulo_id', $moduloId)
          ->findOrFail($leccionId);

        return response()->json([
            'success' => true,
            'data' => $leccion
        ]);
    }

    /**
     * Actualizar una lección
     */
    public function update(Request $request, $moduloId, $leccionId)
    {
        $leccion = Leccion::where('modulo_id', $moduloId)->findOrFail($leccionId);

        $validated = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'contenido' => 'sometimes|string',
            'orden' => 'sometimes|integer',
            'tiene_editor_codigo' => 'sometimes|boolean',
            'tiene_ejercicios' => 'sometimes|boolean',
            'estado' => 'sometimes|in:activo,inactivo'
        ]);

        if (isset($validated['titulo'])) {
            $validated['slug'] = Str::slug($validated['titulo']);
            // Verificar slug único en el módulo
            $existing = Leccion::where('modulo_id', $moduloId)
                              ->where('slug', $validated['slug'])
                              ->where('id', '!=', $leccionId)
                              ->first();
            if ($existing) {
                $validated['slug'] = $validated['slug'] . '-' . uniqid();
            }
        }

        $leccion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lección actualizada exitosamente',
            'data' => $leccion
        ]);
    }

    /**
     * Eliminar una lección
     */
    public function destroy($moduloId, $leccionId)
    {
        $leccion = Leccion::where('modulo_id', $moduloId)->findOrFail($leccionId);

        // Verificar si tiene ejercicios
        if ($leccion->ejercicios()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una lección que tiene ejercicios. Elimine los ejercicios primero.'
            ], 422);
        }

        $modulo = Modulo::find($moduloId);
        $leccion->delete();

        // Actualizar total_lecciones del módulo
        $modulo->total_lecciones = $modulo->lecciones()->count();
        $modulo->save();

        // Reordenar lecciones restantes
        $this->reordenarLecciones($moduloId);

        return response()->json([
            'success' => true,
            'message' => 'Lección eliminada exitosamente'
        ]);
    }

    /**
     * Reordenar lecciones
     */
    public function reorder(Request $request, $moduloId)
    {
        $request->validate([
            'lecciones' => 'required|array',
            'lecciones.*.id' => 'required|exists:lecciones,id',
            'lecciones.*.orden' => 'required|integer'
        ]);

        foreach ($request->lecciones as $item) {
            Leccion::where('id', $item['id'])
                  ->where('modulo_id', $moduloId)
                  ->update(['orden' => $item['orden']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lecciones reordenadas exitosamente'
        ]);
    }

    /**
     * Método auxiliar para reordenar lecciones después de eliminar
     */
    private function reordenarLecciones($moduloId)
    {
        $lecciones = Leccion::where('modulo_id', $moduloId)
                           ->orderBy('orden')
                           ->get();

        $orden = 1;
        foreach ($lecciones as $leccion) {
            $leccion->update(['orden' => $orden++]);
        }
    }
}
