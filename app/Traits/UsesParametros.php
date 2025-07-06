<?php

namespace App\Traits;

use App\Helpers\ParametroHelper;

trait UsesParametros
{
    public function getParametro(string $clave, $defecto = null)
    {
        return ParametroHelper::get($clave, $defecto);
    }

    public function isParametroActivo(string $clave): bool
    {
        return ParametroHelper::getBool($clave, false);
    }

    public function getConfiguracion(string $categoria): object
    {
        return ParametroHelper::getConfig($categoria);
    }

    public function getParametrosRelacionados(): array
    {
        $modelName = strtoupper(class_basename(static::class));
        return ParametroHelper::getCategoria($modelName);
    }
}
