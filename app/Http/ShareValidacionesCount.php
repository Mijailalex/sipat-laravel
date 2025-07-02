<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Validacion;

class ShareValidacionesCount
{
    public function handle(Request $request, Closure $next)
    {
        // Compartir el número de validaciones pendientes con todas las vistas
        $validacionesPendientes = Validacion::pendientes()->count();

        view()->share('validacionesPendientes', $validacionesPendientes);

        return $next($request);
    }
}
