<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comunidad extends Model
{
    protected $table = 'comunidades';
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo_unico',
        'creador_id'
    ];

    public function creador()
    {
        return $this->belongsTo(Usuario::class, 'creador_id');
    }

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuario_comunidad');
    }

    public function competencias()
    {
        return $this->hasMany(Competencia::class);
    }

    public function competenciaActiva()
    {
        return $this->hasOne(Competencia::class)->where('estado', 'activa');
    }
}
