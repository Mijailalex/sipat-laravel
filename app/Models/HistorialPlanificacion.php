<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class HistorialPlanificacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historial_planificaciones';

    protected $fillable = [
        'fecha_planificacion',
        'estado',
        'tipo_planificacion',
        'plantilla_id',
        'usuario_id',
        'resultado',
        'error',
        'metricas',
        'configuracion_utilizada',
        'cambios_realizados',
        'turnos_afectados',
        'conductores_afectados',
        'validaciones_generadas',
        'tiempo_procesamiento',
        'observaciones',
        'created_by'
    ];

    protected $casts = [
        'fecha_planificacion' => 'date',
        'resultado' => 'array',
        'metricas' => 'array',
        'configuracion_utilizada' => 'array',
        'cambios_realizados' => 'array',
        'turnos_afectados' => 'array',
        'conductores_afectados' => 'array',
        'validaciones_generadas' => 'integer',
        'tiempo_procesamiento' => 'integer'
    ];

    // Estados posibles del historial
    const ESTADO_INICIADO = 'INICIADO';
    const ESTADO_EN_PROCESO = 'EN_PROCESO';
    const ESTADO_COMPLETADO = 'COMPLETADO';
    const ESTADO_ERROR = 'ERROR';
    const ESTADO_CANCELADO = 'CANCELADO';
    const ESTADO_OPTIMIZADO = 'OPTIMIZADO';

    // Tipos de planificación
    const TIPO_AUTOMATICA = 'AUTOMATICA';
    const TIPO_MANUAL = 'MANUAL';
    const TIPO_REPLANIFICACION = 'REPLANIFICACION';
    const TIPO_AJUSTE = 'AJUSTE';
    const TIPO_EMERGENCIA = 'EMERGENCIA';

    // =============================================================================
    // RELACIONES
    // =============================================================================

    public function plantilla()
    {
        return $this->belongsTo(Plantilla::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validaciones()
    {
        return $this->hasMany(Validacion::class, 'historial_planificacion_id');
    }

    // =============================================================================
    // SCOPES (FILTROS)
    // =============================================================================

    public function scopeDelDia($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();
        return $query->whereDate('fecha_planificacion', $fecha);
    }

    public function scopeDelMes($query, $year = null, $month = null)
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;
        return $query->whereYear('fecha_planificacion', $year)
                     ->whereMonth('fecha_planificacion', $month);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_planificacion', $tipo);
    }

    public function scopeExitosos($query)
    {
        return $query->where('estado', self::ESTADO_COMPLETADO);
    }

    public function scopeConErrores($query)
    {
        return $query->where('estado', self::ESTADO_ERROR);
    }

    public function scopeRecientes($query, $dias = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    // =============================================================================
    // MÉTODOS ESTÁTICOS DE CONSULTA
    // =============================================================================

    /**
     * Obtener estadísticas del historial de planificaciones
     */
    public static function obtenerEstadisticas($fechaInicio = null, $fechaFin = null)
    {
        $fechaInicio = $fechaInicio ?? now()->subDays(30);
        $fechaFin = $fechaFin ?? now();

        $query = static::whereBetween('created_at', [$fechaInicio, $fechaFin]);

        return [
            'total_planificaciones' => $query->count(),
            'planificaciones_exitosas' => $query->where('estado', self::ESTADO_COMPLETADO)->count(),
            'planificaciones_con_errores' => $query->where('estado', self::ESTADO_ERROR)->count(),
            'por_tipo' => $query->selectRaw('tipo_planificacion, COUNT(*) as total')
                                ->groupBy('tipo_planificacion')
                                ->pluck('total', 'tipo_planificacion')
                                ->toArray(),
            'por_estado' => $query->selectRaw('estado, COUNT(*) as total')
                                  ->groupBy('estado')
                                  ->pluck('total', 'estado')
                                  ->toArray(),
            'tiempo_promedio' => $query->where('tiempo_procesamiento', '>', 0)
                                      ->avg('tiempo_procesamiento'),
            'conductores_mas_afectados' => static::obtenerConductoresMasAfectados($fechaInicio, $fechaFin),
            'dias_mas_activos' => static::obtenerDiasMasActivos($fechaInicio, $fechaFin)
        ];
    }

    /**
     * Obtener el historial de cambios de una plantilla específica
     */
    public static function obtenerHistorialPlantilla($plantillaId)
    {
        return static::where('plantilla_id', $plantillaId)
                     ->orderBy('created_at', 'desc')
                     ->get()
                     ->map(function($historial) {
                         return [
                             'id' => $historial->id,
                             'fecha' => $historial->created_at,
                             'tipo' => $historial->tipo_planificacion,
                             'estado' => $historial->estado,
                             'usuario' => $historial->usuario?->name ?? 'Sistema',
                             'cambios' => $historial->obtenerResumenCambios(),
                             'metricas' => $historial->obtenerMetricasResumen(),
                             'observaciones' => $historial->observaciones
                         ];
                     });
    }

    /**
     * Obtener últimas planificaciones con resumen
     */
    public static function obtenerRecientesConResumen($limite = 10)
    {
        return static::with(['plantilla', 'usuario'])
                     ->orderBy('created_at', 'desc')
                     ->limit($limite)
                     ->get()
                     ->map(function($historial) {
                         return [
                             'id' => $historial->id,
                             'fecha_planificacion' => $historial->fecha_planificacion,
                             'fecha_procesamiento' => $historial->created_at,
                             'tipo' => $historial->tipo_planificacion,
                             'estado' => $historial->estado,
                             'plantilla_id' => $historial->plantilla_id,
                             'usuario' => $historial->usuario?->name ?? 'Sistema',
                             'resumen' => $historial->obtenerResumenEjecucion(),
                             'duracion' => $historial->tiempo_procesamiento ?
                                          $historial->tiempo_procesamiento . ' segundos' : 'N/A',
                             'turnos_procesados' => $historial->metricas['asignaciones_realizadas'] ?? 0,
                             'validaciones' => $historial->validaciones_generadas ?? 0
                         ];
                     });
    }

    // =============================================================================
    // MÉTODOS DE ANÁLISIS Y REPORTES
    // =============================================================================

    /**
     * Analizar tendencias de planificación
     */
    public static function analizarTendencias($periodo = 30)
    {
        $fechaInicio = now()->subDays($periodo);

        $datos = static::where('created_at', '>=', $fechaInicio)
                       ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total_planificaciones,
                                  SUM(CASE WHEN estado = "COMPLETADO" THEN 1 ELSE 0 END) as exitosas,
                                  AVG(tiempo_procesamiento) as tiempo_promedio,
                                  AVG(JSON_EXTRACT(metricas, "$.asignaciones_realizadas")) as asignaciones_promedio')
                       ->groupBy('fecha')
                       ->orderBy('fecha')
                       ->get();

        return [
            'periodo_analizado' => $periodo,
            'datos_diarios' => $datos,
            'tendencia_exito' => static::calcularTendenciaExito($datos),
            'tendencia_tiempo' => static::calcularTendenciaTiempo($datos),
            'promedio_asignaciones' => $datos->avg('asignaciones_promedio'),
            'pico_actividad' => $datos->sortByDesc('total_planificaciones')->first()
        ];
    }

    /**
     * Obtener reporte de eficiencia del sistema
     */
    public static function reporteEficiencia($fechaInicio = null, $fechaFin = null)
    {
        $fechaInicio = $fechaInicio ?? now()->subDays(30);
        $fechaFin = $fechaFin ?? now();

        $planificaciones = static::whereBetween('created_at', [$fechaInicio, $fechaFin])
                                 ->where('estado', self::ESTADO_COMPLETADO)
                                 ->get();

        if ($planificaciones->isEmpty()) {
            return ['mensaje' => 'No hay datos suficientes para el reporte'];
        }

        $tiempoPromedio = $planificaciones->avg('tiempo_procesamiento');
        $asignacionesPromedio = $planificaciones->avg(function($p) {
            return $p->metricas['asignaciones_realizadas'] ?? 0;
        });

        return [
            'periodo' => [
                'inicio' => $fechaInicio->format('Y-m-d'),
                'fin' => $fechaFin->format('Y-m-d')
            ],
            'metricas_generales' => [
                'total_planificaciones' => $planificaciones->count(),
                'tiempo_promedio' => round($tiempoPromedio, 2) . ' segundos',
                'asignaciones_promedio' => round($asignacionesPromedio, 0),
                'eficiencia_asignacion' => static::calcularEficienciaAsignacion($planificaciones)
            ],
            'rendimiento_por_tipo' => static::analizarRendimientoPorTipo($planificaciones),
            'patrones_horarios' => static::analizarPatronesHorarios($planificaciones),
            'recomendaciones' => static::generarRecomendaciones($planificaciones)
        ];
    }

    // =============================================================================
    // MÉTODOS DE INSTANCIA
    // =============================================================================

    /**
     * Obtener resumen de la ejecución
     */
    public function obtenerResumenEjecucion()
    {
        $resumen = "Planificación {$this->tipo_planificacion}";

        if ($this->estado === self::ESTADO_COMPLETADO) {
            $asignaciones = $this->metricas['asignaciones_realizadas'] ?? 0;
            $resumen .= " completada con {$asignaciones} asignaciones";

            if ($this->validaciones_generadas > 0) {
                $resumen .= " y {$this->validaciones_generadas} validaciones";
            }
        } elseif ($this->estado === self::ESTADO_ERROR) {
            $resumen .= " falló: " . ($this->error ?? 'Error desconocido');
        } else {
            $resumen .= " en estado: {$this->estado}";
        }

        return $resumen;
    }

    /**
     * Obtener resumen de cambios realizados
     */
    public function obtenerResumenCambios()
    {
        if (!$this->cambios_realizados) {
            return 'Sin cambios registrados';
        }

        $cambios = $this->cambios_realizados;
        $resumen = [];

        if (isset($cambios['conductores_reasignados'])) {
            $resumen[] = "Reasignados: {$cambios['conductores_reasignados']} conductores";
        }

        if (isset($cambios['turnos_modificados'])) {
            $resumen[] = "Modificados: {$cambios['turnos_modificados']} turnos";
        }

        if (isset($cambios['nuevas_asignaciones'])) {
            $resumen[] = "Nuevas asignaciones: {$cambios['nuevas_asignaciones']}";
        }

        return implode(', ', $resumen) ?: 'Cambios menores';
    }

    /**
     * Obtener métricas en formato resumido
     */
    public function obtenerMetricasResumen()
    {
        if (!$this->metricas) {
            return [];
        }

        return [
            'conductores_procesados' => $this->metricas['conductores_procesados'] ?? 0,
            'asignaciones_realizadas' => $this->metricas['asignaciones_realizadas'] ?? 0,
            'tiempo_procesamiento' => $this->tiempo_procesamiento . ' seg',
            'eficiencia' => $this->calcularEficienciaLocal()
        ];
    }

    /**
     * Verificar si la planificación fue exitosa
     */
    public function fueExitosa()
    {
        return $this->estado === self::ESTADO_COMPLETADO;
    }

    /**
     * Verificar si hay errores críticos
     */
    public function tieneErroresCriticos()
    {
        return $this->estado === self::ESTADO_ERROR ||
               (!empty($this->metricas['errores']) && count($this->metricas['errores']) > 0);
    }

    /**
     * Obtener duración formateada
     */
    public function obtenerDuracionFormateada()
    {
        if (!$this->tiempo_procesamiento) {
            return 'N/A';
        }

        $segundos = $this->tiempo_procesamiento;

        if ($segundos < 60) {
            return $segundos . ' segundos';
        }

        $minutos = floor($segundos / 60);
        $segundosRestantes = $segundos % 60;

        return $minutos . ' min ' . $segundosRestantes . ' seg';
    }

    // =============================================================================
    // MÉTODOS PRIVADOS DE ANÁLISIS
    // =============================================================================

    private static function obtenerConductoresMasAfectados($fechaInicio, $fechaFin)
    {
        // Analizar qué conductores aparecen más frecuentemente en los cambios
        $historiales = static::whereBetween('created_at', [$fechaInicio, $fechaFin])
                             ->whereNotNull('conductores_afectados')
                             ->get();

        $conteos = [];
        foreach ($historiales as $historial) {
            if ($historial->conductores_afectados) {
                foreach ($historial->conductores_afectados as $conductorId) {
                    $conteos[$conductorId] = ($conteos[$conductorId] ?? 0) + 1;
                }
            }
        }

        arsort($conteos);
        return array_slice($conteos, 0, 5, true);
    }

    private static function obtenerDiasMasActivos($fechaInicio, $fechaFin)
    {
        return static::whereBetween('created_at', [$fechaInicio, $fechaFin])
                     ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                     ->groupBy('fecha')
                     ->orderByDesc('total')
                     ->limit(5)
                     ->pluck('total', 'fecha')
                     ->toArray();
    }

    private static function calcularTendenciaExito($datos)
    {
        if ($datos->count() < 2) return 'Insuficientes datos';

        $exitos = $datos->map(function($dia) {
            return $dia->total_planificaciones > 0 ?
                   ($dia->exitosas / $dia->total_planificaciones) * 100 : 0;
        });

        $tendencia = $exitos->last() - $exitos->first();

        if ($tendencia > 5) return 'Mejorando';
        if ($tendencia < -5) return 'Empeorando';
        return 'Estable';
    }

    private static function calcularTendenciaTiempo($datos)
    {
        if ($datos->count() < 2) return 'Insuficientes datos';

        $tiempos = $datos->whereNotNull('tiempo_promedio');
        if ($tiempos->count() < 2) return 'Sin datos de tiempo';

        $diferencia = $tiempos->last()->tiempo_promedio - $tiempos->first()->tiempo_promedio;

        if ($diferencia > 10) return 'Ralentizando';
        if ($diferencia < -10) return 'Acelerando';
        return 'Estable';
    }

    private static function calcularEficienciaAsignacion($planificaciones)
    {
        $totalPosibles = $planificaciones->sum(function($p) {
            return $p->metricas['conductores_procesados'] ?? 0;
        });

        $totalAsignados = $planificaciones->sum(function($p) {
            return $p->metricas['asignaciones_realizadas'] ?? 0;
        });

        return $totalPosibles > 0 ? round(($totalAsignados / $totalPosibles) * 100, 2) : 0;
    }

    private static function analizarRendimientoPorTipo($planificaciones)
    {
        return $planificaciones->groupBy('tipo_planificacion')
                               ->map(function($grupo) {
                                   return [
                                       'cantidad' => $grupo->count(),
                                       'tiempo_promedio' => round($grupo->avg('tiempo_procesamiento'), 2),
                                       'asignaciones_promedio' => round($grupo->avg(function($p) {
                                           return $p->metricas['asignaciones_realizadas'] ?? 0;
                                       }), 0)
                                   ];
                               })
                               ->toArray();
    }

    private static function analizarPatronesHorarios($planificaciones)
    {
        return $planificaciones->groupBy(function($p) {
                                   return $p->created_at->hour;
                               })
                               ->map(function($grupo, $hora) {
                                   return [
                                       'hora' => $hora . ':00',
                                       'cantidad' => $grupo->count(),
                                       'tiempo_promedio' => round($grupo->avg('tiempo_procesamiento'), 2)
                                   ];
                               })
                               ->sortBy('hora')
                               ->values()
                               ->toArray();
    }

    private static function generarRecomendaciones($planificaciones)
    {
        $recomendaciones = [];

        $tiempoPromedio = $planificaciones->avg('tiempo_procesamiento');
        if ($tiempoPromedio > 300) { // 5 minutos
            $recomendaciones[] = 'Considerar optimizar el algoritmo de asignación para reducir tiempos de procesamiento';
        }

        $errorRate = $planificaciones->where('estado', '!=', self::ESTADO_COMPLETADO)->count() / $planificaciones->count();
        if ($errorRate > 0.1) { // 10% de errores
            $recomendaciones[] = 'Alto índice de errores detectado, revisar validaciones de entrada';
        }

        $asignacionPromedio = $planificaciones->avg(function($p) {
            return $p->metricas['asignaciones_realizadas'] ?? 0;
        });
        if ($asignacionPromedio < 10) {
            $recomendaciones[] = 'Pocas asignaciones por planificación, verificar disponibilidad de conductores';
        }

        return $recomendaciones ?: ['Sistema funcionando dentro de parámetros normales'];
    }

    private function calcularEficienciaLocal()
    {
        if (!$this->metricas) return 'N/A';

        $procesados = $this->metricas['conductores_procesados'] ?? 0;
        $asignados = $this->metricas['asignaciones_realizadas'] ?? 0;

        return $procesados > 0 ? round(($asignados / $procesados) * 100, 1) . '%' : 'N/A';
    }

    // =============================================================================
    // MÉTODOS DE UTILIDAD
    // =============================================================================

    /**
     * Limpiar historial antiguo
     */
    public static function limpiarHistorialAntiguo($dias = 90)
    {
        $fechaLimite = now()->subDays($dias);

        return static::where('created_at', '<', $fechaLimite)
                     ->where('estado', '!=', self::ESTADO_ERROR) // Conservar errores más tiempo
                     ->delete();
    }

    /**
     * Exportar historial para análisis
     */
    public static function exportarParaAnalisis($fechaInicio, $fechaFin)
    {
        return static::whereBetween('created_at', [$fechaInicio, $fechaFin])
                     ->select([
                         'fecha_planificacion',
                         'tipo_planificacion',
                         'estado',
                         'tiempo_procesamiento',
                         'validaciones_generadas',
                         'metricas',
                         'created_at'
                     ])
                     ->get()
                     ->map(function($historial) {
                         return [
                             'fecha_planificacion' => $historial->fecha_planificacion,
                             'tipo' => $historial->tipo_planificacion,
                             'estado' => $historial->estado,
                             'duracion_segundos' => $historial->tiempo_procesamiento,
                             'validaciones' => $historial->validaciones_generadas,
                             'conductores_procesados' => $historial->metricas['conductores_procesados'] ?? 0,
                             'asignaciones_realizadas' => $historial->metricas['asignaciones_realizadas'] ?? 0,
                             'fecha_procesamiento' => $historial->created_at
                         ];
                     });
    }
}
