<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $table = 'buses';

    protected $fillable = [
        'codigo', 'placa', 'tipo', 'estado', 'origen_disponibilidad', 'hora_disponibilidad'
    ];

    protected $casts = [
        'hora_disponibilidad' => 'datetime:H:i',
    ];

    // Scopes
    public function scopeOperativos($query)
    {
        return $query->where('estado', 'OPERATIVO');
    }
}
