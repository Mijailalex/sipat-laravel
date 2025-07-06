<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Planificacion extends Model
{
    use SoftDeletes;

    protected $table = 'planificaciones';

    protected $fillable = [
        'fecha_salida',
        'numero_salida',
        'hora_salida',
        'hora_llegada',
        'codigo_bus',
        'codigo_conductor',
        'nombre_conductor',
        'tipo_servicio',
        'origen_destino',
        'origen_conductor',
        'tramo',
        'regimen_conductor',
        'estado_planificacion',
        'observaciones',
        'usuario_creacion',
        'usuario_modificacion',
        'es_replanificacion',
        'planificacion_original_id',
        'motivo_cambio'
    ];

    protected $casts = [
        'fecha_salida' => 'datetime',
        'hora_salida' => 'datetime',
        'hora_llegada' => 'datetime',
        'es_replanificacion' => 'boolean'
    ];

    // ESTADOS DE PLANIFICACIÓN
    const ESTADO_BORRADOR = 'BORRADOR';
    const ESTADO_PROGRAMADO = 'PROGRAMADO';
    const ESTADO_EN_CURSO = 'EN_CURSO';
    const ESTADO_COMPLETADO = 'COMPLETADO';
    const ESTADO_CANCELADO = 'CANCELADO';
    const ESTADO_REPROGRAMADO = 'REPROGRAMADO';

    // TIPOS DE SERVICIO
    const SERVICIO_ESTANDAR = 'ESTANDAR';
    const SERVICIO_NAZCA = 'NAZCA';
    const SERVICIO_VIP = 'VIP';
    const SERVICIO_EXPRESS = 'EXPRESS';
    const SERVICIO_ESCALA = 'ESCALA';

    // TIPOS DE TRAMO
    const TRAMO_LARGA = 'LARGA';
    const TRAMO_CORTA = 'CORTA';

    // REGÍMENES
    const REGIMEN_26_4 = '26_DIAS_4_DESCANSO'; // 26 días laborados por 4 días descanso
    const REGIMEN_6_1 = '6_DIAS_1_DESCANSO';   // 6 días laborados por 1 día descanso

    /**
     * RELACIONES
     */
    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'codigo_conductor', 'codigo');
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class, 'codigo_bus', 'codigo');
    }

    public function planificacionOriginal()
    {
        return $this->belongsTo(Planificacion::class, 'planificacion_original_id');
    }

    public function replanificaciones()
    {
        return $this->hasMany(Planificacion::class, 'planificacion_original_id');
    }

    public function historialCambios()
    {
        return $this->hasMany(HistorialPlanificacion::class);
    }

    /**
     * SCOPES PARA CONSULTAS OPTIMIZADAS
     */
    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha_salida', $fecha);
    }

    public function scopePorTramo($query, $tramo)
    {
        return $query->where('tramo', $tramo);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado_planificacion', $estado);
    }

    public function scopePorConductor($query, $codigoConductor)
    {
        return $query->where('codigo_conductor', $codigoConductor);
    }

    public function scopeVigentes($query)
    {
        return $query->whereIn('estado_planificacion', [
            self::ESTADO_PROGRAMADO,
            self::ESTADO_EN_CURSO
        ]);
    }

    /**
     * ALGORITMO PRINCIPAL DE ASIGNACIÓN DE CONDUCTORES
     */
    public static function asignarMejorConductor($parametrosPlanificacion)
    {
        $conductoresDisponibles = Conductor::disponiblesParaTurno(
            $parametrosPlanificacion['fecha_salida'],
            $parametrosPlanificacion['hora_salida'],
            $parametrosPlanificacion['tramo']
        )->get();

        if ($conductoresDisponibles->isEmpty()) {
            return null;
        }

        $mejorConductor = null;
        $mejorScore = 0;

        foreach ($conductoresDisponibles as $conductor) {
            $score = self::calcularScoreCompatibilidad($conductor, $parametrosPlanificacion);

            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejorConductor = $conductor;
            }
        }

        return [
            'conductor' => $mejorConductor,
            'score' => $mejorScore,
            'compatibilidad' => self::obtenerNivelCompatibilidad($mejorScore)
        ];
    }

    /**
     * ALGORITMO DE COMPATIBILIDAD PARA RUTAS
     */
    private static function calcularScoreCompatibilidad($conductor, $parametros)
    {
        $score = 0;

        // Factor proximidad de origen (30%)
        if ($conductor->origen_disponibilidad === $parametros['origen_conductor']) {
            $score += 30;
        } elseif ($conductor->origen === $parametros['origen_conductor']) {
            $score += 15; // Origen base pero no está ahí actualmente
        }

        // Factor puntualidad (25%)
        $score += ($conductor->puntualidad / 100) * 25;

        // Factor eficiencia (25%)
        $score += ($conductor->eficiencia / 100) * 25;

        // Factor disponibilidad inmediata (20%)
        if ($conductor->estaDisponiblePara($parametros['hora_salida'])) {
            $score += 20;
        } elseif ($conductor->puedeHacerMediaVuelta($parametros['hora_salida'])) {
            $score += 10; // Media vuelta disponible
        }

        // Bonificación por experiencia en tipo de servicio
        if ($conductor->tieneExperienciaEn($parametros['tipo_servicio'])) {
            $score += 5;
        }

        // Penalización por días acumulados sin descanso
        if ($conductor->dias_acumulados >= 5) {
            $score -= 10;
        } elseif ($conductor->dias_acumulados >= 6) {
            $score -= 20; // Más penalización si está cerca del límite
        }

        // Bonificación por régimen compatible
        if ($conductor->regimen === $parametros['regimen_requerido']) {
            $score += 5;
        }

        return round($score, 2);
    }

    /**
     * LÓGICA DE VALIDACIÓN ANTES DE CREAR PLANIFICACIÓN
     */
    public static function validarPlanificacion($datos)
    {
        $errores = [];

        // Validar conductor disponible
        $conductor = Conductor::where('codigo', $datos['codigo_conductor'])->first();
        if (!$conductor) {
            $errores[] = "Conductor {$datos['codigo_conductor']} no encontrado";
        } elseif (!$conductor->estaDisponible()) {
            $errores[] = "Conductor {$conductor->nombre} no está disponible ({$conductor->estado})";
        }

        // Validar bus disponible
        $bus = Bus::where('codigo', $datos['codigo_bus'])->first();
        if (!$bus) {
            $errores[] = "Bus {$datos['codigo_bus']} no encontrado";
        } elseif (!$bus->estaDisponible($datos['fecha_salida'], $datos['hora_salida'])) {
            $errores[] = "Bus {$datos['codigo_bus']} no está disponible en el horario solicitado";
        }

        // Validar horarios
        if (Carbon::parse($datos['hora_salida'])->gte(Carbon::parse($datos['hora_llegada']))) {
            $errores[] = "La hora de llegada debe ser posterior a la hora de salida";
        }

        // Validar solapamiento de turnos del conductor
        $turnosExistentes = self::where('codigo_conductor', $datos['codigo_conductor'])
            ->where('fecha_salida', $datos['fecha_salida'])
            ->whereIn('estado_planificacion', [self::ESTADO_PROGRAMADO, self::ESTADO_EN_CURSO])
            ->get();

        foreach ($turnosExistentes as $turno) {
            if (self::hayConflictoHorario($datos, $turno)) {
                $errores[] = "Conflicto de horario con turno existente #{$turno->numero_salida}";
            }
        }

        // Validar descansos obligatorios
        if ($conductor && $conductor->requiereDescansoObligatorio()) {
            $errores[] = "Conductor {$conductor->nombre} requiere descanso obligatorio ({$conductor->dias_acumulados} días acumulados)";
        }

        return $errores;
    }

    /**
     * CREAR PLANIFICACIÓN CON VALIDACIONES Y AUDITORÍA
     */
    public static function crear($datos, $usuarioId)
    {
        // Validar antes de crear
        $errores = self::validarPlanificacion($datos);
        if (!empty($errores)) {
            throw new \Exception('Errores de validación: ' . implode(', ', $errores));
        }

        DB::beginTransaction();
        try {
            // Crear planificación
            $planificacion = self::create(array_merge($datos, [
                'estado_planificacion' => self::ESTADO_PROGRAMADO,
                'usuario_creacion' => $usuarioId,
                'es_replanificacion' => false
            ]));

            // Actualizar estado del conductor
            $conductor = Conductor::where('codigo', $datos['codigo_conductor'])->first();
            $conductor->asignarATurno($planificacion);

            // Registrar en historial
            HistorialPlanificacion::registrar($planificacion, 'CREACION', $usuarioId);

            // Ejecutar validaciones automáticas
            ValidacionService::ejecutarValidacionesPlanificacion($planificacion);

            DB::commit();
            return $planificacion;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * CREAR REPLANIFICACIÓN
     */
    public function replanificar($nuevosDatos, $motivoCambio, $usuarioId)
    {
        DB::beginTransaction();
        try {
            // Marcar planificación original como reprogramada
            $this->update([
                'estado_planificacion' => self::ESTADO_REPROGRAMADO,
                'usuario_modificacion' => $usuarioId
            ]);

            // Crear nueva planificación
            $nuevaPlanificacion = self::create(array_merge($nuevosDatos, [
                'estado_planificacion' => self::ESTADO_PROGRAMADO,
                'usuario_creacion' => $usuarioId,
                'es_replanificacion' => true,
                'planificacion_original_id' => $this->id,
                'motivo_cambio' => $motivoCambio
            ]));

            // Registrar cambios en historial
            HistorialPlanificacion::registrar($this, 'REPROGRAMACION', $usuarioId, [
                'motivo' => $motivoCambio,
                'nueva_planificacion_id' => $nuevaPlanificacion->id
            ]);

            HistorialPlanificacion::registrar($nuevaPlanificacion, 'CREACION_REPLANIFICACION', $usuarioId);

            // Liberar conductor anterior y asignar nuevo
            if ($this->codigo_conductor !== $nuevaPlanificacion->codigo_conductor) {
                $conductorAnterior = Conductor::where('codigo', $this->codigo_conductor)->first();
                $conductorAnterior->liberarDeTurno();

                $nuevoConductor = Conductor::where('codigo', $nuevaPlanificacion->codigo_conductor)->first();
                $nuevoConductor->asignarATurno($nuevaPlanificacion);
            }

            DB::commit();
            return $nuevaPlanificacion;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * MÉTODOS DE CONSULTA Y REPORTES
     */
    public static function obtenerPlanificacionesPorFecha($fecha)
    {
        return self::with(['conductor', 'bus'])
            ->porFecha($fecha)
            ->orderBy('hora_salida')
            ->get()
            ->groupBy('tramo');
    }

    public static function obtenerMetricasPlanificacion($fechaInicio, $fechaFin)
    {
        return [
            'total_planificaciones' => self::whereBetween('fecha_salida', [$fechaInicio, $fechaFin])->count(),
            'por_tramo' => self::whereBetween('fecha_salida', [$fechaInicio, $fechaFin])
                ->selectRaw('tramo, COUNT(*) as total')
                ->groupBy('tramo')
                ->pluck('total', 'tramo'),
            'por_estado' => self::whereBetween('fecha_salida', [$fechaInicio, $fechaFin])
                ->selectRaw('estado_planificacion, COUNT(*) as total')
                ->groupBy('estado_planificacion')
                ->pluck('total', 'estado_planificacion'),
            'replanificaciones' => self::whereBetween('fecha_salida', [$fechaInicio, $fechaFin])
                ->where('es_replanificacion', true)
                ->count(),
            'eficiencia_cumplimiento' => self::calcularEficienciaCumplimiento($fechaInicio, $fechaFin)
        ];
    }

    /**
     * MÉTODOS AUXILIARES
     */
    private static function hayConflictoHorario($datos, $turnoExistente)
    {
        $nuevaInicio = Carbon::parse($datos['hora_salida']);
        $nuevaFin = Carbon::parse($datos['hora_llegada']);
        $existenteInicio = $turnoExistente->hora_salida;
        $existenteFin = $turnoExistente->hora_llegada;

        return ($nuevaInicio->lt($existenteFin) && $nuevaFin->gt($existenteInicio));
    }

    private static function obtenerNivelCompatibilidad($score)
    {
        if ($score >= 80) return 'EXCELENTE';
        if ($score >= 65) return 'BUENA';
        if ($score >= 50) return 'REGULAR';
        return 'BAJA';
    }

    private static function calcularEficienciaCumplimiento($fechaInicio, $fechaFin)
    {
        $planificadas = self::whereBetween('fecha_salida', [$fechaInicio, $fechaFin])->count();
        $completadas = self::whereBetween('fecha_salida', [$fechaInicio, $fechaFin])
            ->where('estado_planificacion', self::ESTADO_COMPLETADO)
            ->count();

        return $planificadas > 0 ? round(($completadas / $planificadas) * 100, 2) : 0;
    }

    /**
     * EVENTOS DEL MODELO
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($planificacion) {
            if (empty($planificacion->numero_salida)) {
                $planificacion->numero_salida = self::generarNumeroSalida($planificacion->fecha_salida);
            }
        });

        static::created(function ($planificacion) {
            // Cache::forget("planificaciones_{$planificacion->fecha_salida->format('Y-m-d')}");
        });

        static::updated(function ($planificacion) {
            // Cache::forget("planificaciones_{$planificacion->fecha_salida->format('Y-m-d')}");
        });
    }

    private static function generarNumeroSalida($fecha)
    {
        $ultimoNumero = self::whereDate('fecha_salida', $fecha)
            ->max('numero_salida') ?? 0;

        return $ultimoNumero + 1;
    }
}
