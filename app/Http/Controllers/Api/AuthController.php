<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
            // BUSCAR PRIMERO POR EMAIL (unifica cuentas)
            $usuario = Usuario::where('correo', $request->correo)->first();

            if ($usuario) {
                // Si existe por email, actualizar con datos de Google
                $usuario->update([
                    'google_uid' => $request->google_uid,
                    'foto_perfil' => $request->foto_perfil ?? $usuario->foto_perfil,
                    'nombre' => $request->nombre // Actualizar nombre si cambió
                ]);
            } else {
                // Si no existe, crear nuevo usuario
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
        try {
            $user = $request->user();
            
            return response()->json([
                'status' => 'success',
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'correo' => $user->correo,
                    'foto_perfil' => $user->foto_perfil, // ← Asegurar que pase la foto de Google
                    'google_uid' => $user->google_uid,   // ← Pasar el UID de Google
                    'puntaje' => (int)($user->puntaje ?? 0), // ← incluir puntaje total
                    'racha_actual' => (int)($user->racha_actual ?? 0),
                    'clasificacion_id' => $user->clasificacion_id ? (int)$user->clasificacion_id : 0,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo obtener el usuario'
            ], 500);
        }
    }

    public function emailLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $usuario = Usuario::where('correo', $request->correo)->first();

            if (!$usuario || !Hash::check($request->password, $usuario->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Credenciales incorrectas'
                ], 401);
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

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'correo' => 'required|email|unique:usuarios,correo',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $usuario = Usuario::create([
                'nombre' => $request->nombre,
                'correo' => $request->correo,
                'password' => Hash::make($request->password),
                'racha_actual' => 0
            ]);

            $token = $usuario->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Usuario registrado exitosamente',
                'user' => $usuario,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendVerificationCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string|min:6',
            'nombre' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Verificar si el usuario ya existe con contraseña
            $usuarioExistente = Usuario::where('correo', $request->correo)->first();
            
            if ($usuarioExistente && $usuarioExistente->password) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario ya existe. Use login normal.'
                ], 409);
            }

            // Generar código de 6 dígitos
            $codigo = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            // Eliminar códigos anteriores
            EmailVerification::where('correo', $request->correo)->delete();
            
            // Crear nuevo código
            EmailVerification::create([
                'correo' => $request->correo,
                'codigo' => $codigo,
                'password_hash' => Hash::make($request->password),
                'nombre' => $request->nombre ?? ($usuarioExistente ? $usuarioExistente->nombre : 'Usuario'),
                'expires_at' => Carbon::now()->addMinutes(10),
            ]);

            // Enviar email
            Mail::raw("Tu código de verificación es: {$codigo}\n\nEste código expira en 10 minutos.\n\nRutaMapas App", function ($message) use ($request) {
                $message->to($request->correo)
                        ->subject('Código de verificación - RutaMapas');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Código enviado al correo',
                'debug' => env('APP_DEBUG') ? "Código: {$codigo}" : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al enviar código',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyCodeAndLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'codigo' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Verificar código
            $verification = EmailVerification::where('correo', $request->correo)
                                           ->where('codigo', $request->codigo)
                                           ->where('usado', false)
                                           ->first();

            if (!$verification || $verification->isExpired()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Código inválido o expirado'
                ], 400);
            }

            // Extraer nombre inteligente del email ✅
            $nombreExtraido = $this->extraerNombreDelEmail($request->correo);

            // Buscar o crear usuario
            $usuario = Usuario::where('correo', $request->correo)->first();

            if ($usuario) {
                // Actualizar usuario existente
                $usuario->update([
                    'password' => $verification->password_hash,
                    'nombre' => $nombreExtraido, // ✅ Actualizar con nombre extraído
                ]);
            } else {
                // Crear nuevo usuario con nombre extraído
                $usuario = Usuario::create([
                    'nombre' => $nombreExtraido, // ✅ Nombre del email en lugar de "Usuario"
                    'correo' => $request->correo,
                    'password' => $verification->password_hash,
                    'foto_perfil' => null,
                    'racha_actual' => 0
                ]);
            }

            // Marcar código como usado
            $verification->update(['usado' => true]);

            $token = $usuario->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Verificación exitosa',
                'user' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre, // ✅ Nombre extraído del email
                    'correo' => $usuario->correo,
                    'foto_perfil' => $usuario->foto_perfil,
                    'google_uid' => $usuario->google_uid,
                    'racha_actual' => (int)($usuario->racha_actual ?? 0),
                    'clasificacion_id' => $usuario->clasificacion_id ? (int)$usuario->clasificacion_id : 0,
                ],
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en verificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function obtenerDatosRealDeGoogle($accessToken)
    {
        try {
            // Usar el token de acceso para obtener datos reales
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $accessToken);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $userData = json_decode($response, true);

            if (isset($userData['id'])) {
                return [
                    'nombre' => $userData['name'] ?? 'Usuario',
                    'foto_perfil' => $userData['picture'] ?? null,
                    'google_uid' => $userData['id']
                ];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Buscar usuario por correo
            $usuario = Usuario::where('correo', $request->correo)->first();

            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            if (!$usuario->password) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario sin contraseña. Use Google Sign-In.'
                ], 401);
            }

            // DEBUG: Verificar hash
            $passwordCheck = Hash::check($request->password, $usuario->password);
            
            // Si está en modo debug, mostrar información adicional
            if (env('APP_DEBUG')) {
                Log::info('Login Debug', [
                    'email' => $request->correo,
                    'password_input' => $request->password,
                    'password_hash' => $usuario->password,
                    'hash_check' => $passwordCheck
                ]);
            }

            if (!$passwordCheck) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Contraseña incorrecta',
                    'debug' => env('APP_DEBUG') ? [
                        'password_length' => strlen($usuario->password),
                        'starts_with_dollar' => str_starts_with($usuario->password, '$'),
                    ] : null
                ], 401);
            }

            // Login exitoso
            $token = $usuario->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Login exitoso',
                'user' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'correo' => $usuario->correo,
                    'foto_perfil' => $usuario->foto_perfil,
                    'google_uid' => $usuario->google_uid,
                    'racha_actual' => (int)($usuario->racha_actual ?? 0),
                    'clasificacion_id' => $usuario->clasificacion_id ? (int)$usuario->clasificacion_id : 0,
                ],
                'token' => $token
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error en el servidor',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    // Agregar este método privado para extraer nombres del email:
    private function extraerNombreDelEmail($email)
    {
        // Obtener la parte antes del @
        $nombrePartes = explode('@', $email);
        $nombreUsuario = $nombrePartes[0];
        
        // Limpiar números y caracteres especiales
        $nombre = preg_replace('/[0-9]/', '', $nombreUsuario);
        $nombre = str_replace(['.', '_', '-', '+'], ' ', $nombre);
        
        // Capitalizar cada palabra
        $nombre = ucwords(strtolower($nombre));
        $nombre = trim($nombre);
        
        // Si queda muy corto o vacío, usar el nombre original sin limpiar
        if (strlen($nombre) < 2) {
            $nombre = ucwords(str_replace(['.', '_', '-', '+'], ' ', $nombreUsuario));
        }
        
        return $nombre ?: 'Usuario';
    }
}