<?php

namespace App\Services;

use App\Models\Comunidad;
use App\Models\Competencia;
use App\Models\CompetenciaPunto;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompetenciaService
{
    public function calcularFechasPeriodo(Comunidad $comunidad, int $duracionDias, ?Carbon $ref = null): array
    {
        $start = ($ref ?? now())->copy();
        $end = $start->copy()->addDays($duracionDias);
        return [$start, $end];
    }

    public function getOrCreateCompetenciaActiva(Comunidad $comunidad, int $duracionDias, ?Usuario $creador = null): Competencia
    {
        $activa = Competencia::where('comunidad_id', $comunidad->id)
            ->activa()
            ->where('fecha_fin', '>', now())
            ->first();

        if ($activa) {
            return $activa;
        }

        [$inicio, $fin] = $this->calcularFechasPeriodo($comunidad, $duracionDias);

        return Competencia::create([
            'comunidad_id' => $comunidad->id,
            'duracion_dias' => $duracionDias,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'estado' => 'activa',
            'creada_por' => $creador?->id,
        ]);
    }

    public function cerrarSiVencida(Competencia $competencia): void
    {
        if ($competencia->estado === 'activa' && $competencia->fecha_fin <= now()) {
            // Determinar ganador con mayor puntos, desempate por menor id
            $top = CompetenciaPunto::where('competencia_id', $competencia->id)
                ->orderByDesc('puntos')
                ->orderBy('usuario_id')
                ->first();

            $competencia->estado = 'cerrada';
            $competencia->ganador_usuario_id = $top?->usuario_id;
            $competencia->save();
        }
    }

    public function cerrarCompetenciasVencidas(): int
    {
        $vencidas = Competencia::activa()
            ->where('fecha_fin', '<=', now())
            ->get();
        foreach ($vencidas as $comp) {
            $this->cerrarSiVencida($comp);
        }
        return $vencidas->count();
    }

    public function sumarPuntos(Usuario $usuario, Comunidad $comunidad, int $delta, int $duracionDias, ?string $motivo = null): void
    {
        DB::transaction(function () use ($usuario, $comunidad, $delta, $duracionDias) {
            // Validar membresía
            if (!$usuario->comunidades()->where('comunidad_id', $comunidad->id)->exists()) {
                abort(403, 'El usuario no es miembro de la comunidad.');
            }

            // Obtener o crear competencia activa
            $competencia = $this->getOrCreateCompetenciaActiva($comunidad, $duracionDias, $usuario);

            // Upsert de puntos en competencia
            $registro = CompetenciaPunto::firstOrCreate([
                'competencia_id' => $competencia->id,
                'usuario_id' => $usuario->id,
            ], [
                'puntos' => 0,
            ]);
            $nuevo = max(0, $registro->puntos + $delta);
            $registro->update(['puntos' => $nuevo]);

            // Actualizar puntaje global también
            $usuario->puntaje = max(0, $usuario->puntaje + $delta);
            $usuario->save();
        });
    }

    public function rankingActual(Comunidad $comunidad, int $duracionDias, bool $incluirCeros = true, int $limit = 100)
    {
        $competencia = Competencia::where('comunidad_id', $comunidad->id)
            ->activa()
            ->where('fecha_fin', '>', now())
            ->first();

        if (!$competencia) {
            $competencia = $this->getOrCreateCompetenciaActiva($comunidad, $duracionDias);
        }

        if ($incluirCeros) {
            // LEFT JOIN para incluir miembros sin puntos
            return DB::table('usuario_comunidad as uc')
                ->join('usuarios as u', 'u.id', '=', 'uc.usuario_id')
                ->leftJoin('competencia_puntos as cp', function ($join) use ($competencia) {
                    $join->on('cp.usuario_id', '=', 'uc.usuario_id')
                         ->where('cp.competencia_id', '=', $competencia->id);
                })
                ->where('uc.comunidad_id', $comunidad->id)
                ->select('u.id as usuario_id', 'u.nombre', DB::raw('COALESCE(cp.puntos, 0) as puntos'))
                ->orderByDesc('puntos')
                ->orderBy('u.nombre')
                ->limit($limit)
                ->get();
        }

        return CompetenciaPunto::with('usuario')
            ->where('competencia_id', $competencia->id)
            ->orderByDesc('puntos')
            ->orderBy('usuario_id')
            ->limit($limit)
            ->get();
    }

    public function historial(Comunidad $comunidad, int $page = 1, int $perPage = 10)
    {
        return Competencia::where('comunidad_id', $comunidad->id)
            ->where('estado', 'cerrada')
            ->orderByDesc('fecha_fin')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function actualizarCompetenciaActiva(Competencia $competencia, int $duracionDias): Competencia
    {
        // Recalcular fecha_fin desde fecha_inicio con el nuevo N
        $competencia->duracion_dias = $duracionDias;
        $competencia->fecha_fin = Carbon::parse($competencia->fecha_inicio)->copy()->addDays($duracionDias);
        $competencia->save();

        // Si quedó vencida con la nueva configuración, se cierra
        $this->cerrarSiVencida($competencia);

        return $competencia;
    }
}
