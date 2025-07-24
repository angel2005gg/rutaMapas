<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use Notifiable, HasApiTokens;

    protected $table = 'usuarios';
    
    protected $fillable = [
        'nombre',
        'correo',
        'foto_perfil',
        'google_uid',
        'racha_actual',
        'clasificacion_id'
    ];

    protected $hidden = [
        'google_uid',
        'remember_token',
    ];

    public function clasificacion()
    {
        return $this->belongsTo(Clasificacion::class);
    }

    public function rutasGuardadas()
    {
        return $this->hasMany(RutaGuardada::class);
    }

    public function retos()
    {
        return $this->hasMany(UsuarioReto::class);
    }

    public function comunidades()
    {
        return $this->belongsToMany(Comunidad::class, 'usuario_comunidad');
    }
}
