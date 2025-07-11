<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\Turno;
use App\Models\HistorialPlanificacion;
use App\Models\Parametro;
use Carbon\Carbon;

class CacheMetricasService
{
    /**
     * Prefijos para las claves de cache
     */
    private const PREFIX_DASHBOARD = 'sipat_dashboard_';
    private const PREFIX_CONDUCTOR = 'sipat_conductor_';
    private const PREFIX_VALIDACION = 'sipat_validacion_';
    private const PREFIX_TURNO = 'sipat_turno_';
    private const PREFIX_SISTEMA = 'sipat_sistema_';
    private const PREFIX_REPORTES = 'sipat_reportes_';

    /**
     * Tiempos de vida del cache (en minutos)
     */
    private const TTL_DASHBOARD = 5;           // 5 minutos para dashboard
    private const TTL_TENDENCIAS = 60;        // 1 hora para tendencias semanales
    private const TTL_CRITICOS = 15;          // 15 minutos para conductores críticos
    private const TTL_METRICAS_BASICAS = 30;  // 30 minutos para métricas básicas
    private const TTL_ESTADISTICAS = 120;     // 2 horas para estadísticas complejas
    private const TTL_REPORTES = 1440;        // 24 horas para reportes

    /**
     * Obtener métricas del dashboard principal
     */
    public function obtenerMetricasDashboard(): array
    {
        return Cache::remember(
            self::PREFIX_DASHBOARD . 'principal',
            self::TTL_DASHBOARD,
            function () {
                try {
                    return [
                        'conductores' => $this->calcularMetricasConductores(),
                        'validaciones' => $this->calcularMetricasValidaciones(),
                        'turnos' => $this->calcularMetricasTurnos(),
                        'sistema' => $this->calcularMetricasSistema(),
                        'eficiencia' => $this->calcularEficienciaGeneral(),
                        'alertas' => $this->calcularAlertasActivas(),
                        'timestamp' => now()->toISOString()
                    ];
                } catch (\Exception $e) {
                    Log::error('Error calculando métricas dashboard: ' . $e->getMessage());
                    return $this->obtenerMetricasVacias();
                }
            }
        );
    }

