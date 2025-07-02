<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plantilla extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre', 'fecha_inicio', 'fecha_fin', 'estado',
        'turnos_planificados', 'cobertura_alcanzada'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'cobertura_alcanzada' => 'decimal:2',
    ];

    // Relaciones
    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('estado', 'ACTIVA');
    }
}
