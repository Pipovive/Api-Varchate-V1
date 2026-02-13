<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        if (auth()->user()->rol !== 'administrador') {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Se requieren permisos de administrador.'
            ], 403);
        }

        return $next($request);
    }
}
