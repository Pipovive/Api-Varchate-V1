<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Modulo;
use App\Models\Leccion;
use App\Models\Evaluacion;
use App\Models\ProgresoModulo;
use App\Models\ProgresoLeccion;

class ModuloController extends Controller
{
    public function index()
    {
        $usuario = Auth::user();

        // Verificar si es la primera vez del usuario
        $tieneProgreso = ProgresoModulo::where('usuario_id', $usuario->id)->exists();

        if (!$tieneProgreso) {
            // Solo mostrar el primer módulo (Introducción a HTML)
            return Modulo::where('estado', 'activo')
                ->where('slug', 'introduccion-a-html')
                ->orderBy('orden_global')
                ->get();
        } else {
            // Mostrar todos los módulos activos
            return Modulo::where('estado', 'activo')
                ->orderBy('orden_global')
                ->get();
        }
    }

    /**
     * Obtener módulo con TODO para la vista de introducción
     * GET /api/modulos/{slug}/intro-completa
     */
    public function getIntroCompleta($slug)
    {
        try {
            $usuario = Auth::user();

            // 1. Obtener módulo con lecciones
            $modulo = Modulo::where('slug', $slug)
                ->where('estado', 'activo')
                ->firstOrFail();

            // 2. Obtener lecciones para tabla de contenido
            $lecciones = Leccion::where('modulo_id', $modulo->id)
                ->where('estado', 'activo')
                ->orderBy('orden')
                ->get(['id', 'titulo', 'slug', 'orden', 'tiene_ejercicios', 'tiene_editor_codigo']);

            // 3. Obtener evaluación si existe
            $evaluacion = Evaluacion::where('modulo_id', $modulo->id)
                ->where('estado', 'activo')
                ->first(['id', 'titulo', 'descripcion', 'numero_preguntas', 'tiempo_limite', 'puntaje_minimo']);

            // 4. Obtener progreso del usuario
            $progreso = ProgresoModulo::where('usuario_id', $usuario->id)
                ->where('modulo_id', $modulo->id)
                ->first();

            // 5. Preparar respuesta
            return response()->json([
                'modulo' => [
                    'id' => $modulo->id,
                    'titulo' => $modulo->titulo,
                    'slug' => $modulo->slug,
                    'descripcion_larga' => $modulo->descripcion_larga,
                    'tipo' => $modulo->modulo, // html, css, etc.
                    'orden_global' => $modulo->orden_global,
                    'total_lecciones' => $modulo->total_lecciones,
                    'icono' => $this->getIconoModulo($modulo->modulo)
                ],
                'tabla_contenido' => [
                    'lecciones' => $lecciones->map(function ($leccion) use ($usuario) {
                        // Verificar si ya está completada
                        $completada = ProgresoLeccion::where('usuario_id', $usuario->id)
                            ->where('leccion_id', $leccion->id)
                            ->where('vista', true)
                            ->exists();

                        return [
                            'id' => $leccion->id,
                            'titulo' => $leccion->titulo,
                            'slug' => $leccion->slug,
                            'orden' => $leccion->orden,
                            'completada' => $completada,
                            'tiene_ejercicios' => (bool)$leccion->tiene_ejercicios,
                            'tiene_editor' => (bool)$leccion->tiene_editor_codigo,
                            'estado' => $completada ? 'completada' : 'pendiente'
                        ];
                    }),
                    'total' => $lecciones->count()
                ],
                'evaluacion' => $evaluacion ? [
                    'id' => $evaluacion->id,
                    'titulo' => $evaluacion->titulo,
                    'descripcion' => $evaluacion->descripcion,
                    'numero_preguntas' => $evaluacion->numero_preguntas,
                    'tiempo_limite' => $evaluacion->tiempo_limite,
                    'puntaje_minimo' => (float)$evaluacion->puntaje_minimo,
                    'estado' => $progreso && $progreso->evaluacion_aprobada ? 'aprobada' : 'pendiente'
                ] : null,
                'certificacion' => [
                    'disponible' => $progreso && $progreso->certificado_disponible,
                    'requisitos' => 'Aprobar evaluación con mínimo ' . ($evaluacion ? $evaluacion->puntaje_minimo : 70) . '%'
                ],
                'progreso_usuario' => $progreso ? [
                    'porcentaje_completado' => (float)$progreso->porcentaje_completado,
                    'lecciones_vistas' => $progreso->lecciones_vistas,
                    'evaluacion_aprobada' => (bool)$progreso->evaluacion_aprobada
                ] : [
                    'porcentaje_completado' => 0,
                    'lecciones_vistas' => 0,
                    'evaluacion_aprobada' => false
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Módulo no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener información del módulo',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método auxiliar para iconos
     */
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

    /**
     * Obtener módulo por ID
     */
    public function showById($id)
    {
        $modulo = Modulo::where('id', $id)
            ->where('estado', 'activo')
            ->firstOrFail();

        return response()->json([
            'modulo' => $modulo,
            'lecciones_count' => $modulo->lecciones()->count()
        ]);
    }

    public function show($slug)
    {
        $modulo = Modulo::where('slug', $slug)
            ->where('estado', 'activo')
            ->firstOrFail();

        return response()->json($modulo);
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'slug' => 'required|string|unique:modulos',
            'descripcion_larga' => 'nullable|string',
            'modulo' => 'required|in:introduccion,html,css,javascript,php,sql',
            'orden_global' => 'nullable|integer'
        ]);

        $modulo = Modulo::create([
            'titulo' => $request->titulo,
            'slug' => $request->slug,
            'descripcion_larga' => $request->descripcion_larga,
            'modulo' => $request->modulo,
            'orden_global' => $request->orden_global ?? 0,
            'estado' => 'borrador',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Modulo creado correctamnete',
            'modulo' => $modulo
        ], 201);
    }

    public function update(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'slug' => 'required|string|unique:modulos',
            'descripcion_larga' => 'nullable|string',
            'modulo' => 'required|in:introduccion,html,css,javascript,php,sql',
            'orden_global' => 'nullable|integer'
        ]);

        $modulo = Modulo::where();
    }
}
