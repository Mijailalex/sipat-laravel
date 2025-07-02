<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    use HasFactory;

    protected $fillable = [
        'plantilla_id', 'conductor_id', 'fecha_salida', 'numero_salida',
        'hora_salida', 'hora_llegada', 'codigo_bus', 'tipo_servicio',
        'origen_destino', 'estado'
    ];

    protected $casts = [
        'fecha_salida' => 'date',
        'hora_salida' => 'datetime:H:i',
        'hora_llegada' => 'datetime:H:i',
    ];

    // Relaciones
    public function plantilla()
    {
        return $this->belongsTo(Plantilla::class);
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }
}
