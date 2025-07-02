<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\Plantilla;
use App\Models\MetricaDiaria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Métricas principales
        $metricas = $this->calcularMetricas();

        // Conductores por estado
        $conductoresPorEstado = $this->obtenerConductoresPorEstado();

        // Validaciones pendientes
        $validacionesPendientes = Validacion::with('conductor')
            ->pendientes()
            ->latest()
            ->take(5)
            ->get();

        // Conductores destacados
        $conductoresDestacados = Conductor::select('*')
            ->selectRaw('((puntualidad + eficiencia) / 2) as score_general')
            ->orderByDesc('score_general')
            ->take(5)
            ->get();

        // Tendencias semanales
        $tendenciasSemanales = $this->obtenerTendenciasSemanales();

        // Conductores críticos (6+ días trabajados)
        $conductoresCriticos = Conductor::criticos()->count();

        return view('dashboard.index', compact(
            'metricas',
            'conductoresPorEstado',
            'validacionesPendientes',
            'conductoresDestacados',
            'tendenciasSemanales',
            'conductoresCriticos'
        ));
    }

private function calcularMetricas()
{
    $totalConductores = Conductor::count();
    $conductoresActivos = Conductor::where('estado', 'DISPONIBLE')->count();
    $validacionesPendientes = Validacion::where('estado', 'PENDIENTE')->count();
    $validacionesCriticas = Validacion::where('estado', 'PENDIENTE')
                                     ->where('severidad', 'CRITICA')
                                     ->count();

    // Calcular puntualidad promedio
    $puntualidadPromedio = Conductor::avg('puntualidad') ?? 0;

    // Calcular eficiencia promedio
    $eficienciaPromedio = Conductor::avg('eficiencia') ?? 0;

    // Simular cobertura de turnos (en producción vendría de cálculos reales)
    $coberturaTurnos = 98.5;

    return [
        'total_conductores' => $totalConductores,
        'conductores_activos' => $conductoresActivos,
        'validaciones_pendientes' => $validacionesPendientes,
        'validaciones_criticas' => $validacionesCriticas,
        'puntualidad_promedio' => round($puntualidadPromedio, 1),
        'eficiencia_promedio' => round($eficienciaPromedio, 1),
        'cobertura_turnos' => $coberturaTurnos,
        'cumplimiento_regimen' => 94.2,
        'eficiencia_asignacion' => 96.8,
    ];
}

    private function obtenerConductoresPorEstado()
    {
        return Conductor::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();
    }

    private function obtenerTendenciasSemanales()
    {
        // Simular datos semanales (en producción vendría de métricas_diarias)
        $dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $cumplimiento = [95, 97, 94, 98, 96, 99, 97];
        $eficiencia = [92, 94, 91, 96, 93, 97, 95];

        return [
            'dias' => $dias,
            'cumplimiento' => $cumplimiento,
            'eficiencia' => $eficiencia
        ];
    }

    public function getChartData()
    {
        // API endpoint para gráficos dinámicos
        $data = [
            'conductores_por_estado' => $this->obtenerConductoresPorEstado(),
            'tendencias' => $this->obtenerTendenciasSemanales(),
            'metricas' => $this->calcularMetricas()
        ];

        return response()->json($data);
    }
}
