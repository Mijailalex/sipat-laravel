<?php

namespace App\Helpers;

use App\Models\Parametro;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ParametroHelper
{
    /**
     * Obtener valor de parámetro con caché
     */
    public static function get(string $clave, $defecto = null)
    {
        return Parametro::obtenerValor($clave, $defecto);
    }

    /**
     * Establecer valor de parámetro
     */
    public static function set(string $clave, $valor, int $usuarioId = null): bool
    {
        try {
            Parametro::establecerValor($clave, $valor, $usuarioId ?? auth()->id());
            return true;
        } catch (\Exception $e) {
            Log::error("Error estableciendo parámetro {$clave}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener múltiples parámetros de una categoría
     */
    public static function getCategoria(string $categoria): array
    {
        $cacheKey = "parametros_categoria_{$categoria}";

        return Cache::remember($cacheKey, 3600, function () use ($categoria) {
            return Parametro::where('categoria', $categoria)
                           ->pluck('valor', 'clave')
                           ->toArray();
        });
    }

    /**
     * Verificar si un parámetro existe
     */
    public static function exists(string $clave): bool
    {
        return Parametro::where('clave', $clave)->exists();
    }

    /**
     * Obtener valor booleano
     */
    public static function getBool(string $clave, bool $defecto = false): bool
    {
        $valor = static::get($clave, $defecto);

        if (is_bool($valor)) {
            return $valor;
        }

        return in_array(strtolower($valor), ['true', '1', 'yes', 'on', 'si']);
    }

    /**
     * Obtener valor entero
     */
    public static function getInt(string $clave, int $defecto = 0): int
    {
        return (int) static::get($clave, $defecto);
    }

    /**
     * Obtener valor decimal
     */
    public static function getFloat(string $clave, float $defecto = 0.0): float
    {
        return (float) static::get($clave, $defecto);
    }

    /**
     * Obtener valor como array (para JSON)
     */
    public static function getArray(string $clave, array $defecto = []): array
    {
        $valor = static::get($clave, $defecto);

        if (is_array($valor)) {
            return $valor;
        }

        if (is_string($valor)) {
            $decoded = json_decode($valor, true);
            return is_array($decoded) ? $decoded : $defecto;
        }

        return $defecto;
    }

    /**
     * Obtener fecha como Carbon
     */
    public static function getDate(string $clave, $defecto = null): ?\Carbon\Carbon
    {
        $valor = static::get($clave, $defecto);

        if (!$valor) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($valor);
        } catch (\Exception $e) {
            Log::warning("Error parseando fecha del parámetro {$clave}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Restaurar parámetro a valor por defecto
     */
    public static function restore(string $clave): bool
    {
        try {
            $parametro = Parametro::where('clave', $clave)->first();

            if (!$parametro) {
                return false;
            }

            $parametro->restaurarValorDefecto();
            return true;
        } catch (\Exception $e) {
            Log::error("Error restaurando parámetro {$clave}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener configuración completa de una categoría como objeto
     */
    public static function getConfig(string $categoria): object
    {
        $parametros = static::getCategoria($categoria);
        return (object) $parametros;
    }

    /**
     * Validar si un valor es válido para un parámetro
     */
    public static function isValid(string $clave, $valor): bool
    {
        try {
            $parametro = Parametro::where('clave', $clave)->first();

            if (!$parametro) {
                return false;
            }

            Parametro::validarValor($valor, $parametro->tipo, $parametro->opciones);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtener todas las categorías disponibles
     */
    public static function getCategorias(): array
    {
        return Cache::remember('parametros_categorias', 3600, function () {
            return Parametro::select('categoria')
                           ->distinct()
                           ->orderBy('categoria')
                           ->pluck('categoria')
                           ->toArray();
        });
    }

    /**
     * Limpiar caché de parámetros
     */
    public static function clearCache(string $clave = null): void
    {
        if ($clave) {
            Cache::forget("parametro_{$clave}");
        } else {
            // Limpiar todo el caché de parámetros
            $parametros = Parametro::all();
            foreach ($parametros as $parametro) {
                Cache::forget("parametro_{$parametro->clave}");
            }

            // Limpiar caché de categorías
            Cache::forget('parametros_categorias');

            $categorias = static::getCategorias();
            foreach ($categorias as $categoria) {
                Cache::forget("parametros_categoria_{$categoria}");
            }
        }
    }

    /**
     * Obtener estadísticas de parámetros
     */
    public static function getStats(): array
    {
        return Cache::remember('parametros_stats', 1800, function () {
            return [
                'total' => Parametro::count(),
                'modificables' => Parametro::where('modificable', true)->count(),
                'por_categoria' => Parametro::selectRaw('categoria, count(*) as total')
                                          ->groupBy('categoria')
                                          ->pluck('total', 'categoria')
                                          ->toArray(),
                'por_tipo' => Parametro::selectRaw('tipo, count(*) as total')
                                      ->groupBy('tipo')
                                      ->pluck('total', 'tipo')
                                      ->toArray(),
                'modificados' => Parametro::whereRaw('valor != valor_por_defecto')->count()
            ];
        });
    }

    /**
     * Métodos de conveniencia para parámetros comunes
     */

    public static function getEmpresa(): string
    {
        return static::get('nombre_empresa', 'SIPAT Transport');
    }

    public static function getTimezone(): string
    {
        return static::get('timezone', 'America/Lima');
    }

    public static function getItemsPorPagina(): int
    {
        return static::getInt('items_por_pagina', 20);
    }

    public static function getMaxDiasValidacion(): int
    {
        return static::getInt('max_dias_validacion', 7);
    }

    public static function isValidacionAutomatica(): bool
    {
        return static::getBool('validacion_automatica', true);
    }

    public static function getFormatoFecha(): string
    {
        return static::get('formato_fecha', 'Y-m-d');
    }

    public static function isNotificacionesEmail(): bool
    {
        return static::getBool('activar_notificaciones_email', true);
    }
}

// Funciones helpers globales
if (!function_exists('param')) {
    /**
     * Helper global para obtener parámetros
     */
    function param(string $clave, $defecto = null)
    {
        return \App\Helpers\ParametroHelper::get($clave, $defecto);
    }
}

if (!function_exists('param_bool')) {
    /**
     * Helper global para obtener parámetros booleanos
     */
    function param_bool(string $clave, bool $defecto = false): bool
    {
        return \App\Helpers\ParametroHelper::getBool($clave, $defecto);
    }
}

if (!function_exists('param_int')) {
    /**
     * Helper global para obtener parámetros enteros
     */
    function param_int(string $clave, int $defecto = 0): int
    {
        return \App\Helpers\ParametroHelper::getInt($clave, $defecto);
    }
}

if (!function_exists('param_array')) {
    /**
     * Helper global para obtener parámetros como array
     */
    function param_array(string $clave, array $defecto = []): array
    {
        return \App\Helpers\ParametroHelper::getArray($clave, $defecto);
    }
}

if (!function_exists('param_config')) {
    /**
     * Helper global para obtener configuración de categoría
     */
    function param_config(string $categoria): object
    {
        return \App\Helpers\ParametroHelper::getConfig($categoria);
    }
}
