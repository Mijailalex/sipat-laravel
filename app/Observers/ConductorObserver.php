<?php

namespace App\Observers;

use App\Models\Conductor;
use App\Services\AuditoriaService;
use App\Services\CacheMetricasService;
use App\Services\NotificacionService;
use App\Services\ServicioPlanificacionAutomatizada;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Exception;

class ConductorObserver
{
    /**
     * Servicios inyectados
     */
    protected AuditoriaService $auditoriaService;
    protected CacheMetricasService $cacheService;
    protected NotificacionService $notificacionService;

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
     * Manejar el evento "creating" del modelo Conductor
     */
    public function creating(Conductor $conductor): void
    {
        try {
            // Establecer valores por defecto si no están definidos
            if (is_null($conductor->estado)) {
                $conductor->estado = 'DISPONIBLE';
            }

            if (is_null($conductor->dias_acumulados)) {
                $conductor->dias_acumulados = 0;
            }

            if (is_null($conductor->eficiencia)) {
                $conductor->eficiencia = 85.0;
            }

            if (is_null($conductor->puntualidad)) {
                $conductor->puntualidad = 90.0;
            }

            if (is_null($conductor->score_general)) {
                $conductor->score_general = ($conductor->eficiencia + $conductor->puntualidad) / 2;
            }

            // Generar código si no existe
            if (empty($conductor->codigo)) {
                $conductor->codigo = $this->generarCodigoConductor();
            }

            // Establecer fecha de último servicio
            if (is_null($conductor->ultimo_servicio)) {
                $conductor->ultimo_servicio = now()->subDays(7); // Hace una semana por defecto
            }

        } catch (Exception $e) {
            Log::error("Error en ConductorObserver::creating: " . $e->getMessage());
        }
    }

    /**
     * Manejar el evento "created" del modelo Conductor
     */
    public function created(Conductor $conductor): void
    {
        try {
            // Registrar auditoría
            $this->auditoriaService->registrarEvento(
                'conductor_creado',
                [
                    'conductor_id' => $conductor->id,
                    'codigo' => $conductor->codigo,
                    'nombres' => $conductor->nombres,
                    'apellidos' => $conductor->apellidos,
                    'estado_inicial' => $conductor->estado,
                    'origen' => $conductor->origen
                ],
                null,
                'MEDIA'
            );

            // Invalidar cache de métricas
            $this->cacheService->invalidarMetricasConductor($conductor->id);

            // Ejecutar validaciones iniciales
            $this->ejecutarValidacionesIniciales($conductor);

            // Verificar si necesita capacitación inicial
            $this->verificarNecesidadCapacitacion($conductor);

            Log::info("Conductor creado exitosamente", [
                'conductor_id' => $conductor->id,
                'codigo' => $conductor->codigo
            ]);

        } catch (Exception $e) {
            Log::error("Error en ConductorObserver::created: " . $e->getMessage(), [
                'conductor_id' => $conductor->id
            ]);
        }
    }

    /**
     * Manejar el evento "updating" del modelo Conductor
     */
    public function updating(Conductor $conductor): void
    {
        try {
            // Detectar cambios importantes
            $cambiosImportantes = $this->detectarCambiosImportantes($conductor);

            if (!empty($cambiosImportantes)) {
                // Registrar los cambios para procesamiento posterior
                $conductor->cambios_detectados = $cambiosImportantes;
            }

            // Recalcular score general si cambian eficiencia o puntualidad
            if ($conductor->isDirty(['eficiencia', 'puntualidad'])) {
                $conductor->score_general = ($conductor->eficiencia + $conductor->puntualidad) / 2;
            }

            // Validar cambio de estado
            if ($conductor->isDirty('estado')) {
                $this->validarCambioEstado($conductor);
            }

            // Actualizar timestamp de última modificación
            $conductor->fecha_ultima_actualizacion = now();

        } catch (Exception $e) {
            Log::error("Error en ConductorObserver::updating: " . $e->getMessage(), [
                'conductor_id' => $conductor->id
            ]);
        }
    }

