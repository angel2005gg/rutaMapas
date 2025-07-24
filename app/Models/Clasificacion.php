<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clasificacion extends Model
{
    protected $table = 'clasificaciones';
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'puntos_minimos',
        'puntos_maximos'
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class);
    }
}
