<?php

namespace App\Observers;

use App\Models\Validacion;
use App\Models\Conductor;
use App\Services\AuditoriaService;
use App\Services\CacheMetricasService;
use App\Services\NotificacionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

class ValidacionObserver
{
    /**
     * Servicios inyectados
     */
    protected AuditoriaService $auditoriaService;
    protected CacheMetricasService $cacheService;
    protected NotificacionService $notificacionService;

    /**
     * Tipos de validación y sus configuraciones
     */
    private const CONFIGURACIONES_VALIDACION = [
        'DESCANSO_001' => [
            'nombre' => 'Días máximos sin descanso',
            'auto_resolver' => false,
            'requiere_aprobacion' => true,
            'tiempo_limite_horas' => 24,
            'prioridad' => 'ALTA'
        ],
        'EFICIENCIA_002' => [
            'nombre' => 'Eficiencia por debajo del mínimo',
            'auto_resolver' => false,
            'requiere_aprobacion' => false,
            'tiempo_limite_horas' => 72,
            'prioridad' => 'MEDIA'
        ],
        'PUNTUALIDAD_003' => [
            'nombre' => 'Puntualidad por debajo del mínimo',
            'auto_resolver' => false,
            'requiere_aprobacion' => false,
            'tiempo_limite_horas' => 72,
            'prioridad' => 'MEDIA'
        ],
        'LICENCIA_001' => [
            'nombre' => 'Documentación de licencia faltante',
            'auto_resolver' => false,
            'requiere_aprobacion' => true,
            'tiempo_limite_horas' => 48,
            'prioridad' => 'ALTA'
        ],
        'DATOS_001' => [
            'nombre' => 'Datos básicos incompletos',
            'auto_resolver' => false,
            'requiere_aprobacion' => false,
            'tiempo_limite_horas' => 168,
            'prioridad' => 'BAJA'
        ],
        'CAPACITACION_001' => [
            'nombre' => 'Capacitación requerida',
            'auto_resolver' => false,
            'requiere_aprobacion' => false,
            'tiempo_limite_horas' => 336,
            'prioridad' => 'MEDIA'
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
     * Manejar el evento "creating" del modelo Validacion
     */
    public function creating(Validacion $validacion): void
    {
        try {
            // Establecer valores por defecto
            if (is_null($validacion->estado)) {
                $validacion->estado = 'PENDIENTE';
            }

            if (is_null($validacion->severidad)) {
                $validacion->severidad = $this->determinarSeveridad($validacion->tipo);
            }

            // Generar código único si no existe
            if (empty($validacion->codigo)) {
                $validacion->codigo = $this->generarCodigoValidacion($validacion->tipo);
            }

            // Establecer fecha límite basada en el tipo
            if (is_null($validacion->fecha_limite)) {
                $validacion->fecha_limite = $this->calcularFechaLimite($validacion->tipo);
            }

            // Establecer prioridad automática
            $validacion->prioridad = $this->calcularPrioridad($validacion);

            // Verificar si es duplicada
            $this->verificarValidacionDuplicada($validacion);

            // Enriquecer datos adicionales
            $this->enriquecerDatosAdicionales($validacion);

        } catch (Exception $e) {
            Log::error("Error en ValidacionObserver::creating: " . $e->getMessage());
        }
    }

    /**
     * Manejar el evento "created" del modelo Validacion
     */
    public function created(Validacion $validacion): void
    {
        try {
            // Registrar auditoría
            $this->auditoriaService->registrarEvento(
                'validacion_creada',
                [
                    'validacion_id' => $validacion->id,
                    'codigo' => $validacion->codigo,
                    'tipo' => $validacion->tipo,
                    'conductor_id' => $validacion->conductor_id,
                    'severidad' => $validacion->severidad,
                    'descripcion' => $validacion->descripcion
                ],
                null,
                $validacion->severidad === 'CRITICA' ? 'ALTA' : 'MEDIA'
            );

            // Invalidar cache de métricas
            $this->cacheService->invalidarMetricasConductor($validacion->conductor_id);

            // Procesar validación crítica
            if ($validacion->severidad === 'CRITICA') {
                $this->procesarValidacionCritica($validacion);
            }

            // Verificar si requiere notificación inmediata
            if ($this->requiereNotificacionInmediata($validacion)) {
                $this->enviarNotificacionInmediata($validacion);
            }

            // Verificar auto-resolución
            if ($this->puedeAutoResolver($validacion)) {
                $this->intentarAutoResolucion($validacion);
            }

            // Verificar escalamiento automático
            $this->verificarEscalamientoAutomatico($validacion);

            // Actualizar estadísticas del conductor
            $this->actualizarEstadisticasConductor($validacion);

            Log::info("Validación creada exitosamente", [
                'validacion_id' => $validacion->id,
                'codigo' => $validacion->codigo,
                'tipo' => $validacion->tipo
            ]);

        } catch (Exception $e) {
            Log::error("Error en ValidacionObserver::created: " . $e->getMessage(), [
                'validacion_id' => $validacion->id
            ]);
        }
    }

    /**
     * Manejar el evento "updating" del modelo Validacion
     */
    public function updating(Validacion $validacion): void
    {
        try {
            // Detectar cambios importantes
            $cambiosImportantes = $this->detectarCambiosImportantes($validacion);

            if (!empty($cambiosImportantes)) {
                $validacion->cambios_detectados = $cambiosImportantes;
            }

            // Validar cambio de estado
            if ($validacion->isDirty('estado')) {
                $this->validarCambioEstado($validacion);
            }

            // Establecer usuario resolutor si se está resolviendo
            if ($validacion->isDirty('estado') && $validacion->estado === 'RESUELTO') {
                if (is_null($validacion->resuelto_por)) {
                    $validacion->resuelto_por = Auth::id();
                }
                if (is_null($validacion->fecha_resolucion)) {
                    $validacion->fecha_resolucion = now();
                }
            }

            // Calcular tiempo de resolución
            if ($validacion->isDirty('estado') && $validacion->estado === 'RESUELTO') {
                $validacion->tiempo_resolucion_horas = $validacion->created_at->diffInHours(now());
            }

            // Actualizar prioridad si ha cambiado severidad
            if ($validacion->isDirty('severidad')) {
                $validacion->prioridad = $this->calcularPrioridad($validacion);
            }

        } catch (Exception $e) {
            Log::error("Error en ValidacionObserver::updating: " . $e->getMessage(), [
                'validacion_id' => $validacion->id
            ]);
        }
    }

    /**
     * Manejar el evento "updated" del modelo Validacion
     */
    public function updated(Validacion $validacion): void
    {
        try {
            $cambios = $validacion->getChanges();
            $valoresOriginales = $validacion->getOriginal();

            // Registrar auditoría
            $this->auditoriaService->registrarEvento(
                'validacion_actualizada',
                [
                    'validacion_id' => $validacion->id,
                    'codigo' => $validacion->codigo,
                    'cambios' => $cambios,
                    'valores_anteriores' => array_intersect_key($valoresOriginales, $cambios)
                ],
                null,
                'MEDIA'
            );

            // Procesar cambio de estado específico
            if (isset($cambios['estado'])) {
                $this->procesarCambioEstado($validacion, $valoresOriginales['estado'], $cambios['estado']);
            }

            // Procesar cambio de severidad
            if (isset($cambios['severidad'])) {
                $this->procesarCambioSeveridad($validacion, $valoresOriginales['severidad'], $cambios['severidad']);
            }

            // Invalidar cache
            $this->cacheService->invalidarMetricasConductor($validacion->conductor_id);

            // Verificar si requiere re-escalamiento
            if ($this->requiereReEscalamiento($validacion, $cambios)) {
                $this->procesarReEscalamiento($validacion);
            }

        } catch (Exception $e) {
            Log::error("Error en ValidacionObserver::updated: " . $e->getMessage(), [
                'validacion_id' => $validacion->id
            ]);
        }
    }

    /**
     * Manejar el evento "deleted" del modelo Validacion
     */
    public function deleted(Validacion $validacion): void
    {
        try {
            // Registrar auditoría de eliminación
            $this->auditoriaService->registrarEvento(
                'validacion_eliminada',
                [
                    'validacion_id' => $validacion->id,
                    'codigo' => $validacion->codigo,
                    'tipo' => $validacion->tipo,
                    'estado_final' => $validacion->estado,
                    'datos_completos' => $validacion->toArray()
                ],
                null,
                'ALTA'
            );

            // Invalidar cache
            $this->cacheService->invalidarMetricasConductor($validacion->conductor_id);

            Log::warning("Validación eliminada", [
                'validacion_id' => $validacion->id,
                'codigo' => $validacion->codigo
            ]);

        } catch (Exception $e) {
            Log::error("Error en ValidacionObserver::deleted: " . $e->getMessage(), [
                'validacion_id' => $validacion->id
            ]);
        }
    }

    /**
     * Determinar severidad basada en el tipo
     */
    private function determinarSeveridad(string $tipo): string
    {
        $severidadesPorTipo = [
            'DESCANSO_001' => 'CRITICA',
            'LICENCIA_001' => 'CRITICA',
            'EFICIENCIA_002' => 'ADVERTENCIA',
            'PUNTUALIDAD_003' => 'ADVERTENCIA',
            'CAPACITACION_001' => 'ADVERTENCIA',
            'DATOS_001' => 'INFO',
            'DATOS_002' => 'INFO'
        ];

        return $severidadesPorTipo[$tipo] ?? 'ADVERTENCIA';
    }

    /**
     * Generar código único para validación
     */
    private function generarCodigoValidacion(string $tipo): string
    {
        $prefijo = 'VAL';
        $tipoCorto = substr($tipo, 0, 3);
        $numero = Validacion::where('tipo', $tipo)->count() + 1;

        return $prefijo . '-' . $tipoCorto . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular fecha límite para resolución
     */
    private function calcularFechaLimite(string $tipo): Carbon
    {
        $configuracion = self::CONFIGURACIONES_VALIDACION[$tipo] ?? null;
        $horas = $configuracion['tiempo_limite_horas'] ?? 72;

        return now()->addHours($horas);
    }

    /**
     * Calcular prioridad numérica de la validación
     */
    private function calcularPrioridad(Validacion $validacion): int
    {
        $prioridad = 0;

        // Factor severidad (40%)
        $prioridad += match($validacion->severidad) {
            'CRITICA' => 40,
            'ADVERTENCIA' => 25,
            'INFO' => 10,
            default => 15
        };

        // Factor antigüedad (30%)
        if ($validacion->created_at) {
            $horasAntiguedad = $validacion->created_at->diffInHours(now());
            $prioridad += min(30, $horasAntiguedad * 0.5);
        }

        // Factor tipo específico (30%)
        $prioridad += match($validacion->tipo) {
            'DESCANSO_001' => 30,
            'LICENCIA_001' => 25,
            'EFICIENCIA_002' => 20,
            'PUNTUALIDAD_003' => 15,
            'CAPACITACION_001' => 10,
            default => 5
        };

        return min(100, round($prioridad));
    }

    /**
     * Verificar si es una validación duplicada
     */
    private function verificarValidacionDuplicada(Validacion $validacion): void
    {
        $validacionExistente = Validacion::where('conductor_id', $validacion->conductor_id)
            ->where('tipo', $validacion->tipo)
            ->where('estado', 'PENDIENTE')
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($validacionExistente) {
            Log::warning("Validación duplicada detectada", [
                'nueva_validacion' => $validacion->toArray(),
                'validacion_existente_id' => $validacionExistente->id
            ]);

            // Marcar como duplicada en datos adicionales
            $datos = json_decode($validacion->datos_adicionales, true) ?? [];
            $datos['duplicada'] = true;
            $datos['validacion_original_id'] = $validacionExistente->id;
            $validacion->datos_adicionales = json_encode($datos);
        }
    }

    /**
     * Enriquecer datos adicionales de la validación
     */
    private function enriquecerDatosAdicionales(Validacion $validacion): void
    {
        $datos = json_decode($validacion->datos_adicionales, true) ?? [];

        // Agregar información del conductor
        if ($validacion->conductor_id) {
            $conductor = Conductor::find($validacion->conductor_id);
            if ($conductor) {
                $datos['conductor_info'] = [
                    'codigo' => $conductor->codigo,
                    'estado' => $conductor->estado,
                    'eficiencia' => $conductor->eficiencia,
                    'puntualidad' => $conductor->puntualidad,
                    'dias_acumulados' => $conductor->dias_acumulados
                ];
            }
        }

        // Agregar configuración del tipo de validación
        if (isset(self::CONFIGURACIONES_VALIDACION[$validacion->tipo])) {
            $datos['configuracion_tipo'] = self::CONFIGURACIONES_VALIDACION[$validacion->tipo];
        }

        // Agregar contexto temporal
        $datos['contexto_creacion'] = [
            'hora_creacion' => now()->format('H:i:s'),
            'dia_semana' => now()->dayName,
            'es_fin_semana' => now()->isWeekend(),
            'es_horario_laboral' => $this->esHorarioLaboral()
        ];

        $validacion->datos_adicionales = json_encode($datos);
    }

    /**
     * Verificar si es horario laboral
     */
    private function esHorarioLaboral(): bool
    {
        $hora = now()->hour;
        return $hora >= 7 && $hora <= 19 && !now()->isWeekend();
    }

    /**
     * Procesar validación crítica
     */
    private function procesarValidacionCritica(Validacion $validacion): void
    {
        // Disparar evento de validación crítica
        Event::dispatch('validacion.critica.creada', $validacion);

        // Registrar en auditoría con alta prioridad
        $this->auditoriaService->registrarEvento(
            'validacion_critica_creada',
            [
                'validacion_id' => $validacion->id,
                'conductor_id' => $validacion->conductor_id,
                'tipo' => $validacion->tipo,
                'descripcion' => $validacion->descripcion
            ],
            null,
            'CRITICA'
        );

        // Verificar si requiere acción inmediata
        if ($validacion->tipo === 'DESCANSO_001') {
            $this->procesarValidacionDescansoUrgente($validacion);
        }
    }

    /**
     * Procesar validación de descanso urgente
     */
    private function procesarValidacionDescansoUrgente(Validacion $validacion): void
    {
        $conductor = $validacion->conductor;

        if ($conductor && $conductor->estado === 'DISPONIBLE') {
            // Verificar turnos futuros
            $turnosFuturos = $conductor->turnos()
                ->where('estado', 'PENDIENTE')
                ->where('fecha', '>', today())
                ->count();

            if ($turnosFuturos > 0) {
                // Agregar información crítica a la validación
                $datos = json_decode($validacion->datos_adicionales, true) ?? [];
                $datos['accion_requerida'] = 'INMEDIATA';
                $datos['turnos_futuros_afectados'] = $turnosFuturos;
                $datos['recomendacion'] = 'Poner conductor en descanso inmediatamente y reasignar turnos';

                $validacion->update([
                    'datos_adicionales' => json_encode($datos),
                    'solucion_recomendada' => 'Cambiar estado del conductor a DESCANSO FISICO y reasignar ' . $turnosFuturos . ' turno(s) pendiente(s)'
                ]);
            }
        }
    }

    /**
     * Verificar si requiere notificación inmediata
     */
    private function requiereNotificacionInmediata(Validacion $validacion): bool
    {
        // Validaciones críticas siempre requieren notificación inmediata
        if ($validacion->severidad === 'CRITICA') {
            return true;
        }

        // Validaciones fuera de horario laboral
        if (!$this->esHorarioLaboral() && in_array($validacion->tipo, ['DESCANSO_001', 'LICENCIA_001'])) {
            return true;
        }

        // Validaciones de conductores con turnos inmediatos
        return $this->conductorTieneTurnoInmediato($validacion->conductor_id);
    }

    /**
     * Verificar si el conductor tiene turno inmediato
     */
    private function conductorTieneTurnoInmediato(?int $conductorId): bool
    {
        if (!$conductorId) return false;

        return Conductor::find($conductorId)?->turnos()
            ->where('estado', 'PENDIENTE')
            ->where('fecha', today())
            ->where('hora_salida', '>=', now()->format('H:i:s'))
            ->where('hora_salida', '<=', now()->addHours(2)->format('H:i:s'))
            ->exists() ?? false;
    }

    /**
     * Enviar notificación inmediata
     */
    private function enviarNotificacionInmediata(Validacion $validacion): void
    {
        $this->notificacionService->enviarNotificacionCritica($validacion);
    }

    /**
     * Verificar si puede auto-resolver
     */
    private function puedeAutoResolver(Validacion $validacion): bool
    {
        $configuracion = self::CONFIGURACIONES_VALIDACION[$validacion->tipo] ?? null;
        return $configuracion['auto_resolver'] ?? false;
    }

    /**
     * Intentar auto-resolución
     */
    private function intentarAutoResolucion(Validacion $validacion): void
    {
        // Por ahora, solo logging. La auto-resolución se implementaría según reglas específicas
        Log::info("Intentando auto-resolución de validación", [
            'validacion_id' => $validacion->id,
            'tipo' => $validacion->tipo
        ]);
    }

    /**
     * Verificar escalamiento automático
     */
    private function verificarEscalamientoAutomatico(Validacion $validacion): void
    {
        // Escalar validaciones críticas que no se resuelven en tiempo
        if ($validacion->severidad === 'CRITICA') {
            $configuracion = self::CONFIGURACIONES_VALIDACION[$validacion->tipo] ?? null;
            $tiempoLimite = $configuracion['tiempo_limite_horas'] ?? 24;

            // Programar verificación de escalamiento
            $this->programarVerificacionEscalamiento($validacion, $tiempoLimite);
        }
    }

    /**
     * Programar verificación de escalamiento
     */
    private function programarVerificacionEscalamiento(Validacion $validacion, int $horas): void
    {
        // Aquí se programaría un job para verificar después del tiempo límite
        Log::info("Programando verificación de escalamiento", [
            'validacion_id' => $validacion->id,
            'horas_limite' => $horas
        ]);
    }

    /**
     * Actualizar estadísticas del conductor
     */
    private function actualizarEstadisticasConductor(Validacion $validacion): void
    {
        if (!$validacion->conductor_id) return;

        try {
            $conductor = Conductor::find($validacion->conductor_id);
            if (!$conductor) return;

            // Contar validaciones por tipo
            $datos = json_decode($conductor->estadisticas_validaciones, true) ?? [];
            $datos['total'] = ($datos['total'] ?? 0) + 1;
            $datos['por_tipo'][$validacion->tipo] = ($datos['por_tipo'][$validacion->tipo] ?? 0) + 1;
            $datos['por_severidad'][$validacion->severidad] = ($datos['por_severidad'][$validacion->severidad] ?? 0) + 1;
            $datos['ultima_validacion'] = now()->toISOString();

            $conductor->update(['estadisticas_validaciones' => json_encode($datos)]);

        } catch (Exception $e) {
            Log::error("Error actualizando estadísticas del conductor: " . $e->getMessage());
        }
    }

    /**
     * Detectar cambios importantes
     */
    private function detectarCambiosImportantes(Validacion $validacion): array
    {
        $cambiosImportantes = [];

        // Cambio de estado
        if ($validacion->isDirty('estado')) {
            $estadoAnterior = $validacion->getOriginal('estado');
            $estadoNuevo = $validacion->estado;

            $cambiosImportantes['cambio_estado'] = [
                'anterior' => $estadoAnterior,
                'nuevo' => $estadoNuevo,
                'tiempo_transcurrido' => $validacion->created_at->diffForHumans(),
                'resuelto_por' => Auth::user()->name ?? 'Sistema'
            ];
        }

        // Cambio de severidad
        if ($validacion->isDirty('severidad')) {
            $severidadAnterior = $validacion->getOriginal('severidad');
            $severidadNueva = $validacion->severidad;

            $cambiosImportantes['cambio_severidad'] = [
                'anterior' => $severidadAnterior,
                'nueva' => $severidadNueva,
                'escalado' => $this->esSeveridadMayor($severidadNueva, $severidadAnterior)
            ];
        }

        return $cambiosImportantes;
    }

    /**
     * Verificar si la nueva severidad es mayor
     */
    private function esSeveridadMayor(string $nueva, string $anterior): bool
    {
        $niveles = ['INFO' => 1, 'ADVERTENCIA' => 2, 'CRITICA' => 3];
        return ($niveles[$nueva] ?? 0) > ($niveles[$anterior] ?? 0);
    }

    /**
     * Validar cambio de estado
     */
    private function validarCambioEstado(Validacion $validacion): void
    {
        $estadoAnterior = $validacion->getOriginal('estado');
        $estadoNuevo = $validacion->estado;

        // Validar transiciones permitidas
        $transicionesPermitidas = [
            'PENDIENTE' => ['EN_REVISION', 'RESUELTO', 'RECHAZADO'],
            'EN_REVISION' => ['RESUELTO', 'RECHAZADO', 'PENDIENTE'],
            'RESUELTO' => [], // No se puede cambiar desde resuelto
            'RECHAZADO' => ['PENDIENTE'] // Solo se puede reabrir
        ];

        if (isset($transicionesPermitidas[$estadoAnterior]) &&
            !in_array($estadoNuevo, $transicionesPermitidas[$estadoAnterior])) {

            throw new Exception("Transición de estado no permitida: {$estadoAnterior} -> {$estadoNuevo}");
        }

        // Validaciones específicas por estado
        if ($estadoNuevo === 'RESUELTO') {
            $this->validarResolucion($validacion);
        }
    }

    /**
     * Validar resolución de validación
     */
    private function validarResolucion(Validacion $validacion): void
    {
        // Verificar que tenga solución documentada
        if (empty($validacion->solucion_aplicada)) {
            throw new Exception("No se puede resolver la validación sin documentar la solución aplicada");
        }

        // Verificar autorización para validaciones que requieren aprobación
        $configuracion = self::CONFIGURACIONES_VALIDACION[$validacion->tipo] ?? null;
        if ($configuracion['requiere_aprobacion'] ?? false) {
            if (!Auth::user()->can('resolver_validaciones_criticas')) {
                throw new Exception("No tiene permisos para resolver este tipo de validación");
            }
        }
    }

    /**
     * Procesar cambio de estado
     */
    private function procesarCambioEstado(Validacion $validacion, string $estadoAnterior, string $estadoNuevo): void
    {
        switch ($estadoNuevo) {
            case 'RESUELTO':
                $this->procesarResolucion($validacion);
                break;

            case 'EN_REVISION':
                $this->procesarEnRevision($validacion);
                break;

            case 'RECHAZADO':
                $this->procesarRechazo($validacion);
                break;
        }
    }

    /**
     * Procesar resolución de validación
     */
    private function procesarResolucion(Validacion $validacion): void
    {
        // Registrar evento de resolución
        $this->auditoriaService->registrarEvento(
            'validacion_resuelta',
            [
                'validacion_id' => $validacion->id,
                'tipo' => $validacion->tipo,
                'tiempo_resolucion_horas' => $validacion->tiempo_resolucion_horas,
                'solucion_aplicada' => $validacion->solucion_aplicada,
                'resuelto_por' => $validacion->resuelto_por
            ],
            null,
            'MEDIA'
        );

        // Actualizar métricas del resolutor
        if ($validacion->resuelto_por) {
            $this->actualizarMetricasResolutor($validacion);
        }

        // Verificar si resuelve problema del conductor
        $this->verificarImpactoEnConductor($validacion);
    }

    /**
     * Procesar validación en revisión
     */
    private function procesarEnRevision(Validacion $validacion): void
    {
        // Notificar a supervisores si es crítica
        if ($validacion->severidad === 'CRITICA') {
            $this->notificacionService->enviarNotificacion(
                'VALIDACION_CRITICA',
                'Validación Crítica en Revisión',
                "La validación {$validacion->codigo} está siendo revisada y requiere atención de supervisores.",
                ['validacion_id' => $validacion->id],
                null,
                'ADVERTENCIA'
            );
        }
    }

    /**
     * Procesar rechazo de validación
     */
    private function procesarRechazo(Validacion $validacion): void
    {
        // Registrar motivo del rechazo
        $this->auditoriaService->registrarEvento(
            'validacion_rechazada',
            [
                'validacion_id' => $validacion->id,
                'tipo' => $validacion->tipo,
                'motivo_rechazo' => $validacion->observaciones,
                'rechazado_por' => Auth::id()
            ],
            null,
            'MEDIA'
        );
    }

    /**
     * Procesar cambio de severidad
     */
    private function procesarCambioSeveridad(Validacion $validacion, string $severidadAnterior, string $severidadNueva): void
    {
        if ($this->esSeveridadMayor($severidadNueva, $severidadAnterior)) {
            // Escalamiento de severidad
            $this->notificacionService->enviarNotificacion(
                'SISTEMA_ALERTA',
                'Escalamiento de Severidad',
                "La validación {$validacion->codigo} ha sido escalada de {$severidadAnterior} a {$severidadNueva}.",
                [
                    'validacion_id' => $validacion->id,
                    'severidad_anterior' => $severidadAnterior,
                    'severidad_nueva' => $severidadNueva
                ],
                null,
                'ADVERTENCIA'
            );
        }
    }

    /**
     * Verificar si requiere re-escalamiento
     */
    private function requiereReEscalamiento(Validacion $validacion, array $cambios): bool
    {
        // Re-escalar si cambió a crítica o lleva mucho tiempo sin resolver
        return (isset($cambios['severidad']) && $cambios['severidad'] === 'CRITICA') ||
               ($validacion->estado === 'PENDIENTE' &&
                $validacion->created_at->diffInHours(now()) > 48);
    }

    /**
     * Procesar re-escalamiento
     */
    private function procesarReEscalamiento(Validacion $validacion): void
    {
        Log::info("Re-escalando validación por tiempo límite excedido", [
            'validacion_id' => $validacion->id,
            'horas_pendiente' => $validacion->created_at->diffInHours(now())
        ]);

        // Enviar notificación de escalamiento
        $this->notificacionService->enviarNotificacion(
            'SISTEMA_ALERTA',
            'Validación Requiere Escalamiento',
            "La validación {$validacion->codigo} lleva {$validacion->created_at->diffInHours(now())} horas sin resolver.",
            ['validacion_id' => $validacion->id],
            null,
            'ALTA'
        );
    }

    /**
     * Actualizar métricas del resolutor
     */
    private function actualizarMetricasResolutor(Validacion $validacion): void
    {
        // Por ahora solo logging, se puede implementar sistema de métricas de usuarios
        Log::info("Validación resuelta por usuario", [
            'validacion_id' => $validacion->id,
            'resuelto_por' => $validacion->resuelto_por,
            'tiempo_resolucion' => $validacion->tiempo_resolucion_horas
        ]);
    }

    /**
     * Verificar impacto en conductor
     */
    private function verificarImpactoEnConductor(Validacion $validacion): void
    {
        if (!$validacion->conductor_id) return;

        $conductor = Conductor::find($validacion->conductor_id);
        if (!$conductor) return;

        // Verificar si el conductor puede volver a estar disponible
        if ($validacion->tipo === 'DESCANSO_001' && $validacion->estado === 'RESUELTO') {
            if ($conductor->estado !== 'DISPONIBLE') {
                Log::info("Conductor puede volver a estar disponible tras resolver validación de descanso", [
                    'conductor_id' => $conductor->id,
                    'validacion_id' => $validacion->id
                ]);
            }
        }
    }
}
