<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comunidad;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ComunidadController extends Controller
{
    /**
     * Crear una nueva comunidad
     */
    public function crearComunidad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $usuario = $request->user();

            // Generar código único de 6 caracteres (letras y números)
            do {
                $codigoUnico = strtoupper(Str::random(6));
            } while (Comunidad::where('codigo_unico', $codigoUnico)->exists());

            // Crear la comunidad
            $comunidad = Comunidad::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'codigo_unico' => $codigoUnico,
                'creador_id' => $usuario->id,
            ]);

            // Agregar automáticamente al creador a la comunidad
            $comunidad->usuarios()->attach($usuario->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Comunidad creada exitosamente',
                'comunidad' => [
                    'id' => $comunidad->id,
                    'nombre' => $comunidad->nombre,
                    'descripcion' => $comunidad->descripcion,
                    'codigo_unico' => $comunidad->codigo_unico,
                    'creador' => [
                        'id' => $usuario->id,
                        'nombre' => $usuario->nombre,
                    ],
                    'total_miembros' => 1,
                    'created_at' => $comunidad->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear comunidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unirse a una comunidad usando código único
     */
    public function unirseAComunidad(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo_unico' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código inválido',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $usuario = $request->user();
            $codigoUnico = strtoupper($request->codigo_unico);

            // Buscar la comunidad por código
            $comunidad = Comunidad::where('codigo_unico', $codigoUnico)->first();

            if (!$comunidad) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Código de comunidad no encontrado'
                ], 404);
            }

            // Verificar si ya está en la comunidad
            if ($comunidad->usuarios()->where('usuario_id', $usuario->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ya eres miembro de esta comunidad'
                ], 409);
            }

            // Unir usuario a la comunidad
            $comunidad->usuarios()->attach($usuario->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Te has unido a la comunidad exitosamente',
                'comunidad' => [
                    'id' => $comunidad->id,
                    'nombre' => $comunidad->nombre,
                    'descripcion' => $comunidad->descripcion,
                    'codigo_unico' => $comunidad->codigo_unico,
                    'total_miembros' => $comunidad->usuarios()->count(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al unirse a la comunidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las comunidades del usuario con información completa
     */
    public function misComunidades(Request $request)
    {
        try {
            $usuario = $request->user();
            
            if (!$usuario) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Obtener comunidades con relaciones - ASEGURAR que creador existe
            $comunidades = $usuario->comunidades()
                                  ->with(['creador', 'usuarios'])
                                  ->whereHas('creador')
                                  ->get();
            
            $comunidadesData = $comunidades->map(function ($comunidad) use ($usuario) {
                // Obtener ranking de usuarios ordenado por puntaje
                $usuariosRanking = $comunidad->usuarios()
                                            ->orderBy('puntaje', 'desc')
                                            ->orderBy('nombre', 'asc')
                                            ->get()
                                            ->map(function ($user, $index) {
                                                return [
                                                    'posicion' => $index + 1,
                                                    'id' => $user->id,
                                                    'nombre' => $user->nombre,
                                                    'foto_perfil' => $user->foto_perfil,
                                                    'racha_actual' => (int)($user->racha_actual ?? 0),
                                                    'puntaje' => (int)($user->puntaje ?? 0),
                                                ];
                                            });

                return [
                    'id' => $comunidad->id,
                    'nombre' => $comunidad->nombre,
                    'descripcion' => $comunidad->descripcion,
                    'codigo_unico' => $comunidad->codigo_unico,
                    'creador' => [
                        'id' => $comunidad->creador->id,
                        'nombre' => $comunidad->creador->nombre,
                        'foto_perfil' => $comunidad->creador->foto_perfil,
                    ],
                    'total_miembros' => $comunidad->usuarios()->count(),
                    'es_creador' => $comunidad->creador_id === $usuario->id,
                    'usuarios' => $usuariosRanking,
                    'created_at' => $comunidad->created_at,
                ];
            });

            return response()->json([
                'status' => 'success',
                'comunidades' => $comunidadesData
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en misComunidades:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener comunidades',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles de una comunidad con ranking
     */
    public function detallesComunidad(Request $request, $id)
    {
        try {
            $usuario = $request->user();

            $comunidad = Comunidad::with('creador')->find($id);

            if (!$comunidad) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Comunidad no encontrada'
                ], 404);
            }

            // Verificar si el usuario es miembro
            if (!$comunidad->usuarios()->where('usuario_id', $usuario->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No eres miembro de esta comunidad'
                ], 403);
            }

            // Obtener ranking de usuarios (ordenados por puntaje)
            $ranking = $comunidad->usuarios()
                                ->orderBy('puntaje', 'desc')
                                ->orderBy('nombre', 'asc')
                                ->get()
                                ->map(function ($user, $index) {
                                    return [
                                        'posicion' => $index + 1,
                                        'id' => $user->id,
                                        'nombre' => $user->nombre,
                                        'foto_perfil' => $user->foto_perfil,
                                        'racha_actual' => (int)($user->racha_actual ?? 0),
                                        'puntaje' => (int)($user->puntaje ?? 0),
                                    ];
                                });

            return response()->json([
                'status' => 'success',
                'comunidad' => [
                    'id' => $comunidad->id,
                    'nombre' => $comunidad->nombre,
                    'descripcion' => $comunidad->descripcion,
                    'codigo_unico' => $comunidad->codigo_unico,
                    'creador' => [
                        'id' => $comunidad->creador->id,
                        'nombre' => $comunidad->creador->nombre,
                        'foto_perfil' => $comunidad->creador->foto_perfil,
                    ],
                    'total_miembros' => $ranking->count(),
                    'es_creador' => $comunidad->creador_id === $usuario->id,
                    'created_at' => $comunidad->created_at,
                ],
                'ranking' => $ranking
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener detalles de comunidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salir de una comunidad
     */
    public function salirDeComunidad(Request $request, $id)
    {
        try {
            $usuario = $request->user();

            $comunidad = Comunidad::find($id);

            if (!$comunidad) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Comunidad no encontrada'
                ], 404);
            }

            // Verificar si es miembro
            if (!$comunidad->usuarios()->where('usuario_id', $usuario->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No eres miembro de esta comunidad'
                ], 403);
            }

            // El creador no puede salir de su propia comunidad
            if ($comunidad->creador_id === $usuario->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El creador no puede salir de la comunidad'
                ], 403);
            }

            // Remover usuario de la comunidad
            $comunidad->usuarios()->detach($usuario->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Has salido de la comunidad exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al salir de la comunidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar comunidad (solo el creador puede hacerlo)
     */
    public function eliminarComunidad(Request $request, $id)
    {
        try {
            $usuario = $request->user();
            $comunidad = Comunidad::find($id);

            if (!$comunidad) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Comunidad no encontrada'
                ], 404);
            }

            // Solo el creador puede eliminar la comunidad
            if ($comunidad->creador_id !== $usuario->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Solo el creador puede eliminar la comunidad'
                ], 403);
            }

            // Eliminar todas las relaciones usuario-comunidad
            $comunidad->usuarios()->detach();
            
            // Eliminar la comunidad
            $comunidad->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Comunidad eliminada exitosamente'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar comunidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}