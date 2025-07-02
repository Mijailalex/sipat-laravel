<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionSistema extends Model
{
    protected $table = 'configuraciones_sistema';

    protected $fillable = [
        'categoria',
        'clave',
        'nombre',
        'descripcion',
        'tipo',
        'valor',
        'valor_por_defecto',
        'opciones',
        'unidad',
        'editable',
        'activa'
    ];

    protected $casts = [
        'opciones' => 'array',
        'editable' => 'boolean',
        'activa' => 'boolean'
    ];

    public function getValorFormateadoAttribute()
    {
        return match($this->tipo) {
            'BOOLEAN' => $this->valor === '1' || $this->valor === 'true',
            'INTEGER' => (int) $this->valor,
            'JSON' => json_decode($this->valor, true),
            'TIME' => $this->valor,
            default => $this->valor
        };
    }

    public static function obtenerValor($clave, $porDefecto = null)
    {
        $config = self::where('clave', $clave)->where('activa', true)->first();

        if (!$config) {
            return $porDefecto;
        }

        return $config->valor_formateado;
    }

    public static function establecerValor($clave, $valor)
    {
        $config = self::where('clave', $clave)->first();

        if ($config && $config->editable) {
            $config->update(['valor' => $valor]);
            return true;
        }

        return false;
    }

    public function validarValor($valor)
    {
        return match($this->tipo) {
            'BOOLEAN' => in_array($valor, ['0', '1', 'true', 'false', true, false]),
            'INTEGER' => is_numeric($valor),
            'JSON' => json_decode($valor) !== null,
            'TIME' => preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $valor),
            default => true
        };
    }
}