    /**
     * Manejar el evento "updated" del modelo Conductor
     */
    public function updated(Conductor $conductor): void
    {
        try {
            $cambios = $conductor->getChanges();
            $valoresOriginales = $conductor->getOriginal();

            // Registrar auditoría de actualización
            $this->auditoriaService->registrarEvento(
                'conductor_actualizado',
                [
                    'conductor_id' => $conductor->id,
                    'codigo' => $conductor->codigo,
                    'cambios' => $cambios,
                    'valores_anteriores' => array_intersect_key($valoresOriginales, $cambios)
                ],
                null,
                'MEDIA'
            );

            // Procesar cambios específicos
            $this->procesarCambiosEspecificos($conductor, $cambios, $valoresOriginales);

            // Invalidar cache
            $this->cacheService->invalidarMetricasConductor($conductor->id);

            // Ejecutar validaciones post-actualización
            $this->ejecutarValidacionesPostActualizacion($conductor, $cambios);

        } catch (Exception $e) {
            Log::error("Error en ConductorObserver::updated: " . $e->getMessage(), [
                'conductor_id' => $conductor->id
            ]);
        }
    }

    /**
     * Manejar el evento "deleting" del modelo Conductor
     */
    public function deleting(Conductor $conductor): void
    {
        try {
            // Verificar si puede ser eliminado
            $this->verificarEliminacionPermitida($conductor);

            // Registrar auditoría de eliminación
            $this->auditoriaService->registrarEvento(
                'conductor_eliminado',
                [
                    'conductor_id' => $conductor->id,
                    'codigo' => $conductor->codigo,
                    'nombres' => $conductor->nombres,
                    'apellidos' => $conductor->apellidos,
                    'estado_final' => $conductor->estado,
                    'datos_completos' => $conductor->toArray()
                ],
                null,
                'ALTA'
            );

            // Verificar impacto en planificación
            $this->verificarImpactoPlanificacion($conductor);

        } catch (Exception $e) {
            Log::error("Error en ConductorObserver::deleting: " . $e->getMessage(), [
                'conductor_id' => $conductor->id
            ]);

            // Prevenir eliminación si hay error crítico
            throw new Exception("No se puede eliminar el conductor debido a: " . $e->getMessage());
        }
    }

    /**
     * Manejar el evento "deleted" del modelo Conductor
     */
    public function deleted(Conductor $conductor): void
    {
        try {
            // Limpiar cache relacionado
            $this->cacheService->invalidarMetricasConductor($conductor->id);

            // Notificar eliminación si es necesario
            if ($conductor->estado === 'DISPONIBLE') {
                $this->notificacionService->enviarNotificacion(
                    'SISTEMA_ALERTA',
                    'Conductor Eliminado',
                    "Se ha eliminado al conductor {$conductor->codigo} - {$conductor->nombres} {$conductor->apellidos} que estaba en estado DISPONIBLE.",
                    ['conductor_eliminado' => $conductor->toArray()],
                    null,
                    'ADVERTENCIA'
                );
            }

            Log::warning("Conductor eliminado", [
                'conductor_id' => $conductor->id,
                'codigo' => $conductor->codigo
            ]);

        } catch (Exception $e) {
            Log::error("Error en ConductorObserver::deleted: " . $e->getMessage(), [
                'conductor_id' => $conductor->id
            ]);
        }
    }

    /**
     * Generar código único para conductor
     */
    private function generarCodigoConductor(): string
    {
        $prefijo = 'CON';
        $numero = Conductor::count() + 1;
        $codigo = $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);

        // Verificar que sea único
        while (Conductor::where('codigo', $codigo)->exists()) {
            $numero++;
            $codigo = $prefijo . str_pad($numero, 4, '0', STR_PAD_LEFT);
        }

