<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helpers\ParametroHelper;

class ParametroServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('parametro.helper', function ($app) {
            return new ParametroHelper();
        });

        $this->app->alias('parametro.helper', ParametroHelper::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\ParametrosCommand::class,
                \App\Console\Commands\SetupParametrosCommand::class,
            ]);
        }

        view()->composer('*', function ($view) {
            if (class_exists(\App\Models\Parametro::class)) {
                try {
                    $view->with([
                        'empresa_nombre' => ParametroHelper::getEmpresa(),
                        'items_por_pagina' => ParametroHelper::getItemsPorPagina(),
                        'formato_fecha' => ParametroHelper::getFormatoFecha(),
                    ]);
                } catch (\Exception $e) {
                    // Silenciar errores si la tabla no existe aún
                }
            }
        });
    }

    public function provides(): array
    {
        return ['parametro.helper'];
    }
}
