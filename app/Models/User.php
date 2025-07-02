<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Relaciones
    public function validacionesResueltas()
    {
        return $this->hasMany(Validacion::class, 'resuelto_por');
    }

    public function plantillasCreadas()
    {
        return $this->hasMany(Plantilla::class, 'creado_por');
    }

    public function parametrosModificados()
    {
        return $this->hasMany(Parametro::class, 'modificado_por');
    }

    public function descansosAprobados()
    {
        return $this->hasMany(PlanificacionDescanso::class, 'aprobado_por');
    }

    public function descansosCreados()
    {
        return $this->hasMany(PlanificacionDescanso::class, 'creado_por');
    }
}
