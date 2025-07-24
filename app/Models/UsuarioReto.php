<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioReto extends Model
{
    protected $table = 'usuarios_retos';
    
    protected $fillable = [
        'usuario_id',
        'reto',
        'completado',
        'fecha_asignacion',
        'fecha_completado'
    ];

    protected $casts = [
        'completado' => 'boolean',
        'fecha_asignacion' => 'date',
        'fecha_completado' => 'date',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
