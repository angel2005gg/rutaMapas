<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competencia extends Model
{
    use HasFactory;

    protected $fillable = [
        'comunidad_id',
        'duracion_dias',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'ganador_usuario_id',
        'creada_por',
    ];

    public function comunidad(): BelongsTo
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function puntos(): HasMany
    {
        return $this->hasMany(CompetenciaPunto::class);
    }

    public function ganador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ganador_usuario_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creada_por');
    }

    public function scopeActiva($query)
    {
        return $query->where('estado', 'activa');
    }
}
