<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade para gestión de parámetros del sistema SIPAT
 *
 * @method static mixed get(string $clave, mixed $default = null)
 * @method static bool getBool(string $clave, bool $default = false)
 * @method static int getInt(string $clave, int $default = 0)
 * @method static float getFloat(string $clave, float $default = 0.0)
 * @method static array getArray(string $clave, array $default = [])
 * @method static string getString(string $clave, string $default = '')
 * @method static void set(string $clave, mixed $valor, int $userId = null)
 * @method static string getEmpresa()
 * @method static int getMaxDiasValidacion()
 * @method static bool getValidacionAutomatica()
 * @method static array getCategorias()
 * @method static array getConfiguracion()
 *
 * @see \App\Services\ParametroService
 */
class Parametro extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'parametro';
    }

    /**
     * Métodos de conveniencia que delegan al modelo directamente
     */
    public static function obtenerValor($clave, $default = null)
    {
        return \App\Models\Parametro::obtenerValor($clave, $default);
    }

    public static function establecerValor($clave, $valor, $userId = null)
    {
        return \App\Models\Parametro::establecerValor($clave, $valor, $userId);
    }

    public static function porCategoria($categoria)
    {
        return \App\Models\Parametro::categoria($categoria)->visibles()->ordenados()->get();
    }

    public static function obtenerCategorias()
    {
        return \App\Models\Parametro::obtenerCategorias();
    }

    // Métodos de conveniencia para tipos específicos
    public static function getBool($clave, $default = false)
    {
        $valor = static::obtenerValor($clave, $default);
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public static function getInt($clave, $default = 0)
    {
        $valor = static::obtenerValor($clave, $default);
        return (int) $valor;
    }

    public static function getFloat($clave, $default = 0.0)
    {
        $valor = static::obtenerValor($clave, $default);
        return (float) $valor;
    }

    public static function getArray($clave, $default = [])
    {
        $valor = static::obtenerValor($clave, $default);
        return is_array($valor) ? $valor : json_decode($valor, true) ?: $default;
    }

    public static function getString($clave, $default = '')
    {
        $valor = static::obtenerValor($clave, $default);
        return (string) $valor;
    }

    // Métodos de conveniencia para parámetros comunes del sistema
    public static function getEmpresa()
    {
        return static::getString('nombre_empresa', 'SIPAT Transport');
    }

    public static function getMaxDiasValidacion()
    {
        return static::getInt('max_dias_validacion', 7);
    }

    public static function getValidacionAutomatica()
    {
        return static::getBool('validacion_automatica', true);
    }

    public static function getItemsPorPagina()
    {
        return static::getInt('items_por_pagina', 20);
    }

    public static function getZonaHoraria()
    {
        return static::getString('zona_horaria', 'America/Lima');
    }
}
