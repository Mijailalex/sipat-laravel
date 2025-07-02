<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SubempresaAsignacion extends Model
{
    use HasFactory;

    protected $table = 'subempresa_asignaciones';

    protected $fillable = [
        'frecuencia_id',
        'conductor_id',
        'bus_id',
        'fecha_asignacion',
        'estado',
        'hora_real_salida',
        'hora_real_llegada',
        'pasajeros_transportados',
        'ingresos_generados',
        'observaciones'
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
        'hora_real_salida' => 'datetime:H:i',
        'hora_real_llegada' => 'datetime:H:i',
        'pasajeros_transportados' => 'integer',
        'ingresos_generados' => 'decimal:2'
    ];

    // Relaciones
    public function frecuencia()
    {
        return $this->belongsTo(SubempresaFrecuencia::class, 'frecuencia_id');
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    // Scopes
    public function scopeHoy($query)
    {
        return $query->where('fecha_asignacion', now()->toDateString());
    }

    public function scopeFecha($query, $fecha)
    {
        return $query->where('fecha_asignacion', $fecha);
    }

    public function scopeRangoFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_asignacion', [$desde, $hasta]);
    }

    public function scopeAsignadas($query)
    {
        return $query->where('estado', 'ASIGNADO');
    }

    public function scopeConfirmadas($query)
    {
        return $query->where('estado', 'CONFIRMADO');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'COMPLETADO');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', 'CANCELADO');
    }

    public function scopeConductor($query, $conductorId)
    {
        return $query->where('conductor_id', $conductorId);
    }

    public function scopeSubempresa($query, $subempresa)
    {
        return $query->whereHas('frecuencia', function ($q) use ($subempresa) {
            $q->where('nombre_subempresa', $subempresa);
        });
    }

    // Métodos de negocio
    public function getEstadoClaseAttribute()
    {
        return match($this->estado) {
            'ASIGNADO' => 'primary',
            'CONFIRMADO' => 'info',
            'COMPLETADO' => 'success',
            'CANCELADO' => 'danger',
            default => 'secondary'
        };
    }

    public function getDuracionRealAttribute()
    {
        if ($this->hora_real_salida && $this->hora_real_llegada) {
            return Carbon::parse($this->hora_real_salida)
                ->diffInMinutes(Carbon::parse($this->hora_real_llegada));
        }
        return null;
    }

    public function getRetrasoSalidaAttribute()
    {
        if (!$this->hora_real_salida || !$this->frecuencia) {
            return null;
        }

        $horaProgramada = Carbon::parse($this->fecha_asignacion->format('Y-m-d') . ' ' .
            $this->frecuencia->hora_salida->format('H:i:s'));
        $horaReal = Carbon::parse($this->fecha_asignacion->format('Y-m-d') . ' ' .
            $this->hora_real_salida->format('H:i:s'));

        return $horaReal->diffInMinutes($horaProgramada, false);
    }

    public function getIngresoPorPasajeroAttribute()
    {
        return $this->pasajeros_transportados > 0
            ? round($this->ingresos_generados / $this->pasajeros_transportados, 2)
            : 0;
    }

    public function puedeConfirmar()
    {
        return $this->estado === 'ASIGNADO' &&
               $this->fecha_asignacion->isToday();
    }

    public function puedeIniciar()
    {
        return in_array($this->estado, ['ASIGNADO', 'CONFIRMADO']) &&
               $this->fecha_asignacion->isToday();
    }

    public function puedeCompletar()
    {
        return $this->estado === 'CONFIRMADO' &&
               $this->hora_real_salida;
    }

    public function confirmar($busId = null)
    {
        if (!$this->puedeConfirmar()) {
            throw new \Exception('Esta asignación no puede ser confirmada');
        }

        $datosActualizacion = ['estado' => 'CONFIRMADO'];

        if ($busId) {
            $bus = Bus::findOrFail($busId);
            if (!$bus->estaDisponiblePara($this->fecha_asignacion, $this->frecuencia->hora_salida)) {
                throw new \Exception('El bus no está disponible para esta asignación');
            }
            $datosActualizacion['bus_id'] = $busId;
        }

        $this->update($datosActualizacion);

        return true;
    }

    public function iniciar($horaSalida = null)
    {
        if (!$this->puedeIniciar()) {
            throw new \Exception('Esta asignación no puede ser iniciada');
        }

        $this->update([
            'estado' => 'CONFIRMADO',
            'hora_real_salida' => $horaSalida ?: now()->format('H:i'),
            'observaciones' => ($this->observaciones ?? '') . "\nIniciado: " . now()->format('Y-m-d H:i:s')
        ]);

        // Actualizar conductor
        $this->conductor->update([
            'ultimo_servicio' => now(),
            'ultima_ruta_corta' => now()
        ]);

        return true;
    }

    public function completar($datos = [])
    {
        if (!$this->puedeCompletar()) {
            throw new \Exception('Esta asignación no puede ser completada');
        }

        $datosActualizacion = array_merge([
            'estado' => 'COMPLETADO',
            'hora_real_llegada' => now()->format('H:i'),
            'observaciones' => ($this->observaciones ?? '') . "\nCompletado: " . now()->format('Y-m-d H:i:s')
        ], $datos);

        $this->update($datosActualizacion);

        // Actualizar métricas del conductor
        $this->actualizarMetricasConductor();

        return true;
    }

    public function cancelar($motivo = null)
    {
        if (in_array($this->estado, ['COMPLETADO', 'CANCELADO'])) {
            throw new \Exception('No se puede cancelar una asignación completada o ya cancelada');
        }

        $this->update([
            'estado' => 'CANCELADO',
            'observaciones' => ($this->observaciones ?? '') . "\nCancelado: " . now()->format('Y-m-d H:i:s') .
                ($motivo ? " - Motivo: {$motivo}" : '')
        ]);

        return true;
    }

    public function reasignarConductor($nuevoConductorId)
    {
        if ($this->estado === 'COMPLETADO') {
            throw new \Exception('No se puede reasignar una asignación completada');
        }

        $nuevoConductor = Conductor::findOrFail($nuevoConductorId);

        // Verificar disponibilidad del nuevo conductor
        $conflictos = static::where('conductor_id', $nuevoConductorId)
            ->where('fecha_asignacion', $this->fecha_asignacion)
            ->where('id', '!=', $this->id)
            ->whereIn('estado', ['ASIGNADO', 'CONFIRMADO', 'COMPLETADO'])
            ->whereHas('frecuencia', function ($query) {
                $query->whereTime('hora_salida', $this->frecuencia->hora_salida);
            })
            ->exists();

        if ($conflictos) {
            throw new \Exception('El nuevo conductor tiene un conflicto de horario');
        }

        $conductorAnterior = $this->conductor->codigo_conductor;

        $this->update([
            'conductor_id' => $nuevoConductorId,
            'estado' => 'ASIGNADO', // Resetear estado
            'observaciones' => ($this->observaciones ?? '') .
                "\nReasignado de {$conductorAnterior} a {$nuevoConductor->codigo_conductor}: " .
                now()->format('Y-m-d H:i:s')
        ]);

        return true;
    }

    private function actualizarMetricasConductor()
    {
        $conductor = $this->conductor;

        // Incrementar totales
        $conductor->increment('total_rutas_completadas');
        if ($this->ingresos_generados) {
            $conductor->increment('total_ingresos_generados', $this->ingresos_generados);
        }

        // Incrementar días acumulados si es un nuevo día
        if (!$conductor->ultimo_servicio ||
            $conductor->ultimo_servicio->toDateString() !== $this->fecha_asignacion->toDateString()) {
            $conductor->increment('dias_acumulados');
        }

        // Actualizar horas hombre basado en duración real
        if ($this->duracion_real) {
            $horasAdicionales = $this->duracion_real / 60;
            $conductor->increment('horas_hombre', $horasAdicionales);
        }

        // Recalcular métricas generales
        $conductor->actualizarMetricas();
    }

    public static function obtenerEstadisticasHoy()
    {
        $hoy = now()->toDateString();

        return [
            'total' => static::fecha($hoy)->count(),
            'asignadas' => static::fecha($hoy)->asignadas()->count(),
            'confirmadas' => static::fecha($hoy)->confirmadas()->count(),
            'completadas' => static::fecha($hoy)->completadas()->count(),
            'canceladas' => static::fecha($hoy)->canceladas()->count(),
            'sin_confirmar' => static::fecha($hoy)->asignadas()->count(),
            'total_pasajeros' => static::fecha($hoy)->completadas()->sum('pasajeros_transportados'),
            'total_ingresos' => static::fecha($hoy)->completadas()->sum('ingresos_generados'),
            'promedio_pasajeros' => static::fecha($hoy)->completadas()->avg('pasajeros_transportados') ?: 0,
            'promedio_ingresos' => static::fecha($hoy)->completadas()->avg('ingresos_generados') ?: 0,
            'retraso_promedio' => static::fecha($hoy)->completadas()
                ->whereNotNull('hora_real_salida')
                ->get()
                ->avg('retraso_salida') ?: 0
        ];
    }

    public static function obtenerAsignacionesPendientes($limite = 20)
    {
        return static::asignadas()
            ->where('fecha_asignacion', '>=', now()->toDateString())
            ->orderBy('fecha_asignacion')
            ->orderByRaw('(SELECT hora_salida FROM subempresa_frecuencias WHERE id = subempresa_asignaciones.frecuencia_id)')
            ->with(['frecuencia', 'conductor:id,codigo_conductor,nombre,apellido,estado'])
            ->limit($limite)
            ->get();
    }

    public static function obtenerRankingConductores($dias = 30)
    {
        return static::selectRaw('
                conductor_id,
                COUNT(*) as total_asignaciones,
                SUM(CASE WHEN estado = "COMPLETADO" THEN 1 ELSE 0 END) as completadas,
                SUM(CASE WHEN estado = "COMPLETADO" THEN pasajeros_transportados ELSE 0 END) as total_pasajeros,
                SUM(CASE WHEN estado = "COMPLETADO" THEN ingresos_generados ELSE 0 END) as total_ingresos,
                (SUM(CASE WHEN estado = "COMPLETADO" THEN 1 ELSE 0 END) / COUNT(*)) * 100 as porcentaje_eficiencia,
                AVG(CASE WHEN estado = "COMPLETADO" THEN pasajeros_transportados ELSE NULL END) as promedio_pasajeros
            ')
            ->with('conductor:id,codigo_conductor,nombre,apellido,subempresa')
            ->where('fecha_asignacion', '>=', now()->subDays($dias))
            ->groupBy('conductor_id')
            ->having('total_asignaciones', '>', 0)
            ->orderBy('porcentaje_eficiencia', 'desc')
            ->orderBy('total_ingresos', 'desc')
            ->get();
    }

    public static function obtenerTendenciasPorSubempresa($subempresa, $dias = 30)
    {
        return static::selectRaw('
                DATE(fecha_asignacion) as fecha,
                COUNT(*) as total_asignaciones,
                SUM(CASE WHEN estado = "COMPLETADO" THEN 1 ELSE 0 END) as completadas,
                SUM(CASE WHEN estado = "COMPLETADO" THEN pasajeros_transportados ELSE 0 END) as total_pasajeros,
                SUM(CASE WHEN estado = "COMPLETADO" THEN ingresos_generados ELSE 0 END) as total_ingresos,
                COUNT(DISTINCT conductor_id) as conductores_diferentes
            ')
            ->subempresa($subempresa)
            ->where('fecha_asignacion', '>=', now()->subDays($dias))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();
    }

    public static function generarReporteRendimiento($fechaInicio, $fechaFin, $filtros = [])
    {
        $query = static::rangoFechas($fechaInicio, $fechaFin);

        // Aplicar filtros
        if (isset($filtros['subempresa'])) {
            $query->subempresa($filtros['subempresa']);
        }

        if (isset($filtros['conductor_id'])) {
            $query->conductor($filtros['conductor_id']);
        }

        if (isset($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        $asignaciones = $query->with(['frecuencia', 'conductor', 'bus'])->get();
        $completadas = $asignaciones->where('estado', 'COMPLETADO');

        return [
            'periodo' => [
                'inicio' => $fechaInicio,
                'fin' => $fechaFin,
                'dias' => Carbon::parse($fechaInicio)->diffInDays(Carbon::parse($fechaFin)) + 1
            ],
            'resumen_general' => [
                'total_asignaciones' => $asignaciones->count(),
                'completadas' => $completadas->count(),
                'canceladas' => $asignaciones->where('estado', 'CANCELADO')->count(),
                'porcentaje_completadas' => $asignaciones->count() > 0
                    ? round(($completadas->count() / $asignaciones->count()) * 100, 2)
                    : 0,
                'conductores_participantes' => $asignaciones->pluck('conductor_id')->unique()->count(),
                'subempresas_participantes' => $asignaciones->pluck('frecuencia.nombre_subempresa')->unique()->count()
            ],
            'metricas_operacionales' => [
                'total_pasajeros' => $completadas->sum('pasajeros_transportados'),
                'promedio_pasajeros_asignacion' => $completadas->avg('pasajeros_transportados') ?: 0,
                'total_ingresos' => $completadas->sum('ingresos_generados'),
                'promedio_ingresos_asignacion' => $completadas->avg('ingresos_generados') ?: 0,
                'ingreso_promedio_por_pasajero' => $completadas->sum('pasajeros_transportados') > 0
                    ? round($completadas->sum('ingresos_generados') / $completadas->sum('pasajeros_transportados'), 2)
                    : 0
            ],
            'analisis_puntualidad' => [
                'asignaciones_con_hora_real' => $completadas->whereNotNull('hora_real_salida')->count(),
                'retraso_promedio_minutos' => $completadas->whereNotNull('hora_real_salida')->avg('retraso_salida') ?: 0,
                'asignaciones_puntuales' => $completadas->filter(function ($a) {
                    return $a->retraso_salida !== null && $a->retraso_salida <= 5;
                })->count(),
                'porcentaje_puntualidad' => $completadas->whereNotNull('hora_real_salida')->count() > 0
                    ? round(($completadas->filter(function ($a) {
                        return $a->retraso_salida !== null && $a->retraso_salida <= 5;
                    })->count() / $completadas->whereNotNull('hora_real_salida')->count()) * 100, 2)
                    : 0
            ],
            'top_conductores' => $completadas->groupBy('conductor_id')
                ->map(function ($grupo) {
                    $conductor = $grupo->first()->conductor;
                    return [
                        'conductor' => $conductor,
                        'asignaciones' => $grupo->count(),
                        'total_pasajeros' => $grupo->sum('pasajeros_transportados'),
                        'total_ingresos' => $grupo->sum('ingresos_generados'),
                        'promedio_pasajeros' => $grupo->avg('pasajeros_transportados'),
                        'eficiencia' => round(($grupo->count() / $asignaciones->where('conductor_id', $conductor->id)->count()) * 100, 2)
                    ];
                })
                ->sortByDesc('total_ingresos')
                ->take(10)
                ->values(),
            'por_subempresa' => $asignaciones->groupBy('frecuencia.nombre_subempresa')
                ->map(function ($grupo, $subempresa) {
                    $completadas = $grupo->where('estado', 'COMPLETADO');
                    return [
                        'nombre' => $subempresa,
                        'total_asignaciones' => $grupo->count(),
                        'completadas' => $completadas->count(),
                        'porcentaje_eficiencia' => $grupo->count() > 0
                            ? round(($completadas->count() / $grupo->count()) * 100, 2)
                            : 0,
                        'total_pasajeros' => $completadas->sum('pasajeros_transportados'),
                        'total_ingresos' => $completadas->sum('ingresos_generados')
                    ];
                })
                ->sortByDesc('total_ingresos')
                ->values()
        ];
    }

    public static function notificarAsignacionesPendientes()
    {
        $asignacionesMañana = static::where('fecha_asignacion', now()->addDay()->toDateString())
            ->asignadas()
            ->with(['frecuencia', 'conductor'])
            ->get();

        $notificaciones = [];

        foreach ($asignacionesMañana as $asignacion) {
            // Verificar si el conductor sigue disponible
            if ($asignacion->conductor->estado !== 'DISPONIBLE') {
                $notificaciones[] = [
                    'tipo' => 'conductor_no_disponible',
                    'asignacion' => $asignacion,
                    'mensaje' => "El conductor {$asignacion->conductor->codigo_conductor} ya no está disponible"
                ];
            }

            // Verificar si falta confirmar
            if ($asignacion->estado === 'ASIGNADO') {
                $notificaciones[] = [
                    'tipo' => 'pendiente_confirmacion',
                    'asignacion' => $asignacion,
                    'mensaje' => "Asignación pendiente de confirmación para mañana"
                ];
            }
        }

        return $notificaciones;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($asignacion) {
            // Verificar que no exista duplicado
            $existe = static::where('frecuencia_id', $asignacion->frecuencia_id)
                ->where('conductor_id', $asignacion->conductor_id)
                ->where('fecha_asignacion', $asignacion->fecha_asignacion)
                ->exists();

            if ($existe) {
                throw new \Exception('Ya existe una asignación para este conductor en esta frecuencia y fecha');
            }
        });

        static::updated(function ($asignacion) {
            // Si se completa la asignación, actualizar última ruta corta del conductor
            if ($asignacion->isDirty('estado') && $asignacion->estado === 'COMPLETADO') {
                $asignacion->conductor->update(['ultima_ruta_corta' => now()]);
            }
        });
    }
}
