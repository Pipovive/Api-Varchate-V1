<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Modulo;

class ModuloController extends Controller
{
    public function index() {
        return Modulo::where('estado','activo')
            ->orderBy('orden_global')->get();
    }

    public function store(Request $request) {
        dd($request->all(), 1);
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
            'created_by' => 1,
        ]);

        return response()->json([
            'message' => 'Modulo creado correctamnete',
            'modulo' => $modulo
        ], 201);
    }
}
