<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Usuario;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, $id, $hash)
    {
        if (!URL::hasValidSignature($request)) {
            return response()->json([
                'message' => 'Link inválido o expirado'
            ]);
        }

        $usuario = Usuario::findOrFail($id);

        if (!hash_equals(
            sha1($usuario->getEmailForVerification()),
            $hash
        )) {
            return response()->json(['message' => 'hash invalido'], 403);
        }

        if (!$usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
        }

        return response()->json(['message' => 'Correo verificado correctamente']);
    }

    /**
     * Reenviar correo de verificación
     */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El correo ya está verificado'
            ], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Correo de verificación reenviado'
        ], 200);
    }
}
