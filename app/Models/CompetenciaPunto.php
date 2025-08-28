<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetenciaPunto extends Model
{
    use HasFactory;

    protected $fillable = [
        'competencia_id',
        'usuario_id',
        'puntos',
    ];

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
