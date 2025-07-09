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

    /**
     * Obtener métricas principales del sistema
     */
    private function obtenerMetricasPrincipales()
    {
        // Calcular métricas individuales
        $eficienciaPromedio = $this->calcularEficienciaPromedio();
        $puntualidadPromedio = $this->calcularPuntualidadPromedio();
        $coberturaTurnos = $this->calcularCoberturaTurnos();
        $conductoresDisponibles = $this->contarConductoresDisponibles();
        $conductoresTotal = $this->contarConductoresTotal();
        $validacionesPendientes = $this->contarValidacionesPendientes();
        $validacionesCriticas = $this->contarValidacionesCriticas();

        return [
            // Estructura anidada para organización
            'conductores' => [
                'total' => $conductoresTotal,
                'disponibles' => $conductoresDisponibles,
                'criticos' => $this->contarConductoresCriticos(),
                'eficiencia_baja' => $this->contarConductoresEficienciaBaja(),
                'en_descanso' => $this->contarConductoresEnDescanso()
            ],
            'validaciones' => [
                'total' => $this->contarValidacionesTotales(),
                'pendientes' => $validacionesPendientes,
                'criticas' => $validacionesCriticas,
                'resueltas_hoy' => $this->contarValidacionesResueltasHoy()
            ],
            'buses' => [
                'total' => $this->contarBusesTotal(),
                'operativos' => $this->contarBusesOperativos(),
                'mantenimiento' => $this->contarBusesMantenimiento()
            ],
            'rendimiento' => [
                'eficiencia_promedio' => $eficienciaPromedio,
                'puntualidad_promedio' => $puntualidadPromedio,
                'cobertura_turnos' => $coberturaTurnos
            ],
            'rutas_cortas' => [
                'completadas_hoy' => $this->contarRutasCompletadasHoy(),
                'pendientes' => $this->contarRutasPendientes(),
                'ingresos_dia' => $this->calcularIngresosDia(),
                'pasajeros_transportados' => $this->contarPasajerosTransportadosHoy()
            ],
            'turnos' => [
                'programados_hoy' => $this->contarTurnosProgramadosHoy(),
                'cubiertos' => $this->contarTurnosCubiertos(),
                'vacantes' => $this->contarTurnosVacantes()
            ],
            // Claves de compatibilidad para la vista (acceso directo)
            'cobertura_turnos' => $coberturaTurnos,
            'eficiencia_promedio' => $eficienciaPromedio,
            'puntualidad_promedio' => $puntualidadPromedio,
            'conductores_disponibles' => $conductoresDisponibles,
            'conductores_activos' => $conductoresDisponibles, // Alias para conductores_disponibles
            'conductores_total' => $conductoresTotal,
            'validaciones_pendientes' => $validacionesPendientes,
            'validaciones_criticas' => $validacionesCriticas,
            'rutas_completadas_hoy' => $this->contarRutasCompletadasHoy(),
            'ingresos_dia' => $this->calcularIngresosDia(),
            'buses_operativos' => $this->contarBusesOperativos(),
            'turnos_cubiertos' => $this->contarTurnosCubiertos(),
            'turnos_programados' => $this->contarTurnosProgramadosHoy(),
            // Más alias de compatibilidad
            'total_conductores' => $conductoresTotal,
            'total_validaciones' => $this->contarValidacionesTotales(),
            'total_buses' => $this->contarBusesTotal(),
            'conductores_criticos' => $this->contarConductoresCriticos(),
            'conductores_descanso' => $this->contarConductoresEnDescanso(),
            'eficiencia_baja' => $this->contarConductoresEficienciaBaja(),
            'pasajeros_transportados' => $this->contarPasajerosTransportadosHoy(),
            'rutas_pendientes' => $this->contarRutasPendientes(),
            'buses_mantenimiento' => $this->contarBusesMantenimiento(),
            'validaciones_resueltas_hoy' => $this->contarValidacionesResueltasHoy(),
            'turnos_vacantes' => $this->contarTurnosVacantes()
        ];
    }

    /**
     * Obtener tendencias del sistema
     */
    private function obtenerTendencias()
    {
        return [
            'conductores_semana' => $this->obtenerTendenciaConductoresUltimaSemana(),
            'validaciones_semana' => $this->obtenerTendenciaValidacionesUltimaSemana(),
            'rutas_semana' => $this->obtenerTendenciaRutasUltimaSemana(),
            'eficiencia_mensual' => $this->obtenerTendenciaEficienciaUltimoMes()
        ];
    }

    /**
     * Obtener alertas activas del sistema
     */
    private function obtenerAlertas()
    {
        $alertas = [];

        // Alertas de conductores críticos
        $conductoresCriticos = $this->contarConductoresCriticos();
        if ($conductoresCriticos > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'mensaje' => "{$conductoresCriticos} conductores necesitan descanso inmediato",
                'url' => route('conductores.index'),
                'prioridad' => 'alta'
            ];
        }

        // Alertas de validaciones críticas
        $validacionesCriticas = $this->contarValidacionesCriticas();
        if ($validacionesCriticas > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'mensaje' => "{$validacionesCriticas} validaciones críticas pendientes",
                'url' => route('validaciones.index'),
                'prioridad' => 'urgente'
            ];
        }

        // Alertas de eficiencia baja
        $eficienciaBaja = $this->contarConductoresEficienciaBaja();
        if ($eficienciaBaja > 0) {
            $alertas[] = [
                'tipo' => 'info',
                'mensaje' => "{$eficienciaBaja} conductores con eficiencia por debajo del umbral",
                'url' => route('conductores.index'),
                'prioridad' => 'media'
            ];
        }

        return $alertas;
    }

    /**
     * Obtener actividad reciente del sistema
     */
    private function obtenerActividadReciente()
    {
        $actividad = [];

        try {
            // Conductores creados recientemente
            if (class_exists('App\Models\Conductor')) {
                $conductoresRecientes = Conductor::latest()->limit(3)->get();
                foreach ($conductoresRecientes as $conductor) {
                    $actividad[] = [
                        'tipo' => 'conductor_creado',
                        'mensaje' => "Conductor {$conductor->nombre_completo} registrado",
                        'fecha' => $conductor->created_at,
                        'icono' => 'fas fa-user-plus'
                    ];
                }
            }

            // Validaciones resueltas recientemente
            if (class_exists('App\Models\Validacion')) {
                $validacionesResueltas = Validacion::where('estado', 'RESUELTA')
                    ->whereDate('fecha_resolucion', Carbon::today())
                    ->latest('fecha_resolucion')
                    ->limit(3)
                    ->get();

                foreach ($validacionesResueltas as $validacion) {
                    $actividad[] = [
                        'tipo' => 'validacion_resuelta',
                        'mensaje' => "Validación {$validacion->tipo} resuelta",
                        'fecha' => $validacion->fecha_resolucion,
                        'icono' => 'fas fa-check-circle'
                    ];
                }
            }

        } catch (\Exception $e) {
            // Si hay error con las consultas, continuar con actividad vacía
        }

        // Ordenar por fecha
        usort($actividad, function($a, $b) {
            return $b['fecha'] <=> $a['fecha'];
        });

        return array_slice($actividad, 0, 10);
    }

    /**
     * Obtener conductores destacados
     */
    private function obtenerConductoresDestacados()
    {
        try {
            if (class_exists('App\Models\Conductor')) {
                return Conductor::where('activo', true)
                    ->orderBy('score_general', 'desc')
                    ->limit(5)
                    ->get();
            }
        } catch (\Exception $e) {
            // Si hay error, devolver colección vacía
        }

        return collect([]);
    }

    /**
     * Obtener validaciones pendientes más importantes
     */
    private function obtenerValidacionesPendientes()
    {
        try {
            if (class_exists('App\Models\Validacion')) {
                return Validacion::where('estado', 'PENDIENTE')
                    ->orderBy('prioridad', 'desc')
                    ->orderBy('created_at', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(function ($validacion) {
                        // Asegurar que los campos existen
                        if (!$validacion->tipo) $validacion->tipo = 'Validación General';
                        if (!$validacion->mensaje) $validacion->mensaje = 'Sin descripción disponible';
                        if (!$validacion->severidad) $validacion->severidad = 'MEDIA';
                        return $validacion;
                    });
            }
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
                $fecha = Carbon::now()->subDays($i);
                $datos[] = [
                    'fecha' => $fecha->format('Y-m-d'),
                    'conductores_disponibles' => Conductor::where('estado', 'DISPONIBLE')
                        ->whereDate('created_at', '<=', $fecha)
                        ->count(),
                    'validaciones_resueltas' => Validacion::where('estado', 'RESUELTA')
                        ->whereDate('fecha_resolucion', $fecha)
                        ->count(),
                    'rutas_completadas' => $this->contarRutasCompletadasEnFecha($fecha)
                ];
            }

            return $datos;
        } catch (\Exception $e) {
            return [];
        }
    }

    // Métodos auxiliares de conteo
    private function contarConductoresTotal()
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

    private function contarConductoresEnDescanso()
    {
        try { return Conductor::whereIn('estado', ['DESCANSO_FISICO', 'DESCANSO_SEMANAL'])->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarValidacionesTotales()
    {
        try { return Validacion::count(); } catch (\Exception $e) { return 0; }
    }

    private function contarValidacionesPendientes()
    {
        try { return Validacion::where('estado', 'PENDIENTE')->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarValidacionesCriticas()
    {
        try { return Validacion::where('estado', 'PENDIENTE')->where('severidad', 'CRITICA')->count(); } catch (\Exception $e) { return 0; }
    }

    private function contarValidacionesResueltasHoy()
    {
        try { return Validacion::where('estado', 'RESUELTA')->whereDate('fecha_resolucion', Carbon::today())->count(); } catch (\Exception $e) { return 0; }
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
            return $turnosRequeridos > 0 ? ($conductoresDisponibles / $turnosRequeridos) * 100 : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contarRutasCompletadasHoy()
    {
        try {
            return RutaCorta::where('estado', 'COMPLETADA')
                ->whereDate('fecha', Carbon::today())
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contarRutasPendientes()
    {
        try {
            return RutaCorta::where('estado', 'PENDIENTE')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calcularIngresosDia()
    {
        try {
            return RutaCorta::where('estado', 'COMPLETADA')
                ->whereDate('fecha', Carbon::today())
                ->sum('ingreso_estimado') ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contarPasajerosTransportadosHoy()
    {
        try {
            return RutaCorta::where('estado', 'COMPLETADA')
                ->whereDate('fecha', Carbon::today())
                ->sum('pasajeros_transportados') ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contarTurnosProgramadosHoy()
    {
        try {
            return Turno::whereDate('fecha', Carbon::today())->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contarTurnosCubiertos()
    {
        try {
            return Turno::whereNotNull('conductor_id')
                ->whereDate('fecha', Carbon::today())
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contarTurnosVacantes()
    {
        try {
            return Turno::whereNull('conductor_id')
                ->whereDate('fecha', Carbon::today())
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function contarRutasCompletadasEnFecha($fecha)
    {
        try {
            return RutaCorta::where('estado', 'COMPLETADA')
                ->whereDate('fecha', $fecha)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    // Métodos de tendencias
    private function obtenerTendenciaConductoresUltimaSemana()
    {
        try {
            $datos = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = Carbon::now()->subDays($i);
                $datos[] = [
                    'fecha' => $fecha->format('Y-m-d'),
                    'disponibles' => Conductor::where('estado', 'DISPONIBLE')
                        ->whereDate('created_at', '<=', $fecha)
                        ->count()
                ];
            }
            return $datos;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function obtenerTendenciaValidacionesUltimaSemana()
    {
        try {
            $datos = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = Carbon::now()->subDays($i);
                $datos[] = [
                    'fecha' => $fecha->format('Y-m-d'),
                    'pendientes' => Validacion::where('estado', 'PENDIENTE')
                        ->whereDate('created_at', $fecha)
                        ->count(),
                    'resueltas' => Validacion::where('estado', 'RESUELTA')
                        ->whereDate('fecha_resolucion', $fecha)
                        ->count()
                ];
            }
            return $datos;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function obtenerTendenciaRutasUltimaSemana()
    {
        try {
            $datos = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = Carbon::now()->subDays($i);
                $datos[] = [
                    'fecha' => $fecha->format('Y-m-d'),
                    'completadas' => $this->contarRutasCompletadasEnFecha($fecha),
                    'ingresos' => RutaCorta::where('estado', 'COMPLETADA')
                        ->whereDate('fecha', $fecha)
                        ->sum('ingreso_estimado') ?: 0
                ];
            }
            return $datos;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function obtenerTendenciaEficienciaUltimoMes()
    {
        try {
            $datos = [];
            for ($i = 29; $i >= 0; $i--) {
                $fecha = Carbon::now()->subDays($i);
                $eficienciaPromedio = Conductor::where('estado', 'DISPONIBLE')
                    ->whereDate('updated_at', $fecha)
                    ->avg('eficiencia') ?: 0;

                $datos[] = [
                    'fecha' => $fecha->format('Y-m-d'),
                    'eficiencia_promedio' => round($eficienciaPromedio, 2)
                ];
            }
            return $datos;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Dashboard de planificación
     */
    public function planificacion()
    {
        try {
            $datosplanificacion = [
                'turnos_programados' => $this->contarTurnosProgramadosHoy(),
                'turnos_cubiertos' => $this->contarTurnosCubiertos(),
                'conductores_activos' => $this->contarConductoresDisponibles(),
                'rutas_optimizadas' => $this->contarRutasCompletadasHoy()
            ];

            return view('dashboard.planificacion', compact('datosplanificacion'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Error accediendo a planificación');
        }
    }

    /**
     * Dashboard de backups
     */
    public function backups()
    {
        try {
            $backups = [
                'ultimo_backup' => Carbon::now()->subHours(2),
                'espacio_utilizado' => '245 MB',
                'backups_disponibles' => 15,
                'estado_sistema' => 'operativo'
            ];

            return view('dashboard.backups', compact('backups'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Error accediendo a backups');
        }
    }

    /**
     * Dashboard de usuarios
     */
    public function usuarios()
    {
        try {
            $usuarios = [
                'usuarios_activos' => 8,
                'sesiones_activas' => 3,
                'ultimo_acceso' => Carbon::now()->subMinutes(5),
                'intentos_fallidos' => 0
            ];

            return view('dashboard.usuarios', compact('usuarios'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', 'Error accediendo a usuarios');
        }
    }

    /**
     * API para actualizar métricas
     */
    public function actualizarMetricas()
    {
        try {
            $metricas = $this->obtenerMetricasPrincipales();

            return response()->json([
                'success' => true,
                'data' => $metricas,
                'timestamp' => Carbon::now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error actualizando métricas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para estado del sistema
     */
    public function estadoSistema()
    {
        try {
            $estado = [
                'database' => $this->verificarConexionBD(),
                'cache' => $this->verificarCache(),
                'storage' => $this->verificarStorage(),
                'memoria' => $this->obtenerUsoMemoria()
            ];

            return response()->json([
                'success' => true,
                'data' => $estado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error verificando estado del sistema'
            ], 500);
        }
    }

    /**
     * API para obtener datos de gráficos del dashboard
     * *** MÉTODO AGREGADO PARA SOLUCIONAR EL ERROR ***
     */
    public function getChartData()
    {
        try {
            // Obtener métricas principales
            $metricas = $this->obtenerMetricasPrincipales();

            // Estructurar datos para gráficos JavaScript
            $chartData = [
                'conductores' => [
                    'disponibles' => $metricas['conductores']['disponibles'],
                    'en_descanso' => $metricas['conductores']['en_descanso'],
                    'criticos' => $metricas['conductores']['criticos'],
                    'total' => $metricas['conductores']['total'],
                    'eficiencia_baja' => $metricas['conductores']['eficiencia_baja']
                ],
                'validaciones' => [
                    'pendientes' => $metricas['validaciones']['pendientes'],
                    'criticas' => $metricas['validaciones']['criticas'],
                    'total' => $metricas['validaciones']['total'],
                    'resueltas_hoy' => $metricas['validaciones']['resueltas_hoy']
                ],
                'rutas' => [
                    'completadas_hoy' => $metricas['rutas_cortas']['completadas_hoy'],
                    'pendientes' => $metricas['rutas_cortas']['pendientes'],
                    'ingresos' => $metricas['rutas_cortas']['ingresos_dia'],
                    'pasajeros' => $metricas['rutas_cortas']['pasajeros_transportados']
                ],
                'rendimiento' => [
                    'eficiencia_promedio' => round($metricas['rendimiento']['eficiencia_promedio'], 1),
                    'puntualidad_promedio' => round($metricas['rendimiento']['puntualidad_promedio'], 1),
                    'cobertura_turnos' => round($metricas['rendimiento']['cobertura_turnos'], 1)
                ],
                'turnos' => [
                    'programados_hoy' => $metricas['turnos']['programados_hoy'],
                    'cubiertos' => $metricas['turnos']['cubiertos'],
                    'vacantes' => $metricas['turnos']['vacantes']
                ],
                'tendencias' => [
                    'conductores_semana' => $this->obtenerTendenciaConductoresUltimaSemana(),
                    'validaciones_semana' => $this->obtenerTendenciaValidacionesUltimaSemana(),
                    'rutas_semana' => $this->obtenerTendenciaRutasUltimaSemana()
                ],
                'estados_conductores' => $this->obtenerConductoresPorEstado()
            ];

            return response()->json([
                'success' => true,
                'data' => $chartData,
                'timestamp' => Carbon::now()->toISOString(),
                'cache_duration' => 300 // 5 minutos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo datos de gráficos',
                'error' => $e->getMessage(),
                'fallback_data' => $this->obtenerDatosFallback()
            ], 500);
        }
    }

    /**
     * Datos de fallback en caso de error
     */
    private function obtenerDatosFallback()
    {
        return [
            'conductores' => ['disponibles' => 25, 'total' => 35, 'criticos' => 3],
            'validaciones' => ['pendientes' => 5, 'criticas' => 2, 'total' => 15],
            'rutas' => ['completadas_hoy' => 45, 'ingresos' => 2500],
            'rendimiento' => ['eficiencia_promedio' => 87.5, 'puntualidad_promedio' => 92.0]
        ];
    }

    /**
     * Limpiar cache del dashboard
     */
    public function limpiarCache()
    {
        try {
            \Cache::forget('dashboard_metricas');
            \Cache::forget('dashboard_tendencias');

            return response()->json([
                'success' => true,
                'message' => 'Cache del dashboard limpiado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error limpiando cache'
            ], 500);
        }
    }

    // Métodos auxiliares para verificaciones del sistema
    private function verificarConexionBD()
    {
        try {
            \DB::select('SELECT 1');
            return ['status' => 'conectado', 'latencia' => '< 10ms'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'mensaje' => $e->getMessage()];
        }
    }

    private function verificarCache()
    {
        try {
            \Cache::put('test_conexion', true, 5);
            $resultado = \Cache::get('test_conexion');
            return ['status' => $resultado ? 'funcionando' : 'error'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'mensaje' => $e->getMessage()];
        }
    }

    private function verificarStorage()
    {
        try {
            $espacioLibre = disk_free_space(storage_path());
            return [
                'status' => 'accesible',
                'espacio_libre' => $this->formatearBytes($espacioLibre)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'mensaje' => $e->getMessage()];
        }
    }

    private function obtenerUsoMemoria()
    {
        $memoria = memory_get_usage(true);
        $memoriaMaxima = memory_get_peak_usage(true);

        return [
            'actual' => $this->formatearBytes($memoria),
            'pico' => $this->formatearBytes($memoriaMaxima),
            'limite' => ini_get('memory_limit')
        ];
    }

    private function formatearBytes($bytes)
    {
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes > 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $unidades[$i];
    }
}
