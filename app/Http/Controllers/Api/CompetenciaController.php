<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comunidad;
use App\Services\CompetenciaService;
use Illuminate\Http\Request;

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

        $ranking = $this->service->rankingActual($comunidad, $duracion, incluirCeros: true);

        return response()->json([
            'message' => 'Ranking actual',
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

        return response()->json([
            'message' => 'Historial de competencias',
            'data' => $historial,
        ]);
    }
}
