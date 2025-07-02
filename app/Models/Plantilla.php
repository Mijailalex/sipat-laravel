<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Plantilla extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'tipo',
        'configuracion_turnos',
        'parametros_especiales',
        'activa',
        'creado_por',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta'
    ];

    protected $casts = [
        'configuracion_turnos' => 'array',
        'parametros_especiales' => 'array',
        'activa' => 'boolean',
        'fecha_vigencia_desde' => 'datetime',
        'fecha_vigencia_hasta' => 'datetime'
    ];

    // Relaciones
    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function plantillaTurnos()
    {
        return $this->hasMany(PlantillaTurno::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeVigentes($query, $fecha = null)
    {
        $fecha = $fecha ?: now();

        return $query->where(function ($q) use ($fecha) {
            $q->whereNull('fecha_vigencia_desde')
              ->orWhere('fecha_vigencia_desde', '<=', $fecha);
        })->where(function ($q) use ($fecha) {
            $q->whereNull('fecha_vigencia_hasta')
              ->orWhere('fecha_vigencia_hasta', '>=', $fecha);
        });
    }

    public function scopeBuscarTexto($query, $texto)
    {
        return $query->where(function ($q) use ($texto) {
            $q->where('nombre', 'like', "%{$texto}%")
              ->orWhere('codigo', 'like', "%{$texto}%")
              ->orWhere('descripcion', 'like', "%{$texto}%");
        });
    }

    // Métodos de negocio
    public function estaVigente($fecha = null)
    {
        $fecha = $fecha ?: now();

        if (!$this->activa) {
            return false;
        }

        if ($this->fecha_vigencia_desde && $fecha < $this->fecha_vigencia_desde) {
            return false;
        }

        if ($this->fecha_vigencia_hasta && $fecha > $this->fecha_vigencia_hasta) {
            return false;
        }

        return true;
    }

    public function getTotalTurnosAttribute()
    {
        return count($this->configuracion_turnos);
    }

    public function getConductoresRequeridosAttribute()
    {
        return collect($this->configuracion_turnos)
            ->sum('conductores_requeridos');
    }

    public function getHorasPorDiaAttribute()
    {
        $totalMinutos = collect($this->configuracion_turnos)
            ->sum(function ($turno) {
                $inicio = Carbon::parse($turno['hora_inicio']);
                $fin = Carbon::parse($turno['hora_fin']);
                return $inicio->diffInMinutes($fin);
            });

        return round($totalMinutos / 60, 2);
    }

    public function generarTurnos($fechaInicio, $fechaFin, $conductores = [])
    {
        $fechaActual = Carbon::parse($fechaInicio);
        $fechaFinal = Carbon::parse($fechaFin);
        $turnosCreados = [];

        while ($fechaActual <= $fechaFinal) {
            $turnosDia = $this->generarTurnosPorDia($fechaActual, $conductores);
            $turnosCreados = array_merge($turnosCreados, $turnosDia);
            $fechaActual->addDay();
        }

        return $turnosCreados;
    }

    private function generarTurnosPorDia($fecha, $conductores = [])
    {
        $turnosCreados = [];
        $conductoresDisponibles = collect($conductores);

        foreach ($this->configuracion_turnos as $configuracion) {
            // Verificar si el turno aplica para este día
            if (!$this->turnoAplicaParaDia($configuracion, $fecha)) {
                continue;
            }

            $conductoresRequeridos = $configuracion['conductores_requeridos'] ?? 1;

            for ($i = 0; $i < $conductoresRequeridos; $i++) {
                $conductor = $this->seleccionarConductor($conductoresDisponibles, $configuracion);

                $turno = Turno::create([
                    'plantilla_id' => $this->id,
                    'conductor_id' => $conductor ? $conductor->id : null,
                    'fecha_turno' => $fecha->toDateString(),
                    'hora_inicio' => $configuracion['hora_inicio'],
                    'hora_fin' => $configuracion['hora_fin'],
                    'tipo_turno' => $configuracion['tipo'] ?? 'REGULAR',
                    'ruta_asignada' => $configuracion['ruta'] ?? null,
                    'origen_conductor' => $configuracion['origen'] ?? null,
                    'estado' => 'PROGRAMADO'
                ]);

                $turnosCreados[] = $turno;

                // Remover conductor de disponibles para evitar duplicados
                if ($conductor) {
                    $conductoresDisponibles = $conductoresDisponibles->reject(function ($c) use ($conductor) {
                        return $c->id === $conductor->id;
                    });
                }
            }
        }

        return $turnosCreados;
    }

    private function turnoAplicaParaDia($configuracion, $fecha)
    {
        if (!isset($configuracion['dias_semana'])) {
            return true; // Sin restricciones de días
        }

        $diaSemana = $fecha->dayOfWeek; // 0 = Domingo, 1 = Lunes, etc.
        $diasConfiguracion = $configuracion['dias_semana'];

        // Convertir días de texto a números si es necesario
        if (is_array($diasConfiguracion) && isset($diasConfiguracion[0]) && is_string($diasConfiguracion[0])) {
            $mapaDias = [
                'domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3,
                'jueves' => 4, 'viernes' => 5, 'sabado' => 6
            ];

            $diasConfiguracion = array_map(function ($dia) use ($mapaDias) {
                return $mapaDias[strtolower($dia)] ?? $dia;
            }, $diasConfiguracion);
        }

        return in_array($diaSemana, $diasConfiguracion);
    }

    private function seleccionarConductor($conductoresDisponibles, $configuracion)
    {
        if ($conductoresDisponibles->isEmpty()) {
            return null;
        }

        // Filtrar por requisitos especiales
        $conductoresFiltrados = $conductoresDisponibles;

        if (isset($configuracion['requisitos'])) {
            foreach ($configuracion['requisitos'] as $requisito => $valor) {
                switch ($requisito) {
                    case 'turno_preferido':
                        $conductoresFiltrados = $conductoresFiltrados->where('años_experiencia', '>=', $valor);
                        break;
                    case 'eficiencia_minima':
                        $conductoresFiltrados = $conductoresFiltrados->where('eficiencia', '>=', $valor);
                        break;
                    case 'origen_requerido':
                        $conductoresFiltrados = $conductoresFiltrados->where('origen_conductor', $valor);
                        break;
                }
            }
        }

        // Si no hay conductores que cumplan requisitos, usar todos los disponibles
        if ($conductoresFiltrados->isEmpty()) {
            $conductoresFiltrados = $conductoresDisponibles;
        }

        // Seleccionar el mejor conductor basado en score
        return $conductoresFiltrados->sortByDesc(function ($conductor) use ($configuracion) {
            return $this->calcularScoreConductor($conductor, $configuracion);
        })->first();
    }

    private function calcularScoreConductor($conductor, $configuracion)
    {
        $score = 0;

        // Score base por eficiencia y puntualidad
        $score += ($conductor->eficiencia * 0.4) + ($conductor->puntualidad * 0.4);

        // Bonus por turno preferido
        if (isset($configuracion['tipo']) && $conductor->turno_preferido === $configuracion['tipo']) {
            $score += 10;
        }

        // Bonus por origen coincidente
        if (isset($configuracion['origen']) && $conductor->origen_conductor === $configuracion['origen']) {
            $score += 10;
        }

        // Penalización por días acumulados
        $score -= $conductor->dias_acumulados * 2;

        return $score;
    }

    public function clonar($nuevoNombre, $nuevoCodigo = null)
    {
        $nuevoCodigo = $nuevoCodigo ?: $this->codigo . '_COPIA_' . time();

        return static::create([
            'nombre' => $nuevoNombre,
            'codigo' => $nuevoCodigo,
            'descripcion' => $this->descripcion . ' (Copia)',
            'tipo' => $this->tipo,
            'configuracion_turnos' => $this->configuracion_turnos,
            'parametros_especiales' => $this->parametros_especiales,
            'activa' => false, // Las copias inician inactivas
            'creado_por' => auth()->id(),
            'fecha_vigencia_desde' => null,
            'fecha_vigencia_hasta' => null
        ]);
    }

    public function validarConfiguracion()
    {
        $errores = [];

        // Validar estructura básica
        if (empty($this->configuracion_turnos)) {
            $errores[] = 'La plantilla debe tener al menos un turno configurado';
        }

        // Validar cada turno
        foreach ($this->configuracion_turnos as $index => $turno) {
            $numeroTurno = $index + 1;

            if (empty($turno['hora_inicio'])) {
                $errores[] = "Turno {$numeroTurno}: Hora de inicio requerida";
            }

            if (empty($turno['hora_fin'])) {
                $errores[] = "Turno {$numeroTurno}: Hora de fin requerida";
            }

            if (!empty($turno['hora_inicio']) && !empty($turno['hora_fin'])) {
                $inicio = Carbon::parse($turno['hora_inicio']);
                $fin = Carbon::parse($turno['hora_fin']);

                if ($fin <= $inicio) {
                    $errores[] = "Turno {$numeroTurno}: La hora de fin debe ser posterior a la hora de inicio";
                }
            }

            if (isset($turno['conductores_requeridos']) && $turno['conductores_requeridos'] < 1) {
                $errores[] = "Turno {$numeroTurno}: Debe requerir al menos 1 conductor";
            }
        }

        // Validar solapamientos de turnos
        $this->validarSolapamientos($errores);

        if (!empty($errores)) {
            throw new \Exception(implode('. ', $errores));
        }

        return true;
    }

    private function validarSolapamientos(&$errores)
    {
        $turnos = collect($this->configuracion_turnos);

        for ($i = 0; $i < $turnos->count(); $i++) {
            for ($j = $i + 1; $j < $turnos->count(); $j++) {
                $turno1 = $turnos[$i];
                $turno2 = $turnos[$j];

                $inicio1 = Carbon::parse($turno1['hora_inicio']);
                $fin1 = Carbon::parse($turno1['hora_fin']);
                $inicio2 = Carbon::parse($turno2['hora_inicio']);
                $fin2 = Carbon::parse($turno2['hora_fin']);

                // Verificar solapamiento
                if ($inicio1 < $fin2 && $inicio2 < $fin1) {
                    $errores[] = "Los turnos " . ($i + 1) . " y " . ($j + 1) . " tienen horarios solapados";
                }
            }
        }
    }

    public function obtenerEstadisticasUso($dias = 30)
    {
        $turnos = $this->turnos()
            ->where('fecha_turno', '>=', now()->subDays($dias))
            ->get();

        $turnosCompletados = $turnos->where('estado', 'COMPLETADO');

        return [
            'total_turnos_generados' => $turnos->count(),
            'turnos_completados' => $turnosCompletados->count(),
            'porcentaje_eficiencia' => $turnos->count() > 0
                ? round(($turnosCompletados->count() / $turnos->count()) * 100, 2)
                : 0,
            'conductores_diferentes' => $turnos->pluck('conductor_id')->unique()->count(),
            'dias_utilizados' => $turnos->pluck('fecha_turno')->unique()->count(),
            'promedio_turnos_dia' => $turnos->pluck('fecha_turno')->unique()->count() > 0
                ? round($turnos->count() / $turnos->pluck('fecha_turno')->unique()->count(), 2)
                : 0,
            'horas_totales_programadas' => $turnos->sum('horas_trabajadas'),
            'eficiencia_promedio_conductores' => $turnosCompletados->avg('eficiencia_turno') ?: 0
        ];
    }

    public static function obtenerMasUtilizadas($limite = 5, $dias = 30)
    {
        return static::withCount([
                'turnos as total_turnos' => function ($query) use ($dias) {
                    $query->where('fecha_turno', '>=', now()->subDays($dias));
                }
            ])
            ->activas()
            ->having('total_turnos', '>', 0)
            ->orderBy('total_turnos', 'desc')
            ->limit($limite)
            ->get();
    }

    public function activar($fechaVigencia = null)
    {
        // Validar antes de activar
        $this->validarConfiguracion();

        $this->update([
            'activa' => true,
            'fecha_vigencia_desde' => $fechaVigencia ?: now()
        ]);

        return true;
    }

    public function desactivar($fechaFin = null)
    {
        $this->update([
            'activa' => false,
            'fecha_vigencia_hasta' => $fechaFin ?: now()
        ]);

        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plantilla) {
            // Generar código automático si no se proporciona
            if (!$plantilla->codigo) {
                $ultimo = static::max('id') + 1;
                $plantilla->codigo = 'PLANT' . str_pad($ultimo, 4, '0', STR_PAD_LEFT);
            }

            // Validar configuración antes de crear
            if ($plantilla->configuracion_turnos) {
                $temp = new static($plantilla->getAttributes());
                $temp->validarConfiguracion();
            }
        });

        static::updating(function ($plantilla) {
            // Validar configuración antes de actualizar
            if ($plantilla->isDirty('configuracion_turnos')) {
                $plantilla->validarConfiguracion();
            }
        });
    }
}->where('turno_preferido', $valor);
                        break;
                    case 'experiencia_minima':
                        $conductoresFiltrados = $conductoresFiltrados
