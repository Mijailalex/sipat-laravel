<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Turno extends Model
{
    use HasFactory;

    protected $fillable = [
        'plantilla_id',
        'conductor_id',
        'fecha_turno',
        'hora_inicio',
        'hora_fin',
        'tipo_turno',
        'ruta_asignada',
        'origen_conductor',
        'estado',
        'observaciones',
        'horas_trabajadas',
        'eficiencia_turno'
    ];

    protected $casts = [
        'fecha_turno' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'horas_trabajadas' => 'decimal:2',
        'eficiencia_turno' => 'decimal:2'
    ];

    // Relaciones
    public function plantilla()
    {
        return $this->belongsTo(Plantilla::class);
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    // Scopes
    public function scopeHoy($query)
    {
        return $query->where('fecha_turno', now()->toDateString());
    }

    public function scopeFecha($query, $fecha)
    {
        return $query->where('fecha_turno', $fecha);
    }

    public function scopeRangoFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_turno', [$desde, $hasta]);
    }

    public function scopeProgramados($query)
    {
        return $query->where('estado', 'PROGRAMADO');
    }

    public function scopeEnCurso($query)
    {
        return $query->where('estado', 'EN_CURSO');
    }

    public function scopeCompletados($query)
    {
        return $query->where('estado', 'COMPLETADO');
    }

    public function scopeCancelados($query)
    {
        return $query->where('estado', 'CANCELADO');
    }

    public function scopeConductor($query, $conductorId)
    {
        return $query->where('conductor_id', $conductorId);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo_turno', $tipo);
    }

    public function scopeSinConductor($query)
    {
        return $query->whereNull('conductor_id');
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

    public function getEstadoClaseAttribute()
    {
        return match($this->estado) {
            'PROGRAMADO' => 'primary',
            'EN_CURSO' => 'warning',
            'COMPLETADO' => 'success',
            'CANCELADO' => 'danger',
            default => 'secondary'
        };
    }

    public function getHorarioCompletoAttribute()
    {
        return Carbon::parse($this->hora_inicio)->format('H:i') . ' - ' .
               Carbon::parse($this->hora_fin)->format('H:i');
    }

    public function puedeIniciar()
    {
        if ($this->estado !== 'PROGRAMADO') {
            return false;
        }

        if (!$this->conductor_id) {
            return false;
        }

        if (!$this->conductor->estaDisponiblePara($this->hora_inicio)) {
            return false;
        }

        // Verificar que esté dentro del horario permitido (30 min antes)
        $ahora = now();
        $horaInicio = Carbon::parse($this->fecha_turno->format('Y-m-d') . ' ' . $this->hora_inicio->format('H:i:s'));
        $ventanaInicio = $horaInicio->copy()->subMinutes(30);

        return $ahora >= $ventanaInicio && $ahora <= $horaInicio->addHours(1);
    }

    public function iniciar()
    {
        if (!$this->puedeIniciar()) {
            throw new \Exception('El turno no puede ser iniciado en este momento');
        }

        $this->update([
            'estado' => 'EN_CURSO',
            'observaciones' => ($this->observaciones ?? '') . "\nIniciado: " . now()->format('Y-m-d H:i:s')
        ]);

        // Actualizar conductor
        $this->conductor->update([
            'ultimo_servicio' => now()
        ]);

        return true;
    }

    public function completar($datos = [])
    {
        if ($this->estado !== 'EN_CURSO') {
            throw new \Exception('Solo se pueden completar turnos en curso');
        }

        $horaFin = now();
        $horaInicioReal = Carbon::parse($this->observaciones ?
            $this->extraerHoraInicio() :
            $this->fecha_turno->format('Y-m-d') . ' ' . $this->hora_inicio->format('H:i:s')
        );

        $horasTrabajadas = $horaInicioReal->diffInMinutes($horaFin) / 60;
        $eficiencia = $this->calcularEficiencia($horasTrabajadas);

        $datosActualizacion = array_merge([
            'estado' => 'COMPLETADO',
            'horas_trabajadas' => round($horasTrabajadas, 2),
            'eficiencia_turno' => $eficiencia,
            'observaciones' => ($this->observaciones ?? '') . "\nCompletado: " . $horaFin->format('Y-m-d H:i:s')
        ], $datos);

        $this->update($datosActualizacion);

        // Actualizar métricas del conductor
        $this->actualizarConductor($horasTrabajadas);

        return true;
    }

    public function cancelar($motivo = null)
    {
        if (in_array($this->estado, ['COMPLETADO', 'CANCELADO'])) {
            throw new \Exception('No se puede cancelar un turno completado o ya cancelado');
        }

        $this->update([
            'estado' => 'CANCELADO',
            'observaciones' => ($this->observaciones ?? '') . "\nCancelado: " . now()->format('Y-m-d H:i:s') .
                ($motivo ? " - Motivo: {$motivo}" : '')
        ]);

        return true;
    }

    public function asignarConductor($conductorId)
    {
        $conductor = Conductor::findOrFail($conductorId);

        if (!$conductor->estaDisponiblePara($this->hora_inicio)) {
            throw new \Exception('El conductor no está disponible para este turno');
        }

        // Verificar conflictos con otros turnos
        $conflictos = static::where('conductor_id', $conductorId)
            ->where('fecha_turno', $this->fecha_turno)
            ->where('id', '!=', $this->id)
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

        if ($conflictos) {
            throw new \Exception('El conductor tiene un conflicto de horario');
        }

        $this->update([
            'conductor_id' => $conductorId,
            'origen_conductor' => $conductor->origen_conductor
        ]);

        return true;
    }

    private function extraerHoraInicio()
    {
        if (preg_match('/Iniciado: (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $this->observaciones, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function calcularEficiencia($horasReales)
    {
        $horasProgramadas = $this->duracion_horas;

        if ($horasProgramadas == 0) {
            return 100; // Si no hay horas programadas, 100% de eficiencia
        }

        // Eficiencia basada en cercanía a las horas programadas
        $diferencia = abs($horasReales - $horasProgramadas);
        $porcentajeDiferencia = ($diferencia / $horasProgramadas) * 100;

        // Si se excede o queda corto por más del 20%, reducir eficiencia
        if ($porcentajeDiferencia <= 5) {
            return 100;
        } elseif ($porcentajeDiferencia <= 10) {
            return 95;
        } elseif ($porcentajeDiferencia <= 20) {
            return 85;
        } else {
            return max(50, 100 - $porcentajeDiferencia);
        }
    }

    private function actualizarConductor($horasTrabajadas)
    {
        $conductor = $this->conductor;

        // Incrementar horas hombre
        $conductor->increment('horas_hombre', $horasTrabajadas);

        // Incrementar días acumulados si es un nuevo día
        if (!$conductor->ultimo_servicio ||
            $conductor->ultimo_servicio->toDateString() !== $this->fecha_turno->toDateString()) {
            $conductor->increment('dias_acumulados');
        }

        // Actualizar último servicio
        $conductor->update(['ultimo_servicio' => now()]);

        // Recalcular métricas
        $conductor->actualizarMetricas();
    }

    public static function obtenerEstadisticasHoy()
    {
        $hoy = now()->toDateString();

        return [
            'total' => static::fecha($hoy)->count(),
            'programados' => static::fecha($hoy)->programados()->count(),
            'en_curso' => static::fecha($hoy)->enCurso()->count(),
            'completados' => static::fecha($hoy)->completados()->count(),
            'cancelados' => static::fecha($hoy)->cancelados()->count(),
            'sin_conductor' => static::fecha($hoy)->sinConductor()->count(),
            'horas_programadas' => static::fecha($hoy)->sum(\DB::raw('TIME_TO_SEC(TIMEDIFF(hora_fin, hora_inicio))/3600')),
            'horas_trabajadas' => static::fecha($hoy)->completados()->sum('horas_trabajadas'),
            'eficiencia_promedio' => static::fecha($hoy)->completados()->avg('eficiencia_turno') ?: 0
        ];
    }

    public static function obtenerTurnosPendientesAsignacion($limite = 20)
    {
        return static::sinConductor()
            ->programados()
            ->where('fecha_turno', '>=', now()->toDateString())
            ->orderBy('fecha_turno')
            ->orderBy('hora_inicio')
            ->limit($limite)
            ->with('plantilla')
            ->get();
    }

    public static function sugerirConductorParaTurno($turnoId)
    {
        $turno = static::findOrFail($turnoId);

        $conductoresDisponibles = Conductor::disponibles()
            ->where(function ($query) use ($turno) {
                $query->whereNull('ultimo_servicio')
                      ->orWhere('ultimo_servicio', '<=',
                          Carbon::parse($turno->fecha_turno->format('Y-m-d') . ' ' . $turno->hora_inicio->format('H:i:s'))
                              ->subHours(12)
                      );
            })
            ->get();

        return $conductoresDisponibles->map(function ($conductor) use ($turno) {
            return [
                'conductor' => $conductor,
                'compatibilidad' => $conductor->calcularCompatibilidad($turno),
                'disponible' => $conductor->estaDisponiblePara($turno->hora_inicio)
            ];
        })->sortByDesc('compatibilidad');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($turno) {
            // Validar que no existan conflictos de horario para el conductor
            if ($turno->conductor_id) {
                $conflictos = static::where('conductor_id', $turno->conductor_id)
                    ->where('fecha_turno', $turno->fecha_turno)
                    ->where('estado', '!=', 'CANCELADO')
                    ->where(function ($query) use ($turno) {
                        $query->whereBetween('hora_inicio', [$turno->hora_inicio, $turno->hora_fin])
                              ->orWhereBetween('hora_fin', [$turno->hora_inicio, $turno->hora_fin]);
                    })
                    ->exists();

                if ($conflictos) {
                    throw new \Exception('El conductor tiene un conflicto de horario para este turno');
                }
            }
        });
    }
}