        return $codigo;
    }

    /**
     * Detectar cambios importantes que requieren atención especial
     */
    private function detectarCambiosImportantes(Conductor $conductor): array
    {
        $cambiosImportantes = [];

        // Cambio de estado
        if ($conductor->isDirty('estado')) {
            $estadoAnterior = $conductor->getOriginal('estado');
            $estadoNuevo = $conductor->estado;

            $cambiosImportantes['cambio_estado'] = [
                'anterior' => $estadoAnterior,
                'nuevo' => $estadoNuevo,
                'critico' => $this->esCambioEstadoCritico($estadoAnterior, $estadoNuevo)
            ];
        }

        // Cambio significativo en eficiencia
        if ($conductor->isDirty('eficiencia')) {
            $eficienciaAnterior = $conductor->getOriginal('eficiencia');
            $eficienciaNueva = $conductor->eficiencia;
            $diferencia = abs($eficienciaNueva - $eficienciaAnterior);

            if ($diferencia >= 10) { // Cambio de 10% o más
                $cambiosImportantes['cambio_eficiencia'] = [
                    'anterior' => $eficienciaAnterior,
                    'nueva' => $eficienciaNueva,
                    'diferencia' => $diferencia,
                    'tipo' => $eficienciaNueva > $eficienciaAnterior ? 'mejora' : 'deterioro'
                ];
            }
        }

        // Cambio significativo en puntualidad
        if ($conductor->isDirty('puntualidad')) {
            $puntualidadAnterior = $conductor->getOriginal('puntualidad');
            $puntualidadNueva = $conductor->puntualidad;
            $diferencia = abs($puntualidadNueva - $puntualidadAnterior);

            if ($diferencia >= 10) { // Cambio de 10% o más
                $cambiosImportantes['cambio_puntualidad'] = [
                    'anterior' => $puntualidadAnterior,
                    'nueva' => $puntualidadNueva,
                    'diferencia' => $diferencia,
                    'tipo' => $puntualidadNueva > $puntualidadAnterior ? 'mejora' : 'deterioro'
                ];
            }
        }

        // Cambio en días acumulados
        if ($conductor->isDirty('dias_acumulados')) {
            $diasAnteriores = $conductor->getOriginal('dias_acumulados');
            $diasNuevos = $conductor->dias_acumulados;

            $cambiosImportantes['cambio_dias_acumulados'] = [
                'anterior' => $diasAnteriores,
                'nuevo' => $diasNuevos,
                'critico' => $diasNuevos >= sipat_config('dias_maximos_sin_descanso', 6)
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
            'DISPONIBLE' => ['SUSPENDIDO', 'INACTIVO'],
            'DESCANSO FISICO' => ['SUSPENDIDO', 'INACTIVO'],
            'DESCANSO SEMANAL' => ['SUSPENDIDO', 'INACTIVO']
        ];

        return isset($cambiosCriticos[$estadoAnterior]) &&
               in_array($estadoNuevo, $cambiosCriticos[$estadoAnterior]);
    }

    /**
     * Validar cambio de estado
     */
    private function validarCambioEstado(Conductor $conductor): void
    {
        $estadoAnterior = $conductor->getOriginal('estado');
        $estadoNuevo = $conductor->estado;

        // Validaciones específicas por cambio de estado
        switch ($estadoNuevo) {
            case 'DISPONIBLE':
                // Verificar que haya cumplido descanso mínimo
                if (in_array($estadoAnterior, ['DESCANSO FISICO', 'DESCANSO SEMANAL'])) {
                    $this->validarDescansoCompletado($conductor);
                }
                // Resetear días acumulados si viene de descanso
                if (str_contains($estadoAnterior, 'DESCANSO')) {
                    $conductor->dias_acumulados = 0;
                }
                break;

            case 'DESCANSO FISICO':
            case 'DESCANSO SEMANAL':
                // Registrar inicio de descanso
                $conductor->fecha_inicio_descanso = now();
                break;

            case 'SUSPENDIDO':
            case 'INACTIVO':
                // Verificar impacto en turnos activos
                $this->verificarTurnosActivos($conductor);
                break;
        }
    }

    /**
     * Validar que el descanso ha sido completado
     */
    private function validarDescansoCompletado(Conductor $conductor): void
    {
        $horasMinimas = sipat_config('horas_minimas_descanso', 12);

        if ($conductor->fecha_inicio_descanso) {
            $horasDescanso = $conductor->fecha_inicio_descanso->diffInHours(now());

            if ($horasDescanso < $horasMinimas) {
                Log::warning("Conductor volviendo disponible antes de completar descanso mínimo", [
                    'conductor_id' => $conductor->id,
                    'horas_descanso' => $horasDescanso,
                    'minimas_requeridas' => $horasMinimas
                ]);

                // Generar validación
                app('App\Models\Validacion')->create([
                    'conductor_id' => $conductor->id,
                    'tipo' => 'DESCANSO_001',
                    'descripcion' => "Conductor {$conductor->codigo} retornó a servicio con solo {$horasDescanso} horas de descanso (mínimo: {$horasMinimas}h)",
                    'severidad' => 'ADVERTENCIA',
                    'estado' => 'PENDIENTE',
                    'datos_adicionales' => json_encode([
                        'horas_descanso_real' => $horasDescanso,
                        'horas_minimas' => $horasMinimas,
                        'fecha_inicio_descanso' => $conductor->fecha_inicio_descanso,
                        'fecha_retorno' => now()
                    ])
                ]);
            }
        }
    }

    /**
     * Verificar turnos activos del conductor
     */
    private function verificarTurnosActivos(Conductor $conductor): void
    {
        $turnosActivos = $conductor->turnos()
            ->whereIn('estado', ['PENDIENTE', 'EN_CURSO'])
            ->whereDate('fecha', '>=', today())
            ->get();

        if ($turnosActivos->count() > 0) {
            // Notificar sobre turnos que serán afectados
            $this->notificacionService->enviarNotificacion(
                'SISTEMA_ALERTA',
                'Conductor con Turnos Activos Inactivo',
                "El conductor {$conductor->codigo} tiene {$turnosActivos->count()} turno(s) activo(s) y ha sido marcado como {$conductor->estado}. Se requiere reasignación.",
                [
                    'conductor_id' => $conductor->id,
                    'estado_nuevo' => $conductor->estado,
                    'turnos_afectados' => $turnosActivos->pluck('id')->toArray()
                ],
                null,
                'CRITICA'
            );

            // Registrar evento para planificación automática
            Event::dispatch('conductor.inactivo.con.turnos', [$conductor, $turnosActivos]);
        }
    }

    /**
     * Procesar cambios específicos
     */
    private function procesarCambiosEspecificos(Conductor $conductor, array $cambios, array $valoresOriginales): void
    {
        // Cambio de estado
        if (isset($cambios['estado'])) {
            $estadoAnterior = $valoresOriginales['estado'];
            $estadoNuevo = $cambios['estado'];

            // Disparar evento de cambio de estado
            Event::dispatch('conductor.estado.cambio', [$conductor, $estadoAnterior, $estadoNuevo]);

            // Procesar lógica específica de cada estado
            $this->procesarLogicaEstado($conductor, $estadoAnterior, $estadoNuevo);
        }

        // Cambio en métricas de rendimiento
        if (isset($cambios['eficiencia']) || isset($cambios['puntualidad'])) {
            $this->procesarCambioRendimiento($conductor, $cambios, $valoresOriginales);
        }

        // Cambio en días acumulados
        if (isset($cambios['dias_acumulados'])) {
            $this->procesarCambioDiasAcumulados($conductor, $cambios['dias_acumulados'], $valoresOriginales['dias_acumulados']);
        }
    }

    /**
     * Procesar lógica específica por estado
     */
    private function procesarLogicaEstado(Conductor $conductor, string $estadoAnterior, string $estadoNuevo): void
    {
        // Lógica específica según el nuevo estado
        switch ($estadoNuevo) {
            case 'DISPONIBLE':
                // Verificar si puede ser incluido en próximas asignaciones
                if ($estadoAnterior !== 'DISPONIBLE') {
                    app(ServicioPlanificacionAutomatizada::class)->evaluarConductorParaAsignacion($conductor);
                }
                break;

            case 'DESCANSO FISICO':
            case 'DESCANSO SEMANAL':
                // Liberar turnos futuros si los tiene
                $this->liberarTurnosFuturos($conductor);
                break;

            case 'SUSPENDIDO':
                // Procesar suspensión
                $this->procesarSuspension($conductor);
                break;
        }
    }

    /**
     * Procesar cambio en rendimiento
     */
    private function procesarCambioRendimiento(Conductor $conductor, array $cambios, array $valoresOriginales): void
    {
        $mejoraSignificativa = false;
        $deterioroSignificativo = false;

        // Analizar cambio en eficiencia
        if (isset($cambios['eficiencia'])) {
            $diferencia = $cambios['eficiencia'] - $valoresOriginales['eficiencia'];
            if ($diferencia >= 15) {
                $mejoraSignificativa = true;
            } elseif ($diferencia <= -15) {
                $deterioroSignificativo = true;
            }
        }

        // Analizar cambio en puntualidad
        if (isset($cambios['puntualidad'])) {
            $diferencia = $cambios['puntualidad'] - $valoresOriginales['puntualidad'];
            if ($diferencia >= 15) {
                $mejoraSignificativa = true;
            } elseif ($diferencia <= -15) {
                $deterioroSignificativo = true;
            }
        }

        // Generar notificaciones según el tipo de cambio
        if ($mejoraSignificativa) {
            $this->notificacionService->enviarNotificacion(
                'EFICIENCIA_BAJA', // Usamos este tipo para cambios de rendimiento
                'Mejora Significativa en Rendimiento',
                "El conductor {$conductor->codigo} ha mostrado una mejora significativa en su rendimiento.",
                [
                    'conductor_id' => $conductor->id,
                    'tipo_cambio' => 'mejora',
                    'cambios' => $cambios,
                    'valores_anteriores' => $valoresOriginales
                ],
                null,
                'INFO'
            );
        } elseif ($deterioroSignificativo) {
            // Crear validación para seguimiento
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'EFICIENCIA_002',
                'descripcion' => "Deterioro significativo en rendimiento del conductor {$conductor->codigo}",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE',
                'datos_adicionales' => json_encode([
                    'cambios' => $cambios,
                    'valores_anteriores' => $valoresOriginales,
                    'eficiencia_actual' => $conductor->eficiencia,
                    'puntualidad_actual' => $conductor->puntualidad
                ])
            ]);
        }
    }

    /**
     * Procesar cambio en días acumulados
     */
    private function procesarCambioDiasAcumulados(Conductor $conductor, int $diasNuevos, int $diasAnteriores): void
    {
        $limiteMaximo = sipat_config('dias_maximos_sin_descanso', 6);

        // Si alcanza el límite máximo
        if ($diasNuevos >= $limiteMaximo && $diasAnteriores < $limiteMaximo) {
            // Crear validación crítica
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'DESCANSO_001',
                'descripcion' => "Conductor {$conductor->codigo} ha alcanzado {$diasNuevos} días consecutivos de trabajo. Requiere descanso obligatorio.",
                'severidad' => 'CRITICA',
                'estado' => 'PENDIENTE',
                'solucion_recomendada' => 'Asignar descanso inmediatamente y verificar próximos turnos programados.',
                'datos_adicionales' => json_encode([
                    'dias_acumulados' => $diasNuevos,
                    'limite_maximo' => $limiteMaximo,
                    'fecha_ultimo_descanso' => $conductor->fecha_inicio_descanso
                ])
            ]);

            // Enviar notificación crítica
            $this->notificacionService->enviarNotificacionConductorCritico(
                $conductor,
                ["Necesita descanso urgente: {$diasNuevos} días consecutivos trabajados"]
            );
        }
    }

    /**
     * Liberar turnos futuros del conductor
     */
    private function liberarTurnosFuturos(Conductor $conductor): void
    {
        $turnosFuturos = $conductor->turnos()
            ->where('estado', 'PENDIENTE')
            ->where('fecha', '>', today())
            ->get();

        foreach ($turnosFuturos as $turno) {
            $turno->update([
                'conductor_id' => null,
                'estado' => 'PENDIENTE',
                'observaciones' => "Liberado automáticamente - conductor en descanso"
            ]);

            // Registrar evento para reasignación
            Event::dispatch('turno.liberado.por.descanso', [$turno, $conductor]);
        }

        if ($turnosFuturos->count() > 0) {
            Log::info("Turnos futuros liberados por descanso", [
                'conductor_id' => $conductor->id,
                'turnos_liberados' => $turnosFuturos->count()
            ]);
        }
    }

    /**
     * Procesar suspensión del conductor
     */
    private function procesarSuspension(Conductor $conductor): void
    {
        // Cancelar todos los turnos pendientes
        $turnosCancelados = $conductor->turnos()
            ->where('estado', 'PENDIENTE')
            ->where('fecha', '>=', today())
            ->update([
                'estado' => 'CANCELADO',
                'observaciones' => "Cancelado automáticamente - conductor suspendido",
                'conductor_id' => null
            ]);

        if ($turnosCancelados > 0) {
            // Notificar sobre turnos cancelados
            $this->notificacionService->enviarNotificacion(
                'SISTEMA_ALERTA',
                'Turnos Cancelados por Suspensión',
                "Se han cancelado {$turnosCancelados} turno(s) del conductor suspendido {$conductor->codigo}. Se requiere reasignación urgente.",
                [
                    'conductor_id' => $conductor->id,
                    'turnos_cancelados' => $turnosCancelados
                ],
                null,
                'CRITICA'
            );
        }
    }

    /**
     * Ejecutar validaciones iniciales para nuevo conductor
     */
    private function ejecutarValidacionesIniciales(Conductor $conductor): void
    {
        // Validar datos básicos
        if (empty($conductor->telefono)) {
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'DATOS_001',
                'descripcion' => "Conductor {$conductor->codigo} no tiene teléfono registrado",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE'
            ]);
        }

        if (empty($conductor->direccion)) {
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'DATOS_002',
                'descripcion' => "Conductor {$conductor->codigo} no tiene dirección registrada",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE'
            ]);
        }

        // Verificar licencia si aplica
        if (empty($conductor->numero_licencia)) {
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'LICENCIA_001',
                'descripcion' => "Conductor {$conductor->codigo} no tiene número de licencia registrado",
                'severidad' => 'CRITICA',
                'estado' => 'PENDIENTE'
            ]);
        }
    }

    /**
     * Verificar necesidad de capacitación inicial
     */
    private function verificarNecesidadCapacitacion(Conductor $conductor): void
    {
        // Nuevo conductor con eficiencia por defecto necesita capacitación
        if ($conductor->eficiencia <= 85 && is_null($conductor->fecha_ultima_capacitacion)) {
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'CAPACITACION_001',
                'descripcion' => "Conductor nuevo {$conductor->codigo} requiere capacitación inicial",
                'severidad' => 'MEDIA',
                'estado' => 'PENDIENTE',
                'solucion_recomendada' => 'Programar capacitación inicial en procedimientos y seguridad.'
            ]);
        }
    }

    /**
     * Ejecutar validaciones post-actualización
     */
    private function ejecutarValidacionesPostActualizacion(Conductor $conductor, array $cambios): void
    {
        // Validar eficiencia baja
        if (isset($cambios['eficiencia']) && $conductor->eficiencia < sipat_config('eficiencia_minima_conductor', 80)) {
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'EFICIENCIA_002',
                'descripcion' => "Conductor {$conductor->codigo} tiene eficiencia por debajo del mínimo ({$conductor->eficiencia}%)",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE'
            ]);
        }

        // Validar puntualidad baja
        if (isset($cambios['puntualidad']) && $conductor->puntualidad < sipat_config('puntualidad_minima_conductor', 85)) {
            app('App\Models\Validacion')->create([
                'conductor_id' => $conductor->id,
                'tipo' => 'PUNTUALIDAD_003',
                'descripcion' => "Conductor {$conductor->codigo} tiene puntualidad por debajo del mínimo ({$conductor->puntualidad}%)",
                'severidad' => 'ADVERTENCIA',
                'estado' => 'PENDIENTE'
            ]);
        }
    }

    /**
     * Verificar si la eliminación está permitida
     */
    private function verificarEliminacionPermitida(Conductor $conductor): void
    {
        // Verificar turnos activos
        $turnosActivos = $conductor->turnos()
            ->whereIn('estado', ['PENDIENTE', 'EN_CURSO'])
            ->where('fecha', '>=', today())
            ->count();

        if ($turnosActivos > 0) {
            throw new Exception("No se puede eliminar el conductor porque tiene {$turnosActivos} turno(s) activo(s)");
        }

        // Verificar validaciones pendientes críticas
        $validacionesCriticas = $conductor->validaciones()
            ->where('severidad', 'CRITICA')
            ->where('estado', 'PENDIENTE')
            ->count();

        if ($validacionesCriticas > 0) {
            throw new Exception("No se puede eliminar el conductor porque tiene {$validacionesCriticas} validación(es) crítica(s) pendiente(s)");
        }
    }

    /**
     * Verificar impacto en planificación
     */
    private function verificarImpactoPlanificacion(Conductor $conductor): void
    {
        if ($conductor->estado === 'DISPONIBLE') {
            // Verificar si es un conductor clave
            $rutasAsignadas = $conductor->turnos()
                ->where('fecha', '>=', today())
                ->where('fecha', '<=', now()->addDays(7))
                ->count();

            if ($rutasAsignadas > 0) {
                // Disparar evento para replanificación
                Event::dispatch('conductor.eliminado.con.impacto', [$conductor, $rutasAsignadas]);
            }
        }
    }
}
