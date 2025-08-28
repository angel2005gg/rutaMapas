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
        'password', // Agregar esto
        'foto_perfil',
        'google_uid',
        'racha_actual',
        'puntaje', // ← AGREGAR ESTA LÍNEA
        'clasificacion_id'
    ];

    protected $hidden = [
        'password', // Agregar esto
        'google_uid',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
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

    public function competenciaPuntos()
    {
        return $this->hasMany(CompetenciaPunto::class);
    }

    public function competenciasParticipadas()
    {
        return $this->belongsToMany(Competencia::class, 'competencia_puntos');
    }
}
