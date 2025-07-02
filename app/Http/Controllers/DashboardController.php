<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\RutaCorta;
use App\Models\Turno;
use App\Models\Bus;
use App\Models\MetricaDiaria;
use App\Models\BalanceRutasCortas;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Generar métricas del día si no existen
        MetricaDiaria::generarMetricasHoy();

        // Obtener métricas principales
        $metricas = $this->obtenerMetricasPrincipales();

        // Obtener datos para gráficos
        $tendencias = $this->obtenerTendencias();

        // Obtener alertas y notificaciones
        $alertas = $this->obtenerAlertas();

        // Obtener actividad reciente
        $actividadReciente = $this->obtenerActividadReciente();

        return view('dashboard.index', compact(
            'metricas',
            'tendencias',
            'alertas',
            'actividadReciente'
        ));
    }

    private function obtenerMetricasPrincipales()
    {
        $hoy = now()->toDateString();

        return [
            'conductores' => [
                'total' => Conductor::count(),
                'disponibles' => Conductor::where('estado', 'DISPONIBLE')->count(),
                'en_descanso' => Conductor::whereIn('estado', ['DESCANSO_FISICO', 'DESCANSO_SEMANAL'])->count(),
                'criticos' => Conductor::where('dias_acumulados', '>=', 6)->count(),
                'porcentaje_disponibles' => $this->calcularPorcentaje(
                    Conductor::where('estado', 'DISPONIBLE')->count(),
                    Conductor::count()
                )
            ],
            'validaciones' => [
                'pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
                'criticas' => Validacion::where('estado', 'PENDIENTE')->where('severidad', 'CRITICA')->count(),
                'resueltas_hoy' => Validacion::where('fecha_resolucion', '>=', now()->startOfDay())->count(),
                'porcentaje_criticas' => $this->calcularPorcentaje(
                    Validacion::where('estado', 'PENDIENTE')->where('severidad', 'CRITICA')->count(),
                    Validacion::where('estado', 'PENDIENTE')->count()
                )
            ],
            'rutas_cortas' => RutaCorta::obtenerEstadisticasHoy(),
            'turnos' => Turno::obtenerEstadisticasHoy(),
            'buses' => [
                'operativos' => Bus::where('estado', 'OPERATIVO')->count(),
                'mantenimiento' => Bus::where('estado', 'MANTENIMIENTO')->count(),
                'total' => Bus::count(),
                'porcentaje_operativo' => $this->calcularPorcentaje(
                    Bus::where('estado', 'OPERATIVO')->count(),
                    Bus::count()
                )
            ],
            'ingresos' => [
                'hoy' => RutaCorta::where('fecha', $hoy)->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
                'mes_actual' => RutaCorta::where('fecha', '>=', now()->startOfMonth())
                    ->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
                'promedio_diario' => $this->calcularPromedioDiarioIngresos()
            ]
        ];
    }

    private function obtenerTendencias()
    {
        // Tendencias de los últimos 7 días
        $dias = 7;

        return [
            'metricas_diarias' => MetricaDiaria::obtenerTendencias($dias),
            'validaciones' => Validacion::obtenerTendencias($dias),
            'rutas_cortas' => $this->obtenerTendenciasRutasCortas($dias),
            'eficiencia_conductores' => $this->obtenerTendenciasEficiencia($dias)
        ];
    }

    private function obtenerTendenciasRutasCortas($dias)
    {
        return RutaCorta::selectRaw('
                DATE(fecha) as fecha,
                COUNT(*) as total,
                SUM(CASE WHEN estado = "COMPLETADA" THEN 1 ELSE 0 END) as completadas,
                SUM(CASE WHEN estado = "COMPLETADA" THEN ingreso_estimado ELSE 0 END) as ingresos,
                SUM(CASE WHEN estado = "COMPLETADA" THEN pasajeros_transportados ELSE 0 END) as pasajeros
            ')
            ->where('fecha', '>=', now()->subDays($dias))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();
    }

    private function obtenerTendenciasEficiencia($dias)
    {
        return MetricaDiaria::selectRaw('
                fecha,
                eficiencia_promedio,
                puntualidad_promedio,
                conductores_disponibles,
                validaciones_criticas
            ')
            ->where('fecha', '>=', now()->subDays($dias))
            ->orderBy('fecha')
            ->get();
    }

    private function obtenerAlertas()
    {
        $alertas = [];

        // Conductores críticos
        $conductoresCriticos = Conductor::where('dias_acumulados', '>=', 6)->count();
        if ($conductoresCriticos > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'icono' => 'fa-exclamation-triangle',
                'titulo' => 'Conductores requieren descanso',
                'mensaje' => "{$conductoresCriticos} conductores necesitan descanso obligatorio",
                'enlace' => route('conductores.index', ['filtro' => 'criticos']),
                'prioridad' => 'alta'
            ];
        }

        // Validaciones críticas
        $validacionesCriticas = Validacion::where('estado', 'PENDIENTE')->where('severidad', 'CRITICA')->count();
        if ($validacionesCriticas > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'icono' => 'fa-clipboard-check',
                'titulo' => 'Validaciones críticas pendientes',
                'mensaje' => "{$validacionesCriticas} validaciones críticas requieren atención",
                'enlace' => route('validaciones.index', ['severidad' => 'CRITICA']),
                'prioridad' => 'alta'
            ];
        }

        // Buses en mantenimiento
        $busesMantenimiento = Bus::where('estado', 'MANTENIMIENTO')->count();
        if ($busesMantenimiento > 0) {
            $alertas[] = [
                'tipo' => 'info',
                'icono' => 'fa-tools',
                'titulo' => 'Buses en mantenimiento',
                'mensaje' => "{$busesMantenimiento} buses están en mantenimiento",
                'enlace' => '#',
                'prioridad' => 'media'
            ];
        }

        // Eficiencia baja del sistema
        $eficienciaPromedio = Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])->avg('eficiencia');
        if ($eficienciaPromedio < 80) {
            $alertas[] = [
                'tipo' => 'warning',
                'icono' => 'fa-chart-line',
                'titulo' => 'Eficiencia del sistema baja',
                'mensaje' => sprintf('Eficiencia promedio: %.1f%% (objetivo: 80%%)', $eficienciaPromedio),
                'enlace' => route('reportes.index'),
                'prioridad' => 'media'
            ];
        }

        // Ordenar por prioridad
        usort($alertas, function($a, $b) {
            $prioridades = ['alta' => 3, 'media' => 2, 'baja' => 1];
            return $prioridades[$b['prioridad']] - $prioridades[$a['prioridad']];
        });

        return array_slice($alertas, 0, 5); // Máximo 5 alertas
    }

    private function obtenerActividadReciente()
    {
        $actividades = [];

        // Últimas validaciones resueltas
        $validacionesRecientes = Validacion::where('estado', 'RESUELTO')
            ->where('fecha_resolucion', '>=', now()->subHours(24))
            ->with(['conductor:id,codigo_conductor,nombre', 'resueltoBy:id,name'])
            ->orderBy('fecha_resolucion', 'desc')
            ->limit(5)
            ->get();

        foreach ($validacionesRecientes as $validacion) {
            $actividades[] = [
                'tipo' => 'validacion_resuelta',
                'icono' => 'fa-check-circle',
                'clase' => 'success',
                'titulo' => 'Validación resuelta',
                'descripcion' => "Validación {$validacion->tipo} del conductor {$validacion->conductor->codigo_conductor}",
                'usuario' => $validacion->resueltoBy->name ?? 'Sistema',
                'fecha' => $validacion->fecha_resolucion,
                'hace' => $validacion->fecha_resolucion->diffForHumans()
            ];
        }

        // Últimas rutas completadas
        $rutasRecientes = RutaCorta::where('estado', 'COMPLETADA')
            ->where('updated_at', '>=', now()->subHours(6))
            ->with('conductor:id,codigo_conductor,nombre')
            ->orderBy('updated_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($rutasRecientes as $ruta) {
            $actividades[] = [
                'tipo' => 'ruta_completada',
                'icono' => 'fa-route',
                'clase' => 'info',
                'titulo' => 'Ruta completada',
                'descripcion' => "Ruta {$ruta->tramo} - {$ruta->pasajeros_transportados} pasajeros",
                'usuario' => $ruta->conductor->codigo_conductor ?? 'Sin conductor',
                'fecha' => $ruta->updated_at,
                'hace' => $ruta->updated_at->diffForHumans()
            ];
        }

        // Conductores enviados a descanso
        $descansosRecientes = Conductor::whereIn('estado', ['DESCANSO_FISICO', 'DESCANSO_SEMANAL'])
            ->where('fecha_ultimo_descanso', '>=', now()->subHours(24))
            ->orderBy('fecha_ultimo_descanso', 'desc')
            ->limit(3)
            ->get();

        foreach ($descansosRecientes as $conductor) {
            $actividades[] = [
                'tipo' => 'conductor_descanso',
                'icono' => 'fa-bed',
                'clase' => 'warning',
                'titulo' => 'Conductor a descanso',
                'descripcion' => "Conductor {$conductor->codigo_conductor} enviado a {$conductor->estado}",
                'usuario' => 'Sistema',
                'fecha' => $conductor->fecha_ultimo_descanso,
                'hace' => $conductor->fecha_ultimo_descanso->diffForHumans()
            ];
        }

        // Ordenar por fecha y devolver las 10 más recientes
        usort($actividades, function($a, $b) {
            return $b['fecha'] <=> $a['fecha'];
        });

        return array_slice($actividades, 0, 10);
    }

    private function calcularPorcentaje($numerador, $denominador)
    {
        return $denominador > 0 ? round(($numerador / $denominador) * 100, 1) : 0;
    }

    private function calcularPromedioDiarioIngresos()
    {
        return RutaCorta::where('fecha', '>=', now()->subDays(30))
            ->where('estado', 'COMPLETADA')
            ->selectRaw('DATE(fecha) as fecha, SUM(ingreso_estimado) as ingresos_dia')
            ->groupBy('fecha')
            ->avg('ingresos_dia') ?: 0;
    }

    public function obtenerDatosGraficos(Request $request)
    {
        $tipo = $request->get('tipo');
        $dias = $request->get('dias', 7);

        switch ($tipo) {
            case 'tendencias_ingresos':
                return $this->successResponse($this->obtenerGraficoIngresos($dias));

            case 'distribucion_conductores':
                return $this->successResponse($this->obtenerGraficoConductores());

            case 'eficiencia_semanal':
                return $this->successResponse($this->obtenerGraficoEficiencia($dias));

            case 'rutas_por_tramo':
                return $this->successResponse($this->obtenerGraficoRutasTramo($dias));

            default:
                return $this->errorResponse('Tipo de gráfico no válido');
        }
    }

    private function obtenerGraficoIngresos($dias)
    {
        $datos = RutaCorta::selectRaw('
                DATE(fecha) as fecha,
                SUM(CASE WHEN estado = "COMPLETADA" THEN ingreso_estimado ELSE 0 END) as ingresos,
                COUNT(CASE WHEN estado = "COMPLETADA" THEN 1 ELSE NULL END) as rutas_completadas
            ')
            ->where('fecha', '>=', now()->subDays($dias))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return [
        return [
            'labels' => $datos->pluck('fecha')->map(fn($fecha) => Carbon::parse($fecha)->format('d/m')),
            'datasets' => [
                [
                    'label' => 'Ingresos (S/.)',
                    'data' => $datos->pluck('ingresos'),
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Rutas Completadas',
                    'data' => $datos->pluck('rutas_completadas'),
                    'borderColor' => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'yAxisID' => 'y1'
                ]
            ]
        ];
    }

    private function obtenerGraficoConductores()
    {
        $estados = Conductor::selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->get();

        return [
            'labels' => $estados->pluck('estado'),
            'datasets' => [
                [
                    'data' => $estados->pluck('cantidad'),
                    'backgroundColor' => [
                        '#28a745', // DISPONIBLE
                        '#ffc107', // DESCANSO_FISICO
                        '#17a2b8', // DESCANSO_SEMANAL
                        '#6c757d', // VACACIONES
                        '#dc3545', // SUSPENDIDO
                        '#fd7e14', // FALTO_OPERATIVO
                        '#e83e8c'  // FALTO_NO_OPERATIVO
                    ]
                ]
            ]
        ];
    }

    private function obtenerGraficoEficiencia($dias)
    {
        $datos = MetricaDiaria::selectRaw('fecha, eficiencia_promedio, puntualidad_promedio')
            ->where('fecha', '>=', now()->subDays($dias))
            ->orderBy('fecha')
            ->get();

        return [
            'labels' => $datos->pluck('fecha')->map(fn($fecha) => Carbon::parse($fecha)->format('d/m')),
            'datasets' => [
                [
                    'label' => 'Eficiencia (%)',
                    'data' => $datos->pluck('eficiencia_promedio'),
                    'borderColor' => 'rgb(54, 162, 235)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)'
                ],
                [
                    'label' => 'Puntualidad (%)',
                    'data' => $datos->pluck('puntualidad_promedio'),
                    'borderColor' => 'rgb(255, 206, 86)',
                    'backgroundColor' => 'rgba(255, 206, 86, 0.2)'
                ]
            ]
        ];
    }

    private function obtenerGraficoRutasTramo($dias)
    {
        $datos = RutaCorta::selectRaw('tramo, COUNT(*) as total_rutas')
            ->where('fecha', '>=', now()->subDays($dias))
            ->where('estado', 'COMPLETADA')
            ->groupBy('tramo')
            ->orderBy('total_rutas', 'desc')
            ->limit(10)
            ->get();

        return [
            'labels' => $datos->pluck('tramo'),
            'datasets' => [
                [
                    'label' => 'Rutas Completadas',
                    'data' => $datos->pluck('total_rutas'),
                    'backgroundColor' => 'rgba(153, 102, 255, 0.6)',
                    'borderColor' => 'rgba(153, 102, 255, 1)',
                    'borderWidth' => 1
                ]
            ]
        ];
    }

    public function resumenRapido()
    {
        $hoy = now()->toDateString();

        return $this->successResponse([
            'conductores_disponibles' => Conductor::where('estado', 'DISPONIBLE')->count(),
            'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'rutas_hoy' => RutaCorta::where('fecha', $hoy)->count(),
            'ingresos_hoy' => RutaCorta::where('fecha', $hoy)->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
            'ultima_actualizacion' => now()->format('H:i:s')
        ]);
    }
}
