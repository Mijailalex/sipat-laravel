<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PlanificacionDescanso extends Model
{
    use HasFactory;

    protected $table = 'planificacion_descansos';

    protected $fillable = [
        'conductor_id',
        'fecha_inicio_descanso',
        'fecha_fin_descanso',
        'tipo_descanso',
        'estado',
        'motivo',
        'aprobado_por',
        'fecha_aprobacion',
        'creado_por',
        'observaciones',
        'es_automatico',
        'datos_adicionales'
    ];

    protected $casts = [
        'fecha_inicio_descanso' => 'date',
        'fecha_fin_descanso' => 'date',
        'fecha_aprobacion' => 'datetime',
        'es_automatico' => 'boolean',
        'datos_adicionales' => 'array'
    ];

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    // Scopes
    public function scopePlanificados($query)
    {
        return $query->where('estado', 'PLANIFICADO');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeCompletados($query)
    {
        return $query->where('estado', 'COMPLETADO');
    }

    public function scopeCancelados($query)
    {
        return $query->where('estado', 'CANCELADO');
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo_descanso', $tipo);
    }

    public function scopeAutomaticos($query)
    {
        return $query->where('es_automatico', true);
    }

    public function scopeManuales($query)
    {
        return $query->where('es_automatico', false);
    }

    public function scopeEnFecha($query, $fecha)
    {
        return $query->where('fecha_inicio_descanso', '<=', $fecha)
                    ->where('fecha_fin_descanso', '>=', $fecha);
    }

    public function scopeProximosAIniciar($query, $dias = 7)
    {
        return $query->where('fecha_inicio_descanso', '>=', now()->toDateString())
                    ->where('fecha_inicio_descanso', '<=', now()->addDays($dias)->toDateString())
                    ->where('estado', 'PLANIFICADO');
    }

    public function scopeProximosATerminar($query, $dias = 3)
    {
        return $query->where('fecha_fin_descanso', '>=', now()->toDateString())
                    ->where('fecha_fin_descanso', '<=', now()->addDays($dias)->toDateString())
                    ->where('estado', 'ACTIVO');
    }

    // Métodos de negocio
    public function getDuracionDiasAttribute()
    {
        return $this->fecha_inicio_descanso->diffInDays($this->fecha_fin_descanso) + 1;
    }

    public function getEstadoClaseAttribute()
    {
        return match($this->estado) {
            'PLANIFICADO' => 'primary',
            'ACTIVO' => 'warning',
            'COMPLETADO' => 'success',
            'CANCELADO' => 'danger',
            default => 'secondary'
        };
    }

    public function getTipoClaseAttribute()
    {
        return match($this->tipo_descanso) {
            'FISICO' => 'info',
            'SEMANAL' => 'primary',
            'VACACIONES' => 'success',
            'MEDICO' => 'warning',
            'PERSONAL' => 'secondary',
            default => 'light'
        };
    }

    public function getDiasRestantesAttribute()
    {
        if ($this->estado !== 'ACTIVO') {
            return 0;
        }

        $hoy = now()->toDateString();
        if ($hoy > $this->fecha_fin_descanso) {
            return 0;
        }

        return now()->diffInDays($this->fecha_fin_descanso) + 1;
    }

    public function puedeAprobar()
    {
        return $this->estado === 'PLANIFICADO' && !$this->es_automatico;
    }

    public function puedeIniciar()
    {
        return $this->estado === 'PLANIFICADO' &&
               now()->toDateString() >= $this->fecha_inicio_descanso->toDateString();
    }

    public function puedeCompletar()
    {
        return $this->estado === 'ACTIVO' &&
               now()->toDateString() >= $this->fecha_fin_descanso->toDateString();
    }

    public function aprobar($usuario_id = null)
    {
        if (!$this->puedeAprobar()) {
            throw new \Exception('Este descanso no puede ser aprobado');
        }

        $this->update([
            'estado' => $this->puedeIniciar() ? 'ACTIVO' : 'PLANIFICADO',
            'aprobado_por' => $usuario_id ?? auth()->id(),
            'fecha_aprobacion' => now()
        ]);

        // Si puede iniciar inmediatamente, actualizar el conductor
        if ($this->puedeIniciar()) {
            $this->iniciarDescanso();
        }

        return true;
    }

    public function iniciar()
    {
        if (!$this->puedeIniciar()) {
            throw new \Exception('Este descanso no puede ser iniciado aún');
        }

        $this->iniciarDescanso();
        return true;
    }

    private function iniciarDescanso()
    {
        $this->update(['estado' => 'ACTIVO']);

        // Actualizar estado del conductor
        $estadoConductor = match($this->tipo_descanso) {
            'SEMANAL' => 'DESCANSO_SEMANAL',
            'VACACIONES' => 'VACACIONES',
            'MEDICO' => 'SUSPENDIDO',
            default => 'DESCANSO_FISICO'
        };

        $this->conductor->update([
            'estado' => $estadoConductor,
            'fecha_ultimo_descanso' => now(),
            'dias_acumulados' => 0
        ]);
    }

    public function completar($observaciones = null)
    {
        if (!$this->puedeCompletar()) {
            throw new \Exception('Este descanso no puede ser completado aún');
        }

        $this->update([
            'estado' => 'COMPLETADO',
            'observaciones' => $this->observaciones . ($observaciones ? "\n" . $observaciones : '')
        ]);

        // Retornar conductor a disponible
        $this->conductor->update([
            'estado' => 'DISPONIBLE',
            'dias_acumulados' => 0
        ]);

        return true;
    }

    public function cancelar($motivo = null)
    {
        if (in_array($this->estado, ['COMPLETADO', 'CANCELADO'])) {
            throw new \Exception('No se puede cancelar un descanso completado o ya cancelado');
        }

        $this->update([
            'estado' => 'CANCELADO',
            'observaciones' => $this->observaciones . "\nCancelado: " . now()->format('Y-m-d H:i:s') .
                ($motivo ? " - Motivo: {$motivo}" : '')
        ]);

        // Si estaba activo, retornar conductor a disponible
        if ($this->estado === 'ACTIVO') {
            $this->conductor->update(['estado' => 'DISPONIBLE']);
        }

        return true;
    }

    public function extender($nuevaFechaFin, $motivo = null)
    {
        if ($this->estado !== 'ACTIVO') {
            throw new \Exception('Solo se pueden extender descansos activos');
        }

        $nuevaFecha = Carbon::parse($nuevaFechaFin);
        if ($nuevaFecha <= $this->fecha_fin_descanso) {
            throw new \Exception('La nueva fecha debe ser posterior a la fecha actual de fin');
        }

        $this->update([
            'fecha_fin_descanso' => $nuevaFecha,
            'observaciones' => $this->observaciones . "\nExtendido hasta: " . $nuevaFecha->format('Y-m-d') .
                ($motivo ? " - Motivo: {$motivo}" : '')
        ]);

        return true;
    }

    public static function planificarDescansoAutomatico($conductorId, $tipo = 'FISICO')
    {
        $conductor = Conductor::findOrFail($conductorId);

        if (!$conductor->necesitaDescanso() && $tipo === 'FISICO') {
            throw new \Exception('El conductor no requiere descanso obligatorio');
        }

        // Verificar que no tenga descansos activos o planificados
        $descansoExistente = static::where('conductor_id', $conductorId)
            ->whereIn('estado', ['PLANIFICADO', 'ACTIVO'])
            ->exists();

        if ($descansoExistente) {
            throw new \Exception('El conductor ya tiene un descanso planificado o activo');
        }

        $diasDescanso = match($tipo) {
            'FISICO' => 1,
            'SEMANAL' => 2,
            'VACACIONES' => 15,
            default => 1
        };

        $fechaInicio = now()->addDay(); // Comenzar mañana
        $fechaFin = $fechaInicio->copy()->addDays($diasDescanso - 1);

        return static::create([
            'conductor_id' => $conductorId,
            'fecha_inicio_descanso' => $fechaInicio,
            'fecha_fin_descanso' => $fechaFin,
            'tipo_descanso' => $tipo,
            'estado' => 'PLANIFICADO',
            'motivo' => "Descanso automático generado por el sistema",
            'es_automatico' => true,
            'creado_por' => 1, // Usuario del sistema
            'datos_adicionales' => [
                'dias_acumulados_conductor' => $conductor->dias_acumulados,
                'eficiencia_conductor' => $conductor->eficiencia,
                'fecha_generacion' => now()->toDateTimeString()
            ]
        ]);
    }

    public static function obtenerCalendarioDescansos($mes = null, $año = null)
    {
        $mes = $mes ?: now()->month;
        $año = $año ?: now()->year;

        $fechaInicio = Carbon::create($año, $mes, 1)->startOfMonth();
        $fechaFin = $fechaInicio->copy()->endOfMonth();

        return static::with('conductor:id,codigo_conductor,nombre,apellido')
            ->where(function ($query) use ($fechaInicio, $fechaFin) {
                $query->whereBetween('fecha_inicio_descanso', [$fechaInicio, $fechaFin])
                      ->orWhereBetween('fecha_fin_descanso', [$fechaInicio, $fechaFin])
                      ->orWhere(function ($q) use ($fechaInicio, $fechaFin) {
                          $q->where('fecha_inicio_descanso', '<=', $fechaInicio)
                            ->where('fecha_fin_descanso', '>=', $fechaFin);
                      });
            })
            ->whereIn('estado', ['PLANIFICADO', 'ACTIVO'])
            ->orderBy('fecha_inicio_descanso')
            ->get()
            ->map(function ($descanso) use ($fechaInicio, $fechaFin) {
                // Ajustar fechas al rango del mes
                $inicio = $descanso->fecha_inicio_descanso < $fechaInicio
                    ? $fechaInicio
                    : $descanso->fecha_inicio_descanso;
                $fin = $descanso->fecha_fin_descanso > $fechaFin
                    ? $fechaFin
                    : $descanso->fecha_fin_descanso;

                return [
                    'id' => $descanso->id,
                    'conductor' => $descanso->conductor,
                    'tipo' => $descanso->tipo_descanso,
                    'estado' => $descanso->estado,
                    'fecha_inicio_mes' => $inicio,
                    'fecha_fin_mes' => $fin,
                    'duracion_visible' => $inicio->diffInDays($fin) + 1
                ];
            });
    }

    public static function obtenerEstadisticas($dias = 30)
    {
        $fecha_desde = now()->subDays($dias);

        return [
            'total_descansos' => static::where('created_at', '>=', $fecha_desde)->count(),
            'automaticos' => static::where('created_at', '>=', $fecha_desde)->automaticos()->count(),
            'manuales' => static::where('created_at', '>=', $fecha_desde)->manuales()->count(),
            'por_tipo' => static::where('created_at', '>=', $fecha_desde)
                ->selectRaw('tipo_descanso, COUNT(*) as cantidad')
                ->groupBy('tipo_descanso')
                ->pluck('cantidad', 'tipo_descanso')
                ->toArray(),
            'por_estado' => static::where('created_at', '>=', $fecha_desde)
                ->selectRaw('estado, COUNT(*) as cantidad')
                ->groupBy('estado')
                ->pluck('cantidad', 'estado')
                ->toArray(),
            'duracion_promedio' => static::where('created_at', '>=', $fecha_desde)
                ->selectRaw('AVG(DATEDIFF(fecha_fin_descanso, fecha_inicio_descanso) + 1) as promedio')
                ->value('promedio') ?: 0,
            'activos_ahora' => static::activos()->count(),
            'proximos_a_iniciar' => static::proximosAIniciar()->count(),
            'proximos_a_terminar' => static::proximosATerminar()->count()
        ];
    }

    public static function verificarDescansosVencidos()
    {
        // Completar descansos que deberían haber terminado
        $descansosVencidos = static::where('estado', 'ACTIVO')
            ->where('fecha_fin_descanso', '<', now()->toDateString())
            ->get();

        foreach ($descansosVencidos as $descanso) {
            $descanso->completar('Completado automáticamente por vencimiento');
        }

        // Iniciar descansos planificados que deberían comenzar
        $descansosAIniciar = static::where('estado', 'PLANIFICADO')
            ->where('fecha_inicio_descanso', '<=', now()->toDateString())
            ->get();

        foreach ($descansosAIniciar as $descanso) {
            $descanso->iniciar();
        }

        return [
            'completados_automaticamente' => $descansosVencidos->count(),
            'iniciados_automaticamente' => $descansosAIniciar->count()
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($descanso) {
            // Validar que las fechas sean coherentes
            if ($descanso->fecha_fin_descanso < $descanso->fecha_inicio_descanso) {
                throw new \Exception('La fecha de fin no puede ser anterior a la fecha de inicio');
            }

            // Validar que no existan solapamientos para el mismo conductor
            $solapamientos = static::where('conductor_id', $descanso->conductor_id)
                ->whereIn('estado', ['PLANIFICADO', 'ACTIVO'])
                ->where(function ($query) use ($descanso) {
                    $query->whereBetween('fecha_inicio_descanso', [$descanso->fecha_inicio_descanso, $descanso->fecha_fin_descanso])
                          ->orWhereBetween('fecha_fin_descanso', [$descanso->fecha_inicio_descanso, $descanso->fecha_fin_descanso])
                          ->orWhere(function ($q) use ($descanso) {
                              $q->where('fecha_inicio_descanso', '<=', $descanso->fecha_inicio_descanso)
                                ->where('fecha_fin_descanso', '>=', $descanso->fecha_fin_descanso);
                          });
                })
                ->exists();

            if ($solapamientos) {
                throw new \Exception('El conductor ya tiene un descanso planificado en esas fechas');
            }
        });
    }
}
