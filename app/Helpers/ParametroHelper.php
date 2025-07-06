<?php

if (!function_exists('param')) {
    /**
     * Obtener valor de parámetro del sistema
     *
     * @param string $clave
     * @param mixed $default
     * @return mixed
     */
    function param($clave, $default = null)
    {
        return \App\Models\Parametro::obtenerValor($clave, $default);
    }
}

if (!function_exists('param_bool')) {
    /**
     * Obtener valor booleano de parámetro
     *
     * @param string $clave
     * @param bool $default
     * @return bool
     */
    function param_bool($clave, $default = false)
    {
        $valor = param($clave, $default);
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('param_int')) {
    /**
     * Obtener valor entero de parámetro
     *
     * @param string $clave
     * @param int $default
     * @return int
     */
    function param_int($clave, $default = 0)
    {
        $valor = param($clave, $default);
        return (int) $valor;
    }
}

if (!function_exists('param_float')) {
    /**
     * Obtener valor decimal de parámetro
     *
     * @param string $clave
     * @param float $default
     * @return float
     */
    function param_float($clave, $default = 0.0)
    {
        $valor = param($clave, $default);
        return (float) $valor;
    }
}

if (!function_exists('param_array')) {
    /**
     * Obtener valor array de parámetro
     *
     * @param string $clave
     * @param array $default
     * @return array
     */
    function param_array($clave, $default = [])
    {
        $valor = param($clave, $default);
        if (is_array($valor)) {
            return $valor;
        }

        $decoded = json_decode($valor, true);
        return $decoded ?: $default;
    }
}

if (!function_exists('param_string')) {
    /**
     * Obtener valor string de parámetro
     *
     * @param string $clave
     * @param string $default
     * @return string
     */
    function param_string($clave, $default = '')
    {
        $valor = param($clave, $default);
        return (string) $valor;
    }
}

if (!function_exists('sipat_empresa')) {
    /**
     * Obtener nombre de la empresa
     *
     * @return string
     */
    function sipat_empresa()
    {
        return param_string('nombre_empresa', 'SIPAT Transport');
    }
}

if (!function_exists('sipat_zona_horaria')) {
    /**
     * Obtener zona horaria del sistema
     *
     * @return string
     */
    function sipat_zona_horaria()
    {
        return param_string('zona_horaria', 'America/Lima');
    }
}

if (!function_exists('sipat_max_dias_validacion')) {
    /**
     * Obtener máximo de días para validación
     *
     * @return int
     */
    function sipat_max_dias_validacion()
    {
        return param_int('max_dias_validacion', 7);
    }
}

if (!function_exists('sipat_validacion_automatica')) {
    /**
     * Verificar si la validación automática está activa
     *
     * @return bool
     */
    function sipat_validacion_automatica()
    {
        return param_bool('validacion_automatica', true);
    }
}

if (!function_exists('sipat_items_por_pagina')) {
    /**
     * Obtener número de items por página
     *
     * @return int
     */
    function sipat_items_por_pagina()
    {
        return param_int('items_por_pagina', 20);
    }
}

class ParametroHelper
{
    /**
     * Obtener valor de parámetro (método estático)
     */
    public static function get($clave, $default = null)
    {
        return param($clave, $default);
    }

    /**
     * Obtener valor booleano
     */
    public static function getBool($clave, $default = false)
    {
        return param_bool($clave, $default);
    }

    /**
     * Obtener valor entero
     */
    public static function getInt($clave, $default = 0)
    {
        return param_int($clave, $default);
    }

    /**
     * Obtener valor decimal
     */
    public static function getFloat($clave, $default = 0.0)
    {
        return param_float($clave, $default);
    }

    /**
     * Obtener valor array
     */
    public static function getArray($clave, $default = [])
    {
        return param_array($clave, $default);
    }

    /**
     * Obtener valor string
     */
    public static function getString($clave, $default = '')
    {
        return param_string($clave, $default);
    }

    /**
     * Establecer valor de parámetro
     */
    public static function set($clave, $valor, $userId = null)
    {
        return \App\Models\Parametro::establecerValor($clave, $valor, $userId);
    }

    /**
     * Obtener configuración por categoría
     */
    public static function porCategoria($categoria)
    {
        return \App\Models\Parametro::categoria($categoria)->visibles()->ordenados()->get();
    }

    /**
     * Verificar si un parámetro existe
     */
    public static function existe($clave)
    {
        return \App\Models\Parametro::where('clave', $clave)->exists();
    }

    /**
     * Limpiar caché de parámetros
     */
    public static function limpiarCache($clave = null)
    {
        if ($clave) {
            \Illuminate\Support\Facades\Cache::forget("parametro_{$clave}");
        } else {
            \Illuminate\Support\Facades\Cache::flush();
        }
    }
}
