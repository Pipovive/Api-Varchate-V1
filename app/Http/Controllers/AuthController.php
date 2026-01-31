<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;




use function Symfony\Component\String\u;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|unique:usuarios',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()   // mayúscula + minúscula
                    ->numbers()     // al menos un número
            ],
        ]);

        $usuario = Usuario::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $usuario->sendEmailVerificationNotification();
        //Creacion de token de sacnctum

        return response()->json([
            'message' => 'Se envió un correo a tu email para comprobar que eres tú',
        ], 201);
    }


    public function recoverPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $status = PasswordBroker::sendResetLink(
            ['email' => $request->email]
        );

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'No se pudo enviar el correo'
            ], 500);
        }

        return response()->json([
            'message' => 'Correo de recuperación enviado'
        ]);
    }

    public function resetPassword(Request $request)
    {

        $request->validate([
            'email' => 'email|required',
            'token' => 'required|string',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()   // mayúscula + minúscula
                    ->numbers()     // al menos un número
            ],
        ]);

        $status = PasswordBroker::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );
        if ($status === PasswordBroker::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Contraseña actualizada correctamente'
            ]);
        }

        return response()->json([
            'message' => 'Token inválido o expirado'
        ], 400);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        // 1. Buscar usuario
        $usuario = Usuario::where('email', $request->email)->first();

        // 2. Validar existencia y contraseña
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // 3. Validar estado
        if ($usuario->estado !== 'activo') {
            return response()->json([
                'message' => 'Usuario inactivo'
            ], 403);
        }

        // 4. Validar email verificado
        if (!$usuario->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Debes verificar tu correo electrónico antes de iniciar sesión'
            ], 403);
        }

        // 5. Crear token
        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $usuario,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function reSendEmail(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Correo de verificación reenviado'
        ]);
    }

    public function loginWithGoogle(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        try {
            $googleUser = Socialite::driver('google')
                ->userFromToken($request->token);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Token de Google inválido'
            ], 401);
        }

        $usuario = Usuario::where('email', $googleUser->getEmail())->first();

        if (!$usuario) {
            $usuario = Usuario::create([
                'nombre' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'email_verified_at' => now(),
                'proveedor_auth' => 'google',
                'auth_provider_id' => $googleUser->getId(),
                'password' => null,
            ]);
        }

        $token = $usuario->createToken('google-auth')->plainTextToken;

        return response()->json([
            'message' => 'Login con Google exitoso',
            'user' => $usuario,
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }








    //SE PUEDE MOFIDICAR PARA MULTIPLES DISPOSITIVOS


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }

    public function test()
    {
        return response()->json(['message' => 'conectado']);
    }
}
