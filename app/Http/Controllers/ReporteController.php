<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\Plantilla;

class ReporteController extends Controller
{
    public function index()
    {
        $metricas = [
            'conductores_total' => Conductor::count(),
            'conductores_activos' => Conductor::where('estado', 'DISPONIBLE')->count(),
            'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'plantillas_activas' => Plantilla::where('estado', 'ACTIVA')->count()
        ];

        return view('reportes.index', compact('metricas'));
    }
}
