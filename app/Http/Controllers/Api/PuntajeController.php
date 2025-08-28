<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Clasificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Comunidad;
use App\Services\CompetenciaService;

class PuntajeController extends Controller
{
    /**
     * Actualizar puntos del usuario (sumar o restar)
     */
    public function actualizarPuntos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'puntos' => 'required|integer', // Puede ser positivo (sumar) o negativo (restar)
            'motivo' => 'nullable|string|max:255', // Opcional: razón del cambio
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
            $puntosACambiar = $request->puntos;
            $puntajeAnterior = $usuario->puntaje;
            $puntajeNuevo = max(0, $puntajeAnterior + $puntosACambiar); // No puede ser negativo

            // Actualizar puntos
            $usuario->puntaje = $puntajeNuevo;
            
            // Actualizar clasificación automáticamente
            $nuevaClasificacion = $this->obtenerClasificacionPorPuntos($puntajeNuevo);
            if ($nuevaClasificacion) {
                $usuario->clasificacion_id = $nuevaClasificacion->id;
            }

            $usuario->save();

            // Log para auditoria
            Log::info('Puntos actualizados:', [
                'usuario_id' => $usuario->id,
                'puntaje_anterior' => $puntajeAnterior,
                'puntos_cambio' => $puntosACambiar,
                'puntaje_nuevo' => $puntajeNuevo,
                'motivo' => $request->motivo ?? 'Sin motivo especificado'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $puntosACambiar > 0 ? 'Puntos añadidos exitosamente' : 'Puntos restados exitosamente',
                'data' => [
                    'puntaje_anterior' => $puntajeAnterior,
                    'puntos_cambio' => $puntosACambiar,
                    'puntaje_actual' => $puntajeNuevo,
                    'clasificacion' => $nuevaClasificacion ? [
                        'id' => $nuevaClasificacion->id,
                        'nombre' => $nuevaClasificacion->nombre,
                        'descripcion' => $nuevaClasificacion->descripcion
                    ] : null
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error actualizando puntos:', [
                'message' => $e->getMessage(),
                'usuario_id' => $request->user()->id ?? 'N/A'
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar puntos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function actualizarPuntosEnComunidad(Request $request, CompetenciaService $service, $comunidadId)
    {
        $data = $request->validate([
            'puntos' => 'required|integer',
            'duracion_dias' => 'nullable|integer|min:1|max:365',
            'motivo' => 'nullable|string|max:255',
        ]);

        $comunidad = Comunidad::findOrFail($comunidadId);

        // Validar membresía del solicitante
        if (!$request->user()->comunidades()->where('comunidad_id', $comunidad->id)->exists()) {
            return response()->json(['message' => 'No pertenece a la comunidad'], 403);
        }

        $duracion = (int) ($data['duracion_dias'] ?? 7);

        // Sumar al usuario autenticado; si se necesita otro usuario, se puede ampliar el payload
        $service->sumarPuntos($request->user(), $comunidad, (int) $data['puntos'], $duracion, $data['motivo'] ?? null);

        return response()->json(['message' => 'Puntos actualizados en la competencia activa']);
    }

    /**
     * Actualizar racha del usuario
     */
    public function actualizarRacha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'racha' => 'required|integer|min:0', // Solo números positivos o 0
            'accion' => 'required|in:incrementar,resetear,establecer', // Tipo de acción
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
            $rachaAnterior = $usuario->racha_actual;
            $nuevaRacha = 0;

            switch ($request->accion) {
                case 'incrementar':
                    $nuevaRacha = $rachaAnterior + $request->racha;
                    break;
                case 'resetear':
                    $nuevaRacha = 0;
                    break;
                case 'establecer':
                    $nuevaRacha = $request->racha;
                    break;
            }

            $usuario->racha_actual = $nuevaRacha;
            $usuario->save();

            Log::info('Racha actualizada:', [
                'usuario_id' => $usuario->id,
                'racha_anterior' => $rachaAnterior,
                'accion' => $request->accion,
                'racha_nueva' => $nuevaRacha
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Racha actualizada exitosamente',
                'data' => [
                    'racha_anterior' => $rachaAnterior,
                    'racha_actual' => $nuevaRacha,
                    'accion' => $request->accion
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar racha',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información completa del usuario con clasificación
     */
    public function obtenerEstadisticas(Request $request)
    {
        try {
            $usuario = $request->user();
            
            // Cargar clasificación si existe
            $usuario->load('clasificacion');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $usuario->id,
                    'nombre' => $usuario->nombre,
                    'puntaje' => $usuario->puntaje,
                    'racha_actual' => $usuario->racha_actual,
                    'clasificacion' => $usuario->clasificacion ? [
                        'id' => $usuario->clasificacion->id,
                        'nombre' => $usuario->clasificacion->nombre,
                        'descripcion' => $usuario->clasificacion->descripcion,
                        'puntos_minimos' => $usuario->clasificacion->puntos_minimos,
                        'puntos_maximos' => $usuario->clasificacion->puntos_maximos,
                    ] : null,
                    'proxima_clasificacion' => $this->obtenerProximaClasificacion($usuario->puntaje)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener clasificación según puntos
     */
    private function obtenerClasificacionPorPuntos($puntos)
    {
        return Clasificacion::where('puntos_minimos', '<=', $puntos)
                           ->where('puntos_maximos', '>=', $puntos)
                           ->first();
    }

    /**
     * Obtener la próxima clasificación
     */
    private function obtenerProximaClasificacion($puntosActuales)
    {
        return Clasificacion::where('puntos_minimos', '>', $puntosActuales)
                           ->orderBy('puntos_minimos', 'asc')
                           ->first();
    }
}