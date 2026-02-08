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
     * Obtener todas las lecciones de un módulo (versión simplificada)
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

            // Obtener lecciones directamente sin scopes complejos
            $lecciones = Leccion::where('modulo_id', $modulo->id)
                ->where('estado', 'activo')
                ->orderBy('orden')
                ->get();

            return response()->json([
                'modulo' => [
                    'id' => $modulo->id,
                    'titulo' => $modulo->titulo,
                    'slug' => $modulo->slug
                ],
                'lecciones' => $lecciones->map(function ($leccion) {
                    return [
                        'id' => $leccion->id,
                        'titulo' => $leccion->titulo,
                        'slug' => $leccion->slug,
                        'orden' => $leccion->orden,
                        'tiene_editor_codigo' => (bool)$leccion->tiene_editor_codigo,
                        'tiene_ejercicios' => (bool)$leccion->tiene_ejercicios,
                        'cantidad_ejercicios' => $leccion->cantidad_ejercicios
                    ];
                }),
                'total' => $lecciones->count(),
                'debug' => 'Endpoint funcionando'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en LeccionesController@index: ' . $e->getMessage());

            return response()->json([
                'error' => 'Error interno',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
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
