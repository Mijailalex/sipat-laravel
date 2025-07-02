<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionTramo extends Model
{
    use HasFactory;

    protected $table = 'configuracion_tramos';

    protected $fillable = [
        'tramo', 'rumbo', 'duracion_horas', 'es_ruta_corta',
        'ingreso_base', 'activo', 'descripcion'
    ];

    protected $casts = [
        'duracion_horas' => 'decimal:2',
        'es_ruta_corta' => 'boolean',
        'ingreso_base' => 'decimal:2',
        'activo' => 'boolean'
    ];

    public function scopeRutasCortas($query)
    {
        return $query->where('es_ruta_corta', true);
    }

    public function scopeRutasLargas($query)
    {
        return $query->where('es_ruta_corta', false);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
