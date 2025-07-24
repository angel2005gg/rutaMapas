<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RutaGuardada extends Model
{
    protected $table = 'rutas_guardadas';
    
    protected $fillable = [
        'usuario_id',
        'nombre_lugar',
        'direccion',
        'latitud',
        'longitud'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
