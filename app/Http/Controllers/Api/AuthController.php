<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'google_uid' => 'required|string',
            'nombre' => 'required|string',
            'correo' => 'required|email',
            'foto_perfil' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $usuario = Usuario::where('google_uid', $request->google_uid)->first();

            if (!$usuario) {
                $usuario = Usuario::create([
                    'nombre' => $request->nombre,
                    'correo' => $request->correo,
                    'google_uid' => $request->google_uid,
                    'foto_perfil' => $request->foto_perfil,
                    'racha_actual' => 0
                ]);
            }

            $token = $usuario->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Login exitoso',
                'user' => $usuario,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en el servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Logout exitoso'
        ]);
    }

    public function getUser(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user' => $request->user()->load(['clasificacion', 'rutasGuardadas', 'comunidades'])
        ]);
    }
}