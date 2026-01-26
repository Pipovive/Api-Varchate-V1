<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use function Symfony\Component\String\u;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|ma:255',
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


    public function loginWithGoogle (Request $request) {

    }








    //SE PUEDE MOFIDICAR PARA MULTIPLES DISPOSITIVOS


    public function logout(Request $request) {
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
