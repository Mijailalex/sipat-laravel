<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Conductor extends Model
{
    use HasFactory;

    protected $table = 'conductores';

    protected $fillable = [
        'codigo_conductor',
        'nombre',
        'apellido',
        'dni',
        'licencia',
        'categoria_licencia',
        'fecha_vencimiento_licencia',
        'telefono',
        'email',
        'direccion',
        'fecha_nacimiento',
        'genero',
        'contacto_emergencia',
        'telefono_emergencia',
        'fecha_ingreso',
        'años_experiencia',
        'salario_base',
        'certificaciones',
        'turno_preferido',
        'estado',
        'dias_acumulados',
        'eficiencia',
        'puntualidad',
        'score_general',
        'horas_hombre',
        'ultimo_servicio',
        'ultima_ruta_corta',
        'fecha_ultimo_descanso',
        'total_rutas_completadas',
        'total_ingresos_generados',
        'origen_conductor',
        'subempresa',
        'observaciones'
    ];

    protected $casts = [
        'fecha_vencimiento_licencia' => 'date',
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'ultimo_servicio' => 'datetime',
        'ultima_ruta_corta' => 'datetime',
        'fecha_ultimo_descanso' => 'datetime',
        'certificaciones' => 'array',
        'eficiencia' => 'decimal:2',
        'puntualidad' => 'decimal:2',
        'score_general' => 'decimal:2',
        'horas_hombre' => 'decimal:2',
        'salario_base' => 'decimal:2',
        'total_ingresos_generados' => 'decimal:2',
        'años_experiencia' => 'integer',
        'dias_acumulados' => 'integer',
        'total_rutas_completadas' => 'integer'
    ];

    // Relaciones
    public function validaciones()
    {
        return $this->hasMany(Validacion::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function rutasCortas()
    {
        return $this->hasMany(RutaCorta::class);
    }

    public function planificacionDescansos()
    {
        return $this->hasMany(PlanificacionDescanso::class);
    }

    public function asignacionesSubempresa()
    {
        return $this->hasMany(SubempresaAsignacion::class);
    }

    public function backups()
    {
        return $this->hasMany(ConductorBackup::class);
    }

    // Scopes
    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'DISPONIBLE');
    }

    public function scopeEnDescanso($query)
    {
        return $query->whereIn('estado', ['DESCANSO_FISICO', 'DESCANSO_SEMANAL']);
    }

    public function scopeCriticos($query)
    {
        return $query->where('dias_acumulados', '>=', 6)
                    ->orWhere('eficiencia', '<', 80)
                    ->orWhere('puntualidad', '<', 85);
    }

    public function scopeSubempresa($query, $subempresa)
    {
        return $query->where('subempresa', $subempresa);
    }

    // Métodos de negocio
    public function getNombreCompletoAttribute()
    {
        return $this->nombre . ' ' . $this->apellido;
    }

    public function getEdadAttribute()
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    public function necesitaDescanso()
    {
        $maxDias = Parametro::obtenerValor('dias_maximos_sin_descanso', 6);
        return $this->dias_acumulados >= $maxDias;
    }

    public function estaDisponiblePara($horaInicio)
    {
        if ($this->estado !== 'DISPONIBLE') {
            return false;
        }

        // Verificar descanso de 12 horas
        if ($this->ultimo_servicio) {
            $horasDescanso = Carbon::parse($this->ultimo_servicio)
                ->diffInHours(Carbon::parse($horaInicio));
            return $horasDescanso >= 12;
        }

        return true;
    }

    public function calcularCompatibilidad($turno)
    {
        $score = 0;

        // Factor proximidad (30%)
        if ($this->origen_conductor === $turno->origen_conductor) {
            $score += 30;
        }

        // Factor puntualidad (25%)
        $score += ($this->puntualidad / 100) * 25;

        // Factor eficiencia (25%)
        $score += ($this->eficiencia / 100) * 25;

        // Factor disponibilidad (20%)
        if ($this->estaDisponiblePara($turno->hora_salida)) {
            $score += 20;
        }

        return round($score, 2);
    }

    public function actualizarMetricas()
    {
        // Calcular eficiencia basada en turnos completados
        $turnosCompletados = $this->turnos()
            ->where('estado', 'COMPLETADO')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $turnosTotales = $this->turnos()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($turnosTotales > 0) {
            $this->eficiencia = ($turnosCompletados / $turnosTotales) * 100;
        }

        // Calcular puntualidad
        $turnosPuntuales = $this->turnos()
            ->where('estado', 'COMPLETADO')
            ->whereNotNull('eficiencia_turno')
            ->where('eficiencia_turno', '>=', 85)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($turnosCompletados > 0) {
            $this->puntualidad = ($turnosPuntuales / $turnosCompletados) * 100;
        }

        // Score general
        $this->score_general = ($this->eficiencia + $this->puntualidad) / 2;

        $this->save();
    }

    public function enviarADescanso($tipo = 'FISICO', $motivo = null)
    {
        $this->estado = $tipo === 'SEMANAL' ? 'DESCANSO_SEMANAL' : 'DESCANSO_FISICO';
        $this->fecha_ultimo_descanso = now();
        $this->dias_acumulados = 0;
        $this->save();

        // Crear registro de planificación
        PlanificacionDescanso::create([
            'conductor_id' => $this->id,
            'fecha_inicio_descanso' => now()->toDateString(),
            'fecha_fin_descanso' => now()->addDays($tipo === 'SEMANAL' ? 2 : 1)->toDateString(),
            'tipo_descanso' => $tipo,
            'estado' => 'ACTIVO',
            'motivo' => $motivo ?? 'Descanso automático por días acumulados',
            'es_automatico' => true,
            'creado_por' => 1 // Usuario del sistema
        ]);
    }

    public function validarDatos()
    {
        $validaciones = [];

        // Validar días acumulados
        if ($this->necesitaDescanso()) {
            $validaciones[] = [
                'tipo' => 'DESCANSO_001',
                'severidad' => 'CRITICA',
                'titulo' => 'Conductor requiere descanso obligatorio',
                'descripcion' => "El conductor {$this->nombre_completo} ha acumulado {$this->dias_acumulados} días sin descanso."
            ];
        }

        // Validar eficiencia
        if ($this->eficiencia < 80) {
            $validaciones[] = [
                'tipo' => 'EFICIENCIA_002',
                'severidad' => 'ADVERTENCIA',
                'titulo' => 'Eficiencia por debajo del mínimo',
                'descripcion' => "El conductor {$this->nombre_completo} tiene una eficiencia de {$this->eficiencia}%."
            ];
        }

        // Validar puntualidad
        if ($this->puntualidad < 85) {
            $validaciones[] = [
                'tipo' => 'PUNTUALIDAD_003',
                'severidad' => 'ADVERTENCIA',
                'titulo' => 'Puntualidad por debajo del mínimo',
                'descripcion' => "El conductor {$this->nombre_completo} tiene una puntualidad de {$this->puntualidad}%."
            ];
        }

        // Crear validaciones
        foreach ($validaciones as $validacion) {
            Validacion::firstOrCreate(
                [
                    'conductor_id' => $this->id,
                    'tipo' => $validacion['tipo'],
                    'estado' => 'PENDIENTE'
                ],
                array_merge($validacion, [
                    'prioridad_calculada' => $this->calcularPrioridadValidacion($validacion)
                ])
            );
        }

        return count($validaciones);
    }

    private function calcularPrioridadValidacion($validacion)
    {
        $prioridad = 0;

        // Severidad (40%)
        $prioridad += match($validacion['severidad']) {
            'CRITICA' => 40,
            'ADVERTENCIA' => 25,
            'INFO' => 10
        };

        // Tipo de validación (30%)
        $prioridad += match($validacion['tipo']) {
            'DESCANSO_001' => 30,
            'EFICIENCIA_002' => 20,
            'PUNTUALIDAD_003' => 15,
            default => 10
        };

        // Días acumulados como factor adicional (30%)
        $prioridad += min(30, $this->dias_acumulados * 5);

        return min(100, $prioridad);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($conductor) {
            // Generar código si no existe
            if (!$conductor->codigo_conductor) {
                $conductor->codigo_conductor = 'COND' . str_pad(
                    static::max('id') + 1, 4, '0', STR_PAD_LEFT
                );
            }
        });

        static::updated(function ($conductor) {
            // Crear backup de cambios
            ConductorBackup::create([
                'conductor_id' => $conductor->id,
                'accion' => 'ACTUALIZADO',
                'datos_anteriores' => $conductor->getOriginal(),
                'datos_nuevos' => $conductor->getAttributes(),
                'campos_modificados' => array_keys($conductor->getDirty()),
                'usuario_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
        });
    }
}
