<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\Leccion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuloController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Modulo::withCount('lecciones');

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            $modulos = $query->orderBy('orden_global')->get();

            return response()->json([
                'success' => true,
                'data' => $modulos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $modulo = Modulo::with('lecciones')->find($id);

            if (!$modulo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Módulo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $modulo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'titulo' => 'required|string|max:255',
                'descripcion_larga' => 'required|string',
                'modulo' => 'required|in:introduccion,html,css,javascript,php,sql',
                'orden_global' => 'nullable|integer',
                'estado' => 'nullable|in:activo,inactivo,borrador'
            ]);

            $validated['slug'] = Str::slug($validated['titulo']);
            $validated['created_by'] = auth()->id();

            // Verificar slug único
            if (Modulo::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $validated['slug'] . '-' . uniqid();
            }

            $modulo = Modulo::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Módulo creado exitosamente',
                'data' => $modulo
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear módulo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $modulo = Modulo::find($id);

            if (!$modulo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Módulo no encontrado'
                ], 404);
            }

            $validated = $request->validate([
                'titulo' => 'sometimes|string|max:255',
                'descripcion_larga' => 'sometimes|string',
                'modulo' => 'sometimes|in:introduccion,html,css,javascript,php,sql',
                'orden_global' => 'sometimes|integer',
                'estado' => 'sometimes|in:activo,inactivo,borrador'
            ]);

            if (isset($validated['titulo'])) {
                $validated['slug'] = Str::slug($validated['titulo']);
            }

            $modulo->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Módulo actualizado exitosamente',
                'data' => $modulo
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar módulo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $modulo = Modulo::find($id);

            if (!$modulo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Módulo no encontrado'
                ], 404);
            }

            if ($modulo->lecciones()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un módulo con lecciones'
                ], 422);
            }

            $modulo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Módulo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar módulo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statistics()
    {
        try {
            $stats = [
                'total' => Modulo::count(),
                'activos' => Modulo::where('estado', 'activo')->count(),
                'por_tipo' => Modulo::selectRaw('modulo, count(*) as total')
                                    ->groupBy('modulo')
                                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'modulos' => 'required|array',
                'modulos.*.id' => 'required|exists:modulos,id',
                'modulos.*.orden_global' => 'required|integer'
            ]);

            foreach ($request->modulos as $item) {
                Modulo::where('id', $item['id'])->update(['orden_global' => $item['orden_global']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Módulos reordenados exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reordenar módulos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
