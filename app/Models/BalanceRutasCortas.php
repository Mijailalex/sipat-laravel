<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceRutasCortas extends Model
{
    use HasFactory;

    protected $table = 'balance_rutas_cortas';

    protected $fillable = [
        'conductor_id', 'semana_numero', 'año', 'rutas_completadas',
        'rutas_programadas', 'objetivo_cumplido', 'total_ingresos'
    ];

    protected $casts = [
        'objetivo_cumplido' => 'boolean',
        'total_ingresos' => 'decimal:2'
    ];

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }
}
