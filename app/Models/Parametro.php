<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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

    // Relaciones
    public function modificadoPor()
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }

    // Scopes
    public function scopeCategoria($query, $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    public function scopeVisibles($query)
    {
        return $query->where('visible_interfaz', true);
    }

    public function scopeModificables($query)
    {
        return $query->where('modificable', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('categoria')
                    ->orderBy('orden_visualizacion')
                    ->orderBy('nombre');
    }

    // Métodos estáticos para gestión de parámetros
    public static function obtenerValor($clave, $default = null)
    {
        $cacheKey = "parametro_{$clave}";

        return Cache::remember($cacheKey, 3600, function () use ($clave, $default) {
            $parametro = static::where('clave', $clave)->first();

            if (!$parametro) {
                return $default;
            }

            return static::convertirValor($parametro->valor, $parametro->tipo);
        });
    }

    public static function establecerValor($clave, $valor, $usuario_id = null)
    {
        $parametro = static::where('clave', $clave)->first();

        if (!$parametro) {
            throw new \Exception("Parámetro '{$clave}' no encontrado");
        }

        if (!$parametro->modificable) {
            throw new \Exception("Parámetro '{$clave}' no es modificable");
        }

        // Validar el valor
        $valorValidado = static::validarValor($valor, $parametro);

        // Actualizar
        $parametro->update([
            'valor' => $valorValidado,
            'modificado_por' => $usuario_id ?? auth()->id()
        ]);

        // Limpiar cache
        Cache::forget("parametro_{$clave}");

        return true;
    }

    public static function obtenerPorCategoria($categoria)
    {
        $cacheKey = "parametros_categoria_{$categoria}";

        return Cache::remember($cacheKey, 3600, function () use ($categoria) {
            return static::categoria($categoria)
                ->visibles()
                ->ordenados()
                ->get()
                ->mapWithKeys(function ($parametro) {
                    return [
                        $parametro->clave => [
                            'valor' => static::convertirValor($parametro->valor, $parametro->tipo),
                            'nombre' => $parametro->nombre,
                            'descripcion' => $parametro->descripcion,
                            'tipo' => $parametro->tipo,
                            'opciones' => $parametro->opciones,
                            'modificable' => $parametro->modificable
                        ]
                    ];
                });
        });
    }

    public static function limpiarCache($categoria = null)
    {
        if ($categoria) {
            Cache::forget("parametros_categoria_{$categoria}");

            // Limpiar parámetros individuales de la categoría
            static::categoria($categoria)->get()->each(function ($parametro) {
                Cache::forget("parametro_{$parametro->clave}");
            });
        } else {
            // Limpiar todo el cache de parámetros
            static::all()->each(function ($parametro) {
                Cache::forget("parametro_{$parametro->clave}");
                Cache::forget("parametros_categoria_{$parametro->categoria}");
            });
        }
    }

    private static function convertirValor($valor, $tipo)
    {
        return match($tipo) {
            'INTEGER' => (int) $valor,
            'DECIMAL' => (float) $valor,
            'BOOLEAN' => filter_var($valor, FILTER_VALIDATE_BOOLEAN),
            'JSON' => json_decode($valor, true),
            'DATE' => \Carbon\Carbon::parse($valor),
            'TIME' => \Carbon\Carbon::parse($valor),
            default => $valor
        };
    }

    private static function validarValor($valor, $parametro)
    {
        // Validar tipo
        switch ($parametro->tipo) {
            case 'INTEGER':
                if (!is_numeric($valor) || !is_int($valor + 0)) {
                    throw new \Exception("El valor debe ser un número entero");
                }
                break;

            case 'DECIMAL':
                if (!is_numeric($valor)) {
                    throw new \Exception("El valor debe ser un número decimal");
                }
                break;

            case 'BOOLEAN':
                if (!in_array(strtolower($valor), ['true', 'false', '1', '0', 'yes', 'no'])) {
                    throw new \Exception("El valor debe ser verdadero o falso");
                }
                $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                break;

            case 'JSON':
                if (is_string($valor)) {
                    json_decode($valor);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception("El valor debe ser un JSON válido");
                    }
                } else {
                    $valor = json_encode($valor);
                }
                break;

            case 'DATE':
                try {
                    \Carbon\Carbon::parse($valor);
                } catch (\Exception $e) {
                    throw new \Exception("El valor debe ser una fecha válida");
                }
                break;

            case 'TIME':
                try {
                    \Carbon\Carbon::parse($valor);
                } catch (\Exception $e) {
                    throw new \Exception("El valor debe ser una hora válida");
                }
                break;
        }

        // Validar opciones
        if ($parametro->opciones && !in_array($valor, $parametro->opciones)) {
            throw new \Exception("El valor debe ser una de las opciones válidas: " .
                implode(', ', $parametro->opciones));
        }

        // Validaciones adicionales
        if ($parametro->validaciones) {
            foreach ($parametro->validaciones as $regla => $valorRegla) {
                switch ($regla) {
                    case 'min':
                        if (is_numeric($valor) && $valor < $valorRegla) {
                            throw new \Exception("El valor mínimo es {$valorRegla}");
                        }
                        break;

                    case 'max':
                        if (is_numeric($valor) && $valor > $valorRegla) {
                            throw new \Exception("El valor máximo es {$valorRegla}");
                        }
                        break;

                    case 'min_length':
                        if (strlen($valor) < $valorRegla) {
                            throw new \Exception("La longitud mínima es {$valorRegla} caracteres");
                        }
                        break;

                    case 'max_length':
                        if (strlen($valor) > $valorRegla) {
                            throw new \Exception("La longitud máxima es {$valorRegla} caracteres");
                        }
                        break;
                }
            }
        }

        return $valor;
    }

    public function getValorFormateadoAttribute()
    {
        return static::convertirValor($this->valor, $this->tipo);
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
        Cache::forget("parametros_categoria_{$this->categoria}");

        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($parametro) {
            // Limpiar cache cuando se actualiza un parámetro
            Cache::forget("parametro_{$parametro->clave}");
            Cache::forget("parametros_categoria_{$parametro->categoria}");
        });
    }
