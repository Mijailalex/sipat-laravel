<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MetricaDiaria extends Model
{
    use HasFactory;

    protected $table = 'metricas_diarias';

    protected $fillable = [
        'fecha',
        'total_conductores',
        'conductores_disponibles',
        'conductores_descanso',
        'conductores_suspendidos',
        'validaciones_pendientes',
        'validaciones_criticas',
        'eficiencia_promedio',
        'puntualidad_promedio',
        'turnos_programados',
        'turnos_completados',
        'rutas_cortas_completadas',
        'ingresos_estimados_rutas',
        'metricas_adicionales'
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_conductores' => 'integer',
        'conductores_disponibles' => 'integer',
        'conductores_descanso' => 'integer',
        'conductores_suspendidos' => 'integer',
        'validaciones_pendientes' => 'integer',
        'validaciones_criticas' => 'integer',
        'turnos_programados' => 'integer',
        'turnos_completados' => 'integer',
        'rutas_cortas_completadas' => 'integer',
        'eficiencia_promedio' => 'decimal:2',
        'puntualidad_promedio' => 'decimal:2',
        'ingresos_estimados_rutas' => 'decimal:2',
        'metricas_adicionales' => 'array'
    ];

    // Scopes
    public function scopeFecha($query, $fecha)
    {
        return $query->where('fecha', $fecha);
    }

    public function scopeRangoFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    public function scopeUltimaSemana($query)
    {
        return $query->where('fecha', '>=', now()->subDays(7));
    }

    public function scopeUltimoMes($query)
    {
        return $query->where('fecha', '>=', now()->subDays(30));
    }

    // Métodos de negocio
    public function getPorcentajeDisponibilidadAttribute()
    {
        return $this->total_conductores > 0
            ? round(($this->conductores_disponibles / $this->total_conductores) * 100, 2)
            : 0;
    }

    public function getPorcentajeEficienciaTurnosAttribute()
    {
        return $this->turnos_programados > 0
            ? round(($this->turnos_completados / $this->turnos_programados) * 100, 2)
            : 0;
    }

    public function getIngresoPromedioPorRutaAttribute()
    {
        return $this->rutas_cortas_completadas > 0
            ? round($this->ingresos_estimados_rutas / $this->rutas_cortas_completadas, 2)
            : 0;
    }

    public function getIndiceSaludSistemaAttribute()
    {
        $indice = 0;
        $factores = 0;

        // Factor disponibilidad (30%)
        if ($this->total_conductores > 0) {
            $indice += ($this->porcentaje_disponibilidad * 0.3);
            $factores += 0.3;
        }

        // Factor eficiencia turnos (25%)
        if ($this->turnos_programados > 0) {
            $indice += ($this->porcentaje_eficiencia_turnos * 0.25);
            $factores += 0.25;
        }

        // Factor eficiencia conductores (20%)
        $indice += ($this->eficiencia_promedio * 0.2);
        $factores += 0.2;

        // Factor puntualidad (15%)
        $indice += ($this->puntualidad_promedio * 0.15);
        $factores += 0.15;

        // Factor validaciones críticas (10% - negativo)
        $porcentajeValidacionesCriticas = $this->validaciones_pendientes > 0
            ? ($this->validaciones_criticas / $this->validaciones_pendientes) * 100
            : 0;
        $indice += ((100 - $porcentajeValidacionesCriticas) * 0.1);
        $factores += 0.1;

        return $factores > 0 ? round($indice / $factores, 2) : 0;
    }

    public static function generarMetricasHoy()
    {
        $fecha = now()->toDateString();

        // Verificar si ya existen métricas para hoy
        $metricaExistente = static::where('fecha', $fecha)->first();

        if ($metricaExistente) {
            return static::actualizarMetricas($fecha);
        }

        return static::crearNuevasMetricas($fecha);
    }

    public static function crearNuevasMetricas($fecha)
    {
        $datos = static::calcularDatosMetricas($fecha);

        return static::create(array_merge(['fecha' => $fecha], $datos));
    }

    public static function actualizarMetricas($fecha)
    {
        $datos = static::calcularDatosMetricas($fecha);

        return static::updateOrCreate(
            ['fecha' => $fecha],
            $datos
        );
    }

    private static function calcularDatosMetricas($fecha)
    {
        // Métricas de conductores
        $totalConductores = Conductor::count();
        $conductoresDisponibles = Conductor::where('estado', 'DISPONIBLE')->count();
        $conductoresDescanso = Conductor::whereIn('estado', ['DESCANSO_FISICO', 'DESCANSO_SEMANAL'])->count();
        $conductoresSuspendidos = Conductor::where('estado', 'SUSPENDIDO')->count();

        // Métricas de eficiencia y puntualidad
        $promedioEficiencia = Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])
            ->avg('eficiencia') ?: 0;
        $promedioPuntualidad = Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])
            ->avg('puntualidad') ?: 0;

        // Métricas de validaciones
        $validacionesPendientes = Validacion::where('estado', 'PENDIENTE')->count();
        $validacionesCriticas = Validacion::where('estado', 'PENDIENTE')
            ->where('severidad', 'CRITICA')->count();

        // Métricas de turnos
        $turnosProgramados = Turno::where('fecha_turno', $fecha)->count();
        $turnosCompletados = Turno::where('fecha_turno', $fecha)
            ->where('estado', 'COMPLETADO')->count();

        // Métricas de rutas cortas
        $rutasCortasCompletadas = RutaCorta::where('fecha', $fecha)
            ->where('estado', 'COMPLETADA')->count();
        $ingresosRutas = RutaCorta::where('fecha', $fecha)
            ->where('estado', 'COMPLETADA')
            ->sum('ingreso_estimado');

        // Métricas adicionales
        $metricasAdicionales = [
            'conductores_criticos' => Conductor::where('dias_acumulados', '>=', 6)
                ->orWhere('eficiencia', '<', 80)
                ->orWhere('puntualidad', '<', 85)
                ->count(),
            'promedio_dias_acumulados' => Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])
                ->avg('dias_acumulados') ?: 0,
            'buses_operativos' => Bus::where('estado', 'OPERATIVO')->count(),
            'buses_mantenimiento' => Bus::where('estado', 'MANTENIMIENTO')->count(),
            'plantillas_activas' => Plantilla::where('activa', true)->count(),
            'tramos_activos' => ConfiguracionTramo::where('activo', true)->count()
        ];

        return [
            'total_conductores' => $totalConductores,
            'conductores_disponibles' => $conductoresDisponibles,
            'conductores_descanso' => $conductoresDescanso,
            'conductores_suspendidos' => $conductoresSuspendidos,
            'validaciones_pendientes' => $validacionesPendientes,
            'validaciones_criticas' => $validacionesCriticas,
            'eficiencia_promedio' => round($promedioEficiencia, 2),
            'puntualidad_promedio' => round($promedioPuntualidad, 2),
            'turnos_programados' => $turnosProgramados,
            'turnos_completados' => $turnosCompletados,
            'rutas_cortas_completadas' => $rutasCortasCompletadas,
            'ingresos_estimados_rutas' => round($ingresosRutas, 2),
            'metricas_adicionales' => $metricasAdicionales
        ];
    }

    public static function obtenerTendencias($dias = 30)
    {
        return static::selectRaw('
                fecha,
                porcentaje_disponibilidad,
                eficiencia_promedio,
                puntualidad_promedio,
                porcentaje_eficiencia_turnos,
                validaciones_criticas,
                ingresos_estimados_rutas,
                indice_salud_sistema
            ')
            ->where('fecha', '>=', now()->subDays($dias))
            ->orderBy('fecha')
            ->get()
            ->map(function ($metrica) {
                return [
                    'fecha' => $metrica->fecha->format('Y-m-d'),
                    'disponibilidad' => $metrica->porcentaje_disponibilidad,
                    'eficiencia' => $metrica->eficiencia_promedio,
                    'puntualidad' => $metrica->puntualidad_promedio,
                    'eficiencia_turnos' => $metrica->porcentaje_eficiencia_turnos,
                    'validaciones_criticas' => $metrica->validaciones_criticas,
                    'ingresos' => $metrica->ingresos_estimados_rutas,
                    'salud_sistema' => $metrica->indice_salud_sistema
                ];
            });
    }

    public static function obtenerComparacionSemanal()
    {
        $semanaActual = static::where('fecha', '>=', now()->startOfWeek())
            ->where('fecha', '<=', now()->endOfWeek())
            ->get();

        $semanaAnterior = static::where('fecha', '>=', now()->subWeek()->startOfWeek())
            ->where('fecha', '<=', now()->subWeek()->endOfWeek())
            ->get();

        $promediosActual = [
            'disponibilidad' => $semanaActual->avg('porcentaje_disponibilidad'),
            'eficiencia' => $semanaActual->avg('eficiencia_promedio'),
            'puntualidad' => $semanaActual->avg('puntualidad_promedio'),
            'ingresos' => $semanaActual->sum('ingresos_estimados_rutas'),
            'salud_sistema' => $semanaActual->avg('indice_salud_sistema')
        ];

        $promediosAnterior = [
            'disponibilidad' => $semanaAnterior->avg('porcentaje_disponibilidad'),
            'eficiencia' => $semanaAnterior->avg('eficiencia_promedio'),
            'puntualidad' => $semanaAnterior->avg('puntualidad_promedio'),
            'ingresos' => $semanaAnterior->sum('ingresos_estimados_rutas'),
            'salud_sistema' => $semanaAnterior->avg('indice_salud_sistema')
        ];

        return [
            'semana_actual' => $promediosActual,
            'semana_anterior' => $promediosAnterior,
            'variaciones' => [
                'disponibilidad' => static::calcularVariacion($promediosActual['disponibilidad'], $promediosAnterior['disponibilidad']),
                'eficiencia' => static::calcularVariacion($promediosActual['eficiencia'], $promediosAnterior['eficiencia']),
                'puntualidad' => static::calcularVariacion($promediosActual['puntualidad'], $promediosAnterior['puntualidad']),
                'ingresos' => static::calcularVariacion($promediosActual['ingresos'], $promediosAnterior['ingresos']),
                'salud_sistema' => static::calcularVariacion($promediosActual['salud_sistema'], $promediosAnterior['salud_sistema'])
            ]
        ];
    }

    private static function calcularVariacion($actual, $anterior)
    {
        if ($anterior == 0) {
            return $actual > 0 ? 100 : 0;
        }

        return round((($actual - $anterior) / $anterior) * 100, 2);
    }

    public static function obtenerResumenMensual($mes = null, $año = null)
    {
        $mes = $mes ?: now()->month;
        $año = $año ?: now()->year;

        $fechaInicio = Carbon::create($año, $mes, 1)->startOfMonth();
        $fechaFin = $fechaInicio->copy()->endOfMonth();

        $metricas = static::whereBetween('fecha', [$fechaInicio, $fechaFin])->get();

        if ($metricas->isEmpty()) {
            return null;
        }

        return [
            'periodo' => $fechaInicio->format('F Y'),
            'dias_registrados' => $metricas->count(),
            'promedios' => [
                'conductores_disponibles' => round($metricas->avg('conductores_disponibles'), 0),
                'eficiencia' => round($metricas->avg('eficiencia_promedio'), 2),
                'puntualidad' => round($metricas->avg('puntualidad_promedio'), 2),
                'disponibilidad' => round($metricas->avg('porcentaje_disponibilidad'), 2),
                'salud_sistema' => round($metricas->avg('indice_salud_sistema'), 2)
            ],
            'totales' => [
                'turnos_completados' => $metricas->sum('turnos_completados'),
                'rutas_completadas' => $metricas->sum('rutas_cortas_completadas'),
                'ingresos' => $metricas->sum('ingresos_estimados_rutas'),
                'validaciones_resueltas' => $metricas->sum('validaciones_pendientes') - $metricas->last()->validaciones_pendientes
            ],
            'mejor_dia' => $metricas->sortByDesc('indice_salud_sistema')->first(),
            'peor_dia' => $metricas->sortBy('indice_salud_sistema')->first()
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($metrica) {
            // Verificar que no exista una métrica para la misma fecha
            $existe = static::where('fecha', $metrica->fecha)->exists();
            if ($existe) {
                throw new \Exception("Ya existe una métrica para la fecha {$metrica->fecha}");
            }
        });
    }
}
