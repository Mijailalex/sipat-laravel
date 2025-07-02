<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Validacion;

class ShareValidacionesCount
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ IMPORTANTE: Agregar ->count() al final
        $validacionesPendientes = Validacion::where('estado', 'PENDIENTE')->count();

        view()->share('validacionesPendientes', $validacionesPendientes);

        return $next($request);
    }
}
