<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SubempresaFrecuencia extends Model
{
    use HasFactory;

    protected $table = 'subempresa_frecuencias';

    protected $fillable = [
        'nombre_subempresa',
        'codigo_frecuencia',
        'ruta',
        'hora_salida',
        'dias_operacion',
        'dias_personalizados',
        'conductores_requeridos',
        'tipo_servicio',
        'activa',
        'configuracion_especial'
    ];

    protected $casts = [
        'hora_salida' => 'datetime:H:i',
        'dias_personalizados' => 'array',
        'conductores_requeridos' => 'integer',
        'activa' => 'boolean',
        'configuracion_especial' => 'array'
    ];

    // Relaciones
    public function asignaciones()
    {
        return $this->hasMany(SubempresaAsignacion::class, 'frecuencia_id');
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeSubempresa($query, $subempresa)
    {
        return $query->where('nombre_subempresa', $subempresa);
    }

    public function scopeTipoServicio($query, $tipo)
    {
        return $query->where('tipo_servicio', $tipo);
    }

    public function scopeOperaHoy($query)
    {
        $hoy = now()->dayOfWeek; // 0=Domingo, 1=Lunes, etc.

        return $query->where(function ($q) use ($hoy) {
            $q->where('dias_operacion', 'L-D')
              ->orWhere(function ($query) use ($hoy) {
                  if ($hoy >= 1 && $hoy <= 5) { // Lunes a Viernes
                      $query->where('dias_operacion', 'L-V');
                  }
              })
              ->orWhere(function ($query) use ($hoy) {
                  if ($hoy >= 1 && $hoy <= 6) { // Lunes a Sábado
                      $query->where('dias_operacion', 'L-S');
                  }
              })
              ->orWhere(function ($query) use ($hoy) {
                  $query->where('dias_operacion', 'PERSONALIZADO')
                        ->whereJsonContains('dias_personalizados', $hoy);
              });
        });
    }

    public function scopeHoraSalida($query, $hora)
    {
        return $query->whereTime('hora_salida', $hora);
    }

    public function scopeBuscarTexto($query, $texto)
    {
        return $query->where(function ($q) use ($texto) {
            $q->where('codigo_frecuencia', 'like', "%{$texto}%")
              ->orWhere('ruta', 'like', "%{$texto}%")
              ->orWhere('nombre_subempresa', 'like', "%{$texto}%");
        });
    }

    // Métodos de negocio
    public function operaEnDia($fecha = null)
    {
        $fecha = $fecha ?: now();
        $diaSemana = $fecha->dayOfWeek;

        return match($this->dias_operacion) {
            'L-V' => $diaSemana >= 1 && $diaSemana <= 5,
            'L-S' => $diaSemana >= 1 && $diaSemana <= 6,
            'L-D' => true,
            'PERSONALIZADO' => in_array($diaSemana, $this->dias_personalizados ?: []),
            default => false
        };
    }

    public function getDiasOperacionTextoAttribute()
    {
        return match($this->dias_operacion) {
            'L-V' => 'Lunes a Viernes',
            'L-S' => 'Lunes a Sábado',
            'L-D' => 'Todos los días',
            'PERSONALIZADO' => $this->getDiasPersonalizadosTexto(),
            default => 'No definido'
        };
    }

    private function getDiasPersonalizadosTexto()
    {
        if (!$this->dias_personalizados) {
            return 'Días no configurados';
        }

        $nombresDias = [
            0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
        ];

        $diasTexto = array_map(function($dia) use ($nombresDias) {
            return $nombresDias[$dia] ?? $dia;
        }, $this->dias_personalizados);

        return implode(', ', $diasTexto);
    }

    public function getHoraSalidaFormateadaAttribute()
    {
        return Carbon::parse($this->hora_salida)->format('H:i');
    }

    public function generarAsignaciones($fechaInicio, $fechaFin, $conductores = [])
    {
        $fechaActual = Carbon::parse($fechaInicio);
        $fechaFinal = Carbon::parse($fechaFin);
        $asignacionesCreadas = [];
        $conductoresDisponibles = collect($conductores);

        while ($fechaActual <= $fechaFinal) {
            if ($this->operaEnDia($fechaActual)) {
                $asignacionesDia = $this->generarAsignacionesPorDia($fechaActual, $conductoresDisponibles);
                $asignacionesCreadas = array_merge($asignacionesCreadas, $asignacionesDia);
            }
            $fechaActual->addDay();
        }

        return $asignacionesCreadas;
    }

    private function generarAsignacionesPorDia($fecha, $conductoresDisponibles)
    {
        $asignacionesCreadas = [];

        for ($i = 0; $i < $this->conductores_requeridos; $i++) {
            $conductor = $this->seleccionarMejorConductor($conductoresDisponibles, $fecha);

            if (!$conductor) {
                break; // No hay más conductores disponibles
            }

            $asignacion = SubempresaAsignacion::create([
                'frecuencia_id' => $this->id,
                'conductor_id' => $conductor->id,
                'fecha_asignacion' => $fecha->toDateString(),
                'estado' => 'ASIGNADO'
            ]);

            $asignacionesCreadas[] = $asignacion;

            // Remover conductor de disponibles para esta fecha
            $conductoresDisponibles = $conductoresDisponibles->reject(function ($c) use ($conductor) {
                return $c->id === $conductor->id;
            });
        }

        return $asignacionesCreadas;
    }

    private function seleccionarMejorConductor($conductoresDisponibles, $fecha)
    {
        if ($conductoresDisponibles->isEmpty()) {
            return null;
        }

        // Filtrar conductores que ya tienen asignaciones en esta fecha/hora
        $conductoresSinConflicto = $conductoresDisponibles->filter(function ($conductor) use ($fecha) {
            return !SubempresaAsignacion::where('conductor_id', $conductor->id)
                ->where('fecha_asignacion', $fecha->toDateString())
                ->whereHas('frecuencia', function ($query) {
                    $query->whereTime('hora_salida', $this->hora_salida);
                })
                ->whereIn('estado', ['ASIGNADO', 'CONFIRMADO', 'COMPLETADO'])
                ->exists();
        });

        if ($conductoresSinConflicto->isEmpty()) {
            return null;
        }

        // Seleccionar el mejor conductor basado en criterios
        return $conductoresSinConflicto->sortByDesc(function ($conductor) {
            return $this->calcularScoreConductor($conductor);
        })->first();
    }

    private function calcularScoreConductor($conductor)
    {
        $score = 0;

        // Score base por eficiencia y puntualidad
        $score += ($conductor->eficiencia * 0.4) + ($conductor->puntualidad * 0.4);

        // Bonus por subempresa coincidente
        if ($conductor->subempresa === $this->nombre_subempresa) {
            $score += 15;
        }

        // Bonus por experiencia
        $score += min(10, $conductor->años_experiencia * 2);

        // Penalización por días acumulados
        $score -= $conductor->dias_acumulados * 3;

        // Bonus por disponibilidad
        if ($conductor->estado === 'DISPONIBLE') {
            $score += 10;
        }

        return $score;
    }

    public function obtenerEstadisticasRendimiento($dias = 30)
    {
        $asignaciones = $this->asignaciones()
            ->where('fecha_asignacion', '>=', now()->subDays($dias))
            ->get();

        $completadas = $asignaciones->where('estado', 'COMPLETADO');

        return [
            'total_asignaciones' => $asignaciones->count(),
            'completadas' => $completadas->count(),
            'porcentaje_completadas' => $asignaciones->count() > 0
                ? round(($completadas->count() / $asignaciones->count()) * 100, 2)
                : 0,
            'conductores_diferentes' => $asignaciones->pluck('conductor_id')->unique()->count(),
            'dias_operados' => $asignaciones->pluck('fecha_asignacion')->unique()->count(),
            'promedio_asignaciones_dia' => $asignaciones->pluck('fecha_asignacion')->unique()->count() > 0
                ? round($asignaciones->count() / $asignaciones->pluck('fecha_asignacion')->unique()->count(), 2)
                : 0,
            'total_pasajeros' => $completadas->sum('pasajeros_transportados'),
            'ingresos_generados' => $completadas->sum('ingresos_generados'),
            'promedio_pasajeros' => $completadas->avg('pasajeros_transportados') ?: 0,
            'promedio_ingresos' => $completadas->avg('ingresos_generados') ?: 0
        ];
    }

    public function clonar($nuevoCodigo, $nuevaHora = null)
    {
        return static::create([
            'nombre_subempresa' => $this->nombre_subempresa,
            'codigo_frecuencia' => $nuevoCodigo,
            'ruta' => $this->ruta,
            'hora_salida' => $nuevaHora ?: $this->hora_salida,
            'dias_operacion' => $this->dias_operacion,
            'dias_personalizados' => $this->dias_personalizados,
            'conductores_requeridos' => $this->conductores_requeridos,
            'tipo_servicio' => $this->tipo_servicio,
            'activa' => false, // Las copias inician inactivas
            'configuracion_especial' => $this->configuracion_especial
        ]);
    }

    public static function obtenerFrecuenciasHoy()
    {
        return static::activas()
            ->operaHoy()
            ->orderBy('hora_salida')
            ->with(['asignaciones' => function ($query) {
                $query->where('fecha_asignacion', now()->toDateString())
                      ->with('conductor:id,codigo_conductor,nombre,apellido,estado');
            }])
            ->get()
            ->map(function ($frecuencia) {
                $asignacionesHoy = $frecuencia->asignaciones;

                return [
                    'frecuencia' => $frecuencia,
                    'asignaciones_completas' => $asignacionesHoy->count() >= $frecuencia->conductores_requeridos,
                    'asignaciones_faltantes' => max(0, $frecuencia->conductores_requeridos - $asignacionesHoy->count()),
                    'conductores_asignados' => $asignacionesHoy->pluck('conductor'),
                    'estado_general' => $asignacionesHoy->count() >= $frecuencia->conductores_requeridos ? 'COMPLETA' : 'INCOMPLETA'
                ];
            });
    }

    public static function obtenerSubempresasEstadisticas($dias = 7)
    {
        return static::selectRaw('
                nombre_subempresa,
                COUNT(*) as total_frecuencias,
                SUM(CASE WHEN activa = 1 THEN 1 ELSE 0 END) as frecuencias_activas,
                SUM(conductores_requeridos) as conductores_totales_requeridos
            ')
            ->groupBy('nombre_subempresa')
            ->get()
            ->map(function ($subempresa) use ($dias) {
                $asignaciones = SubempresaAsignacion::whereHas('frecuencia', function ($query) use ($subempresa) {
                    $query->where('nombre_subempresa', $subempresa->nombre_subempresa);
                })
                ->where('fecha_asignacion', '>=', now()->subDays($dias))
                ->get();

                return [
                    'nombre' => $subempresa->nombre_subempresa,
                    'total_frecuencias' => $subempresa->total_frecuencias,
                    'frecuencias_activas' => $subempresa->frecuencias_activas,
                    'conductores_requeridos' => $subempresa->conductores_totales_requeridos,
                    'asignaciones_periodo' => $asignaciones->count(),
                    'asignaciones_completadas' => $asignaciones->where('estado', 'COMPLETADO')->count(),
                    'eficiencia_asignaciones' => $asignaciones->count() > 0
                        ? round(($asignaciones->where('estado', 'COMPLETADO')->count() / $asignaciones->count()) * 100, 2)
                        : 0
                ];
            });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($frecuencia) {
            // Validar unicidad de código + hora
            $existe = static::where('codigo_frecuencia', $frecuencia->codigo_frecuencia)
                ->whereTime('hora_salida', $frecuencia->hora_salida)
                ->exists();

            if ($existe) {
                throw new \Exception("Ya existe una frecuencia con el código {$frecuencia->codigo_frecuencia} a las {$frecuencia->hora_salida}");
            }

            // Validar días personalizados
            if ($frecuencia->dias_operacion === 'PERSONALIZADO' && empty($frecuencia->dias_personalizados)) {
                throw new \Exception('Debe especificar los días personalizados para frecuencias con días personalizados');
            }
        });
    }
}
