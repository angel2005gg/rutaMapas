<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comunidad;
use App\Services\CompetenciaService;
use Illuminate\Http\Request;
use App\Models\Competencia;
use App\Models\CompetenciaPunto;
use Carbon\Carbon;

class CompetenciaController extends Controller
{
    public function __construct(private CompetenciaService $service)
    {
    }

    // POST /comunidades/{id}/configurar-periodo { duracion_dias }
    public function configurarPeriodo(Request $request, $id)
    {
        $request->validate([
            'duracion_dias' => 'required|integer|min:1|max:365',
        ]);

        $comunidad = Comunidad::findOrFail($id);

        // Solo el creador puede configurar
        if ($request->user()->id !== $comunidad->creador_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Crear o refrescar competencia activa con nueva duración
        $competencia = $this->service->getOrCreateCompetenciaActiva($comunidad, (int) $request->duracion_dias, $request->user());

        return response()->json([
            'message' => 'Competencia activa configurada',
            'data' => $competencia,
        ]);
    }

    // PATCH /comunidades/{id}/competencia/editar { duracion_dias }
    public function editarCompetenciaActiva(Request $request, $id)
    {
        $request->validate([
            'duracion_dias' => 'required|integer|min:1|max:365',
        ]);

        $comunidad = Comunidad::findOrFail($id);

        // Solo el creador puede editar
        if ($request->user()->id !== $comunidad->creador_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $competencia = $comunidad->competenciaActiva()->first();
        if (!$competencia) {
            return response()->json(['message' => 'No hay competencia activa para editar'], 404);
        }

        $competencia = $this->service->actualizarCompetenciaActiva($competencia, (int) $request->duracion_dias);

        return response()->json([
            'message' => 'Competencia activa actualizada',
            'data' => $competencia,
        ]);
    }

    // GET /comunidades/{id}/ranking-actual?duracion_dias=N
    public function rankingActual(Request $request, $id)
    {
        $duracion = (int) ($request->query('duracion_dias', 7));
        $comunidad = Comunidad::findOrFail($id);

        // Validar membresía
        if (!$request->user()->comunidades()->where('comunidad_id', $comunidad->id)->exists()) {
            return response()->json(['message' => 'No pertenece a la comunidad'], 403);
        }

        // Asegurar obtener la competencia a reportar
        $competencia = Competencia::where('comunidad_id', $comunidad->id)
            ->activa()
            ->where('fecha_fin', '>', now())
            ->first();
        if (!$competencia) {
            $competencia = $this->service->getOrCreateCompetenciaActiva($comunidad, $duracion);
        }

        $ranking = $this->service->rankingActual($comunidad, $duracion, incluirCeros: true);

        return response()->json([
            'message' => 'Ranking actual',
            'competencia' => [
                'id' => $competencia->id,
                'fecha_inicio' => $competencia->fecha_inicio,
                'fecha_fin' => $competencia->fecha_fin,
                'duracion_dias' => (int) $competencia->duracion_dias,
            ],
            'server_time' => Carbon::now(),
            'data' => $ranking,
        ]);
    }

    // GET /comunidades/{id}/historial?page=1&per_page=10
    public function historial(Request $request, $id)
    {
        $comunidad = Comunidad::findOrFail($id);

        // Validar membresía
        if (!$request->user()->comunidades()->where('comunidad_id', $comunidad->id)->exists()) {
            return response()->json(['message' => 'No pertenece a la comunidad'], 403);
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);

        $historial = $this->service->historial($comunidad, $page, $perPage);

        // Enriquecer cada item con ganador_nombre, ganador_id y puntaje_ganador
        $items = $historial->getCollection()->load('ganador')->map(function ($comp) {
            $puntajeGanador = null;
            if ($comp->ganador_usuario_id) {
                $puntajeGanador = CompetenciaPunto::where('competencia_id', $comp->id)
                    ->where('usuario_id', $comp->ganador_usuario_id)
                    ->value('puntos');
            } else {
                $puntajeGanador = CompetenciaPunto::where('competencia_id', $comp->id)->max('puntos');
            }

            return [
                'id' => $comp->id,
                'fecha_inicio' => $comp->fecha_inicio,
                'fecha_fin' => $comp->fecha_fin,
                'estado' => $comp->estado,
                'ganador_id' => $comp->ganador_usuario_id,
                'ganador_nombre' => $comp->ganador?->nombre,
                'puntaje_ganador' => (int) ($puntajeGanador ?? 0),
                'duracion_dias' => (int) $comp->duracion_dias,
            ];
        });

        $historial->setCollection(collect($items));

        return response()->json([
            'message' => 'Historial de competencias',
            'data' => $historial,
        ]);
    }
}
