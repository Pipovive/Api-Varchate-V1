<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

use function Symfony\Component\String\u;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'required|email|unique:usuarios',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $usuario = Usuario::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        //Creacion de token de sacnctum

        $token = $usuario->createToken('web_app')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'user' => $usuario,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'string|required'
        ]);
        //validar que el usuario existe

        $usuario = Usuario::where('email', $request->email)->first();

        if (!$usuario ||  !Hash::check($request->password, $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }
        //Validar que el usuario este activo

        if ($usuario->estado !== 'activo') {
            return response()->json([
                'message' => 'Usuario inactivo'
            ]);
        }
        //crear token de entrar a la api

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $usuario,
            'access_token' => $token,
            'token_type' => 'Bearer',
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
                'email_verificado' => 1,
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
