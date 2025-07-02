<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubempresaAsignacion extends Model
{
    protected $table = 'subempresa_asignaciones';

    protected $fillable = [
        'subempresa_id',
        'conductor_id',
        'plantilla_turno_id',
        'fecha_asignacion',
        'semana_numero',
        'estado',
        'score_compatibilidad',
        'observaciones'
    ];

    protected $casts = [
        'fecha_asignacion' => 'date'
    ];

    public function subempresa()
    {
        return $this->belongsTo(Subempresa::class);
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function turno()
    {
        return $this->belongsTo(PlantillaTurno::class, 'plantilla_turno_id');
    }
}
