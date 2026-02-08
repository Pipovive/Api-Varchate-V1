<?php

namespace App\Http\Controllers;

use App\Models\Ranking;
use App\Models\Modulo;
use App\Models\ProgresoModulo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    /**
     * Obtener TOP 5 real del módulo
     * GET /ranking/modulo/{moduloId}/top5
     *
     * Reglas:
     * 1. Ordenar por porcentaje_progreso DESC (mayor a menor)
     * 2. Si hay empate, ordenar por fecha_ultima_actualizacion ASC (más antiguo primero)
     * 3. Solo 5 registros
     */
    public function getTop5Modulo($moduloId)
    {
        try {
            // Verificar que el módulo existe
            $modulo = Modulo::where('id', $moduloId)
                ->where('estado', 'activo')
                ->firstOrFail();

            // OBTENER TOP 5 CON LÓGICA CORRECTA
            $top5 = Ranking::select([
                    'ranking.*',
                    'usuarios.nombre',
                    'usuarios.avatar_id',
                    DB::raw('DATE(ranking.fecha_ultima_actualizacion) as fecha_finalizacion')
                ])
                ->join('usuarios', 'ranking.usuario_id', '=', 'usuarios.id')
                ->where('ranking.modulo_id', $moduloId)
                ->where('ranking.porcentaje_progreso', '>', 0) // Solo usuarios con progreso
                ->orderBy('ranking.porcentaje_progreso', 'DESC') // Primero: mayor porcentaje
                ->orderBy('ranking.fecha_ultima_actualizacion', 'ASC') // Segundo: más antiguo primero (si empate)
                ->limit(5)
                ->get()
                ->map(function ($ranking, $index) {
                    return [
                        'posicion' => $index + 1, // Posición en el top (1-5)
                        'usuario' => [
                            'id' => $ranking->usuario_id,
                            'nombre' => $ranking->nombre,
                            'avatar_id' => $ranking->avatar_id,
                            'iniciales' => $this->getIniciales($ranking->nombre)
                        ],
                        'progreso' => [
                            'porcentaje' => (float) $ranking->porcentaje_progreso,
                            'completado' => $ranking->porcentaje_progreso >= 100,
                            'fecha_ultima_actualizacion' => $ranking->fecha_ultima_actualizacion,
                            'fecha_finalizacion' => $ranking->fecha_finalizacion
                        ],
                        'medalla' => $this->getMedalla($index + 1)
                    ];
                });

            // Si no hay suficientes, completar con usuarios activos
            if ($top5->count() < 5) {
                $top5 = $this->completarTop5($moduloId, $top5);
            }

            // Estadísticas
            $totalParticipantes = Ranking::where('modulo_id', $moduloId)->count();
            $promedioProgreso = Ranking::where('modulo_id', $moduloId)
                ->avg('porcentaje_progreso');

            return response()->json([
                'success' => true,
                'data' => [
                    'modulo' => [
                        'id' => $modulo->id,
                        'titulo' => $modulo->titulo,
                        'slug' => $modulo->slug,
                        'descripcion_corta' => $this->getDescripcionCorta($modulo->descripcion_larga)
                    ],
                    'top_5' => $top5,
                    'estadisticas' => [
                        'total_participantes' => $totalParticipantes,
                        'promedio_progreso' => $promedioProgreso ? round($promedioProgreso, 2) : 0,
                        'actualizado' => now()->format('d/m/Y H:i')
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener top 5',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Completar top 5 si no hay suficientes usuarios con progreso
     */
    private function completarTop5($moduloId, $top5Actual)
    {
        $usuariosEnTop = $top5Actual->pluck('usuario.id')->toArray();
        $necesarios = 5 - $top5Actual->count();

        if ($necesarios <= 0) {
            return $top5Actual;
        }

        // Buscar otros usuarios con progreso pero no en el top
        $otrosUsuarios = Ranking::select([
                'ranking.*',
                'usuarios.nombre',
                'usuarios.avatar_id'
            ])
            ->join('usuarios', 'ranking.usuario_id', '=', 'usuarios.id')
            ->where('ranking.modulo_id', $moduloId)
            ->whereNotIn('ranking.usuario_id', $usuariosEnTop)
            ->where('ranking.porcentaje_progreso', '>', 0)
            ->orderBy('ranking.porcentaje_progreso', 'DESC')
            ->orderBy('ranking.fecha_ultima_actualizacion', 'ASC')
            ->limit($necesarios)
            ->get()
            ->map(function ($ranking, $index) use ($top5Actual) {
                $posicion = $top5Actual->count() + $index + 1;

                return [
                    'posicion' => $posicion,
                    'usuario' => [
                        'id' => $ranking->usuario_id,
                        'nombre' => $ranking->nombre,
                        'avatar_id' => $ranking->avatar_id,
                        'iniciales' => $this->getIniciales($ranking->nombre)
                    ],
                    'progreso' => [
                        'porcentaje' => (float) $ranking->porcentaje_progreso,
                        'completado' => $ranking->porcentaje_progreso >= 100,
                        'fecha_ultima_actualizacion' => $ranking->fecha_ultima_actualizacion
                    ],
                    'medalla' => $this->getMedalla($posicion)
                ];
            });

        return $top5Actual->merge($otrosUsuarios);
    }

    /**
     * Obtener medalla según posición
     */
    private function getMedalla($posicion)
    {
        switch ($posicion) {
            case 1: return ['tipo' => 'oro', 'icono' => '🥇'];
            case 2: return ['tipo' => 'plata', 'icono' => '🥈'];
            case 3: return ['tipo' => 'bronce', 'icono' => '🥉'];
            default: return ['tipo' => 'top5', 'icono' => '⭐'];
        }
    }

    /**
     * Obtener iniciales del nombre
     */
    private function getIniciales($nombre)
    {
        $palabras = explode(' ', trim($nombre));
        $iniciales = '';

        foreach ($palabras as $palabra) {
            if (!empty($palabra)) {
                $iniciales .= strtoupper(substr($palabra, 0, 1));
            }
        }

        return substr($iniciales, 0, 2);
    }

    /**
     * Descripción corta para la tarjeta
     */
    private function getDescripcionCorta($descripcion)
    {
        if (empty($descripcion)) {
            return 'Módulo de aprendizaje';
        }

        $descripcion = strip_tags($descripcion);
        return strlen($descripcion) > 80
            ? substr($descripcion, 0, 77) . '...'
            : $descripcion;
    }

    /**
     * Endpoint para pantalla principal - Top 5 de todos los módulos
     * GET /ranking/pantalla-principal
     */
    public function getPantallaPrincipal()
    {
        try {
            $usuario = Auth::user();

            // Obtener módulos activos
            $modulos = Modulo::where('estado', 'activo')
                ->orderBy('orden_global', 'asc')
                ->get();

            $top5PorModulo = [];

            foreach ($modulos as $modulo) {
                // Top 5 de cada módulo
                $top5 = $this->getTop5ParaModulo($modulo->id);

                // Mi posición en este módulo
                $miPosicion = $this->getMiPosicionEnModulo($usuario->id, $modulo->id);

                $top5PorModulo[] = [
                    'modulo' => [
                        'id' => $modulo->id,
                        'titulo' => $modulo->titulo,
                        'slug' => $modulo->slug,
                        'icono' => $this->getIconoModulo($modulo->modulo)
                    ],
                    'top_5' => $top5,
                    'mi_posicion' => $miPosicion,
                    'total_participantes' => Ranking::where('modulo_id', $modulo->id)->count()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'top_5_por_modulo' => $top5PorModulo,
                    'actualizado' => now()->format('d/m/Y H:i')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos para pantalla principal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método helper: Obtener top 5 para un módulo específico
     */
    private function getTop5ParaModulo($moduloId)
    {
        return Ranking::select([
                'ranking.*',
                'usuarios.nombre',
                'usuarios.avatar_id'
            ])
            ->join('usuarios', 'ranking.usuario_id', '=', 'usuarios.id')
            ->where('ranking.modulo_id', $moduloId)
            ->where('ranking.porcentaje_progreso', '>', 0)
            ->orderBy('ranking.porcentaje_progreso', 'DESC')
            ->orderBy('ranking.fecha_ultima_actualizacion', 'ASC')
            ->limit(5)
            ->get()
            ->map(function ($ranking, $index) {
                return [
                    'posicion' => $index + 1,
                    'usuario' => [
                        'id' => $ranking->usuario_id,
                        'nombre' => $ranking->nombre,
                        'avatar_id' => $ranking->avatar_id,
                        'iniciales' => $this->getIniciales($ranking->nombre)
                    ],
                    'porcentaje' => (float) $ranking->porcentaje_progreso,
                    'completado' => $ranking->porcentaje_progreso >= 100
                ];
            });
    }

    /**
     * Método helper: Obtener mi posición en un módulo
     */
    private function getMiPosicionEnModulo($usuarioId, $moduloId)
    {
        $miRanking = Ranking::where('usuario_id', $usuarioId)
            ->where('modulo_id', $moduloId)
            ->first();

        if (!$miRanking) {
            return null;
        }

        $posicion = Ranking::where('modulo_id', $moduloId)
            ->where('porcentaje_progreso', '>', $miRanking->porcentaje_progreso)
            ->count() + 1;

        return [
            'posicion' => $posicion,
            'porcentaje' => (float) $miRanking->porcentaje_progreso,
            'en_top_5' => $posicion <= 5
        ];
    }

    /**
     * Icono según tipo de módulo
     */
    private function getIconoModulo($tipoModulo)
    {
        $iconos = [
            'html' => '📄',
            'css' => '🎨',
            'javascript' => '⚡',
            'php' => '🐘',
            'sql' => '🗃️',
            'introduccion' => '🚀'
        ];

        return $iconos[$tipoModulo] ?? '📚';
    }
}
