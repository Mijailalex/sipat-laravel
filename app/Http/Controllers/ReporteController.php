<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\RutaCorta;
use App\Models\Validacion;
use App\Models\MetricaDiaria;
use App\Models\BalanceRutasCortas;
use App\Models\SubempresaAsignacion;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    public function index()
    {
        return view('reportes.index');
    }

    public function conductores(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'nullable|string',
            'subempresa' => 'nullable|string',
            'formato' => 'nullable|in:html,pdf,excel'
        ]);

        $query = Conductor::with(['rutasCortas', 'validaciones', 'turnos']);

        // Filtros
        if ($validated['estado']) {
            $query->where('estado', $validated['estado']);
        }

        if ($validated['subempresa']) {
            $query->where('subempresa', $validated['subempresa']);
        }

        $conductores = $query->get();

        // Calcular métricas para cada conductor en el período
        $reporte = $conductores->map(function ($conductor) use ($validated) {
            $rutasPeriodo = $conductor->rutasCortas()
                ->whereBetween('fecha', [$validated['fecha_inicio'], $validated['fecha_fin']])
                ->get();

            $turnosPeriodo = $conductor->turnos()
                ->whereBetween('fecha_turno', [$validated['fecha_inicio'], $validated['fecha_fin']])
                ->get();

            $validacionesPeriodo = $conductor->validaciones()
                ->whereBetween('created_at', [$validated['fecha_inicio'], $validated['fecha_fin']])
                ->get();

            return [
                'conductor' => $conductor,
                'metricas' => [
                    'rutas_total' => $rutasPeriodo->count(),
                    'rutas_completadas' => $rutasPeriodo->where('estado', 'COMPLETADA')->count(),
                    'eficiencia_rutas' => $rutasPeriodo->count() > 0 ?
                        round(($rutasPeriodo->where('estado', 'COMPLETADA')->count() / $rutasPeriodo->count()) * 100, 2) : 0,
                    'turnos_total' => $turnosPeriodo->count(),
                    'turnos_completados' => $turnosPeriodo->where('estado', 'COMPLETADO')->count(),
                    'validaciones_generadas' => $validacionesPeriodo->count(),
                    'validaciones_criticas' => $validacionesPeriodo->where('severidad', 'CRITICA')->count(),
                    'total_pasajeros' => $rutasPeriodo->where('estado', 'COMPLETADA')->sum('pasajeros_transportados'),
                    'total_ingresos' => $rutasPeriodo->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
                    'promedio_calificacion' => $rutasPeriodo->where('estado', 'COMPLETADA')
                        ->whereNotNull('calificacion_servicio')->avg('calificacion_servicio') ?: 0
                ]
            ];
        });

        $formato = $validated['formato'] ?? 'html';

        switch ($formato) {
            case 'pdf':
                return $this->generarPDFConductores($reporte, $validated);
            case 'excel':
                return $this->generarExcelConductores($reporte, $validated);
            default:
                return view('reportes.conductores', compact('reporte', 'validated'));
        }
    }

    public function rutasCortas(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'tramo' => 'nullable|string',
            'conductor_id' => 'nullable|exists:conductores,id',
            'formato' => 'nullable|in:html,pdf,excel'
        ]);

        $query = RutaCorta::with(['conductor', 'bus', 'configuracionTramo']);

        // Filtros
        $query->whereBetween('fecha', [$validated['fecha_inicio'], $validated['fecha_fin']]);

        if ($validated['tramo']) {
            $query->where('tramo', $validated['tramo']);
        }

        if ($validated['conductor_id']) {
            $query->where('conductor_id', $validated['conductor_id']);
        }

        $rutas = $query->orderBy('fecha', 'desc')->get();

        // Estadísticas generales
        $estadisticas = [
            'total_rutas' => $rutas->count(),
            'completadas' => $rutas->where('estado', 'COMPLETADA')->count(),
            'canceladas' => $rutas->where('estado', 'CANCELADA')->count(),
            'en_curso' => $rutas->where('estado', 'EN_CURSO')->count(),
            'total_pasajeros' => $rutas->where('estado', 'COMPLETADA')->sum('pasajeros_transportados'),
            'total_ingresos' => $rutas->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
            'promedio_pasajeros' => $rutas->where('estado', 'COMPLETADA')->avg('pasajeros_transportados') ?: 0,
            'promedio_ingresos' => $rutas->where('estado', 'COMPLETADA')->avg('ingreso_estimado') ?: 0,
            'eficiencia_general' => $rutas->count() > 0 ?
                round(($rutas->where('estado', 'COMPLETADA')->count() / $rutas->count()) * 100, 2) : 0
        ];

        $formato = $validated['formato'] ?? 'html';

        switch ($formato) {
            case 'pdf':
                return $this->generarPDFRutas($rutas, $estadisticas, $validated);
            case 'excel':
                return $this->generarExcelRutas($rutas, $estadisticas, $validated);
            default:
                return view('reportes.rutas-cortas', compact('rutas', 'estadisticas', 'validated'));
        }
    }

    public function validaciones(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'severidad' => 'nullable|in:INFO,ADVERTENCIA,CRITICA',
            'tipo' => 'nullable|string',
            'estado' => 'nullable|in:PENDIENTE,EN_PROCESO,RESUELTO,OMITIDO',
            'formato' => 'nullable|in:html,pdf,excel'
        ]);

        $query = Validacion::with(['conductor', 'resueltoBy']);

        // Filtros
        $query->whereBetween('created_at', [$validated['fecha_inicio'], $validated['fecha_fin'] . ' 23:59:59']);

        if ($validated['severidad']) {
            $query->where('severidad', $validated['severidad']);
        }

        if ($validated['tipo']) {
            $query->where('tipo', $validated['tipo']);
        }

        if ($validated['estado']) {
            $query->where('estado', $validated['estado']);
        }

        $validaciones = $query->orderBy('created_at', 'desc')->get();

        // Estadísticas
        $estadisticas = [
            'total' => $validaciones->count(),
            'pendientes' => $validaciones->where('estado', 'PENDIENTE')->count(),
            'resueltas' => $validaciones->where('estado', 'RESUELTO')->count(),
            'criticas' => $validaciones->where('severidad', 'CRITICA')->count(),
            'por_tipo' => $validaciones->groupBy('tipo')->map->count(),
            'tiempo_promedio_resolucion' => $validaciones->where('estado', 'RESUELTO')
                ->avg(function ($v) {
                    return $v->created_at->diffInHours($v->fecha_resolucion);
                }) ?: 0
        ];

        $formato = $validated['formato'] ?? 'html';

        return view('reportes.validaciones', compact('validaciones', 'estadisticas', 'validated'));
    }

    public function metricasDiarias(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        $metricas = MetricaDiaria::whereBetween('fecha', [$validated['fecha_inicio'], $validated['fecha_fin']])
            ->orderBy('fecha')
            ->get();

        // Calcular tendencias
        $tendencias = [
            'disponibilidad_promedio' => $metricas->avg('porcentaje_disponibilidad'),
            'eficiencia_promedio' => $metricas->avg('eficiencia_promedio'),
            'puntualidad_promedio' => $metricas->avg('puntualidad_promedio'),
            'ingresos_totales' => $metricas->sum('ingresos_estimados_rutas'),
            'validaciones_criticas_promedio' => $metricas->avg('validaciones_criticas')
        ];

        return view('reportes.metricas-diarias', compact('metricas', 'tendencias', 'validated'));
    }

    public function balanceTramos(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio'
        ]);

        $balance = BalanceRutasCortas::with('configuracionTramo')
            ->whereBetween('fecha', [$validated['fecha_inicio'], $validated['fecha_fin']])
            ->get();

        // Agrupar por tramo y calcular totales
        $resumen = $balance->groupBy('tramo')->map(function ($grupo) {
            return [
                'tramo' => $grupo->first()->tramo,
                'configuracion' => $grupo->first()->configuracionTramo,
                'total_rutas' => $grupo->sum('total_rutas'),
                'rutas_completadas' => $grupo->sum('rutas_completadas'),
                'rutas_canceladas' => $grupo->sum('rutas_canceladas'),
                'total_pasajeros' => $grupo->sum('total_pasajeros'),
                'ingreso_total' => $grupo->sum('ingreso_total'),
                'ocupacion_promedio' => $grupo->avg('ocupacion_promedio'),
                'eficiencia_promedio' => $grupo->avg('eficiencia_promedio'),
                'dias_operacion' => $grupo->count(),
                'porcentaje_exito' => $grupo->sum('total_rutas') > 0 ?
                    round(($grupo->sum('rutas_completadas') / $grupo->sum('total_rutas')) * 100, 2) : 0
            ];
        })->sortByDesc('ingreso_total');

        return view('reportes.balance-tramos', compact('resumen', 'validated'));
    }

    public function subempresas(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'subempresa' => 'nullable|string'
        ]);

        $query = SubempresaAsignacion::with(['frecuencia', 'conductor', 'bus']);

        $query->whereBetween('fecha_asignacion', [$validated['fecha_inicio'], $validated['fecha_fin']]);

        if ($validated['subempresa']) {
            $query->subempresa($validated['subempresa']);
        }

        $asignaciones = $query->get();

        // Generar reporte por subempresa
        $reporte = $asignaciones->groupBy('frecuencia.nombre_subempresa')->map(function ($grupo, $subempresa) {
            $completadas = $grupo->where('estado', 'COMPLETADO');

            return [
                'nombre' => $subempresa,
                'total_asignaciones' => $grupo->count(),
                'completadas' => $completadas->count(),
                'canceladas' => $grupo->where('estado', 'CANCELADO')->count(),
                'eficiencia' => $grupo->count() > 0 ?
                    round(($completadas->count() / $grupo->count()) * 100, 2) : 0,
                'total_pasajeros' => $completadas->sum('pasajeros_transportados'),
                'total_ingresos' => $completadas->sum('ingresos_generados'),
                'conductores_diferentes' => $grupo->pluck('conductor_id')->unique()->count(),
                'frecuencias_diferentes' => $grupo->pluck('frecuencia_id')->unique()->count()
            ];
        })->sortByDesc('total_ingresos');

        return view('reportes.subempresas', compact('reporte', 'validated'));
    }

    public function dashboard(Request $request)
    {
        $dias = $request->get('dias', 30);

        // Resumen ejecutivo
        $resumen = [
            'conductores' => [
                'total' => Conductor::count(),
                'disponibles' => Conductor::disponibles()->count(),
                'criticos' => Conductor::criticos()->count(),
                'eficiencia_promedio' => Conductor::avg('eficiencia')
            ],
            'rutas_cortas' => [
                'total_periodo' => RutaCorta::where('fecha', '>=', now()->subDays($dias))->count(),
                'completadas_periodo' => RutaCorta::where('fecha', '>=', now()->subDays($dias))
                    ->where('estado', 'COMPLETADA')->count(),
                'ingresos_periodo' => RutaCorta::where('fecha', '>=', now()->subDays($dias))
                    ->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
                'pasajeros_periodo' => RutaCorta::where('fecha', '>=', now()->subDays($dias))
                    ->where('estado', 'COMPLETADA')->sum('pasajeros_transportados')
            ],
            'validaciones' => [
                'pendientes' => Validacion::pendientes()->count(),
                'criticas' => Validacion::criticas()->pendientes()->count(),
                'resueltas_periodo' => Validacion::where('fecha_resolucion', '>=', now()->subDays($dias))->count()
            ]
        ];

        // Tendencias
        $tendencias = MetricaDiaria::where('fecha', '>=', now()->subDays($dias))
            ->orderBy('fecha')
            ->get();

        // Top performers
        $topConductores = RutaCorta::obtenerRankingConductores($dias)->take(10);
        $topTramos = BalanceRutasCortas::obtenerRankingRentabilidad($dias)->take(10);

        return view('reportes.dashboard', compact(
            'resumen',
            'tendencias',
            'topConductores',
            'topTramos',
            'dias'
        ));
    }

    // Métodos para generar PDFs
    private function generarPDFConductores($reporte, $parametros)
    {
        $pdf = PDF::loadView('reportes.pdf.conductores', compact('reporte', 'parametros'));

        return $pdf->download('reporte_conductores_' . now()->format('Y-m-d') . '.pdf');
    }

    private function generarPDFRutas($rutas, $estadisticas, $parametros)
    {
        $pdf = PDF::loadView('reportes.pdf.rutas-cortas', compact('rutas', 'estadisticas', 'parametros'));

        return $pdf->download('reporte_rutas_cortas_' . now()->format('Y-m-d') . '.pdf');
    }

    // Métodos para generar Excel
    private function generarExcelConductores($reporte, $parametros)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_conductores_' . now()->format('Y-m-d') . '.csv"'
        ];

        $callback = function() use ($reporte) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Código', 'Nombre', 'Estado', 'Subempresa', 'Rutas Total', 'Rutas Completadas',
                'Eficiencia Rutas %', 'Turnos Total', 'Turnos Completados', 'Total Pasajeros',
                'Total Ingresos', 'Promedio Calificación', 'Validaciones Generadas', 'Validaciones Críticas'
            ]);

            foreach ($reporte as $item) {
                fputcsv($file, [
                    $item['conductor']->codigo_conductor,
                    $item['conductor']->nombre_completo,
                    $item['conductor']->estado,
                    $item['conductor']->subempresa ?: 'Sin asignar',
                    $item['metricas']['rutas_total'],
                    $item['metricas']['rutas_completadas'],
                    $item['metricas']['eficiencia_rutas'],
                    $item['metricas']['turnos_total'],
                    $item['metricas']['turnos_completados'],
                    $item['metricas']['total_pasajeros'],
                    number_format($item['metricas']['total_ingresos'], 2),
                    number_format($item['metricas']['promedio_calificacion'], 2),
                    $item['metricas']['validaciones_generadas'],
                    $item['metricas']['validaciones_criticas']
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function generarExcelRutas($rutas, $estadisticas, $parametros)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_rutas_cortas_' . now()->format('Y-m-d') . '.csv"'
        ];

        $callback = function() use ($rutas) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Fecha', 'Conductor', 'Bus', 'Tramo', 'Origen', 'Destino',
                'Hora Inicio', 'Hora Fin', 'Estado', 'Pasajeros', 'Ingresos',
                'Calificación', 'Duración (min)'
            ]);

            foreach ($rutas as $ruta) {
                fputcsv($file, [
                    $ruta->fecha->format('Y-m-d'),
                    $ruta->conductor ? $ruta->conductor->codigo_conductor : 'Sin conductor',
                    $ruta->bus ? $ruta->bus->numero_bus : 'Sin bus',
                    $ruta->tramo,
                    $ruta->origen,
                    $ruta->destino,
                    $ruta->hora_inicio ? $ruta->hora_inicio->format('H:i') : '',
                    $ruta->hora_fin ? $ruta->hora_fin->format('H:i') : '',
                    $ruta->estado,
                    $ruta->pasajeros_transportados ?: 0,
                    number_format($ruta->ingreso_estimado ?: 0, 2),
                    $ruta->calificacion_servicio ?: '',
                    $ruta->duracion_minutos ?: ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // API Methods
    public function apiResumenEjecutivo(Request $request)
    {
        $dias = $request->get('dias', 30);

        $resumen = [
            'conductores_activos' => Conductor::disponibles()->count(),
            'rutas_completadas_periodo' => RutaCorta::where('fecha', '>=', now()->subDays($dias))
                ->where('estado', 'COMPLETADA')->count(),
            'ingresos_periodo' => RutaCorta::where('fecha', '>=', now()->subDays($dias))
                ->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
            'validaciones_pendientes' => Validacion::pendientes()->count(),
            'eficiencia_sistema' => MetricaDiaria::where('fecha', '>=', now()->subDays(7))
                ->avg('eficiencia_promedio')
        ];

        return $this->successResponse($resumen);
    }

    public function apiTendenciasKPI(Request $request)
    {
        $dias = $request->get('dias', 30);

        $kpis = BalanceRutasCortas::obtenerIndicadoresKPI($dias);

        return $this->successResponse($kpis);
    }
}
