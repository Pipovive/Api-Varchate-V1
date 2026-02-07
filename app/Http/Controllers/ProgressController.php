<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Leccion;
use App\Models\ProgresoModulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    /**
     * Guardar última lección vista para reanudar
     */
    public function saveLastLesson(Request $request, $moduloId)
    {
        $request->validate([
            'leccion_id' => 'required|integer|exists:lecciones,id',
            'porcentaje_visto' => 'nullable|numeric|min:0|max:100'
        ]);

        try {
            DB::beginTransaction();

            $usuarioId = Auth::id();
            $leccionId = $request->leccion_id;

            // Verificar que la lección pertenece al módulo
            $leccion = Leccion::where('id', $leccionId)
                ->where('modulo_id', $moduloId)
                ->firstOrFail();

            // Buscar o crear progreso del módulo
            $progreso = ProgresoModulo::updateOrCreate(
                [
                    'usuario_id' => $usuarioId,
                    'modulo_id' => $moduloId
                ],
                [
                    'fecha_ultimo_progreso' => now(),
                    'porcentaje_completado' => $this->calcularPorcentajeCompletado($moduloId, $usuarioId),
                    'lecciones_vistas' => $this->contarLeccionesVistas($moduloId, $usuarioId)
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Progreso guardado correctamente',
                'progreso' => $progreso,
                'leccion_actual' => [
                    'id' => $leccion->id,
                    'titulo' => $leccion->titulo,
                    'orden' => $leccion->orden
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error al guardar progreso',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener última lección vista para reanudar
     */
    public function getLastLesson($moduloId)
    {
        try {
            $usuarioId = Auth::id();

            // Obtener progreso del módulo
            $progreso = ProgresoModulo::where('usuario_id', $usuarioId)
                ->where('modulo_id', $moduloId)
                ->first();

            if (!$progreso) {
                return response()->json([
                    'ultima_leccion' => null,
                    'mensaje' => 'No hay progreso registrado para este módulo'
                ]);
            }

            // Obtener la última lección vista (la de mayor orden)
            $ultimaLeccion = Leccion::whereHas('progresos', function ($query) use ($usuarioId) {
                $query->where('usuario_id', $usuarioId)
                      ->where('vista', 1);
            })
                ->where('modulo_id', $moduloId)
                ->orderBy('orden', 'desc')
                ->first();

            return response()->json([
                'ultima_leccion' => $ultimaLeccion ? [
                    'id' => $ultimaLeccion->id,
                    'titulo' => $ultimaLeccion->titulo,
                    'slug' => $ultimaLeccion->slug,
                    'orden' => $ultimaLeccion->orden
                ] : null,
                'progreso_modulo' => [
                    'porcentaje_completado' => $progreso->porcentaje_completado,
                    'lecciones_vistas' => $progreso->lecciones_vistas,
                    'fecha_ultimo_progreso' => $progreso->fecha_ultimo_progreso
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener último progreso',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular porcentaje completado del módulo
     */
    private function calcularPorcentajeCompletado($moduloId, $usuarioId)
    {
        $totalLecciones = Leccion::where('modulo_id', $moduloId)
            ->where('estado', 'activo')
            ->count();

        if ($totalLecciones === 0) {
            return 0;
        }

        $leccionesVistas = Leccion::where('modulo_id', $moduloId)
            ->whereHas('progresos', function ($query) use ($usuarioId) {
                $query->where('usuario_id', $usuarioId)
                      ->where('vista', 1);
            })
            ->count();

        return round(($leccionesVistas / $totalLecciones) * 100, 2);
    }

    /**
     * Contar lecciones vistas
     */
    private function contarLeccionesVistas($moduloId, $usuarioId)
    {
        return Leccion::where('modulo_id', $moduloId)
            ->whereHas('progresos', function ($query) use ($usuarioId) {
                $query->where('usuario_id', $usuarioId)
                      ->where('vista', 1);
            })
            ->count();
    }
}
