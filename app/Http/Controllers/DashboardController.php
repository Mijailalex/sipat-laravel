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
        try {
            MetricaDiaria::generarMetricasHoy();
        } catch (\Exception $e) {
            // Si MetricaDiaria tiene problemas, continuar
        }

        // Obtener métricas principales
        $metricas = $this->obtenerMetricasPrincipales();

        // Obtener datos para gráficos
        $tendencias = $this->obtenerTendencias();

        // Obtener alertas y notificaciones
        $alertas = $this->obtenerAlertas();

        // Obtener actividad reciente
        $actividadReciente = $this->obtenerActividadReciente();

        // VARIABLES ADICIONALES QUE LA VISTA NECESITA
        $conductoresDestacados = $this->obtenerConductoresDestacados();
        $validacionesPendientes = $this->obtenerValidacionesPendientes();
        $conductoresCriticos = $this->contarConductoresCriticos();
        $conductoresPorEstado = $this->obtenerConductoresPorEstado(); // ← Variable faltante para gráficos JS
        $tendenciasSemanales = $this->obtenerTendenciasSemanales(); // ← Nombre corregido y método corregido

        return view('dashboard.index', compact(
            'metricas',
            'tendencias',
            'alertas',
            'actividadReciente',
            'conductoresDestacados',
            'validacionesPendientes',
            'conductoresCriticos',
            'conductoresPorEstado',
            'tendenciasSemanales'
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
            // LÓGICA ORIGINAL RESTAURADA - Métricas de rutas cortas
            'rutas_cortas' => $this->obtenerEstadisticasRutasCortas(),
            // LÓGICA ORIGINAL RESTAURADA - Métricas de turnos
            'turnos' => $this->obtenerEstadisticasTurnos(),
            // LÓGICA ORIGINAL RESTAURADA - Métricas de buses
            'buses' => [
                'operativos' => $this->contarBusesOperativos(),
                'mantenimiento' => $this->contarBusesMantenimiento(),
                'total' => $this->contarBusesTotal(),
                'porcentaje_operativo' => $this->calcularPorcentaje(
                    $this->contarBusesOperativos(),
                    $this->contarBusesTotal()
                )
            ],
            // LÓGICA ORIGINAL RESTAURADA - Métricas de ingresos
            'ingresos' => [
                'hoy' => $this->calcularIngresosHoy($hoy),
                'mes_actual' => $this->calcularIngresosMesActual(),
                'promedio_diario' => $this->calcularPromedioDiarioIngresos()
            ],
            // MÉTRICAS ADICIONALES PARA LA VISTA
            'cobertura_turnos' => $this->calcularCoberturaTurnos(),
            'conductores_activos' => Conductor::where('estado', 'DISPONIBLE')->count(),
            'puntualidad_promedio' => round($this->calcularPuntualidadPromedio(), 1),
            'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'eficiencia_promedio' => round($this->calcularEficienciaPromedio(), 1)
        ];
    }

    private function obtenerTendencias()
    {
        // LÓGICA ORIGINAL RESTAURADA - Tendencias de los últimos 7 días
        $dias = 7;

        return [
            'metricas_diarias' => $this->obtenerTendenciasMetricasDiarias($dias),
            'validaciones' => $this->obtenerTendenciasValidaciones($dias),
            'rutas_cortas' => $this->obtenerTendenciasRutasCortas($dias),
            'eficiencia_conductores' => $this->obtenerTendenciasEficiencia($dias)
        ];
    }

    private function obtenerAlertas()
    {
        return [
            'conductores_criticos' => $this->contarConductoresCriticos(),
            'validaciones_criticas' => $this->contarValidacionesCriticas(),
            'eficiencia_baja' => $this->contarConductoresEficienciaBaja(),
            'buses_mantenimiento' => $this->contarBusesMantenimiento()
        ];
    }

    private function obtenerActividadReciente()
    {
        try {
            $actividades = collect();

            // Conductores actualizados recientemente
            $conductoresRecientes = Conductor::where('updated_at', '>=', now()->subDay())
                ->latest('updated_at')
                ->limit(3)
                ->get()
                ->map(function($conductor) {
                    return [
                        'tipo' => 'conductor',
                        'accion' => 'actualizado',
                        'descripcion' => "Conductor {$conductor->nombre} fue actualizado",
                        'timestamp' => $conductor->updated_at,
                        'icono' => 'fas fa-user'
                    ];
                });

            // Validaciones recientes
            $validacionesRecientes = Validacion::where('created_at', '>=', now()->subDay())
                ->latest('created_at')
                ->limit(3)
                ->get()
                ->map(function($validacion) {
                    return [
                        'tipo' => 'validacion',
                        'accion' => 'creada',
                        'descripcion' => "Nueva validación: " . ($validacion->tipo ?? 'General'),
                        'timestamp' => $validacion->created_at,
                        'icono' => 'fas fa-exclamation-triangle'
                    ];
                });

            return $actividades->merge($conductoresRecientes)
                ->merge($validacionesRecientes)
                ->sortByDesc('timestamp')
                ->take(6)
                ->values();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    // LÓGICA ORIGINAL RESTAURADA - Métodos de conductores destacados
    private function obtenerConductoresDestacados()
    {
        try {
            return Conductor::where('eficiencia', '>', 85)
                ->where('puntualidad', '>', 90)
                ->orderByRaw('(eficiencia + puntualidad) DESC')
                ->limit(5)
                ->get()
                ->map(function($conductor) {
                    $conductor->score_general = ($conductor->eficiencia + $conductor->puntualidad) / 2;
                    return $conductor;
                });
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    // LÓGICA ORIGINAL RESTAURADA - Validaciones pendientes como colección
    private function obtenerValidacionesPendientes()
    {
        try {
            return Validacion::with(['conductor:id,nombre,codigo_conductor'])
                ->where('estado', 'PENDIENTE')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($validacion) {
                    if (!$validacion->conductor) {
                        $validacion->conductor = (object)[
                            'nombre' => 'Conductor No Asignado',
                            'codigo' => 'N/A'
                        ];
                    }
                    if (!$validacion->tipo) $validacion->tipo = 'Validación General';
                    if (!$validacion->mensaje) $validacion->mensaje = 'Sin descripción disponible';
                    if (!$validacion->severidad) $validacion->severidad = 'MEDIA';
                    return $validacion;
                });
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    // NUEVA FUNCIÓN REQUERIDA - Conductores por estado para gráficos JS
    private function obtenerConductoresPorEstado()
    {
        try {
            $estados = Conductor::selectRaw('estado, COUNT(*) as cantidad')
                ->groupBy('estado')
                ->get();

            $datos = [];
            foreach ($estados as $estado) {
                $datos[$estado->estado] = $estado->cantidad;
            }

            // Asegurar que todos los estados posibles estén incluidos
            $estadosPosibles = ['DISPONIBLE', 'DESCANSO_FISICO', 'DESCANSO_SEMANAL', 'VACACIONES', 'SUSPENDIDO', 'FALTO_OPERATIVO', 'FALTO_NO_OPERATIVO'];

            foreach ($estadosPosibles as $estado) {
                if (!isset($datos[$estado])) {
                    $datos[$estado] = 0;
                }
            }

            return $datos;
        } catch (\Exception $e) {
            return [
                'DISPONIBLE' => 0,
                'DESCANSO_FISICO' => 0,
                'DESCANSO_SEMANAL' => 0,
                'VACACIONES' => 0,
                'SUSPENDIDO' => 0,
                'FALTO_OPERATIVO' => 0,
                'FALTO_NO_OPERATIVO' => 0
            ];
        }
    }

    // NUEVA FUNCIÓN REQUERIDA - Tendencias semanales para gráficos JS
    private function obtenerTendenciasSemanales()
    {
        try {
            $datos = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = now()->subDays($i);
                $conductoresDisponibles = Conductor::where('estado', 'DISPONIBLE')->count();
                $validacionesPendientes = Validacion::where('estado', 'PENDIENTE')
                    ->whereDate('created_at', $fecha->toDateString())
                    ->count();

                $datos[] = [
                    'fecha' => $fecha->format('d/m'),
                    'conductores' => $conductoresDisponibles,
                    'validaciones' => $validacionesPendientes
                ];
            }

            return $datos;
        } catch (\Exception $e) {
            return [];
        }
    }

    // LÓGICA ORIGINAL RESTAURADA - Tendencias específicas
    private function obtenerTendenciasRutasCortas($dias)
    {
        try {
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
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    private function obtenerTendenciasEficiencia($dias)
    {
        try {
            $tendencias = [];
            for ($i = $dias; $i >= 0; $i--) {
                $fecha = now()->subDays($i);
                $eficiencia = Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])
                    ->avg('eficiencia') ?: 0;
                $tendencias[] = [
                    'fecha' => $fecha->toDateString(),
                    'eficiencia_promedio' => round($eficiencia, 1)
                ];
            }
            return collect($tendencias);
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    private function obtenerTendenciasValidaciones($dias)
    {
        try {
            return Validacion::selectRaw('
                    DATE(created_at) as fecha,
                    COUNT(*) as total,
                    SUM(CASE WHEN severidad = "CRITICA" THEN 1 ELSE 0 END) as criticas,
                    SUM(CASE WHEN estado = "RESUELTO" THEN 1 ELSE 0 END) as resueltas
                ')
                ->where('created_at', '>=', now()->subDays($dias))
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    private function obtenerTendenciasMetricasDiarias($dias)
    {
        try {
            return MetricaDiaria::where('fecha', '>=', now()->subDays($dias))
                ->orderBy('fecha')
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    // LÓGICA ORIGINAL RESTAURADA - Métodos de estadísticas específicas
    private function obtenerEstadisticasRutasCortas()
    {
        try {
            $hoy = now()->toDateString();
            $total = RutaCorta::where('fecha', $hoy)->count();
            $completadas = RutaCorta::where('fecha', $hoy)->where('estado', 'COMPLETADA')->count();

            return [
                'total_hoy' => $total,
                'completadas_hoy' => $completadas,
                'porcentaje_completadas' => $this->calcularPorcentaje($completadas, $total),
                'ingresos_hoy' => RutaCorta::where('fecha', $hoy)->where('estado', 'COMPLETADA')->sum('ingreso_estimado')
            ];
        } catch (\Exception $e) {
            return [
                'total_hoy' => 0,
                'completadas_hoy' => 0,
                'porcentaje_completadas' => 0,
                'ingresos_hoy' => 0
            ];
        }
    }

    private function obtenerEstadisticasTurnos()
    {
        try {
            $hoy = now()->toDateString();
            $programados = Turno::where('fecha_turno', $hoy)->count();
            $completados = Turno::where('fecha_turno', $hoy)->where('estado', 'COMPLETADO')->count();

            return [
                'programados_hoy' => $programados,
                'completados_hoy' => $completados,
                'porcentaje_completados' => $this->calcularPorcentaje($completados, $programados),
                'sin_conductor' => Turno::where('fecha_turno', $hoy)->whereNull('conductor_id')->count()
            ];
        } catch (\Exception $e) {
            return [
                'programados_hoy' => 0,
                'completados_hoy' => 0,
                'porcentaje_completados' => 0,
                'sin_conductor' => 0
            ];
        }
    }

    // MÉTODOS AUXILIARES CON MANEJO DE ERRORES

    private function contarConductores()
    {
        try { return Conductor::count(); } catch (\Exception $e) { return 0; }
    }

    private function contarConductoresDisponibles()
    {
        try { return Conductor::where('estado', 'DISPONIBLE')->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarConductoresCriticos()
    {
        try { return Conductor::where('dias_acumulados', '>=', 6)->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarConductoresEficienciaBaja()
    {
        try { return Conductor::where('eficiencia', '<', 80)->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarValidacionesPendientes()
    {
        try { return Validacion::where('estado', 'PENDIENTE')->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarValidacionesCriticas()
    {
        try { return Validacion::where('estado', 'PENDIENTE')->where('severidad', 'CRITICA')->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarBusesOperativos()
    {
        try { return Bus::where('estado', 'OPERATIVO')->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarBusesMantenimiento()
    {
        try { return Bus::where('estado', 'MANTENIMIENTO')->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarBusesTotal()
    {
        try { return Bus::count(); } catch (\Exception $e) { return 0; }
    }

    private function calcularEficienciaPromedio()
    {
        try {
            return Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])->avg('eficiencia') ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcularPuntualidadPromedio()
    {
        try {
            return Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])->avg('puntualidad') ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcularCoberturaTurnos()
    {
        try {
            $turnosRequeridos = 100; // Ajustar según lógica de negocio
            $conductoresDisponibles = $this->contarConductoresDisponibles();
            return $turnosRequeridos > 0 ? round(($conductoresDisponibles / $turnosRequeridos) * 100, 1) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcularIngresosHoy($fecha)
    {
        try {
            return RutaCorta::where('fecha', $fecha)->where('estado', 'COMPLETADA')->sum('ingreso_estimado');
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcularIngresosMesActual()
    {
        try {
            return RutaCorta::where('fecha', '>=', now()->startOfMonth())
                ->where('estado', 'COMPLETADA')->sum('ingreso_estimado');
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcularPromedioDiarioIngresos()
    {
        try {
            return RutaCorta::where('fecha', '>=', now()->subDays(30))
                ->where('estado', 'COMPLETADA')
                ->selectRaw('DATE(fecha) as fecha, SUM(ingreso_estimado) as ingresos_dia')
                ->groupBy('fecha')
                ->avg('ingresos_dia') ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcularPorcentaje($numerador, $denominador)
    {
        return $denominador > 0 ? round(($numerador / $denominador) * 100, 1) : 0;
    }

    // LÓGICA ORIGINAL RESTAURADA - Métodos para gráficos
    public function obtenerDatosGraficos(Request $request)
    {
        $tipo = $request->get('tipo');
        $dias = $request->get('dias', 7);

        try {
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
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener datos de gráfico: ' . $e->getMessage());
        }
    }

    private function obtenerGraficoIngresos($dias)
    {
        try {
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
        } catch (\Exception $e) {
            return ['labels' => [], 'datasets' => []];
        }
    }

    private function obtenerGraficoConductores()
    {
        try {
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
        } catch (\Exception $e) {
            return ['labels' => [], 'datasets' => []];
        }
    }

    private function obtenerGraficoEficiencia($dias)
    {
        try {
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
        } catch (\Exception $e) {
            return ['labels' => [], 'datasets' => []];
        }
    }

    private function obtenerGraficoRutasTramo($dias)
    {
        try {
            $datos = RutaCorta::selectRaw('tramo, COUNT(*) as total, SUM(ingreso_estimado) as ingresos')
                ->where('fecha', '>=', now()->subDays($dias))
                ->where('estado', 'COMPLETADA')
                ->groupBy('tramo')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            return [
                'labels' => $datos->pluck('tramo'),
                'datasets' => [
                    [
                        'label' => 'Rutas Completadas',
                        'data' => $datos->pluck('total'),
                        'backgroundColor' => 'rgba(54, 162, 235, 0.5)'
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return ['labels' => [], 'datasets' => []];
        }
    }

    // Métodos auxiliares para respuestas API
    private function successResponse($data, $message = 'Operación exitosa')
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message
        ]);
    }

    private function errorResponse($message, $code = 500)
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }

    // MÉTODO PARA API DE GRÁFICOS
    public function getChartData()
    {
        try {
            $metricas = $this->obtenerMetricasPrincipales();

            return response()->json([
                'conductores' => $metricas['conductores'],
                'validaciones' => $metricas['validaciones'],
                'tendencias' => $this->obtenerTendencias(),
                'rutas_cortas' => $metricas['rutas_cortas'],
                'turnos' => $metricas['turnos']
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener datos'], 500);
        }
    }
}
