<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Parametro;
use App\Observers\ParametroObserver;

class ParametroServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Bind singleton para gestión de parámetros
        $this->app->singleton('parametro', function ($app) {
            return new \App\Services\ParametroService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Registrar Observer para limpiar caché automáticamente
        if (class_exists(ParametroObserver::class)) {
            Parametro::observe(ParametroObserver::class);
        }

        // Cargar helpers personalizados si existe
        $helpersPath = app_path('Helpers/ParametroHelper.php');
        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }

        // Registrar macros útiles para parámetros
        $this->registerMacros();
    }

    /**
     * Registrar macros útiles
     */
    protected function registerMacros()
    {
        // Macro para obtener parámetros por categoría
        Parametro::macro('porCategoria', function ($categoria) {
            return Parametro::where('categoria', $categoria)
                           ->visibles()
                           ->ordenados()
                           ->get();
        });

        // Macro para configuración rápida
        Parametro::macro('configuracionRapida', function () {
            return Parametro::visibles()
                           ->ordenados()
                           ->get()
                           ->groupBy('categoria')
                           ->map(function ($parametros) {
                               return $parametros->pluck('valor', 'clave');
                           });
        });
    }
}
