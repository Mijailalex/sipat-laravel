<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index()
    {
        $notificaciones = Notificacion::latest()
            ->recientes()
            ->paginate(20);

        $noLeidas = Notificacion::noLeidas()->count();

        return view('notificaciones.index', compact('notificaciones', 'noLeidas'));
    }

    public function marcarLeida(Notificacion $notificacion)
    {
        $notificacion->marcarComoLeida();

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída'
        ]);
    }

    public function marcarTodasLeidas()
    {
        Notificacion::noLeidas()->update(['leida_en' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las notificaciones marcadas como leídas'
        ]);
    }

    public function obtenerNoLeidas()
    {
        $notificaciones = Notificacion::noLeidas()
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'notificaciones' => $notificaciones,
            'total' => Notificacion::noLeidas()->count()
        ]);
    }
}
