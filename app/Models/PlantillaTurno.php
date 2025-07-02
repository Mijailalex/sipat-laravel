<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PlantillaTurno extends Model
{
    use HasFactory;

    protected $table = 'plantilla_turnos';

    protected $fillable = [
        'plantilla_id',
        'nombre_turno',
        'hora_inicio',
        'hora_fin',
        'tipo',
        'descripcion',
        'dias_semana',
        'cantidad_conductores_requeridos',
        'requisitos_especiales',
        'factor_pago',
        'activo'
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'dias_semana' => 'array',
        'cantidad_conductores_requeridos' => 'integer',
        'requisitos_especiales' => 'array',
        'factor_pago' => 'decimal:2',
        'activo' => 'boolean'
    ];

    // Relaciones
    public function obtenerConductoresElegibles()
    {
        $conductores = Conductor::disponibles()->get();

        return $conductores->filter(function ($conductor) {
            return $this->cumpleRequisitos($conductor);
        });
    }

    public function calcularCostoEstimado()
    {
        $salarioBasePorHora = 15; // S/. por hora base
        $costoBase = $this->duracion_horas * $salarioBasePorHora * $this->cantidad_conductores_requeridos;

        return $costoBase * $this->factor_pago;
    }

    public function validarHorarios()
    {
        $errores = [];

        if (!$this->hora_inicio) {
            $errores[] = 'Hora de inicio requerida';
        }

        if (!$this->hora_fin) {
            $errores[] = 'Hora de fin requerida';
        }

        if ($this->hora_inicio && $this->hora_fin) {
            $inicio = Carbon::parse($this->hora_inicio);
            $fin = Carbon::parse($this->hora_fin);

            if ($fin <= $inicio) {
                $errores[] = 'La hora de fin debe ser posterior a la hora de inicio';
            }

            if ($inicio->diffInHours($fin) > 12) {
                $errores[] = 'Un turno no puede durar más de 12 horas';
            }
        }

        if (empty($this->dias_semana)) {
            $errores[] = 'Debe especificar al menos un día de la semana';
        }

        if ($this->cantidad_conductores_requeridos < 1) {
            $errores[] = 'Debe requerir al menos 1 conductor';
        }

        if ($this->factor_pago < 0.5 || $this->factor_pago > 3.0) {
            $errores[] = 'El factor de pago debe estar entre 0.5 y 3.0';
        }

        return $errores;
    }

    public function verificarSolapamientos()
    {
        $solapamientos = static::where('plantilla_id', $this->plantilla_id)
            ->where('id', '!=', $this->id)
            ->activos()
            ->get()
            ->filter(function ($otroTurno) {
                // Verificar si tienen días en común
                $diasEnComun = array_intersect($this->dias_semana ?: [], $otroTurno->dias_semana ?: []);

                if (empty($diasEnComun)) {
                    return false;
                }

                // Verificar solapamiento de horarios
                $inicio1 = Carbon::parse($this->hora_inicio);
                $fin1 = Carbon::parse($this->hora_fin);
                $inicio2 = Carbon::parse($otroTurno->hora_inicio);
                $fin2 = Carbon::parse($otroTurno->hora_fin);

                return $inicio1 < $fin2 && $inicio2 < $fin1;
            });

        return $solapamientos;
    }

    public function generarTurnos($fecha, $conductores = [])
    {
        if (!$this->aplicaEnFecha($fecha)) {
            return [];
        }

        $turnosCreados = [];
        $conductoresDisponibles = collect($conductores);

        for ($i = 0; $i < $this->cantidad_conductores_requeridos; $i++) {
            $conductor = $this->seleccionarMejorConductor($conductoresDisponibles, $fecha);

            $turno = Turno::create([
                'plantilla_id' => $this->plantilla_id,
                'conductor_id' => $conductor ? $conductor->id : null,
                'fecha_turno' => $fecha,
                'hora_inicio' => $this->hora_inicio,
                'hora_fin' => $this->hora_fin,
                'tipo_turno' => $this->tipo,
                'ruta_asignada' => null,
                'origen_conductor' => $conductor ? $conductor->origen_conductor : null,
                'estado' => 'PROGRAMADO',
                'observaciones' => "Generado desde plantilla turno: {$this->nombre_turno}"
            ]);

            $turnosCreados[] = $turno;

            // Remover conductor de disponibles
            if ($conductor) {
                $conductoresDisponibles = $conductoresDisponibles->reject(function ($c) use ($conductor) {
                    return $c->id === $conductor->id;
                });
            }
        }

        return $turnosCreados;
    }

    private function seleccionarMejorConductor($conductoresDisponibles, $fecha)
    {
        // Filtrar por requisitos
        $conductoresElegibles = $conductoresDisponibles->filter(function ($conductor) {
            return $this->cumpleRequisitos($conductor);
        });

        if ($conductoresElegibles->isEmpty()) {
            return null;
        }

        // Verificar disponibilidad en la fecha/hora
        $conductoresSinConflicto = $conductoresElegibles->filter(function ($conductor) use ($fecha) {
            return !Turno::where('conductor_id', $conductor->id)
                ->where('fecha_turno', $fecha)
                ->where('estado', '!=', 'CANCELADO')
                ->where(function ($query) {
                    $query->whereBetween('hora_inicio', [$this->hora_inicio, $this->hora_fin])
                          ->orWhereBetween('hora_fin', [$this->hora_inicio, $this->hora_fin])
                          ->orWhere(function ($q) {
                              $q->where('hora_inicio', '<=', $this->hora_inicio)
                                ->where('hora_fin', '>=', $this->hora_fin);
                          });
                })
                ->exists();
        });

        if ($conductoresSinConflicto->isEmpty()) {
            return null;
        }

        // Seleccionar el mejor basado en score
        return $conductoresSinConflicto->sortByDesc(function ($conductor) {
            return $this->calcularScoreConductor($conductor);
        })->first();
    }

    private function calcularScoreConductor($conductor)
    {
        $score = 0;

        // Score base por eficiencia y puntualidad
        $score += ($conductor->eficiencia * 0.4) + ($conductor->puntualidad * 0.4);

        // Bonus por tipo de turno preferido
        if ($conductor->turno_preferido === $this->tipo || $conductor->turno_preferido === 'ROTATIVO') {
            $score += 15;
        }

        // Bonus por experiencia para turnos especiales
        if (in_array($this->tipo, ['NOCTURNO', 'ESPECIAL'])) {
            $score += min(10, $conductor->años_experiencia * 2);
        }

        // Penalización por días acumulados
        $score -= $conductor->dias_acumulados * 3;

        // Bonus por factor de pago alto (turnos más complejos)
        if ($this->factor_pago > 1.2) {
            $score += 5;
        }

        return $score;
    }

    public function clonar($nuevaPlantilla = null, $nuevoNombre = null)
    {
        return static::create([
            'plantilla_id' => $nuevaPlantilla ?: $this->plantilla_id,
            'nombre_turno' => $nuevoNombre ?: $this->nombre_turno . ' (Copia)',
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'tipo' => $this->tipo,
            'descripcion' => $this->descripcion,
            'dias_semana' => $this->dias_semana,
            'cantidad_conductores_requeridos' => $this->cantidad_conductores_requeridos,
            'requisitos_especiales' => $this->requisitos_especiales,
            'factor_pago' => $this->factor_pago,
            'activo' => false // Las copias inician inactivas
        ]);
    }

    public static function obtenerTurnosParaFecha($fecha, $plantillaId = null)
    {
        $query = static::activos();

        if ($plantillaId) {
            $query->where('plantilla_id', $plantillaId);
        }

        $fecha = is_string($fecha) ? Carbon::parse($fecha) : $fecha;
        $diaSemana = $fecha->dayOfWeek;

        return $query->whereJsonContains('dias_semana', $diaSemana)
            ->orderBy('hora_inicio')
            ->get();
    }

    public static function obtenerEstadisticasUso($plantillaId, $dias = 30)
    {
        $turnos = Turno::where('plantilla_id', $plantillaId)
            ->where('fecha_turno', '>=', now()->subDays($dias))
            ->get();

        $turnosCompletados = $turnos->where('estado', 'COMPLETADO');

        // Agrupar por tipo de turno
        $estadisticasPorTipo = $turnos->groupBy(function ($turno) {
            // Buscar el plantilla turno correspondiente
            $plantillaTurnos = static::where('plantilla_id', $turno->plantilla_id)->get();

            foreach ($plantillaTurnos as $pt) {
                if ($pt->hora_inicio->format('H:i') === $turno->hora_inicio->format('H:i') &&
                    $pt->hora_fin->format('H:i') === $turno->hora_fin->format('H:i')) {
                    return $pt->tipo;
                }
            }

            return 'NO_IDENTIFICADO';
        });

        return [
            'total_turnos_generados' => $turnos->count(),
            'turnos_completados' => $turnosCompletados->count(),
            'porcentaje_completados' => $turnos->count() > 0
                ? round(($turnosCompletados->count() / $turnos->count()) * 100, 2)
                : 0,
            'conductores_diferentes' => $turnos->pluck('conductor_id')->unique()->count(),
            'horas_totales_trabajadas' => $turnosCompletados->sum('horas_trabajadas'),
            'eficiencia_promedio' => $turnosCompletados->avg('eficiencia_turno') ?: 0,
            'por_tipo' => $estadisticasPorTipo->map(function ($grupo, $tipo) {
                $completados = $grupo->where('estado', 'COMPLETADO');
                return [
                    'total' => $grupo->count(),
                    'completados' => $completados->count(),
                    'porcentaje' => $grupo->count() > 0
                        ? round(($completados->count() / $grupo->count()) * 100, 2)
                        : 0
                ];
            })->toArray()
        ];
    }

    public static function validarCoherenciaPlantilla($plantillaId)
    {
        $turnos = static::where('plantilla_id', $plantillaId)->activos()->get();
        $errores = [];

        // Verificar solapamientos
        foreach ($turnos as $turno) {
            $solapamientos = $turno->verificarSolapamientos();
            if ($solapamientos->isNotEmpty()) {
                $errores[] = "El turno '{$turno->nombre_turno}' tiene solapamientos con otros turnos";
            }
        }

        // Verificar cobertura de días
        $diasCubiertos = $turnos->pluck('dias_semana')->flatten()->unique()->sort()->values();
        if ($diasCubiertos->count() < 7) {
            $diasFaltantes = collect([0, 1, 2, 3, 4, 5, 6])->diff($diasCubiertos);
            $nombresDias = [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
            $diasTexto = $diasFaltantes->map(fn($dia) => $nombresDias[$dia])->implode(', ');
            $errores[] = "Faltan turnos para los días: {$diasTexto}";
        }

        // Verificar conductores requeridos vs disponibles
        $totalConductoresRequeridos = $turnos->sum('cantidad_conductores_requeridos');
        $conductoresDisponibles = Conductor::disponibles()->count();

        if ($totalConductoresRequeridos > $conductoresDisponibles) {
            $errores[] = "Se requieren {$totalConductoresRequeridos} conductores pero solo hay {$conductoresDisponibles} disponibles";
        }

        return $errores;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plantillaTurno) {
            $errores = $plantillaTurno->validarHorarios();
            if (!empty($errores)) {
                throw new \Exception(implode('. ', $errores));
            }
        });

        static::updating(function ($plantillaTurno) {
            $errores = $plantillaTurno->validarHorarios();
            if (!empty($errores)) {
                throw new \Exception(implode('. ', $errores));
            }
        });
    }
} function plantilla()
    {
        return $this->belongsTo(Plantilla::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePlantilla($query, $plantillaId)
    {
        return $query->where('plantilla_id', $plantillaId);
    }

    public function scopeAplicaHoy($query)
    {
        $hoy = now()->dayOfWeek; // 0=Domingo, 1=Lunes, etc.

        return $query->whereJsonContains('dias_semana', $hoy);
    }

    public function scopeEnHorario($query, $hora)
    {
        return $query->whereTime('hora_inicio', '<=', $hora)
                    ->whereTime('hora_fin', '>=', $hora);
    }

    // Métodos de negocio
    public function getDuracionHorasAttribute()
    {
        if ($this->hora_inicio && $this->hora_fin) {
            $inicio = Carbon::parse($this->hora_inicio);
            $fin = Carbon::parse($this->hora_fin);
            return round($inicio->diffInMinutes($fin) / 60, 2);
        }
        return 0;
    }

    public function getTipoClaseAttribute()
    {
        return match($this->tipo) {
            'REGULAR' => 'primary',
            'NOCTURNO' => 'dark',
            'ESPECIAL' => 'warning',
            'REFUERZO' => 'info',
            default => 'secondary'
        };
    }

    public function getHorarioCompletoAttribute()
    {
        return Carbon::parse($this->hora_inicio)->format('H:i') . ' - ' .
               Carbon::parse($this->hora_fin)->format('H:i');
    }

    public function getDiasTextoAttribute()
    {
        if (!$this->dias_semana) {
            return 'Sin días definidos';
        }

        $nombresDias = [
            0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mié',
            4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'
        ];

        $diasTexto = array_map(function($dia) use ($nombresDias) {
            return $nombresDias[$dia] ?? $dia;
        }, $this->dias_semana);

        return implode(', ', $diasTexto);
    }

    public function aplicaEnFecha($fecha)
    {
        $fecha = is_string($fecha) ? Carbon::parse($fecha) : $fecha;
        $diaSemana = $fecha->dayOfWeek;

        return in_array($diaSemana, $this->dias_semana ?: []);
    }

    public function cumpleRequisitos($conductor)
    {
        if (!$this->requisitos_especiales) {
            return true;
        }

        foreach ($this->requisitos_especiales as $requisito => $valor) {
            switch ($requisito) {
                case 'experiencia_minima':
                    if ($conductor->años_experiencia < $valor) {
                        return false;
                    }
                    break;

                case 'eficiencia_minima':
                    if ($conductor->eficiencia < $valor) {
                        return false;
                    }
                    break;

                case 'puntualidad_minima':
                    if ($conductor->puntualidad < $valor) {
                        return false;
                    }
                    break;

                case 'categoria_licencia':
                    if ($conductor->categoria_licencia !== $valor) {
                        return false;
                    }
                    break;

                case 'turno_preferido':
                    if ($conductor->turno_preferido !== $valor) {
                        return false;
                    }
                    break;

                case 'certificaciones_requeridas':
                    $certificacionesConductor = $conductor->certificaciones ?: [];
                    foreach ($valor as $certificacion) {
                        if (!in_array($certificacion, $certificacionesConductor)) {
                            return false;
                        }
                    }
                    break;

                case 'subempresa':
                    if ($conductor->subempresa !== $valor) {
                        return false;
                    }
                    break;

                case 'origen_requerido':
                    if ($conductor->origen_conductor !== $valor) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    public
