<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\RutaCorta;
use App\Models\AuditoriaLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteAvanzadoController extends Controller
{
    public function index()
    {
        return view('reportes-avanzados.index');
    }

    public function conductoresDetallado(Request $request)
    {
        $fechaInicio = Carbon::parse($request->get('fecha_inicio', now()->startOfMonth()));
        $fechaFin = Carbon::parse($request->get('fecha_fin', now()->endOfMonth()));

        $conductores = Conductor::with(['validaciones' => function($query) use ($fechaInicio, $fechaFin) {
            $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
        }])->get();

        $reporte = [
            'periodo' => $fechaInicio->format('d/m/Y') . ' - ' . $fechaFin->format('d/m/Y'),
            'total_conductores' => $conductores->count(),
            'disponibles' => $conductores->where('estado', 'DISPONIBLE')->count(),
            'en_descanso' => $conductores->where('estado', 'DESCANSO')->count(),
            'suspendidos' => $conductores->where('estado', 'SUSPENDIDO')->count(),
            'promedio_dias_acumulados' => $conductores->avg('dias_acumulados'),
            'conductores_criticos' => $conductores->where('dias_acumulados', '>=', 6)->count(),
            'validaciones_periodo' => Validacion::whereBetween('created_at', [$fechaInicio, $fechaFin])->count(),
            'conductores' => $conductores
        ];

        if ($request->get('formato') === 'pdf') {
            $pdf = PDF::loadView('reportes-avanzados.conductores-pdf', $reporte);
            return $pdf->download('reporte-conductores-detallado.pdf');
        }

        return view('reportes-avanzados.conductores', $reporte);
    }

    public function validacionesAnalisis(Request $request)
    {
        $fechaInicio = Carbon::parse($request->get('fecha_inicio', now()->startOfMonth()));
        $fechaFin = Carbon::parse($request->get('fecha_fin', now()->endOfMonth()));

        $validaciones = Validacion::with('conductor')
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->get();

        $reporte = [
            'periodo' => $fechaInicio->format('d/m/Y') . ' - ' . $fechaFin->format('d/m/Y'),
            'total_validaciones' => $validaciones->count(),
            'por_tipo' => $validaciones->groupBy('tipo')->map->count(),
            'por_severidad' => $validaciones->groupBy('severidad')->map->count(),
            'por_estado' => $validaciones->groupBy('estado')->map->count(),
            'tiempo_promedio_resolucion' => $this->calcularTiempoPromedioResolucion($validaciones),
            'tendencia_diaria' => $this->calcularTendenciaDiaria($validaciones, $fechaInicio, $fechaFin),
            'validaciones' => $validaciones
        ];

        return view('reportes-avanzados.validaciones', $reporte);
    }

    public function eficienciaOperacional(Request $request)
    {
        $fechaInicio = Carbon::parse($request->get('fecha_inicio', now()->startOfMonth()));
        $fechaFin = Carbon::parse($request->get('fecha_fin', now()->endOfMonth()));

        $rutasCortas = RutaCorta::whereBetween('created_at', [$fechaInicio, $fechaFin])->get();
        $conductores = Conductor::all();

        $reporte = [
            'periodo' => $fechaInicio->format('d/m/Y') . ' - ' . $fechaFin->format('d/m/Y'),
            'kpis' => [
                'cobertura_turnos' => $this->calcularCoberturaTurnos(),
                'eficiencia_conductores' => $conductores->avg('eficiencia'),
                'puntualidad_promedio' => $conductores->avg('puntualidad'),
                'ingresos_rutas_cortas' => $rutasCortas->sum('ingreso_estimado'),
                'tiempo_promedio_ruta' => $rutasCortas->avg('duracion_horas')
            ],
            'tendencias' => $this->calcularTendenciasKPI($fechaInicio, $fechaFin),
            'conductores_top' => $conductores->sortByDesc(function($c) {
                return ($c->eficiencia + $c->puntualidad) / 2;
            })->take(10),
            'rutas_mas_eficientes' => $rutasCortas->sortByDesc('ingreso_estimado')->take(10)
        ];

        return view('reportes-avanzados.eficiencia', $reporte);
    }

    public function auditoriaActividad(Request $request)
    {
        $fechaInicio = Carbon::parse($request->get('fecha_inicio', now()->startOfWeek()));
        $fechaFin = Carbon::parse($request->get('fecha_fin', now()->endOfWeek()));

        $logs = AuditoriaLog::whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->latest()
            ->paginate(50);

        $resumen = [
            'periodo' => $fechaInicio->format('d/m/Y') . ' - ' . $fechaFin->format('d/m/Y'),
            'total_acciones' => $logs->total(),
            'por_accion' => AuditoriaLog::whereBetween('created_at', [$fechaInicio, $fechaFin])
                ->groupBy('accion')->selectRaw('accion, count(*) as total')->pluck('total', 'accion'),
            'por_modelo' => AuditoriaLog::whereBetween('created_at', [$fechaInicio, $fechaFin])
                ->groupBy('modelo')->selectRaw('modelo, count(*) as total')->pluck('total', 'modelo'),
            'actividad_por_hora' => $this->calcularActividadPorHora($fechaInicio, $fechaFin)
        ];

        return view('reportes-avanzados.auditoria', compact('logs', 'resumen'));
    }

    // Métodos auxiliares privados
    private function calcularTiempoPromedioResolucion($validaciones)
    {
        $resueltas = $validaciones->whereNotNull('fecha_resolucion');
        if ($resueltas->isEmpty()) return 0;

        $tiempos = $resueltas->map(function($v) {
            return $v->created_at->diffInHours($v->fecha_resolucion);
        });

        return round($tiempos->avg(), 2);
    }

    private function calcularTendenciaDiaria($validaciones, $fechaInicio, $fechaFin)
    {
        $tendencia = [];
        $current = $fechaInicio->copy();

        while ($current <= $fechaFin) {
            $count = $validaciones->where('created_at', '>=', $current->startOfDay())
                                 ->where('created_at', '<=', $current->endOfDay())
                                 ->count();

            $tendencia[$current->format('Y-m-d')] = $count;
            $current->addDay();
        }

        return $tendencia;
    }

    private function calcularCoberturaTurnos()
    {
        // Lógica simplificada - puedes expandir según tu modelo de turnos
        $turnosAsignados = Conductor::where('estado', 'DISPONIBLE')->count();
        $turnosRequeridos = 100; // Este valor debería venir de tu lógica de negocio

        return $turnosRequeridos > 0 ? ($turnosAsignados / $turnosRequeridos) * 100 : 0;
    }

    private function calcularTendenciasKPI($fechaInicio, $fechaFin)
    {
        // Implementar lógica de tendencias basada en métricas históricas
        return [
            'cobertura' => ['anterior' => 85.2, 'actual' => 87.5],
            'eficiencia' => ['anterior' => 82.1, 'actual' => 84.3],
            'puntualidad' => ['anterior' => 91.8, 'actual' => 93.2]
        ];
    }

    private function calcularActividadPorHora($fechaInicio, $fechaFin)
    {
        $actividad = [];
        for ($hora = 0; $hora < 24; $hora++) {
            $count = AuditoriaLog::whereBetween('created_at', [$fechaInicio, $fechaFin])
                ->whereRaw('HOUR(created_at) = ?', [$hora])
                ->count();
            $actividad[$hora] = $count;
        }
        return $actividad;
    }
}
