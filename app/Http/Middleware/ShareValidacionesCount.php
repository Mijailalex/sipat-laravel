<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Validacion;
use App\Models\Conductor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;

class ShareValidacionesCount
{
    public function handle(Request $request, Closure $next)
    {
        // Solo ejecutar para rutas web
        if ($request->route() && !$request->is('api/*')) {

            // Usar cache para evitar consultas repetidas
            $validacionesData = Cache::remember('navbar_validaciones_data', 300, function () {
                return [
                    'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
                    'validaciones_criticas' => Validacion::where('estado', 'PENDIENTE')
                        ->where('severidad', 'CRITICA')->count(),
                    'conductores_criticos' => Conductor::where('dias_acumulados', '>=', 6)
                        ->where('estado', 'DISPONIBLE')->count(),
                    'conductores_baja_eficiencia' => Conductor::where('eficiencia', '<', 80)
                        ->where('estado', 'DISPONIBLE')->count()
                ];
            });

            // Compartir con todas las vistas
            View::share('navbar_data', $validacionesData);
        }

        return $next($request);
    }
}
