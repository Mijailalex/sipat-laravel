<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\ParametroHelper;
use Symfony\Component\HttpFoundation\Response;

class ParametroMiddleware
{
    public function handle(Request $request, Closure $next, string $verificacion = null): Response
    {
        switch ($verificacion) {
            case 'mantenimiento':
                if (ParametroHelper::getBool('modo_mantenimiento', false)) {
                    return response()->view('maintenance', [], 503);
                }
                break;

            case 'validaciones_activas':
                if (!ParametroHelper::getBool('validacion_automatica', true)) {
                    return redirect()->route('dashboard')
                                   ->with('warning', 'Las validaciones automáticas están desactivadas.');
                }
                break;

            case 'notificaciones_email':
                if (!ParametroHelper::getBool('activar_notificaciones_email', true)) {
                    $request->merge(['skip_email_notifications' => true]);
                }
                break;
        }

        return $next($request);
    }
}
