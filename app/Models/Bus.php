<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Bus extends Model
{
    use HasFactory;

    protected $table = 'buses';

    protected $fillable = [
        'numero_bus',
        'placa',
        'marca',
        'modelo',
        'año',
        'capacidad_pasajeros',
        'tipo_combustible',
        'estado',
        'subempresa',
        'kilometraje',
        'fecha_ultima_revision',
        'fecha_proxima_revision',
        'ubicacion_actual',
        'observaciones'
    ];

    protected $casts = [
        'año' => 'integer',
        'capacidad_pasajeros' => 'integer',
        'kilometraje' => 'decimal:2',
        'fecha_ultima_revision' => 'date',
        'fecha_proxima_revision' => 'date'
    ];

    // Relaciones
    public function rutasCortas()
    {
        return $this->hasMany(RutaCorta::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function asignacionesSubempresa()
    {
        return $this->hasMany(SubempresaAsignacion::class);
    }

    // Scopes
    public function scopeOperativos($query)
    {
        return $query->where('estado', 'OPERATIVO');
    }

    public function scopeEnMantenimiento($query)
    {
        return $query->where('estado', 'MANTENIMIENTO');
    }

    public function scopeSubempresa($query, $subempresa)
    {
        return $query->where('subempresa', $subempresa);
    }

    public function scopeTipoCombustible($query, $tipo)
    {
        return $query->where('tipo_combustible', $tipo);
    }

    public function scopeRevisionVencida($query)
    {
        return $query->where('fecha_proxima_revision', '<', now());
    }

    public function scopeRevisionProxima($query, $dias = 7)
    {
        return $query->whereBetween('fecha_proxima_revision', [
            now(),
            now()->addDays($dias)
        ]);
    }

    public function scopeBuscarTexto($query, $texto)
    {
        return $query->where(function ($q) use ($texto) {
            $q->where('numero_bus', 'like', "%{$texto}%")
              ->orWhere('placa', 'like', "%{$texto}%")
              ->orWhere('marca', 'like', "%{$texto}%")
              ->orWhere('modelo', 'like', "%{$texto}%");
        });
    }

    // Métodos de negocio
    public function getDescripcionCompletaAttribute()
    {
        return "{$this->marca} {$this->modelo} ({$this->año})";
    }

    public function getAntiguedadAttribute()
    {
        return now()->year - $this->año;
    }

    public function getEstadoRevisionAttribute()
    {
        if (!$this->fecha_proxima_revision) {
            return 'SIN_PROGRAMAR';
        }

        $dias = now()->diffInDays($this->fecha_proxima_revision, false);

        if ($dias < 0) {
            return 'VENCIDA';
        } elseif ($dias <= 7) {
            return 'PROXIMA';
        } else {
            return 'VIGENTE';
        }
    }

    public function getClaseEstadoAttribute()
    {
        return match($this->estado) {
            'OPERATIVO' => 'success',
            'MANTENIMIENTO' => 'warning',
            'FUERA_SERVICIO' => 'danger',
            'ACCIDENTADO' => 'dark',
            default => 'secondary'
        };
    }

    public function getClaseRevisionAttribute()
    {
        return match($this->estado_revision) {
            'VENCIDA' => 'danger',
            'PROXIMA' => 'warning',
            'VIGENTE' => 'success',
            'SIN_PROGRAMAR' => 'secondary',
            default => 'secondary'
        };
    }

    public function estaDisponiblePara($fecha, $hora = null)
    {
        if ($this->estado !== 'OPERATIVO') {
            return false;
        }

        // Verificar si tiene asignaciones en conflicto
        $fecha = is_string($fecha) ? Carbon::parse($fecha) : $fecha;

        $asignacionesConflicto = $this->asignacionesSubempresa()
            ->where('fecha_asignacion', $fecha->toDateString())
            ->where('estado', '!=', 'CANCELADO')
            ->when($hora, function ($query, $hora) {
                // Si se proporciona hora, verificar solapamiento
                return $query->where(function ($q) use ($hora) {
                    $q->whereTime('hora_real_salida', '<=', $hora)
                      ->whereTime('hora_real_llegada', '>=', $hora);
                });
            })
            ->exists();

        return !$asignacionesConflicto;
    }

    public function programarMantenimiento($fechaInicio, $motivo = null, $fechaFin = null)
    {
        if ($this->estado === 'MANTENIMIENTO') {
            throw new \Exception('El bus ya está en mantenimiento');
        }

        $this->update([
            'estado' => 'MANTENIMIENTO',
            'observaciones' => ($this->observaciones ?? '') . "\n" .
                "Mantenimiento programado: " . now()->format('Y-m-d H:i') .
                ($motivo ? " - {$motivo}" : '')
        ]);

        return true;
    }

    public function finalizarMantenimiento($fechaRevision = null)
    {
        if ($this->estado !== 'MANTENIMIENTO') {
            throw new \Exception('El bus no está en mantenimiento');
        }

        $fechaRevision = $fechaRevision ?: now();
        $proximaRevision = Carbon::parse($fechaRevision)->addMonths(6);

        $this->update([
            'estado' => 'OPERATIVO',
            'fecha_ultima_revision' => $fechaRevision,
            'fecha_proxima_revision' => $proximaRevision,
            'observaciones' => ($this->observaciones ?? '') . "\n" .
                "Mantenimiento finalizado: " . now()->format('Y-m-d H:i')
        ]);

        return true;
    }

    public function actualizarKilometraje($nuevosKm)
    {
        if ($nuevosKm <= $this->kilometraje) {
            throw new \Exception('El nuevo kilometraje debe ser mayor al actual');
        }

        $this->update(['kilometraje' => $nuevosKm]);

        // Verificar si necesita mantenimiento por kilometraje
        $this->verificarMantenimientoPorKilometraje();

        return true;
    }

    private function verificarMantenimientoPorKilometraje()
    {
        $kmMantenimiento = 50000; // Cada 50,000 km
        $ultimoMantenimientoKm = $this->kilometraje - ($this->kilometraje % $kmMantenimiento);

        if ($this->kilometraje >= $ultimoMantenimientoKm + $kmMantenimiento) {
            // Crear notificación o alerta de mantenimiento
            $this->update([
                'observaciones' => ($this->observaciones ?? '') . "\n" .
                    "Requiere mantenimiento por kilometraje: {$this->kilometraje} km - " . now()->format('Y-m-d')
            ]);
        }
    }

    public function obtenerEstadisticasUso($dias = 30)
    {
        $rutasCortas = $this->rutasCortas()
            ->where('fecha', '>=', now()->subDays($dias))
            ->get();

        $rutasCompletadas = $rutasCortas->where('estado', 'COMPLETADA');

        return [
            'total_rutas' => $rutasCortas->count(),
            'rutas_completadas' => $rutasCompletadas->count(),
            'porcentaje_utilizacion' => $rutasCortas->count() > 0
                ? round(($rutasCompletadas->count() / $rutasCortas->count()) * 100, 2)
                : 0,
            'total_pasajeros' => $rutasCompletadas->sum('pasajeros_transportados'),
            'promedio_pasajeros' => $rutasCompletadas->avg('pasajeros_transportados') ?: 0,
            'ocupacion_promedio' => $rutasCompletadas->avg('ocupacion_porcentaje') ?: 0,
            'ingresos_generados' => $rutasCompletadas->sum('ingreso_estimado'),
            'dias_activos' => $rutasCortas->pluck('fecha')->unique()->count(),
            'conductores_diferentes' => $rutasCortas->pluck('conductor_id')->unique()->count()
        ];
    }

    public static function obtenerEstadisticasFlota()
    {
        $total = static::count();

        return [
            'total_buses' => $total,
            'operativos' => static::where('estado', 'OPERATIVO')->count(),
            'en_mantenimiento' => static::where('estado', 'MANTENIMIENTO')->count(),
            'fuera_servicio' => static::where('estado', 'FUERA_SERVICIO')->count(),
            'accidentados' => static::where('estado', 'ACCIDENTADO')->count(),
            'porcentaje_operativo' => $total > 0
                ? round((static::where('estado', 'OPERATIVO')->count() / $total) * 100, 2)
                : 0,
            'revisiones_vencidas' => static::revisionVencida()->count(),
            'revisiones_proximas' => static::revisionProxima()->count(),
            'capacidad_total' => static::sum('capacidad_pasajeros'),
            'capacidad_operativa' => static::operativos()->sum('capacidad_pasajeros'),
            'antiguedad_promedio' => round(static::avg(\DB::raw('YEAR(NOW()) - año')), 1),
            'por_tipo_combustible' => static::selectRaw('tipo_combustible, COUNT(*) as cantidad')
                ->groupBy('tipo_combustible')
                ->pluck('cantidad', 'tipo_combustible')
                ->toArray()
        ];
    }

    public static function obtenerBusesRequierenAtencion()
    {
        return static::where(function ($query) {
                $query->revisionVencida()
                      ->orWhere(function ($q) {
                          $q->revisionProxima(7);
                      });
            })
            ->orWhere('estado', '!=', 'OPERATIVO')
            ->with(['rutasCortas' => function ($query) {
                $query->where('fecha', '>=', now()->subDays(7));
            }])
            ->get()
            ->map(function ($bus) {
                $bus->razon_atencion = [];

                if ($bus->estado_revision === 'VENCIDA') {
                    $bus->razon_atencion[] = 'Revisión vencida';
                } elseif ($bus->estado_revision === 'PROXIMA') {
                    $bus->razon_atencion[] = 'Revisión próxima';
                }

                if ($bus->estado !== 'OPERATIVO') {
                    $bus->razon_atencion[] = "Estado: {$bus->estado}";
                }

                return $bus;
            });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bus) {
            // Generar número de bus automático si no se proporciona
            if (!$bus->numero_bus) {
                $ultimo = static::max('id') + 1;
                $bus->numero_bus = 'B' . str_pad($ultimo, 4, '0', STR_PAD_LEFT);
            }

            // Establecer próxima revisión si no se proporciona
            if (!$bus->fecha_proxima_revision && $bus->fecha_ultima_revision) {
                $bus->fecha_proxima_revision = Carbon::parse($bus->fecha_ultima_revision)->addMonths(6);
            }
        });
    }
}
