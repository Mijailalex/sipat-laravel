<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BalanceRutasCortas extends Model
{
    use HasFactory;

    protected $table = 'balance_rutas_cortas';

    protected $fillable = [
        'fecha',
        'tramo',
        'total_rutas',
        'rutas_completadas',
        'rutas_canceladas',
        'total_pasajeros',
        'ingreso_total',
        'ingreso_promedio_ruta',
        'ocupacion_promedio',
        'conductores_participantes',
        'eficiencia_promedio',
        'metricas_adicionales'
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_rutas' => 'integer',
        'rutas_completadas' => 'integer',
        'rutas_canceladas' => 'integer',
        'total_pasajeros' => 'integer',
        'conductores_participantes' => 'integer',
        'ingreso_total' => 'decimal:2',
        'ingreso_promedio_ruta' => 'decimal:2',
        'ocupacion_promedio' => 'decimal:2',
        'eficiencia_promedio' => 'decimal:2',
        'metricas_adicionales' => 'array'
    ];

    // Relaciones
    public function configuracionTramo()
    {
        return $this->belongsTo(ConfiguracionTramo::class, 'tramo', 'codigo_tramo');
    }

    // Scopes
    public function scopeFecha($query, $fecha)
    {
        return $query->where('fecha', $fecha);
    }

    public function scopeRangoFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    public function scopeTramo($query, $tramo)
    {
        return $query->where('tramo', $tramo);
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
    public function getPorcentajeCompletadasAttribute()
    {
        return $this->total_rutas > 0
            ? round(($this->rutas_completadas / $this->total_rutas) * 100, 2)
            : 0;
    }

    public function getPorcentajeCanceladasAttribute()
    {
        return $this->total_rutas > 0
            ? round(($this->rutas_canceladas / $this->total_rutas) * 100, 2)
            : 0;
    }

    public function getPromedioPasajerosPorRutaAttribute()
    {
        return $this->rutas_completadas > 0
            ? round($this->total_pasajeros / $this->rutas_completadas, 2)
            : 0;
    }

    public function getIngresoPorPasajeroAttribute()
    {
        return $this->total_pasajeros > 0
            ? round($this->ingreso_total / $this->total_pasajeros, 2)
            : 0;
    }

    public static function actualizarBalance($fecha, $tramo)
    {
        $fecha = is_string($fecha) ? Carbon::parse($fecha)->toDateString() : $fecha->toDateString();

        $rutas = RutaCorta::where('fecha', $fecha)
            ->where('tramo', $tramo)
            ->get();

        $rutasCompletadas = $rutas->where('estado', 'COMPLETADA');
        $rutasCanceladas = $rutas->where('estado', 'CANCELADA');

        $conductoresUnicos = $rutas->pluck('conductor_id')->unique();

        // Calcular ocupación promedio
        $ocupacionPromedio = 0;
        if ($rutasCompletadas->count() > 0) {
            $ocupaciones = $rutasCompletadas->map(function ($ruta) {
                return $ruta->ocupacion_porcentaje ?? 0;
            })->filter();

            $ocupacionPromedio = $ocupaciones->count() > 0 ? $ocupaciones->avg() : 0;
        }

        // Calcular eficiencia promedio de conductores
        $eficienciaPromedio = 0;
        if ($conductoresUnicos->count() > 0) {
            $eficiencias = Conductor::whereIn('id', $conductoresUnicos)
                ->pluck('eficiencia');
            $eficienciaPromedio = $eficiencias->avg();
        }

        $datos = [
            'fecha' => $fecha,
            'tramo' => $tramo,
            'total_rutas' => $rutas->count(),
            'rutas_completadas' => $rutasCompletadas->count(),
            'rutas_canceladas' => $rutasCanceladas->count(),
            'total_pasajeros' => $rutasCompletadas->sum('pasajeros_transportados'),
            'ingreso_total' => $rutasCompletadas->sum('ingreso_estimado'),
            'ingreso_promedio_ruta' => $rutasCompletadas->avg('ingreso_estimado') ?: 0,
            'ocupacion_promedio' => $ocupacionPromedio,
            'conductores_participantes' => $conductoresUnicos->count(),
            'eficiencia_promedio' => $eficienciaPromedio,
            'metricas_adicionales' => [
                'calificacion_promedio' => $rutasCompletadas->whereNotNull('calificacion_servicio')
                    ->avg('calificacion_servicio') ?: 0,
                'tiempo_promedio_real' => $rutasCompletadas->avg('duracion_minutos') ?: 0,
                'distancia_total' => $rutasCompletadas->sum('distancia_km') ?: 0,
                'rutas_por_conductor' => $conductoresUnicos->count() > 0
                    ? round($rutas->count() / $conductoresUnicos->count(), 2)
                    : 0
            ]
        ];

        return static::updateOrCreate(
            ['fecha' => $fecha, 'tramo' => $tramo],
            $datos
        );
    }

    public static function obtenerResumenDiario($fecha = null)
    {
        $fecha = $fecha ?: now()->toDateString();

        return static::selectRaw('
                DATE(fecha) as fecha,
                COUNT(*) as tramos_operados,
                SUM(total_rutas) as total_rutas,
                SUM(rutas_completadas) as rutas_completadas,
                SUM(rutas_canceladas) as rutas_canceladas,
                SUM(total_pasajeros) as total_pasajeros,
                SUM(ingreso_total) as ingreso_total,
                AVG(ocupacion_promedio) as ocupacion_promedio,
                SUM(conductores_participantes) as total_conductores,
                AVG(eficiencia_promedio) as eficiencia_promedio
            ')
            ->where('fecha', $fecha)
            ->groupBy('fecha')
            ->first();
    }

    public static function obtenerTendenciasSemanal($tramo = null)
    {
        $query = static::selectRaw('
                DATE(fecha) as fecha,
                DAYNAME(fecha) as dia_semana,
                SUM(total_rutas) as total_rutas,
                SUM(rutas_completadas) as rutas_completadas,
                SUM(total_pasajeros) as total_pasajeros,
                SUM(ingreso_total) as ingreso_total,
                AVG(ocupacion_promedio) as ocupacion_promedio
            ')
            ->where('fecha', '>=', now()->subDays(7));

        if ($tramo) {
            $query->where('tramo', $tramo);
        }

        return $query->groupBy('fecha', 'dia_semana')
            ->orderBy('fecha')
            ->get();
    }

    public static function obtenerComparacionTramos($dias = 7)
    {
        return static::selectRaw('
                tramo,
                SUM(total_rutas) as total_rutas,
                SUM(rutas_completadas) as rutas_completadas,
                SUM(total_pasajeros) as total_pasajeros,
                SUM(ingreso_total) as ingreso_total,
                AVG(ocupacion_promedio) as ocupacion_promedio,
                AVG(eficiencia_promedio) as eficiencia_promedio,
                (SUM(rutas_completadas) / SUM(total_rutas)) * 100 as porcentaje_exito
            ')
            ->with('configuracionTramo:codigo_tramo,nombre,origen,destino')
            ->where('fecha', '>=', now()->subDays($dias))
            ->groupBy('tramo')
            ->having('total_rutas', '>', 0)
            ->orderBy('ingreso_total', 'desc')
            ->get();
    }

    public static function obtenerRankingRentabilidad($dias = 30)
    {
        return static::selectRaw('
                tramo,
                SUM(ingreso_total) as ingresos_totales,
                SUM(total_pasajeros) as pasajeros_totales,
                AVG(ingreso_promedio_ruta) as ingreso_promedio,
                SUM(rutas_completadas) as rutas_completadas,
                (SUM(ingreso_total) / SUM(total_pasajeros)) as ingreso_por_pasajero,
                (SUM(ingreso_total) / SUM(rutas_completadas)) as ingreso_por_ruta
            ')
            ->with('configuracionTramo:codigo_tramo,nombre,distancia_km,tarifa_base')
            ->where('fecha', '>=', now()->subDays($dias))
            ->groupBy('tramo')
            ->having('rutas_completadas', '>', 0)
            ->orderBy('ingresos_totales', 'desc')
            ->get();
    }

    public static function generarReporteMensual($mes = null, $año = null)
    {
        $mes = $mes ?: now()->month;
        $año = $año ?: now()->year;

        $fechaInicio = Carbon::create($año, $mes, 1)->startOfMonth();
        $fechaFin = $fechaInicio->copy()->endOfMonth();

        return static::selectRaw('
                tramo,
                COUNT(DISTINCT fecha) as dias_operacion,
                SUM(total_rutas) as total_rutas,
                SUM(rutas_completadas) as rutas_completadas,
                SUM(rutas_canceladas) as rutas_canceladas,
                SUM(total_pasajeros) as total_pasajeros,
                SUM(ingreso_total) as ingreso_total,
                AVG(ocupacion_promedio) as ocupacion_promedio,
                MAX(conductores_participantes) as max_conductores_dia,
                AVG(eficiencia_promedio) as eficiencia_promedio,
                (SUM(rutas_completadas) / SUM(total_rutas)) * 100 as porcentaje_exito
            ')
            ->with('configuracionTramo')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->groupBy('tramo')
            ->orderBy('ingreso_total', 'desc')
            ->get()
            ->map(function ($balance) use ($fechaInicio, $fechaFin) {
                $balance->periodo = $fechaInicio->format('M Y');
                $balance->promedio_rutas_dia = $balance->dias_operacion > 0
                    ? round($balance->total_rutas / $balance->dias_operacion, 2)
                    : 0;
                $balance->promedio_ingresos_dia = $balance->dias_operacion > 0
                    ? round($balance->ingreso_total / $balance->dias_operacion, 2)
                    : 0;
                return $balance;
            });
    }

    public static function obtenerIndicadoresKPI($dias = 30)
    {
        $datos = static::where('fecha', '>=', now()->subDays($dias))
            ->get();

        $totalRutas = $datos->sum('total_rutas');
        $rutasCompletadas = $datos->sum('rutas_completadas');
        $ingresoTotal = $datos->sum('ingreso_total');
        $totalPasajeros = $datos->sum('total_pasajeros');

        return [
            'eficiencia_operacional' => $totalRutas > 0
                ? round(($rutasCompletadas / $totalRutas) * 100, 2)
                : 0,
            'ingreso_promedio_diario' => $datos->count() > 0
                ? round($ingresoTotal / $datos->pluck('fecha')->unique()->count(), 2)
                : 0,
            'promedio_pasajeros_diario' => $datos->count() > 0
                ? round($totalPasajeros / $datos->pluck('fecha')->unique()->count(), 2)
                : 0,
            'ocupacion_promedio_general' => round($datos->avg('ocupacion_promedio'), 2),
            'tramos_mas_utilizados' => $datos->groupBy('tramo')
                ->map(function ($grupo) {
                    return $grupo->sum('total_rutas');
                })
                ->sortDesc()
                ->take(5)
                ->keys()
                ->toArray(),
            'tendencia_ingresos' => $datos->groupBy('fecha')
                ->map(function ($grupo) {
                    return [
                        'fecha' => $grupo->first()->fecha->format('Y-m-d'),
                        'ingreso' => $grupo->sum('ingreso_total')
                    ];
                })
                ->values()
                ->toArray()
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($balance) {
            // Validar que no exista duplicado para la misma fecha y tramo
            $existe = static::where('fecha', $balance->fecha)
                ->where('tramo', $balance->tramo)
                ->exists();

            if ($existe) {
                throw new \Exception("Ya existe un balance para el tramo {$balance->tramo} en la fecha {$balance->fecha}");
            }
        });
    }
}
