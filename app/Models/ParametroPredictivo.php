<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ParametroPredictivo extends Model
{
    // Nombre de tabla personalizado
    protected $table = 'parametros_predictivos';

    // Campos permitidos para asignación masiva
    protected $fillable = [
        'clave',
        'configuracion',
        'tipo_prediccion',
        'descripcion',
        'activo',
        'prioridad',
        'umbral_confianza',
        'validaciones_asociadas',
        'historial_predicciones',
        'metricas_rendimiento'
    ];

    // Conversión de tipos de datos
    protected $casts = [
        'configuracion' => 'array',
        'validaciones_asociadas' => 'array',
        'historial_predicciones' => 'array',
        'metricas_rendimiento' => 'array',
        'activo' => 'boolean',
        'umbral_confianza' => 'decimal:2'
    ];

    // Constantes para tipos de predicción
    const TIPO_RANGO = 'RANGO';
    const TIPO_FORMULA = 'FORMULA';
    const TIPO_CONDICIONAL = 'CONDICIONAL';
    const TIPO_AUTOMATICO = 'AUTOMATICO';
    const TIPO_ML = 'ML';

    // Relaciones con otras tablas
    public function validaciones()
    {
        return $this->hasMany(Validacion::class, 'parametro_predictivo_id');
    }

    // Scopes (consultas predefinidas)
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorPrioridad($query)
    {
        return $query->orderBy('prioridad', 'desc');
    }

    // Método de evaluación principal
    public function evaluar($datos)
    {
        // Implementación básica de evaluación
        $resultado = [
            'cumple' => false,
            'confianza' => 0,
            'mensaje' => 'Evaluación no implementada'
        ];

        // Aquí irá la lógica de evaluación según el tipo de predicción

        return $resultado;
    }
}
