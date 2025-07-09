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
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /**
     * Método temporal para hasRole - todos los usuarios son admin por ahora
     */
    public function hasRole($role)
    {
        // Para simplicidad, todos los usuarios autenticados son admin
        return true;
    }

    /**
     * Método temporal para role - devuelve admin por defecto
     */
    public function role($role)
    {
        return $this;
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function isAdmin()
    {
        return $this->email === 'admin@sipat.com' || $this->hasRole('admin');
    }

    /**
     * Verificar si el usuario puede hacer algo específico
     * Compatible con Laravel Authorization
     */
    public function can($abilities, $arguments = [])
    {
        // Para simplicidad inicial, todos los usuarios pueden hacer todo
        return true;
    }

    // Relaciones existentes
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

    /**
     * Obtener el nombre completo del usuario
     */
    public function getFullNameAttribute()
    {
        return $this->name;
    }

    /**
     * Verificar si el usuario está activo
     */
    public function isActive()
    {
        return true; // Por ahora todos están activos
    }
}
