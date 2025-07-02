<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Parametro extends Model
{
    use HasFactory;

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
        'categoria',
        'editable',
        'requerido',
        'orden',
        'validaciones'
    ];

    protected $casts = [
        'editable' => 'boolean',
        'requerido' => 'boolean',
        'validaciones' => 'array'
    ];

    // Obtener valor parseado según tipo
    public function getValorParseadoAttribute()
    {
        return match($this->tipo) {
            'INTEGER' => (int) $this->valor,
            'DECIMAL' => (float) $this->valor,
            'BOOLEAN' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'JSON' => json_decode($this->valor, true),
            default => $this->valor
        };
    }

    // Validar valor según tipo
    public function validarValor($nuevoValor)
    {
        return match($this->tipo) {
            'INTEGER' => is_numeric($nuevoValor) && (int)$nuevoValor == $nuevoValor,
            'DECIMAL' => is_numeric($nuevoValor),
            'BOOLEAN' => in_array(strtolower($nuevoValor), ['true', 'false', '1', '0']),
            'EMAIL' => filter_var($nuevoValor, FILTER_VALIDATE_EMAIL),
            'URL' => filter_var($nuevoValor, FILTER_VALIDATE_URL),
            default => is_string($nuevoValor)
        };
    }

    // Scopes
    public function scopePorCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopeEditables($query)
    {
        return $query->where('editable', true);
    }

    // Obtener parámetros por categoría
    public static function obtenerPorCategoria($categoria)
    {
        return self::porCategoria($categoria)
            ->orderBy('orden')
            ->orderBy('clave')
            ->get();
    }

    // Obtener valor de parámetro específico
    public static function obtenerValor($clave, $default = null)
    {
        $parametro = self::where('clave', $clave)->first();
        return $parametro ? $parametro->valor_parseado : $default;
    }

    // Actualizar valor de parámetro
    public static function actualizarValor($clave, $valor)
    {
        $parametro = self::where('clave', $clave)->first();
        if ($parametro && $parametro->editable && $parametro->validarValor($valor)) {
            $parametro->update(['valor' => $valor]);
            return true;
        }
        return false;
    }
}
