<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\RutaCorta;
use App\Models\Notificacion;
use App\Models\MetricaDiaria;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Métricas principales
        $metricas = $this->calcularMetricasPrincipales();

        // Notificaciones recientes
        $notificaciones = Notificacion::noLeidas()->latest()->take(5)->get();

        // Validaciones críticas
        $validacionesCriticas = Validacion::where('severidad', 'CRITICA')
            ->where('estado', 'PENDIENTE')
            ->latest()
            ->take(10)
            ->get();

        // Tendencias semanales
        $tendencias = $this->calcularTendenciasSemanales();

        // Conductores que requieren atención
        $conductoresAtencion = Conductor::where('dias_acumulados', '>=', 5)
            ->orWhere('eficiencia', '<', 80)
            ->orWhere('puntualidad', '<', 85)
            ->latest()
            ->take(10)
            ->get();

        // Rutas cortas del día
        $rutasHoy = RutaCorta::whereDate('created_at', today())
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'metricas',
            'notificaciones',
            'validacionesCriticas',
            'tendencias',
            'conductoresAtencion',
            'rutasHoy'
        ));
    }

    public function obtenerMetricasEnTiempoReal()
    {
        $metricas = $this->calcularMetricasPrincipales();

        return response()->json([
            'metricas' => $metricas,
            'timestamp' => now()->format('H:i:s'),
            'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'notificaciones_nuevas' => Notificacion::noLeidas()->count()
        ]);
    }

    private function calcularMetricasPrincipales()
    {
        $totalConductores = Conductor::count();
        $conductoresDisponibles = Conductor::where('estado', 'DISPONIBLE')->count();

        return [
            'total_conductores' => $totalConductores,
            'conductores_disponibles' => $conductoresDisponibles,
            'conductores_descanso' => Conductor::where('estado', 'DESCANSO')->count(),
            'conductores_criticos' => Conductor::where('dias_acumulados', '>=', 6)->count(),
            'cobertura_turnos' => $totalConductores > 0 ? ($conductoresDisponibles / $totalConductores) * 100 : 0,
            'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'validaciones_criticas' => Validacion::where('severidad', 'CRITICA')->where('estado', 'PENDIENTE')->count(),
            'rutas_cortas_hoy' => RutaCorta::whereDate('created_at', today())->count(),
            'ingresos_estimados_hoy' => RutaCorta::whereDate('created_at', today())->sum('ingreso_estimado'),
            'eficiencia_promedio' => Conductor::avg('eficiencia') ?? 0,
            'puntualidad_promedio' => Conductor::avg('puntualidad') ?? 0
        ];
    }

    private function calcularTendenciasSemanales()
    {
        $datos = [];
        $fechas = [];

        for ($i = 6; $i >= 0; $i--) {
            $fecha = Carbon::now()->subDays($i);
            $fechas[] = $fecha->format('d/m');

            $datos['validaciones'][] = Validacion::whereDate('created_at', $fecha)->count();
            $datos['conductores_activos'][] = Conductor::where('estado', 'DISPONIBLE')->count();
            $datos['rutas_cortas'][] = RutaCorta::whereDate('created_at', $fecha)->count();
        }

        return [
            'labels' => $fechas,
            'datasets' => $datos
        ];
    }
}
