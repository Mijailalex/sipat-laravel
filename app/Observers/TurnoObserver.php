<?php

namespace App\Observers;

use App\Models\Turno;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Services\AuditoriaService;
use App\Services\CacheMetricasService;
use App\Services\NotificacionService;
use App\Services\ServicioPlanificacionAutomatizada;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class TurnoObserver
{
    /**
     * Servicios inyectados
     */
    protected AuditoriaService $auditoriaService;
    protected CacheMetricasService $cacheService;
    protected NotificacionService $notificacionService;

    /**
     * Estados válidos para turnos
     */
    private const ESTADOS_VALIDOS = [
        'PENDIENTE',
        'ASIGNADO',
        'EN_CURSO',
        'COMPLETADO',
        'CANCELADO',
        'RETRASADO',
        'SUSPENDIDO'
    ];

    /**
     * Tipos de turno y sus configuraciones
     */
    private const CONFIGURACIONES_TURNO = [
        'RUTA_CORTA' => [
            'duracion_minima_horas' => 1,
            'duracion_maxima_horas' => 6,
            'requiere_validacion_previa' => false,
            'genera_validacion_eficiencia' => true,
            'impacto_dias_acumulados' => 0.5
        ],
        'RUTA_LARGA' => [
            'duracion_minima_horas' => 6,
            'duracion_maxima_horas' => 12,
            'requiere_validacion_previa' => true,
            'genera_validacion_eficiencia' => true,
            'impacto_dias_acumulados' => 1
        ],
        'EXTRAORDINARIO' => [
            'duracion_minima_horas' => 2,
            'duracion_maxima_horas' => 8,
            'requiere_validacion_previa' => true,
            'genera_validacion_eficiencia' => false,
            'impacto_dias_acumulados' => 0.5
        ]
    ];

    public function __construct(
        AuditoriaService $auditoriaService,
        CacheMetricasService $cacheService,
        NotificacionService $notificacionService
    ) {
        $this->auditoriaService = $auditoriaService;
        $this->cacheService = $cacheService;
        $this->notificacionService = $notificacionService;
    }

    /**
     * Manejar el evento "creating" del modelo Turno
     */
    public function creating(Turno $turno): void
    {
        try {
            // Establecer valores por defecto
            if (is_null($turno->estado)) {
                $turno->estado = 'PENDIENTE';
            }

            if (is_null($turno->tipo)) {
                $turno->tipo = 'RUTA_CORTA';
            }

            // Generar código único si no existe
            if (empty($turno->codigo)) {
                $turno->codigo = $this->generarCodigoTurno($turno);
            }

            // Calcular duración estimada si no está definida
            if (is_null($turno->horas_estimadas) && $turno->hora_salida && $turno->hora_llegada) {
                $turno->horas_estimadas = $this->calcularDuracionHoras($turno->hora_salida, $turno->hora_llegada);
            }

            // Establecer fecha de creación del turno
            if (is_null($turno->fecha_creacion)) {
                $turno->fecha_creacion = now();
            }

            // Validar datos del turno
            $this->validarDatosTurno($turno);

            // Establecer prioridad inicial
            $turno->prioridad = $this->calcularPrioridadTurno($turno);

        } catch (Exception $e) {
            Log::error("Error en TurnoObserver::creating: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Manejar el evento "created" del modelo Turno
     */
    public function created(Turno $turno): void
    {
        try {
            // Registrar auditoría
            $this->auditoriaService->registrarEvento(
                'turno_creado',
                [
                    'turno_id' => $turno->id,
                    'codigo' => $turno->codigo,
                    'tipo' => $turno->tipo,
                    'fecha' => $turno->fecha,
                    'origen' => $turno->origen_conductor,
                    'destino' => $turno->destino,
                    'conductor_id' => $turno->conductor_id,
                    'horas_estimadas' => $turno->horas_estimadas
                ],
                null,
                'MEDIA'
            );

            // Invalidar cache de métricas
            $this->cacheService->invalidarMetricasConductor($turno->conductor_id);

            // Verificar disponibilidad del conductor si está asignado
            if ($turno->conductor_id) {
                $this->verificarDisponibilidadConductor($turno);
            }

            // Verificar solapamientos de horarios
            $this->verificarSolapamientos($turno);

            // Procesar asignación automática si no tiene conductor
            if (!$turno->conductor_id && $turno->asignacion_automatica) {
                $this->procesarAsignacionAutomatica($turno);
            }

            // Generar validaciones automáticas
            $this->generarValidacionesAutomaticas($turno);

            Log::info("Turno creado exitosamente", [
                'turno_id' => $turno->id,
                'codigo' => $turno->codigo,
                'tipo' => $turno->tipo
            ]);

        } catch (Exception $e) {
            Log::error("Error en TurnoObserver::created: " . $e->getMessage(), [
                'turno_id' => $turno->id
            ]);
        }
    }

    /**
     * Manejar el evento "updating" del modelo Turno
     */
    public function updating(Turno $turno): void
    {
        try {
            // Detectar cambios importantes
            $cambiosImportantes = $this->detectarCambiosImportantes($turno);

            if (!empty($cambiosImportantes)) {
                $turno->cambios_detectados = $cambiosImportantes;
            }

            // Validar cambios de estado
            if ($turno->isDirty('estado')) {
                $this->validarCambioEstado($turno);
            }

            // Validar cambios de conductor
            if ($turno->isDirty('conductor_id')) {
                $this->validarCambioConductor($turno);
            }

            // Recalcular duración si cambian las horas
            if ($turno->isDirty(['hora_salida', 'hora_llegada'])) {
                $turno->horas_estimadas = $this->calcularDuracionHoras($turno->hora_salida, $turno->hora_llegada);
            }

            // Actualizar métricas de eficiencia si se completa
            if ($turno->isDirty('estado') && $turno->estado === 'COMPLETADO') {
                $this->calcularMetricasCompletado($turno);
            }

            // Actualizar timestamp de modificación
            $turno->fecha_ultima_modificacion = now();

        } catch (Exception $e) {
            Log::error("Error en TurnoObserver::updating: " . $e->getMessage(), [
                'turno_id' => $turno->id
            ]);
            throw $e;
        }
    }

    /**
     * Manejar el evento "updated" del modelo Turno
     */
    public function updated(Turno $turno): void
    {
        try {
            $cambios = $turno->getChanges();
            $valoresOriginales = $turno->getOriginal();

            // Registrar auditoría
            $this->auditoriaService->registrarEvento(
                'turno_actualizado',
                [
                    'turno_id' => $turno->id,
                    'codigo' => $turno->codigo,
                    'cambios' => $cambios,
                    'valores_anteriores' => array_intersect_key($valoresOriginales, $cambios)
                ],
                null,
                'MEDIA'
            );

            // Procesar cambios específicos
            $this->procesarCambiosEspecificos($turno, $cambios, $valoresOriginales);

            // Invalidar cache
            if (isset($cambios['conductor_id'])) {
                // Invalidar cache del conductor anterior y nuevo
                if ($valoresOriginales['conductor_id']) {
                    $this->cacheService->invalidarMetricasConductor($valoresOriginales['conductor_id']);
                }
                if ($turno->conductor_id) {
                    $this->cacheService->invalidarMetricasConductor($turno->conductor_id);
                }
            }

            // Verificar si requiere notificaciones
            if ($this->requiereNotificacion($cambios)) {
                $this->enviarNotificacionesCambio($turno, $cambios);
            }

        } catch (Exception $e) {
            Log::error("Error en TurnoObserver::updated: " . $e->getMessage(), [
                'turno_id' => $turno->id
            ]);
        }
    }

    /**
     * Manejar el evento "deleted" del modelo Turno
     */
    public function deleted(Turno $turno): void
    {
        try {
            // Registrar auditoría de eliminación
            $this->auditoriaService->registrarEvento(
                'turno_eliminado',
                [
                    'turno_id' => $turno->id,
                    'codigo' => $turno->codigo,
                    'tipo' => $turno->tipo,
                    'estado_final' => $turno->estado,
                    'conductor_id' => $turno->conductor_id,
                    'datos_completos' => $turno->toArray()
                ],
                null,
                'ALTA'
            );

            // Liberar conductor si estaba asignado
            if ($turno->conductor_id) {
                $this->liberarConductor($turno);
            }

            // Invalidar cache
            $this->cacheService->invalidarMetricasConductor($turno->conductor_id);

            Log::warning("Turno eliminado", [
                'turno_id' => $turno->id,
                'codigo' => $turno->codigo
            ]);

        } catch (Exception $e) {
            Log::error("Error en TurnoObserver::deleted: " . $e->getMessage(), [
                'turno_id' => $turno->id
            ]);
        }
    }

    /**
     * Generar código único para turno
     */
    private function generarCodigoTurno(Turno $turno): string
    {
        $prefijo = match($turno->tipo) {
            'RUTA_CORTA' => 'RC',
            'RUTA_LARGA' => 'RL',
            'EXTRAORDINARIO' => 'EX',
            default => 'TU'
        };

        $fecha = Carbon::parse($turno->fecha)->format('ymd');
        $numero = Turno::whereDate('fecha', $turno->fecha)->count() + 1;

        return $prefijo . '-' . $fecha . '-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular duración en horas entre dos horarios
     */
    private function calcularDuracionHoras(string $horaSalida, string $horaLlegada): float
    {
        try {
            $salida = Carbon::createFromFormat('H:i:s', $horaSalida);
            $llegada = Carbon::createFromFormat('H:i:s', $horaLlegada);

            // Si la llegada es menor que la salida, asumimos que es al día siguiente
            if ($llegada->lt($salida)) {
                $llegada->addDay();
            }

            return $salida->diffInMinutes($llegada) / 60;
        } catch (Exception $e) {
            Log::error("Error calculando duración de turno: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Validar datos del turno
     */
    private function validarDatosTurno(Turno $turno): void
    {
        // Validar estado
        if (!in_array($turno->estado, self::ESTADOS_VALIDOS)) {
            throw new Exception("Estado de turno no válido: {$turno->estado}");
        }

        // Validar duración según el tipo
        if ($turno->horas_estimadas) {
            $configuracion = self::CONFIGURACIONES_TURNO[$turno->tipo] ?? null;
            if ($configuracion) {
                $minimo = $configuracion['duracion_minima_horas'];
                $maximo = $configuracion['duracion_maxima_horas'];

                if ($turno->horas_estimadas < $minimo || $turno->horas_estimadas > $maximo) {
                    Log::warning("Duración de turno fuera de rango", [
                        'turno_codigo' => $turno->codigo,
                        'tipo' => $turno->tipo,
                        'horas_estimadas' => $turno->horas_estimadas,
                        'minimo' => $minimo,
                        'maximo' => $maximo
                    ]);
                }
            }
        }

        // Validar fecha del turno
        if ($turno->fecha && Carbon::parse($turno->fecha)->isPast() && $turno->estado === 'PENDIENTE') {
            Log::warning("Turno creado con fecha pasada", [
                'turno_codigo' => $turno->codigo,
                'fecha' => $turno->fecha
            ]);
        }
    }

    /**
     * Calcular prioridad del turno
     */
    private function calcularPrioridadTurno(Turno $turno): int
    {
        $prioridad = 0;

        // Factor urgencia por fecha (40%)
        $diasHastaFecha = Carbon::parse($turno->fecha)->diffInDays(now(), false);
        if ($diasHastaFecha <= 0) {
            $prioridad += 40; // Hoy o pasado
        } elseif ($diasHastaFecha <= 1) {
            $prioridad += 30; // Mañana
        } elseif ($diasHastaFecha <= 7) {
            $prioridad += 20; // Esta semana
        } else {
            $prioridad += 10; // Futuro
        }

        // Factor tipo de turno (30%)
        $prioridad += match($turno->tipo) {
            'EXTRAORDINARIO' => 30,
            'RUTA_LARGA' => 20,
            'RUTA_CORTA' => 15,
            default => 10
        };

        // Factor duración (20%)
        if ($turno->horas_estimadas) {
            if ($turno->horas_estimadas >= 8) {
                $prioridad += 20;
            } elseif ($turno->horas_estimadas >= 6) {
                $prioridad += 15;
            } else {
                $prioridad += 10;
            }
        }

        // Factor conductor asignado (10%)
        if ($turno->conductor_id) {
            $prioridad += 10;
        }

        return min(100, $prioridad);
    }

    /**
     * Verificar disponibilidad del conductor
     */
    private function verificarDisponibilidadConductor(Turno $turno): void
    {
        if (!$turno->conductor_id) return;

        $conductor = Conductor::find($turno->conductor_id);
        if (!$conductor) {
            Log::error("Conductor no encontrado para turno", [
                'turno_id' => $turno->id,
                'conductor_id' => $turno->conductor_id
            ]);
            return;
        }

        // Verificar estado del conductor
        if ($conductor->estado !== 'DISPONIBLE') {
            Validacion::create([
                'conductor_id' => $conductor->id,
                'tipo' => 'ASIGNACION_001',
                'descripcion' => "Turno {$turno->codigo} asignado a conductor {$conductor->codigo} que no está disponible (estado: {$conductor->estado})",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE',
                'datos_adicionales' => json_encode([
                    'turno_id' => $turno->id,
                    'estado_conductor' => $conductor->estado
                ])
            ]);
        }

        // Verificar días acumulados
        $limiteDias = sipat_config('dias_maximos_sin_descanso', 6);
        if ($conductor->dias_acumulados >= $limiteDias) {
            Validacion::create([
                'conductor_id' => $conductor->id,
                'tipo' => 'DESCANSO_001',
                'descripcion' => "Turno {$turno->codigo} asignado a conductor {$conductor->codigo} que necesita descanso ({$conductor->dias_acumulados} días acumulados)",
                'severidad' => 'CRITICA',
                'estado' => 'PENDIENTE',
                'datos_adicionales' => json_encode([
                    'turno_id' => $turno->id,
                    'dias_acumulados' => $conductor->dias_acumulados,
                    'limite_maximo' => $limiteDias
                ])
            ]);
        }

        // Verificar horas de descanso entre turnos
        $this->verificarHorasDescanso($turno, $conductor);
    }

    /**
     * Verificar horas de descanso entre turnos
     */
    private function verificarHorasDescanso(Turno $turno, Conductor $conductor): void
    {
        $ultimoTurno = $conductor->turnos()
            ->where('id', '!=', $turno->id)
            ->where('estado', 'COMPLETADO')
            ->whereDate('fecha', '>=', now()->subDays(2))
            ->orderByDesc('fecha')
            ->orderByDesc('hora_llegada')
            ->first();

        if (!$ultimoTurno) return;

        $finUltimoTurno = Carbon::parse($ultimoTurno->fecha . ' ' . $ultimoTurno->hora_llegada);
        $inicioNuevoTurno = Carbon::parse($turno->fecha . ' ' . $turno->hora_salida);

        $horasDescanso = $finUltimoTurno->diffInHours($inicioNuevoTurno);
        $minimasRequeridas = sipat_config('horas_minimas_descanso', 12);

        if ($horasDescanso < $minimasRequeridas) {
            Validacion::create([
                'conductor_id' => $conductor->id,
                'tipo' => 'DESCANSO_002',
                'descripcion' => "Turno {$turno->codigo} asignado con solo {$horasDescanso} horas de descanso (mínimo: {$minimasRequeridas}h)",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE',
                'datos_adicionales' => json_encode([
                    'turno_id' => $turno->id,
                    'ultimo_turno_id' => $ultimoTurno->id,
                    'horas_descanso_real' => $horasDescanso,
                    'horas_minimas' => $minimasRequeridas
                ])
            ]);
        }
    }

    /**
     * Verificar solapamientos de horarios
     */
    private function verificarSolapamientos(Turno $turno): void
    {
        if (!$turno->conductor_id) return;

        $solapamientos = Turno::where('conductor_id', $turno->conductor_id)
            ->where('id', '!=', $turno->id)
            ->where('fecha', $turno->fecha)
            ->where('estado', '!=', 'CANCELADO')
            ->where(function ($query) use ($turno) {
                $query->whereBetween('hora_salida', [$turno->hora_salida, $turno->hora_llegada])
                      ->orWhereBetween('hora_llegada', [$turno->hora_salida, $turno->hora_llegada])
                      ->orWhere(function ($q) use ($turno) {
                          $q->where('hora_salida', '<=', $turno->hora_salida)
                            ->where('hora_llegada', '>=', $turno->hora_llegada);
                      });
            })
            ->get();

        if ($solapamientos->count() > 0) {
            Validacion::create([
                'conductor_id' => $turno->conductor_id,
                'tipo' => 'SOLAPAMIENTO_001',
                'descripcion' => "Turno {$turno->codigo} tiene solapamiento de horarios con otros turnos del mismo conductor",
                'severidad' => 'CRITICA',
                'estado' => 'PENDIENTE',
                'datos_adicionales' => json_encode([
                    'turno_id' => $turno->id,
                    'turnos_solapados' => $solapamientos->pluck('id')->toArray()
                ])
            ]);
        }
    }

    /**
     * Procesar asignación automática
     */
    private function procesarAsignacionAutomatica(Turno $turno): void
    {
        try {
            $conductorAsignado = app(ServicioPlanificacionAutomatizada::class)
                ->asignarConductorOptimo($turno);

            if ($conductorAsignado) {
                $turno->update([
                    'conductor_id' => $conductorAsignado->id,
                    'estado' => 'ASIGNADO',
                    'fecha_asignacion' => now(),
                    'metodo_asignacion' => 'AUTOMATICO'
                ]);

                Event::dispatch('turno.asignado.automaticamente', [$turno, $conductorAsignado]);
            } else {
                Log::warning("No se pudo asignar conductor automáticamente", [
                    'turno_id' => $turno->id,
                    'codigo' => $turno->codigo
                ]);
            }
        } catch (Exception $e) {
            Log::error("Error en asignación automática: " . $e->getMessage(), [
                'turno_id' => $turno->id
            ]);
        }
    }

    /**
     * Generar validaciones automáticas
     */
    private function generarValidacionesAutomaticas(Turno $turno): void
    {
        $configuracion = self::CONFIGURACIONES_TURNO[$turno->tipo] ?? null;
        if (!$configuracion) return;

        // Validación previa requerida
        if ($configuracion['requiere_validacion_previa'] && !$turno->validacion_previa_completada) {
            Validacion::create([
                'conductor_id' => $turno->conductor_id,
                'tipo' => 'VALIDACION_PREVIA_001',
                'descripcion' => "Turno {$turno->codigo} de tipo {$turno->tipo} requiere validación previa",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE',
                'datos_adicionales' => json_encode([
                    'turno_id' => $turno->id,
                    'tipo_turno' => $turno->tipo
                ])
            ]);
        }
    }

    /**
     * Detectar cambios importantes
     */
    private function detectarCambiosImportantes(Turno $turno): array
    {
        $cambiosImportantes = [];

        // Cambio de estado
        if ($turno->isDirty('estado')) {
            $estadoAnterior = $turno->getOriginal('estado');
            $estadoNuevo = $turno->estado;

            $cambiosImportantes['cambio_estado'] = [
                'anterior' => $estadoAnterior,
                'nuevo' => $estadoNuevo,
                'critico' => $this->esCambioEstadoCritico($estadoAnterior, $estadoNuevo)
            ];
        }

        // Cambio de conductor
        if ($turno->isDirty('conductor_id')) {
            $conductorAnterior = $turno->getOriginal('conductor_id');
            $conductorNuevo = $turno->conductor_id;

            $cambiosImportantes['cambio_conductor'] = [
                'anterior_id' => $conductorAnterior,
                'nuevo_id' => $conductorNuevo,
                'tipo' => $conductorAnterior ? ($conductorNuevo ? 'reasignacion' : 'liberacion') : 'asignacion'
            ];
        }

        // Cambios de horario
        if ($turno->isDirty(['fecha', 'hora_salida', 'hora_llegada'])) {
            $cambiosImportantes['cambio_horario'] = [
                'fecha_anterior' => $turno->getOriginal('fecha'),
                'fecha_nueva' => $turno->fecha,
                'hora_salida_anterior' => $turno->getOriginal('hora_salida'),
                'hora_salida_nueva' => $turno->hora_salida,
                'hora_llegada_anterior' => $turno->getOriginal('hora_llegada'),
                'hora_llegada_nueva' => $turno->hora_llegada
            ];
        }

        return $cambiosImportantes;
    }

    /**
     * Verificar si el cambio de estado es crítico
     */
    private function esCambioEstadoCritico(string $estadoAnterior, string $estadoNuevo): bool
    {
        $cambiosCriticos = [
            'ASIGNADO' => ['CANCELADO', 'SUSPENDIDO'],
            'EN_CURSO' => ['CANCELADO', 'SUSPENDIDO'],
            'PENDIENTE' => ['CANCELADO']
        ];

        return isset($cambiosCriticos[$estadoAnterior]) &&
               in_array($estadoNuevo, $cambiosCriticos[$estadoAnterior]);
    }

    /**
     * Validar cambio de estado
     */
    private function validarCambioEstado(Turno $turno): void
    {
        $estadoAnterior = $turno->getOriginal('estado');
        $estadoNuevo = $turno->estado;

        // Transiciones válidas
        $transicionesValidas = [
            'PENDIENTE' => ['ASIGNADO', 'CANCELADO'],
            'ASIGNADO' => ['EN_CURSO', 'CANCELADO', 'RETRASADO'],
            'EN_CURSO' => ['COMPLETADO', 'SUSPENDIDO'],
            'RETRASADO' => ['EN_CURSO', 'COMPLETADO', 'CANCELADO'],
            'SUSPENDIDO' => ['EN_CURSO', 'CANCELADO'],
            'COMPLETADO' => [], // No se puede cambiar desde completado
            'CANCELADO' => ['PENDIENTE'] // Solo reactivación
        ];

        if (isset($transicionesValidas[$estadoAnterior]) &&
            !in_array($estadoNuevo, $transicionesValidas[$estadoAnterior])) {
            throw new Exception("Transición de estado no permitida: {$estadoAnterior} -> {$estadoNuevo}");
        }

        // Validaciones específicas por estado
        $this->validarEstadoEspecifico($turno, $estadoNuevo);
    }

    /**
     * Validar estado específico
     */
    private function validarEstadoEspecifico(Turno $turno, string $estado): void
    {
        switch ($estado) {
            case 'ASIGNADO':
                if (!$turno->conductor_id) {
                    throw new Exception("No se puede marcar turno como ASIGNADO sin conductor");
                }
                break;

            case 'EN_CURSO':
                if (!$turno->conductor_id) {
                    throw new Exception("No se puede iniciar turno sin conductor asignado");
                }
                $turno->hora_inicio_real = now();
                break;

            case 'COMPLETADO':
                if (!$turno->hora_inicio_real) {
                    $turno->hora_inicio_real = now()->subHours($turno->horas_estimadas ?? 8);
                }
                $turno->hora_fin_real = now();
                break;
        }
    }

    /**
     * Validar cambio de conductor
     */
    private function validarCambioConductor(Turno $turno): void
    {
        $conductorAnterior = $turno->getOriginal('conductor_id');
        $conductorNuevo = $turno->conductor_id;

        // Si hay conductor nuevo, verificar disponibilidad
        if ($conductorNuevo) {
            $conductor = Conductor::find($conductorNuevo);
            if (!$conductor) {
                throw new Exception("Conductor no encontrado: {$conductorNuevo}");
            }

            if ($conductor->estado !== 'DISPONIBLE') {
                Log::warning("Asignando turno a conductor no disponible", [
                    'turno_id' => $turno->id,
                    'conductor_id' => $conductorNuevo,
                    'estado_conductor' => $conductor->estado
                ]);
            }
        }

        // Registrar el cambio para auditoría
        if ($conductorAnterior && $conductorNuevo && $conductorAnterior !== $conductorNuevo) {
            $turno->fecha_reasignacion = now();
            $turno->reasignado_por = Auth::id();
        }
    }

    /**
     * Calcular métricas cuando se completa el turno
     */
    private function calcularMetricasCompletado(Turno $turno): void
    {
        try {
            // Calcular eficiencia del turno
            $duracionEstimada = $turno->horas_estimadas ?? 8;
            $duracionReal = $turno->hora_inicio_real && $turno->hora_fin_real ?
                $turno->hora_inicio_real->diffInHours($turno->hora_fin_real) : $duracionEstimada;

            $eficiencia = $duracionEstimada > 0 ?
                min(100, round(($duracionEstimada / $duracionReal) * 100, 2)) : 100;

            $turno->duracion_real_horas = $duracionReal;
            $turno->eficiencia_calculada = $eficiencia;

            // Actualizar métricas del conductor
            if ($turno->conductor_id) {
                $this->actualizarMetricasConductor($turno);
            }

        } catch (Exception $e) {
            Log::error("Error calculando métricas de turno completado: " . $e->getMessage());
        }
    }

    /**
     * Actualizar métricas del conductor
     */
    private function actualizarMetricasConductor(Turno $turno): void
    {
        $conductor = Conductor::find($turno->conductor_id);
        if (!$conductor) return;

        try {
            // Incrementar días acumulados según configuración
            $configuracion = self::CONFIGURACIONES_TURNO[$turno->tipo] ?? null;
            $impacto = $configuracion['impacto_dias_acumulados'] ?? 1;

            $conductor->increment('dias_acumulados', $impacto);

            // Actualizar fecha del último servicio
            $conductor->update(['ultimo_servicio' => now()]);

            // Actualizar eficiencia promedio (promedio móvil de últimos 10 turnos)
            $ultimosTurnos = $conductor->turnos()
                ->where('estado', 'COMPLETADO')
                ->whereNotNull('eficiencia_calculada')
                ->orderByDesc('fecha')
                ->limit(10)
                ->get();

            if ($ultimosTurnos->count() > 0) {
                $eficienciaPromedio = $ultimosTurnos->avg('eficiencia_calculada');
                $conductor->update(['eficiencia' => round($eficienciaPromedio, 2)]);
            }

            // Disparar evento de turno completado
            Event::dispatch('turno.completado', $turno);

        } catch (Exception $e) {
            Log::error("Error actualizando métricas del conductor: " . $e->getMessage());
        }
    }

    /**
     * Procesar cambios específicos
     */
    private function procesarCambiosEspecificos(Turno $turno, array $cambios, array $valoresOriginales): void
    {
        // Cambio de estado
        if (isset($cambios['estado'])) {
            $this->procesarCambioEstado($turno, $valoresOriginales['estado'], $cambios['estado']);
        }

        // Cambio de conductor
        if (isset($cambios['conductor_id'])) {
            $this->procesarCambioConductor($turno, $valoresOriginales['conductor_id'], $cambios['conductor_id']);
        }

        // Cambio de horario
        if (array_intersect(array_keys($cambios), ['fecha', 'hora_salida', 'hora_llegada'])) {
            $this->procesarCambioHorario($turno, $cambios);
        }
    }

    /**
     * Procesar cambio de estado específico
     */
    private function procesarCambioEstado(Turno $turno, string $estadoAnterior, string $estadoNuevo): void
    {
        switch ($estadoNuevo) {
            case 'COMPLETADO':
                $this->auditoriaService->registrarEvento(
                    'turno_completado',
                    [
                        'turno_id' => $turno->id,
                        'conductor_id' => $turno->conductor_id,
                        'duracion_real' => $turno->duracion_real_horas,
                        'eficiencia' => $turno->eficiencia_calculada
                    ],
                    null,
                    'MEDIA'
                );
                break;

            case 'CANCELADO':
                $this->auditoriaService->registrarEvento(
                    'turno_cancelado',
                    [
                        'turno_id' => $turno->id,
                        'estado_anterior' => $estadoAnterior,
                        'conductor_id' => $turno->conductor_id,
                        'motivo' => $turno->observaciones
                    ],
                    null,
                    'MEDIA'
                );
                break;
        }
    }

    /**
     * Procesar cambio de conductor
     */
    private function procesarCambioConductor(Turno $turno, ?int $conductorAnterior, ?int $conductorNuevo): void
    {
        if ($conductorAnterior && $conductorNuevo) {
            // Reasignación
            $this->auditoriaService->registrarEvento(
                'conductor_asignado_turno',
                [
                    'turno_id' => $turno->id,
                    'conductor_anterior' => $conductorAnterior,
                    'conductor_nuevo' => $conductorNuevo,
                    'tipo_cambio' => 'reasignacion'
                ],
                null,
                'MEDIA'
            );
        } elseif (!$conductorAnterior && $conductorNuevo) {
            // Asignación
            $turno->update([
                'estado' => 'ASIGNADO',
                'fecha_asignacion' => now()
            ]);
        } elseif ($conductorAnterior && !$conductorNuevo) {
            // Liberación
            $turno->update([
                'estado' => 'PENDIENTE',
                'fecha_asignacion' => null
            ]);
        }
    }

    /**
     * Procesar cambio de horario
     */
    private function procesarCambioHorario(Turno $turno, array $cambios): void
    {
        // Verificar nuevos solapamientos
        $this->verificarSolapamientos($turno);

        // Notificar si es un cambio significativo y está cerca la fecha
        if (Carbon::parse($turno->fecha)->diffInDays(now()) <= 1) {
            $this->notificacionService->enviarNotificacion(
                'TURNO_ASIGNADO',
                'Cambio de Horario de Turno',
                "El turno {$turno->codigo} ha tenido cambios de horario próximo a su fecha de ejecución.",
                [
                    'turno_id' => $turno->id,
                    'cambios' => $cambios
                ],
                null,
                'ADVERTENCIA'
            );
        }
    }

    /**
     * Verificar si requiere notificación
     */
    private function requiereNotificacion(array $cambios): bool
    {
        $camposCriticos = ['estado', 'conductor_id', 'fecha', 'hora_salida'];
        return !empty(array_intersect(array_keys($cambios), $camposCriticos));
    }

    /**
     * Enviar notificaciones por cambios
     */
    private function enviarNotificacionesCambio(Turno $turno, array $cambios): void
    {
        if (isset($cambios['estado']) && $cambios['estado'] === 'CANCELADO') {
            $this->notificacionService->enviarNotificacion(
                'SISTEMA_ALERTA',
                'Turno Cancelado',
                "El turno {$turno->codigo} ha sido cancelado.",
                [
                    'turno_id' => $turno->id,
                    'conductor_id' => $turno->conductor_id
                ],
                null,
                'ADVERTENCIA'
            );
        }

        if (isset($cambios['conductor_id']) && $turno->conductor_id) {
            $this->notificacionService->enviarNotificacion(
                'TURNO_ASIGNADO',
                'Turno Asignado',
                "Se le ha asignado el turno {$turno->codigo}.",
                ['turno_id' => $turno->id],
                [['id' => $turno->conductor_id, 'email' => $turno->conductor->email ?? 'no-email@example.com']],
                'INFO'
            );
        }
    }

    /**
     * Liberar conductor al eliminar turno
     */
    private function liberarConductor(Turno $turno): void
    {
        $conductor = Conductor::find($turno->conductor_id);
        if ($conductor) {
            Log::info("Conductor liberado por eliminación de turno", [
                'conductor_id' => $conductor->id,
                'turno_eliminado' => $turno->id
            ]);
        }
    }
}
