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
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'nombre' => $user->nombre,
            'name' => $user->nombre,
            'email' => $user->email,
            'avatar_id' => $user->avatar_id,
        ]);
    }

    /**
     * Actualizar nombre y avatar
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'nombre' => 'sometimes|string|max:255', // opcional: permite actualizar solo avatar
            'name' => 'sometimes|string|max:255', // alias enviado por el JS
            'avatar_id' => 'nullable|exists:avatars,id',
        ], [
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no puede exceder los 255 caracteres.',
            'avatar_id.exists' => 'El avatar seleccionado no es válido.',
        ]);

        $user = $request->user();

        // Aceptar 'nombre' o 'name' (alias enviado por el JS del frontend)
        $nuevoNombre = $request->nombre ?? $request->name ?? null;

        $updateData = [
            'avatar_id' => $request->avatar_id ?? $user->avatar_id,
        ];

        // Solo actualizar nombre si se proporcionó uno
        if (!empty($nuevoNombre)) {
            $updateData['nombre'] = $nuevoNombre;
        }

        $user->update($updateData);

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
        ], [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
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
