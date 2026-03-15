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
    $frontendUrl = env('FRONTEND_URL', 'http://127.0.0.1:8000');

    if (!URL::hasValidSignature($request)) {
        return redirect("{$frontendUrl}/email-verificado?status=expired");
    }

    $usuario = Usuario::findOrFail($id);

    if (!hash_equals(
        sha1($usuario->getEmailForVerification()),
        $hash
    )) {
        return redirect("{$frontendUrl}/email-verificado?status=expired");
    }

    if (!$usuario->hasVerifiedEmail()) {
        $usuario->markEmailAsVerified();
    }

    // Si ya estaba verificado
    // (llegó aquí porque la firma era válida pero ya estaba marcado)
    return redirect("{$frontendUrl}/email-verificado?status=success");
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
