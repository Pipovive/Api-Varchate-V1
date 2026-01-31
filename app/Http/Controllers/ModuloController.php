<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Modulo;

class ModuloController extends Controller
{
    public function index()
    {
        return Modulo::where('estado', 'activo')
            ->orderBy('orden_global')->get();
    }

    public function show($slug) {
        $modulo = Modulo::where('slug', $slug)
            ->where('estado', 'activo')
            ->firstOrFail();

        return  response()->json($modulo);
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
