<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Validacion extends Model
{
    use HasFactory;

    protected $table = 'validaciones';

    protected $fillable = [
        'tipo', 'conductor_id', 'mensaje', 'severidad', 'estado',
        'fecha_deteccion', 'fecha_resolucion', 'resuelto_por'
    ];

    protected $casts = [
        'fecha_deteccion' => 'datetime',
        'fecha_resolucion' => 'datetime',
    ];

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    // ✅ SCOPES CORREGIDOS
    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    public function scopeCriticas($query)
    {
        return $query->where('severidad', 'CRITICA');
    }
}
