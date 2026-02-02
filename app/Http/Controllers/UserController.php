<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Mostrar perfil
     */
    public function me(Request $request)
    {
        return response()->json([
            'id' => $request->user()->id,
            'nombre' => $request->user()->nombre,
            'email' => $request->user()->email,
            'avatar_id' => $request->user()->avatar_id,
        ]);
    }

    /**
     * Actualizar nombre y avatar
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'avatar_id' => 'nullable|exists:avatars,id',
        ]);

        $user = $request->user();

        $user->update([
            'nombre' => $request->nombre,
            'avatar_id' => $request->avatar_id ?? $user->avatar_id,
        ]);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => [
                'nombre' => $user->nombre,
                'avatar_id' => $user->avatar_id,
            ],
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $user = $request->user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual no es correcta',
            ], 422);
        }

        // Guardar nueva contraseña
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }
}
