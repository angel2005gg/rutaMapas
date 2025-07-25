<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    protected $fillable = [
        'correo',
        'codigo',
        'password_hash',
        'nombre',
        'expires_at',
        'usado'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'usado' => 'boolean'
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->usado;
    }
}