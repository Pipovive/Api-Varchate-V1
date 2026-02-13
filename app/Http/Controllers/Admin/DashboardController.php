<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Modulo;
use App\Models\Leccion;
use App\Models\Ejercicio;
use App\Models\Certificacion;
use App\Models\IntentosEvaluacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $stats = [
                'usuarios' => [
                    'total' => Usuario::count(),
                    'activos' => Usuario::where('estado', 'activo')->count(),
                    'administradores' => Usuario::where('rol', 'administrador')->count(),
                    'aprendices' => Usuario::where('rol', 'aprendiz')->count(),
                ],
                'contenido' => [
                    'modulos' => Modulo::count(),
                    'lecciones' => Leccion::count(),
                    'ejercicios' => Ejercicio::count(),
                ],
                'certificaciones' => [
                    'total' => Certificacion::count(),
                ]
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

    public function charts()
    {
        try {
            // Usuarios por mes (últimos 6 meses)
            $usuariosPorMes = Usuario::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'usuarios_por_mes' => $usuariosPorMes
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de gráficos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recentActivity()
    {
        try {
            $actividades = [];

            // Últimos usuarios
            $nuevosUsuarios = Usuario::latest()
                ->take(5)
                ->get()
                ->map(function ($usuario) {
                    return [
                        'tipo' => 'nuevo_usuario',
                        'descripcion' => "Nuevo usuario: {$usuario->nombre}",
                        'fecha' => $usuario->created_at->toDateTimeString()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $nuevosUsuarios
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener actividad reciente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
