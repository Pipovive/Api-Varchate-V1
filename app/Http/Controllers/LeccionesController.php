<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Leccion;
use App\Models\ProgresoLeccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeccionesController extends Controller
{
    /**
 * Obtener todas las lecciones de un módulo CON ESTADO DE DISPONIBILIDAD
 */
    public function index(Request $request, $moduloSlug)
    {
        try {
            \Log::info('Accediendo a lecciones del módulo: ' . $moduloSlug);

            // Buscar el módulo
            $modulo = Modulo::where('slug', $moduloSlug)
                ->where('estado', 'activo')
                ->first();

            if (!$modulo) {
                return response()->json([
                    'error' => 'Módulo no encontrado'
                ], 404);
            }

            $usuario = Auth::user();

            // Obtener lecciones
            $lecciones = Leccion::where('modulo_id', $modulo->id)
                ->where('estado', 'activo')
                ->orderBy('orden')
                ->get();

            // Obtener progreso del usuario en este módulo
            $progresoLecciones = ProgresoLeccion::where('usuario_id', $usuario->id)
                ->whereIn('leccion_id', $lecciones->pluck('id'))
                ->where('vista', true)
                ->pluck('leccion_id')
                ->toArray();

            $leccionesConEstado = $lecciones->map(function ($leccion) use ($progresoLecciones, $usuario, $modulo) {

                // Determinar si ya fue vista
                $vista = in_array($leccion->id, $progresoLecciones);

                // Determinar si está disponible (desbloqueada)
                $disponible = $this->estaLeccionDisponible($leccion, $usuario, $modulo, $progresoLecciones);

                return [
                    'id' => $leccion->id,
                    'titulo' => $leccion->titulo,
                    'slug' => $leccion->slug,
                    'orden' => $leccion->orden,
                    'tiene_editor_codigo' => (bool)$leccion->tiene_editor_codigo,
                    'tiene_ejercicios' => (bool)$leccion->tiene_ejercicios,
                    'cantidad_ejercicios' => $leccion->cantidad_ejercicios,
                    'vista' => $vista,
                    'disponible' => $disponible,
                    'estado' => $this->getEstadoLeccion($vista, $disponible)
                ];
            });

            return response()->json([
                'modulo' => [
                    'id' => $modulo->id,
                    'titulo' => $modulo->titulo,
                    'slug' => $modulo->slug
                ],
                'lecciones' => $leccionesConEstado,
                'total' => $lecciones->count(),
                'estadisticas' => [
                    'vistas' => count($progresoLecciones),
                    'disponibles' => $leccionesConEstado->where('disponible', true)->count(),
                    'completadas' => $leccionesConEstado->where('vista', true)->count()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en LeccionesController@index: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error interno',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determinar si una lección está disponible para el usuario
     */
    private function estaLeccionDisponible($leccion, $usuario, $modulo, $progresoLecciones)
    {
        // Lección 1 siempre disponible (introducción)
        if ($leccion->orden === 1) {
            return true;
        }

        // Para lecciones > 1, verificar si la anterior fue vista
        $leccionAnterior = Leccion::where('modulo_id', $modulo->id)
            ->where('orden', $leccion->orden - 1)
            ->where('estado', 'activo')
            ->first();

        if (!$leccionAnterior) {
            return false;
        }

        // Verificar si la lección anterior está en el progreso
        return in_array($leccionAnterior->id, $progresoLecciones);
    }

    /**
     * Obtener estado legible de la lección
     */
    private function getEstadoLeccion($vista, $disponible)
    {
        if ($vista) {
            return 'completada';
        } elseif ($disponible) {
            return 'disponible';
        } else {
            return 'bloqueada';
        }
    }

    /**
     * Obtener lección por IDs (módulo_id y leccion_id)
     */
    public function showById($moduloId, $leccionId)
    {
        try {
            $usuario = Auth::user();

            // Verificar que el módulo existe y está activo
            $modulo = Modulo::where('id', $moduloId)
                ->where('estado', 'activo')
                ->firstOrFail();

            // Buscar la lección
            $leccion = Leccion::where('id', $leccionId)
                ->where('modulo_id', $modulo->id)
                ->where('estado', 'activo')
                ->firstOrFail();

            // ===== VALIDAR DESBLOQUEO LINEAL =====
            // Si NO es la lección 1 (introducción), verificar anterior
            if ($leccion->orden > 1) {
                $leccionAnterior = Leccion::where('modulo_id', $moduloId)
                    ->where('orden', $leccion->orden - 1)
                    ->where('estado', 'activo')
                    ->first();

                if ($leccionAnterior) {
                    $progresoAnterior = ProgresoLeccion::where('usuario_id', $usuario->id)
                        ->where('leccion_id', $leccionAnterior->id)
                        ->where('vista', true)
                        ->exists();

                    if (!$progresoAnterior) {
                        return response()->json([
                            'error' => 'Debes completar la lección anterior primero',
                            'detalle' => 'Lección requerida: ' . $leccionAnterior->titulo,
                            'leccion_requerida_id' => $leccionAnterior->id
                        ], 403);
                    }
                }
            }

            // Registrar progreso si es la primera vez
            $this->registrarProgreso($leccion->id);

            return response()->json([
                'leccion' => [
                    'id' => $leccion->id,
                    'titulo' => $leccion->titulo,
                    'slug' => $leccion->slug,
                    'contenido' => $leccion->contenido,
                    'orden' => $leccion->orden,
                    'tiene_editor_codigo' => (bool)$leccion->tiene_editor_codigo,
                    'tiene_ejercicios' => (bool)$leccion->tiene_ejercicios,
                    'cantidad_ejercicios' => $leccion->cantidad_ejercicios,
                    'modulo' => [
                        'id' => $modulo->id,
                        'titulo' => $modulo->titulo,
                        'slug' => $modulo->slug
                    ]
                ],
                'acceso_permitido' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al acceder a lección',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una lección específica por slug
     */
    public function show($moduloSlug, $leccionSlug)
    {
        try {
            $usuario = Auth::user();

            // Buscar el módulo
            $modulo = Modulo::where('slug', $moduloSlug)
                ->where('estado', 'activo')
                ->first();

            if (!$modulo) {
                return response()->json([
                    'error' => 'Módulo no encontrado'
                ], 404);
            }

            // Buscar la lección
            $leccion = Leccion::where('modulo_id', $modulo->id)
                ->where('slug', $leccionSlug)
                ->where('estado', 'activo')
                ->first();

            if (!$leccion) {
                return response()->json([
                    'error' => 'Lección no encontrada'
                ], 404);
            }

            // ===== VALIDAR DESBLOQUEO LINEAL (MISMA VALIDACIÓN QUE showById) =====
            if ($leccion->orden > 1) {
                $leccionAnterior = Leccion::where('modulo_id', $modulo->id)
                    ->where('orden', $leccion->orden - 1)
                    ->where('estado', 'activo')
                    ->first();

                if ($leccionAnterior) {
                    $progresoAnterior = ProgresoLeccion::where('usuario_id', $usuario->id)
                        ->where('leccion_id', $leccionAnterior->id)
                        ->where('vista', true)
                        ->exists();

                    if (!$progresoAnterior) {
                        return response()->json([
                            'error' => 'Debes completar la lección anterior primero',
                            'detalle' => 'Lección requerida: ' . $leccionAnterior->titulo,
                            'leccion_requerida_id' => $leccionAnterior->id
                        ], 403);
                    }
                }
            }

            // Registrar progreso
            $this->registrarProgreso($leccion->id);

            return response()->json([
                'leccion' => [
                    'id' => $leccion->id,
                    'titulo' => $leccion->titulo,
                    'slug' => $leccion->slug,
                    'contenido' => $leccion->contenido,
                    'orden' => $leccion->orden,
                    'tiene_editor_codigo' => (bool)$leccion->tiene_editor_codigo,
                    'tiene_ejercicios' => (bool)$leccion->tiene_ejercicios,
                    'cantidad_ejercicios' => $leccion->cantidad_ejercicios,
                    'modulo' => [
                        'id' => $modulo->id,
                        'titulo' => $modulo->titulo,
                        'slug' => $modulo->slug
                    ]
                ],
                'acceso_permitido' => true
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en LeccionesController@show: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Registrar progreso de una lección
     */
    private function registrarProgreso($leccionId)
    {
        try {
            $usuarioId = Auth::id();

            // Verificar si ya existe progreso
            $progreso = ProgresoLeccion::where('usuario_id', $usuarioId)
                ->where('leccion_id', $leccionId)
                ->first();

            if (!$progreso) {
                // Crear nuevo progreso
                ProgresoLeccion::create([
                    'usuario_id' => $usuarioId,
                    'leccion_id' => $leccionId,
                    'vista' => 1,
                    'fecha_vista' => now()
                ]);

                \Log::info('Progreso creado para usuario ' . $usuarioId . ', lección ' . $leccionId);
            } elseif (!$progreso->vista) {
                // Actualizar si no estaba marcada como vista
                $progreso->update([
                    'vista' => 1,
                    'fecha_vista' => now()
                ]);

                \Log::info('Progreso actualizado para usuario ' . $usuarioId . ', lección ' . $leccionId);
            }

        } catch (\Exception $e) {
            \Log::error('Error al registrar progreso: ' . $e->getMessage());
            // No lanzamos excepción para no romper el flujo principal
        }
    }

    /**
     * Obtener lección por ID (para uso interno)
     */
    public function getById($id)
    {
        try {
            $leccion = Leccion::with('modulo')
                ->where('id', $id)
                ->where('estado', 'activo')
                ->firstOrFail();

            return response()->json([
                'leccion' => $leccion
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lección no encontrada',
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