    /**
     * Calcular métricas de conductores
     */
    private function calcularMetricasConductores(): array
    {
        try {
            $total = Conductor::count();
            $disponibles = Conductor::where('estado', 'DISPONIBLE')->count();
            $descanso = Conductor::where('estado', 'DESCANSO FISICO')->count();
            $criticos = Conductor::where('dias_acumulados', '>=',
                sipat_config('dias_maximos_sin_descanso', 6))->count();
            $bajoDendimiento = Conductor::where('eficiencia', '<',
                sipat_config('eficiencia_minima_conductor', 80))->count();

            $porcentajeDisponibilidad = $total > 0 ? round(($disponibles / $total) * 100, 1) : 0;
            $promedioEficiencia = Conductor::where('estado', 'DISPONIBLE')
                ->avg('eficiencia') ?? 0;
            $promedioPuntualidad = Conductor::where('estado', 'DISPONIBLE')
                ->avg('puntualidad') ?? 0;

            return [
                'total' => $total,
                'disponibles' => $disponibles,
                'en_descanso' => $descanso,
                'criticos' => $criticos,
                'bajo_rendimiento' => $bajoDendimiento,
                'porcentaje_disponibilidad' => $porcentajeDisponibilidad,
                'promedio_eficiencia' => round($promedioEficiencia, 1),
                'promedio_puntualidad' => round($promedioPuntualidad, 1),
                'score_general' => round(($promedioEficiencia + $promedioPuntualidad) / 2, 1),
                'estados' => $this->obtenerDistribucionEstados()
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando métricas de conductores: ' . $e->getMessage());
            return [
                'total' => 0,
                'disponibles' => 0,
                'en_descanso' => 0,
                'criticos' => 0,
                'bajo_rendimiento' => 0,
                'porcentaje_disponibilidad' => 0,
                'promedio_eficiencia' => 0,
                'promedio_puntualidad' => 0,
                'score_general' => 0,
                'estados' => []
            ];
        }
    }

    /**
     * Obtener distribución de estados de conductores
     */
    private function obtenerDistribucionEstados(): array
    {
        try {
            return Conductor::selectRaw('estado, count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Calcular métricas de validaciones
     */
    private function calcularMetricasValidaciones(): array
    {
        try {
            $pendientes = Validacion::where('estado', 'PENDIENTE')->count();
            $criticas = Validacion::where('severidad', 'CRITICA')
                ->where('estado', 'PENDIENTE')->count();
            $resueltasHoy = Validacion::where('estado', 'RESUELTO')
                ->whereDate('updated_at', today())->count();
            $totalHoy = Validacion::whereDate('created_at', today())->count();

            $tiempoPromedioResolucion = Validacion::where('estado', 'RESUELTO')
                ->whereNotNull('fecha_resolucion')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, fecha_resolucion)) as promedio')
                ->value('promedio') ?? 0;

            $efectividad = $totalHoy > 0 ? round(($resueltasHoy / $totalHoy) * 100, 1) : 100;

            return [
                'pendientes' => $pendientes,
                'criticas' => $criticas,
                'resueltas_hoy' => $resueltasHoy,
                'total_hoy' => $totalHoy,
                'tiempo_promedio_resolucion' => round($tiempoPromedioResolucion, 1),
                'efectividad_diaria' => $efectividad,
                'distribucion_severidad' => $this->obtenerDistribucionSeveridad(),
                'tipos_frecuentes' => $this->obtenerTiposValidacionesFrecuentes()
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando métricas de validaciones: ' . $e->getMessage());
            return [
                'pendientes' => 0,
                'criticas' => 0,
                'resueltas_hoy' => 0,
                'total_hoy' => 0,
                'tiempo_promedio_resolucion' => 0,
                'efectividad_diaria' => 0,
                'distribucion_severidad' => [],
                'tipos_frecuentes' => []
            ];
        }
    }

    /**
     * Obtener distribución de severidad de validaciones
     */
    private function obtenerDistribucionSeveridad(): array
    {
        try {
            return Validacion::where('estado', 'PENDIENTE')
                ->selectRaw('severidad, count(*) as total')
                ->groupBy('severidad')
                ->pluck('total', 'severidad')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtener tipos de validaciones más frecuentes
     */
    private function obtenerTiposValidacionesFrecuentes(): array
    {
        try {
            return Validacion::whereDate('created_at', '>=', now()->subDays(7))
                ->selectRaw('tipo, count(*) as total')
                ->groupBy('tipo')
                ->orderByDesc('total')
                ->limit(5)
                ->pluck('total', 'tipo')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Calcular métricas de turnos
     */
    private function calcularMetricasTurnos(): array
    {
        try {
            $hoy = today();
            $turnosHoy = Turno::whereDate('fecha', $hoy)->count();
            $turnosCompletados = Turno::whereDate('fecha', $hoy)
                ->where('estado', 'COMPLETADO')->count();
            $turnosEnCurso = Turno::whereDate('fecha', $hoy)
                ->where('estado', 'EN_CURSO')->count();
            $turnosPendientes = Turno::whereDate('fecha', $hoy)
                ->where('estado', 'PENDIENTE')->count();

            $cobertura = $turnosHoy > 0 ? round((($turnosCompletados + $turnosEnCurso) / $turnosHoy) * 100, 1) : 0;

            $horasHombre = Turno::whereDate('fecha', $hoy)
                ->where('estado', '!=', 'CANCELADO')
                ->sum('horas_estimadas') ?? 0;

            $eficienciaPromedio = Turno::whereDate('fecha', $hoy)
                ->where('estado', 'COMPLETADO')
                ->avg('eficiencia_calculada') ?? 0;

            return [
                'hoy' => $turnosHoy,
                'completados' => $turnosCompletados,
                'en_curso' => $turnosEnCurso,
                'pendientes' => $turnosPendientes,
                'cobertura_porcentaje' => $cobertura,
                'horas_hombre_dia' => round($horasHombre, 1),
                'eficiencia_promedio' => round($eficienciaPromedio, 1),
                'distribucion_estados' => $this->obtenerDistribucionEstadosTurnos()
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando métricas de turnos: ' . $e->getMessage());
            return [
                'hoy' => 0,
                'completados' => 0,
                'en_curso' => 0,
                'pendientes' => 0,
                'cobertura_porcentaje' => 0,
                'horas_hombre_dia' => 0,
                'eficiencia_promedio' => 0,
                'distribucion_estados' => []
            ];
        }
    }

    /**
     * Obtener distribución de estados de turnos
     */
    private function obtenerDistribucionEstadosTurnos(): array
    {
        try {
            return Turno::whereDate('fecha', today())
                ->selectRaw('estado, count(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Calcular métricas del sistema
     */
    private function calcularMetricasSistema(): array
    {
        try {
            $configuraciones = Parametro::count();
            $usuariosActivos = DB::table('users')
                ->where('email_verified_at', '!=', null)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->convertirABytes($memoryLimit);
            $memoryPercent = round(($memoryUsage / $memoryLimitBytes) * 100, 1);

            return [
                'version' => config('sipat.version'),
                'estado' => $this->obtenerEstadoSistema(),
                'configuraciones_totales' => $configuraciones,
                'usuarios_activos_mes' => $usuariosActivos,
                'memoria_uso_mb' => round($memoryUsage / 1024 / 1024, 1),
                'memoria_limite' => $memoryLimit,
                'memoria_porcentaje' => $memoryPercent,
                'uptime' => $this->calcularUptime(),
                'ultima_actualizacion' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando métricas del sistema: ' . $e->getMessage());
            return [
                'version' => config('sipat.version', '1.0.0'),
                'estado' => 'DESCONOCIDO',
                'configuraciones_totales' => 0,
                'usuarios_activos_mes' => 0,
                'memoria_uso_mb' => 0,
                'memoria_limite' => 'N/A',
                'memoria_porcentaje' => 0,
                'uptime' => 'N/A',
                'ultima_actualizacion' => now()->toISOString()
            ];
        }
    }

    /**
     * Convertir string de memoria a bytes
     */
    private function convertirABytes($memoria): int
    {
        $memoria = trim($memoria);
        $ultimo = strtolower($memoria[strlen($memoria) - 1]);
        $numero = (int) $memoria;

        return match($ultimo) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero
        };
    }

    /**
     * Obtener estado general del sistema
     */
    private function obtenerEstadoSistema(): string
    {
        try {
            // Verificar estado del cache
            $estadoCache = Cache::get('sipat_sistema_estado', 'OPERATIVO');

            // Verificar validaciones críticas
            $validacionesCriticas = Validacion::where('severidad', 'CRITICA')
                ->where('estado', 'PENDIENTE')
                ->count();

            if ($estadoCache === 'MANTENIMIENTO') {
                return 'MANTENIMIENTO';
            }

            if ($validacionesCriticas > 0) {
                return 'ALERTA';
            }

            return 'OPERATIVO';
        } catch (\Exception $e) {
            return 'ERROR';
        }
    }

    /**
     * Calcular uptime aproximado del sistema
     */
    private function calcularUptime(): string
    {
        try {
            $inicioCache = Cache::get('sipat_tiempo_inicio');
            if (!$inicioCache) {
                return 'N/A';
            }

            $segundos = now()->timestamp - $inicioCache;
            $horas = floor($segundos / 3600);
            $minutos = floor(($segundos % 3600) / 60);

            return "{$horas}h {$minutos}m";
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Calcular eficiencia general del sistema
     */
    private function calcularEficienciaGeneral(): array
    {
        try {
            $eficienciaConductores = Conductor::where('estado', 'DISPONIBLE')
                ->avg('eficiencia') ?? 0;

            $eficienciaTurnos = Turno::whereDate('created_at', '>=', now()->subDays(7))
                ->where('estado', 'COMPLETADO')
                ->avg('eficiencia_calculada') ?? 0;

            $validacionesResueltas24h = Validacion::where('estado', 'RESUELTO')
                ->where('updated_at', '>=', now()->subHours(24))
                ->count();

            $validacionesTotal24h = Validacion::where('created_at', '>=', now()->subHours(24))
                ->count();

            $eficienciaValidaciones = $validacionesTotal24h > 0 ?
                round(($validacionesResueltas24h / $validacionesTotal24h) * 100, 1) : 100;

            $eficienciaGeneral = round(($eficienciaConductores + $eficienciaTurnos + $eficienciaValidaciones) / 3, 1);

            return [
                'general' => $eficienciaGeneral,
                'conductores' => round($eficienciaConductores, 1),
                'turnos' => round($eficienciaTurnos, 1),
                'validaciones' => $eficienciaValidaciones,
                'tendencia' => $this->calcularTendenciaEficiencia()
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando eficiencia general: ' . $e->getMessage());
            return [
                'general' => 0,
                'conductores' => 0,
                'turnos' => 0,
                'validaciones' => 0,
                'tendencia' => 'ESTABLE'
            ];
        }
    }

    /**
     * Calcular tendencia de eficiencia
     */
    private function calcularTendenciaEficiencia(): string
    {
        try {
            $eficienciaHoy = Turno::whereDate('created_at', today())
                ->where('estado', 'COMPLETADO')
                ->avg('eficiencia_calculada') ?? 0;

            $eficienciaAyer = Turno::whereDate('created_at', yesterday())
                ->where('estado', 'COMPLETADO')
                ->avg('eficiencia_calculada') ?? 0;

            if ($eficienciaAyer == 0) {
                return 'ESTABLE';
            }

            $diferencia = (($eficienciaHoy - $eficienciaAyer) / $eficienciaAyer) * 100;

            if ($diferencia > 5) {
                return 'MEJORANDO';
            } elseif ($diferencia < -5) {
                return 'DECLINANDO';
            } else {
                return 'ESTABLE';
            }
        } catch (\Exception $e) {
            return 'ESTABLE';
        }
    }

    /**
     * Calcular alertas activas
     */
    private function calcularAlertasActivas(): array
    {
        try {
            $alertas = [];

            // Conductores críticos por días acumulados
            $conductoresCriticos = Conductor::where('dias_acumulados', '>=',
                sipat_config('dias_maximos_sin_descanso', 6))->count();

            if ($conductoresCriticos > 0) {
                $alertas[] = [
                    'tipo' => 'CONDUCTOR_CRITICO',
                    'mensaje' => "{$conductoresCriticos} conductor(es) necesitan descanso urgente",
                    'severidad' => 'CRITICA',
                    'count' => $conductoresCriticos
                ];
            }

            // Validaciones críticas pendientes
            $validacionesCriticas = Validacion::where('severidad', 'CRITICA')
                ->where('estado', 'PENDIENTE')->count();

            if ($validacionesCriticas > 0) {
                $alertas[] = [
                    'tipo' => 'VALIDACION_CRITICA',
                    'mensaje' => "{$validacionesCriticas} validación(es) crítica(s) pendiente(s)",
                    'severidad' => 'CRITICA',
                    'count' => $validacionesCriticas
                ];
            }

            // Uso excesivo de memoria
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->convertirABytes(ini_get('memory_limit'));
            $memoryPercent = ($memoryUsage / $memoryLimit) * 100;

            if ($memoryPercent > 85) {
                $alertas[] = [
                    'tipo' => 'MEMORIA_ALTA',
                    'mensaje' => "Uso de memoria alto: " . round($memoryPercent, 1) . "%",
                    'severidad' => 'ADVERTENCIA',
                    'count' => round($memoryPercent, 1)
                ];
            }

            // Conductores con bajo rendimiento
            $bajoRendimiento = Conductor::where('eficiencia', '<',
                sipat_config('eficiencia_minima_conductor', 80))->count();

            if ($bajoRendimiento > 0) {
                $alertas[] = [
                    'tipo' => 'BAJO_RENDIMIENTO',
                    'mensaje' => "{$bajoRendimiento} conductor(es) con eficiencia baja",
                    'severidad' => 'ADVERTENCIA',
                    'count' => $bajoRendimiento
                ];
            }

            return [
                'total' => count($alertas),
                'criticas' => count(array_filter($alertas, fn($a) => $a['severidad'] === 'CRITICA')),
                'advertencias' => count(array_filter($alertas, fn($a) => $a['severidad'] === 'ADVERTENCIA')),
                'detalles' => $alertas
            ];
        } catch (\Exception $e) {
            Log::error('Error calculando alertas activas: ' . $e->getMessage());
            return [
                'total' => 0,
                'criticas' => 0,
                'advertencias' => 0,
                'detalles' => []
            ];
        }
    }

    /**
     * Obtener métrica específica
     */
    public function obtenerMetrica(string $metrica, array $parametros = [])
    {
        $clave = self::PREFIX_SISTEMA . "metrica_{$metrica}_" . md5(serialize($parametros));

        return Cache::remember($clave, self::TTL_METRICAS_BASICAS, function () use ($metrica, $parametros) {
            return match($metrica) {
                'conductores_disponibles' => Conductor::where('estado', 'DISPONIBLE')->count(),
                'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
                'validaciones_criticas' => Validacion::where('severidad', 'CRITICA')
                    ->where('estado', 'PENDIENTE')->count(),
                'turnos_hoy' => Turno::whereDate('fecha', today())->count(),
                'eficiencia_promedio' => round(Conductor::where('estado', 'DISPONIBLE')
                    ->avg('eficiencia') ?? 0, 1),
                'conductores_criticos' => Conductor::where('dias_acumulados', '>=',
                    sipat_config('dias_maximos_sin_descanso', 6))->count(),
                'horas_hombre_semana' => $this->calcularHorasHombreSemana(),
                'cobertura_turnos' => $this->calcularCoberturaTurnos(),
                default => null
            };
        });
    }

    /**
     * Calcular horas hombre de la semana
     */
    private function calcularHorasHombreSemana(): float
    {
        try {
            return Turno::whereBetween('fecha', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->where('estado', '!=', 'CANCELADO')
            ->sum('horas_estimadas') ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calcular cobertura de turnos
     */
    private function calcularCoberturaTurnos(): float
    {
        try {
            $totalTurnos = Turno::whereDate('fecha', today())->count();
            $turnosCubiertos = Turno::whereDate('fecha', today())
                ->where('estado', '!=', 'PENDIENTE')->count();

            return $totalTurnos > 0 ? round(($turnosCubiertos / $totalTurnos) * 100, 1) : 100;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Obtener tendencias semanales
     */
    public function obtenerTendenciasSemanales(): array
    {
        return Cache::remember(
            self::PREFIX_DASHBOARD . 'tendencias_semanales',
            self::TTL_TENDENCIAS,
            function () {
                try {
                    $diasSemana = [];
                    for ($i = 6; $i >= 0; $i--) {
                        $fecha = now()->subDays($i);
                        $diasSemana[] = [
                            'fecha' => $fecha->format('Y-m-d'),
                            'dia' => $fecha->format('l'),
                            'turnos' => Turno::whereDate('fecha', $fecha)->count(),
                            'completados' => Turno::whereDate('fecha', $fecha)
                                ->where('estado', 'COMPLETADO')->count(),
                            'validaciones' => Validacion::whereDate('created_at', $fecha)->count(),
                            'eficiencia_promedio' => round(
                                Turno::whereDate('fecha', $fecha)
                                    ->where('estado', 'COMPLETADO')
                                    ->avg('eficiencia_calculada') ?? 0, 1
                            )
                        ];
                    }

                    return $diasSemana;
                } catch (\Exception $e) {
                    Log::error('Error calculando tendencias semanales: ' . $e->getMessage());
                    return [];
                }
            }
        );
    }

    /**
     * Obtener conductores críticos
     */
    public function obtenerConductoresCriticos(): array
    {
        return Cache::remember(
            self::PREFIX_CONDUCTOR . 'criticos',
            self::TTL_CRITICOS,
            function () {
                try {
                    return Conductor::where(function ($query) {
                        $query->where('dias_acumulados', '>=', sipat_config('dias_maximos_sin_descanso', 6))
                              ->orWhere('eficiencia', '<', sipat_config('eficiencia_minima_conductor', 80))
                              ->orWhere('puntualidad', '<', sipat_config('puntualidad_minima_conductor', 85));
                    })
                    ->select('id', 'codigo', 'nombres', 'apellidos', 'estado',
                            'dias_acumulados', 'eficiencia', 'puntualidad')
                    ->get()
                    ->map(function ($conductor) {
                        $razones = [];
                        if ($conductor->dias_acumulados >= sipat_config('dias_maximos_sin_descanso', 6)) {
                            $razones[] = 'Necesita descanso';
                        }
                        if ($conductor->eficiencia < sipat_config('eficiencia_minima_conductor', 80)) {
                            $razones[] = 'Eficiencia baja';
                        }
                        if ($conductor->puntualidad < sipat_config('puntualidad_minima_conductor', 85)) {
                            $razones[] = 'Puntualidad baja';
                        }

                        return [
                            'id' => $conductor->id,
                            'codigo' => $conductor->codigo,
                            'nombre_completo' => $conductor->nombres . ' ' . $conductor->apellidos,
                            'estado' => $conductor->estado,
                            'dias_acumulados' => $conductor->dias_acumulados,
                            'eficiencia' => $conductor->eficiencia,
                            'puntualidad' => $conductor->puntualidad,
                            'razones' => $razones,
                            'prioridad' => $this->calcularPrioridadConductor($conductor)
                        ];
                    })
                    ->sortByDesc('prioridad')
                    ->values()
                    ->toArray();
                } catch (\Exception $e) {
                    Log::error('Error obteniendo conductores críticos: ' . $e->getMessage());
                    return [];
                }
            }
        );
    }

    /**
     * Calcular prioridad de atención para conductor crítico
     */
    private function calcularPrioridadConductor($conductor): int
    {
        $prioridad = 0;

        // Días acumulados (40% del peso)
        if ($conductor->dias_acumulados >= sipat_config('dias_maximos_sin_descanso', 6)) {
            $prioridad += ($conductor->dias_acumulados - 6) * 10 + 40;
        }

        // Eficiencia baja (30% del peso)
        $eficienciaMinima = sipat_config('eficiencia_minima_conductor', 80);
        if ($conductor->eficiencia < $eficienciaMinima) {
            $prioridad += ($eficienciaMinima - $conductor->eficiencia) + 30;
        }

        // Puntualidad baja (30% del peso)
        $puntualidadMinima = sipat_config('puntualidad_minima_conductor', 85);
        if ($conductor->puntualidad < $puntualidadMinima) {
            $prioridad += ($puntualidadMinima - $conductor->puntualidad) + 30;
        }

        return min($prioridad, 100);
    }

    /**
     * Invalidar cache de métricas de un conductor específico
     */
    public function invalidarMetricasConductor(int $conductorId): void
    {
        try {
            $claves = [
                self::PREFIX_CONDUCTOR . "metricas_{$conductorId}",
                self::PREFIX_CONDUCTOR . 'criticos',
                self::PREFIX_DASHBOARD . 'principal',
                self::PREFIX_DASHBOARD . 'tendencias_semanales'
            ];

            foreach ($claves as $clave) {
                Cache::forget($clave);
            }

            Log::info("Cache invalidado para conductor {$conductorId}");
        } catch (\Exception $e) {
            Log::error("Error invalidando cache del conductor {$conductorId}: " . $e->getMessage());
        }
    }

    /**
     * Invalidar cache de usuario específico
     */
    public function invalidarCacheUsuario(int $usuarioId): void
    {
        try {
            $claves = [
                self::PREFIX_DASHBOARD . "usuario_{$usuarioId}",
                self::PREFIX_SISTEMA . "notificaciones_{$usuarioId}",
            ];

            foreach ($claves as $clave) {
                Cache::forget($clave);
            }
        } catch (\Exception $e) {
            Log::error("Error invalidando cache del usuario {$usuarioId}: " . $e->getMessage());
        }
    }

    /**
     * Limpiar todo el cache de métricas
     */
    public function limpiarCacheMetricas(): void
    {
        try {
            $prefijos = [
                self::PREFIX_DASHBOARD,
                self::PREFIX_CONDUCTOR,
                self::PREFIX_VALIDACION,
                self::PREFIX_TURNO,
                self::PREFIX_SISTEMA,
                self::PREFIX_REPORTES
            ];

            $contador = 0;
            foreach ($prefijos as $prefijo) {
                // Laravel no tiene método nativo para limpiar por prefijo,
                // así que limpiamos las claves conocidas más importantes
                $claves = $this->obtenerClavesConocidas($prefijo);
                foreach ($claves as $clave) {
                    if (Cache::forget($clave)) {
                        $contador++;
                    }
                }
            }

            Log::info("Cache de métricas limpiado: {$contador} claves eliminadas");
        } catch (\Exception $e) {
            Log::error('Error limpiando cache de métricas: ' . $e->getMessage());
        }
    }

    /**
     * Obtener claves de cache conocidas por prefijo
     */
    private function obtenerClavesConocidas(string $prefijo): array
    {
        $claves = [
            self::PREFIX_DASHBOARD => [
                'principal',
                'tendencias_semanales'
            ],
            self::PREFIX_CONDUCTOR => [
                'criticos'
            ],
            self::PREFIX_SISTEMA => [
                'estado',
                'metricas_basicas'
            ]
        ];

        $clavesCompletas = [];
        $clavesBase = $claves[$prefijo] ?? [];

        foreach ($clavesBase as $clave) {
            $clavesCompletas[] = $prefijo . $clave;
        }

        return $clavesCompletas;
    }

    /**
     * Obtener métricas vacías por defecto
     */
    private function obtenerMetricasVacias(): array
    {
        return [
            'conductores' => [
                'total' => 0,
                'disponibles' => 0,
                'en_descanso' => 0,
                'criticos' => 0,
                'bajo_rendimiento' => 0,
                'porcentaje_disponibilidad' => 0,
                'promedio_eficiencia' => 0,
                'promedio_puntualidad' => 0,
                'score_general' => 0,
                'estados' => []
            ],
            'validaciones' => [
                'pendientes' => 0,
                'criticas' => 0,
                'resueltas_hoy' => 0,
                'total_hoy' => 0,
                'tiempo_promedio_resolucion' => 0,
                'efectividad_diaria' => 0,
                'distribucion_severidad' => [],
                'tipos_frecuentes' => []
            ],
            'turnos' => [
                'hoy' => 0,
                'completados' => 0,
                'en_curso' => 0,
                'pendientes' => 0,
                'cobertura_porcentaje' => 0,
                'horas_hombre_dia' => 0,
                'eficiencia_promedio' => 0,
                'distribucion_estados' => []
            ],
            'sistema' => [
                'version' => config('sipat.version', '1.0.0'),
                'estado' => 'ERROR',
                'configuraciones_totales' => 0,
                'usuarios_activos_mes' => 0,
                'memoria_uso_mb' => 0,
                'memoria_limite' => 'N/A',
                'memoria_porcentaje' => 0,
                'uptime' => 'N/A',
                'ultima_actualizacion' => now()->toISOString()
            ],
            'eficiencia' => [
                'general' => 0,
                'conductores' => 0,
                'turnos' => 0,
                'validaciones' => 0,
                'tendencia' => 'ESTABLE'
            ],
            'alertas' => [
                'total' => 0,
                'criticas' => 0,
                'advertencias' => 0,
                'detalles' => []
            ],
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Obtener estadísticas del cache
     */
    public function obtenerEstadisticasCache(): array
    {
        try {
            return [
                'estado' => 'OPERATIVO',
                'driver' => config('cache.default'),
                'ttl_configurado' => [
                    'dashboard' => self::TTL_DASHBOARD . ' minutos',
                    'tendencias' => self::TTL_TENDENCIAS . ' minutos',
                    'criticos' => self::TTL_CRITICOS . ' minutos',
                    'metricas_basicas' => self::TTL_METRICAS_BASICAS . ' minutos',
                    'estadisticas' => self::TTL_ESTADISTICAS . ' minutos',
                    'reportes' => self::TTL_REPORTES . ' minutos'
                ],
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'estado' => 'ERROR',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }
}
