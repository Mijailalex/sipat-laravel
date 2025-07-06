<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Parametro extends Model
{
    use HasFactory;

    protected $fillable = [
        'categoria',
        'clave',
        'nombre',
        'descripcion',
        'tipo',
        'valor',
        'valor_por_defecto',
        'opciones',
        'validaciones',
        'modificable',
        'visible_interfaz',
        'orden_visualizacion',
        'modificado_por'
    ];

    protected $casts = [
        'opciones' => 'array',
        'validaciones' => 'array',
        'modificable' => 'boolean',
        'visible_interfaz' => 'boolean',
        'orden_visualizacion' => 'integer'
    ];

    protected $appends = ['valor_formateado'];

    const TIPO_STRING = 'STRING';
    const TIPO_INTEGER = 'INTEGER';
    const TIPO_DECIMAL = 'DECIMAL';
    const TIPO_BOOLEAN = 'BOOLEAN';
    const TIPO_JSON = 'JSON';
    const TIPO_DATE = 'DATE';
    const TIPO_TIME = 'TIME';

    public function modificadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'modificado_por');
    }

    public function scopeVisible($query)
    {
        return $query->where('visible_interfaz', true);
    }

    public function scopeModificable($query)
    {
        return $query->where('modificable', true);
    }

    public function scopeCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('categoria')
                    ->orderBy('orden_visualizacion')
                    ->orderBy('nombre');
    }

    public function getValorFormateadoAttribute()
    {
        return $this->formatearValor($this->valor, $this->tipo);
    }

    public function getValorConvertidoAttribute()
    {
        return $this->convertirValor($this->valor, $this->tipo);
    }

    public static function obtenerValor($clave, $defecto = null)
    {
        $cacheKey = "parametro_{$clave}";

        return Cache::remember($cacheKey, 3600, function () use ($clave, $defecto) {
            $parametro = static::where('clave', $clave)->first();

            if (!$parametro) {
                Log::warning("Parámetro no encontrado: {$clave}");
                return $defecto;
            }

            return $parametro->valor_convertido;
        });
    }

    public static function establecerValor($clave, $valor, $usuarioId = null)
    {
        $parametro = static::where('clave', $clave)->first();

        if (!$parametro) {
            throw new \Exception("Parámetro no encontrado: {$clave}");
        }

        if (!$parametro->modificable) {
            throw new \Exception("El parámetro {$clave} no es modificable");
        }

        static::validarValor($valor, $parametro->tipo, $parametro->opciones);

        $parametro->update([
            'valor' => $valor,
            'modificado_por' => $usuarioId,
            'updated_at' => now()
        ]);

        Cache::forget("parametro_{$clave}");

        Log::info("Parámetro actualizado: {$clave} = {$valor}", [
            'usuario_id' => $usuarioId,
            'valor_anterior' => $parametro->getOriginal('valor')
        ]);

        return $parametro->fresh();
    }

    public static function validarValor($valor, $tipo, $opciones = null)
    {
        if ($opciones && !in_array($valor, $opciones)) {
            throw new \Exception("El valor '{$valor}' no está en las opciones válidas: " . implode(', ', $opciones));
        }

        switch ($tipo) {
            case self::TIPO_INTEGER:
                if (!is_numeric($valor) || intval($valor) != $valor) {
                    throw new \Exception("El valor debe ser un número entero");
                }
                break;

            case self::TIPO_DECIMAL:
                if (!is_numeric($valor)) {
                    throw new \Exception("El valor debe ser un número decimal");
                }
                break;

            case self::TIPO_BOOLEAN:
                if (!in_array(strtolower($valor), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'])) {
                    throw new \Exception("El valor debe ser verdadero o falso");
                }
                break;

            case self::TIPO_JSON:
                json_decode($valor);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("El valor debe ser JSON válido: " . json_last_error_msg());
                }
                break;

            case self::TIPO_DATE:
                try {
                    Carbon::parse($valor);
                } catch (\Exception $e) {
                    throw new \Exception("El valor debe ser una fecha válida");
                }
                break;

            case self::TIPO_TIME:
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $valor)) {
                    throw new \Exception("El valor debe ser una hora válida (HH:MM o HH:MM:SS)");
                }
                break;
        }

        return true;
    }

    public static function convertirValor($valor, $tipo)
    {
        switch ($tipo) {
            case self::TIPO_INTEGER:
                return (int) $valor;
            case self::TIPO_DECIMAL:
                return (float) $valor;
            case self::TIPO_BOOLEAN:
                return in_array(strtolower($valor), ['true', '1', 'yes', 'on']);
            case self::TIPO_JSON:
                return json_decode($valor, true);
            case self::TIPO_DATE:
                return Carbon::parse($valor);
            case self::TIPO_TIME:
                return Carbon::createFromFormat('H:i:s', strlen($valor) === 5 ? $valor . ':00' : $valor);
            default:
                return (string) $valor;
        }
    }

    public function formatearValor($valor, $tipo)
    {
        switch ($tipo) {
            case self::TIPO_BOOLEAN:
                return $this->convertirValor($valor, $tipo) ? 'Verdadero' : 'Falso';
            case self::TIPO_DATE:
                try {
                    return $this->convertirValor($valor, $tipo)->format('d/m/Y');
                } catch (\Exception $e) {
                    return $valor;
                }
            case self::TIPO_TIME:
                try {
                    return $this->convertirValor($valor, $tipo)->format('H:i');
                } catch (\Exception $e) {
                    return $valor;
                }
            case self::TIPO_JSON:
                try {
                    return json_encode($this->convertirValor($valor, $tipo), JSON_PRETTY_PRINT);
                } catch (\Exception $e) {
                    return $valor;
                }
            case self::TIPO_DECIMAL:
                return number_format((float) $valor, 2);
            default:
                return $valor;
        }
    }

    public function restaurarValorDefecto()
    {
        if (!$this->modificable) {
            throw new \Exception("Este parámetro no puede ser modificado");
        }

        $this->update([
            'valor' => $this->valor_por_defecto,
            'modificado_por' => auth()->id()
        ]);

        Cache::forget("parametro_{$this->clave}");
        return $this;
    }

    public function esValorDefecto()
    {
        return $this->valor === $this->valor_por_defecto;
    }

    public function tieneOpciones()
    {
        return !empty($this->opciones);
    }

    public function validarValorActual()
    {
        try {
            static::validarValor($this->valor, $this->tipo, $this->opciones);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function exportarConfiguracion()
    {
        $parametros = static::ordenado()->get();
        $configuracion = [];

        foreach ($parametros as $parametro) {
            $configuracion[$parametro->categoria][$parametro->clave] = [
                'nombre' => $parametro->nombre,
                'descripcion' => $parametro->descripcion,
                'tipo' => $parametro->tipo,
                'valor_actual' => $parametro->valor,
                'valor_por_defecto' => $parametro->valor_por_defecto,
                'opciones' => $parametro->opciones,
                'modificable' => $parametro->modificable,
                'orden_visualizacion' => $parametro->orden_visualizacion,
                'exportado_en' => now()->toISOString()
            ];
        }

        return $configuracion;
    }

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($parametro) {
            if ($parametro->isDirty('valor')) {
                static::validarValor(
                    $parametro->valor,
                    $parametro->tipo,
                    $parametro->opciones
                );
            }
        });

        static::updated(function ($parametro) {
            Cache::forget("parametro_{$parametro->clave}");

            if ($parametro->isDirty('valor')) {
                Log::info("Parámetro modificado", [
                    'clave' => $parametro->clave,
                    'valor_anterior' => $parametro->getOriginal('valor'),
                    'valor_nuevo' => $parametro->valor,
                    'usuario_id' => $parametro->modificado_por
                ]);
            }
        });

        static::deleted(function ($parametro) {
            Cache::forget("parametro_{$parametro->clave}");
        });
    }
}
