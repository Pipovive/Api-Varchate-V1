<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Avatar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Usuario::with('avatar');

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('rol') && $request->rol !== 'todos') {
                $query->where('rol', $request->rol);
            }

            $usuarios = $query->orderBy('created_at', 'desc')
                             ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $usuarios
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $usuario = Usuario::with(['avatar', 'progresoModulos.modulo'])->find($id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $usuario
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'email' => 'required|email|unique:usuarios,email',
                'password' => 'required|string|min:8',
                'rol' => 'required|in:administrador,aprendiz',
                'avatar_id' => 'nullable|exists:avatars,id'
            ]);

            $validated['password'] = Hash::make($validated['password']);
            $validated['email_verified_at'] = now();
            $validated['terms_accepted'] = 1;

            $usuario = Usuario::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => $usuario
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $usuario = Usuario::find($id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            $validated = $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', Rule::unique('usuarios')->ignore($id)],
                'rol' => 'sometimes|in:administrador,aprendiz',
                'estado' => 'sometimes|in:activo,inactivo',
                'avatar_id' => 'nullable|exists:avatars,id'
            ]);

            if ($request->has('password') && $request->password) {
                $validated['password'] = Hash::make($request->password);
            }

            $usuario->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'data' => $usuario
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $usuario = Usuario::find($id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            if ($usuario->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminar tu propio usuario'
                ], 403);
            }

            $usuario->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $usuario = Usuario::find($id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            $usuario->estado = $usuario->estado === 'activo' ? 'inactivo' : 'activo';
            $usuario->save();

            return response()->json([
                'success' => true,
                'message' => "Usuario {$usuario->estado} correctamente",
                'data' => $usuario
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statistics()
    {
        try {
            $stats = [
                'total' => Usuario::count(),
                'administradores' => Usuario::where('rol', 'administrador')->count(),
                'aprendices' => Usuario::where('rol', 'aprendiz')->count(),
                'activos' => Usuario::where('estado', 'activo')->count(),
                'inactivos' => Usuario::where('estado', 'inactivo')->count(),
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

    public function getAvatars()
    {
        try {
            $avatars = Avatar::all();
            return response()->json([
                'success' => true,
                'data' => $avatars
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener avatares',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
