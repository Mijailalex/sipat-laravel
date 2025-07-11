/**
 * =============================================================================
 * FUNCIÓN COMPLETA PARA RESOLVER VALIDACIONES CRÍTICAS
 * =============================================================================
 * Reemplaza la función básica en app/Services/ServicioPlanificacionAutomatizada.php
 */

/**
 * Resolver validación crítica automáticamente según su tipo
 */
private function resolverValidacionCritica($plantilla, $validacion)
{
    try {
        Log::info("🔧 Iniciando resolución de validación crítica", [
            'tipo' => $validacion['tipo'] ?? ($validacion->tipo ?? 'DESCONOCIDO'),
            'validacion_id' => $validacion['id'] ?? ($validacion->id ?? null),
            'plantilla_id' => $plantilla->id ?? null
        ]);

        // Convertir array a objeto si es necesario
        $validacionObj = $this->normalizarValidacion($validacion);

        // Resolver según el tipo de validación
        $resultado = match($validacionObj->tipo) {
            'DESCANSO_001' => $this->resolverDescansoObligatorio($plantilla, $validacionObj),
            'EFICIENCIA_002' => $this->resolverBajaEficiencia($plantilla, $validacionObj),
            'PUNTUALIDAD_003' => $this->resolverBajaPuntualidad($plantilla, $validacionObj),
            'TURNO_VACIO_004' => $this->resolverTurnoVacio($plantilla, $validacionObj),
            'PROXIMO_DESCANSO' => $this->resolverProximoDescanso($plantilla, $validacionObj),
            'HORAS_EXCEDIDAS' => $this->resolverHorasExcedidas($plantilla, $validacionObj),
            'CONDUCTOR_NO_DISPONIBLE' => $this->resolverConductorNoDisponible($plantilla, $validacionObj),
            'CONFLICTO_HORARIO' => $this->resolverConflictoHorario($plantilla, $validacionObj),
            'RUTA_CORTA_CONSECUTIVA' => $this->resolverRutaCortaConsecutiva($plantilla, $validacionObj),
            'SOBRECARGA_TRABAJO' => $this->resolverSobrecargaTrabajo($plantilla, $validacionObj),
            default => $this->resolverValidacionGenerica($plantilla, $validacionObj)
        };

        // Actualizar estado de la validación
        $this->actualizarEstadoValidacion($validacionObj, $resultado);

        // Registrar en métricas
        $this->registrarResolucionEnMetricas($validacionObj->tipo, $resultado['resuelto']);

        // Enviar notificación si es necesario
        if ($resultado['notificar']) {
            $this->enviarNotificacionResolucion($validacionObj, $resultado);
        }

        Log::info("✅ Validación crítica procesada", [
            'tipo' => $validacionObj->tipo,
            'resuelto' => $resultado['resuelto'],
            'accion_tomada' => $resultado['accion'],
            'requiere_atencion_humana' => $resultado['requiere_atencion_humana'] ?? false
        ]);

        return $resultado;

    } catch (\Exception $e) {
        Log::error("❌ Error resolviendo validación crítica", [
            'tipo' => $validacion['tipo'] ?? ($validacion->tipo ?? 'DESCONOCIDO'),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Marcar como error para revisión manual
        $this->marcarValidacionParaRevisionManual($validacion, $e->getMessage());

        return [
            'resuelto' => false,
            'accion' => 'ERROR_PROCESAMIENTO',
            'mensaje' => 'Error en resolución automática: ' . $e->getMessage(),
            'requiere_atencion_humana' => true,
            'notificar' => true
        ];
    }
}

/**
 * =============================================================================
 * RESOLVERS ESPECÍFICOS POR TIPO DE VALIDACIÓN
 * =============================================================================
 */

/**
 * Resolver conductor con descanso obligatorio (6+ días trabajados)
 */
private function resolverDescansoObligatorio($plantilla, $validacion)
{
    $conductor = $this->obtenerConductorDeValidacion($validacion);

    if (!$conductor) {
        return $this->crearResultadoError('Conductor no encontrado para validación de descanso');
    }

    Log::info("   🛏️ Resolviendo descanso obligatorio para conductor", [
        'conductor_id' => $conductor->id,
        'nombre' => $conductor->nombre,
        'dias_acumulados' => $conductor->dias_acumulados
    ]);

    // Paso 1: Buscar conductor de reemplazo disponible
    $conductorReemplazo = $this->buscarConductorReemplazo($conductor, $plantilla);

    if ($conductorReemplazo) {
        // Paso 2: Reasignar turnos del conductor original al reemplazo
        $turnosReasignados = $this->reasignarTurnos($conductor, $conductorReemplazo, $plantilla);

        // Paso 3: Cambiar estado del conductor original a descanso
        $this->cambiarConductorADescanso($conductor, 'Descanso automático por días acumulados');

        // Paso 4: Actualizar métricas del conductor de reemplazo
        $this->actualizarMetricasConductor($conductorReemplazo);

        return [
            'resuelto' => true,
            'accion' => 'REASIGNACION_CON_DESCANSO',
            'mensaje' => "Conductor {$conductor->nombre} enviado a descanso. " .
                        "Turnos reasignados a {$conductorReemplazo->nombre}.",
            'detalles' => [
                'conductor_original' => $conductor->id,
                'conductor_reemplazo' => $conductorReemplazo->id,
                'turnos_reasignados' => $turnosReasignados,
                'tipo_descanso' => 'OBLIGATORIO'
            ],
            'requiere_atencion_humana' => false,
            'notificar' => true
        ];
    } else {
        // No hay reemplazo: intentar redistribuir turnos
        $redistribucion = $this->redistribuirTurnosEntreConductores($conductor, $plantilla);

        if ($redistribucion['exitosa']) {
            $this->cambiarConductorADescanso($conductor, 'Descanso automático - turnos redistribuidos');

            return [
                'resuelto' => true,
                'accion' => 'REDISTRIBUCION_CON_DESCANSO',
                'mensaje' => "Conductor {$conductor->nombre} enviado a descanso. " .
                            "Turnos redistribuidos entre {$redistribucion['conductores_usados']} conductores.",
                'detalles' => [
                    'conductor_original' => $conductor->id,
                    'redistribucion' => $redistribucion,
                    'tipo_descanso' => 'OBLIGATORIO'
                ],
                'requiere_atencion_humana' => false,
                'notificar' => true
            ];
        } else {
            // No se puede resolver automáticamente
            return [
                'resuelto' => false,
                'accion' => 'REQUIERE_INTERVENCION_MANUAL',
                'mensaje' => "Conductor {$conductor->nombre} requiere descanso obligatorio " .
                            "pero no hay conductores disponibles para reemplazo.",
                'detalles' => [
                    'conductor_id' => $conductor->id,
                    'dias_acumulados' => $conductor->dias_acumulados,
                    'motivo_fallo' => 'Sin conductores de reemplazo disponibles'
                ],
                'requiere_atencion_humana' => true,
                'notificar' => true,
                'prioridad_resolucion' => 'ALTA'
            ];
        }
    }
}

/**
 * Resolver conductor con baja eficiencia (<80%)
 */
private function resolverBajaEficiencia($plantilla, $validacion)
{
    $conductor = $this->obtenerConductorDeValidacion($validacion);

    if (!$conductor) {
        return $this->crearResultadoError('Conductor no encontrado para validación de eficiencia');
    }

    Log::info("   📉 Resolviendo baja eficiencia para conductor", [
        'conductor_id' => $conductor->id,
        'eficiencia_actual' => $conductor->eficiencia
    ]);

    // Analizar tendencia de eficiencia
    $tendencia = $this->analizarTendenciaEficiencia($conductor);

    if ($tendencia['es_tendencia_negativa']) {
        // Tendencia decreciente: reasignar a rutas menos críticas
        $reasignacion = $this->reasignarARutasMenosCriticas($conductor, $plantilla);

        if ($reasignacion['exitosa']) {
            // Programar seguimiento
            $this->programarSeguimientoConductor($conductor, 'EFICIENCIA_BAJA', 7); // 7 días

            return [
                'resuelto' => true,
                'accion' => 'REASIGNACION_RUTAS_MENOS_CRITICAS',
                'mensaje' => "Conductor {$conductor->nombre} reasignado a rutas menos críticas " .
                            "debido a baja eficiencia ({$conductor->eficiencia}%).",
                'detalles' => [
                    'conductor_id' => $conductor->id,
                    'eficiencia_anterior' => $conductor->eficiencia,
                    'rutas_reasignadas' => $reasignacion['rutas'],
                    'seguimiento_programado' => true
                ],
                'requiere_atencion_humana' => false,
                'notificar' => true
            ];
        }
    }

    // Si es fluctuación temporal: mantener en observación
    $this->marcarConductorEnObservacion($conductor, 'EFICIENCIA_BAJA');

    return [
        'resuelto' => true,
        'accion' => 'OBSERVACION_EFICIENCIA',
        'mensaje' => "Conductor {$conductor->nombre} marcado para observación por eficiencia baja. " .
                    "Se monitoreará por 5 días.",
        'detalles' => [
            'conductor_id' => $conductor->id,
            'eficiencia_actual' => $conductor->eficiencia,
            'tendencia' => $tendencia,
            'periodo_observacion' => 5
        ],
        'requiere_atencion_humana' => false,
        'notificar' => false // No notificar para observación simple
    ];
}

/**
 * Resolver conductor con baja puntualidad (<85%)
 */
private function resolverBajaPuntualidad($plantilla, $validacion)
{
    $conductor = $this->obtenerConductorDeValidacion($validacion);

    if (!$conductor) {
        return $this->crearResultadoError('Conductor no encontrado para validación de puntualidad');
    }

    Log::info("   ⏰ Resolviendo baja puntualidad para conductor", [
        'conductor_id' => $conductor->id,
        'puntualidad_actual' => $conductor->puntualidad
    ]);

    // Analizar patrón de impuntualidad
    $patron = $this->analizarPatronImpuntualidad($conductor);

    if ($patron['horarios_problematicos']) {
        // Reasignar evitando horarios problemáticos
        $reasignacion = $this->reasignarEvitandoHorarios($conductor, $patron['horarios_problematicos'], $plantilla);

        if ($reasignacion['exitosa']) {
            return [
                'resuelto' => true,
                'accion' => 'REASIGNACION_HORARIOS_OPTIMOS',
                'mensaje' => "Conductor {$conductor->nombre} reasignado evitando horarios problemáticos " .
                            "para mejorar puntualidad.",
                'detalles' => [
                    'conductor_id' => $conductor->id,
                    'puntualidad_anterior' => $conductor->puntualidad,
                    'horarios_evitados' => $patron['horarios_problematicos'],
                    'nuevos_horarios' => $reasignacion['horarios']
                ],
                'requiere_atencion_humana' => false,
                'notificar' => true
            ];
        }
    }

    // Programar capacitación
    $this->programarCapacitacionConductor($conductor, 'PUNTUALIDAD');

    return [
        'resuelto' => true,
        'accion' => 'CAPACITACION_PROGRAMADA',
        'mensaje' => "Programada capacitación en puntualidad para conductor {$conductor->nombre}.",
        'detalles' => [
            'conductor_id' => $conductor->id,
            'tipo_capacitacion' => 'PUNTUALIDAD',
            'puntualidad_actual' => $conductor->puntualidad
        ],
        'requiere_atencion_humana' => false,
        'notificar' => true
    ];
}

/**
 * Resolver turno sin conductor asignado
 */
private function resolverTurnoVacio($plantilla, $validacion)
{
    $turno = $this->obtenerTurnoDeValidacion($validacion);

    if (!$turno) {
        return $this->crearResultadoError('Turno no encontrado para validación');
    }

    Log::info("   🚌 Resolviendo turno vacío", [
        'turno_id' => $turno->id,
        'hora_salida' => $turno->hora_salida,
        'origen' => $turno->origen
    ]);

    // Buscar conductor disponible con mejor compatibilidad
    $conductorCompatible = $this->buscarConductorMasCompatible($turno);

    if ($conductorCompatible) {
        // Asignar conductor al turno
        $this->asignarConductorATurno($conductorCompatible, $turno);

        return [
            'resuelto' => true,
            'accion' => 'ASIGNACION_AUTOMATICA',
            'mensaje' => "Turno asignado a conductor {$conductorCompatible->nombre} " .
                        "(compatibilidad: {$conductorCompatible->score_compatibilidad}%).",
            'detalles' => [
                'turno_id' => $turno->id,
                'conductor_asignado' => $conductorCompatible->id,
                'score_compatibilidad' => $conductorCompatible->score_compatibilidad,
                'criterios_seleccion' => $conductorCompatible->criterios_usados
            ],
            'requiere_atencion_humana' => false,
            'notificar' => false
        ];
    } else {
        // Intentar usar conductor de back-up
        $conductorBackup = $this->buscarConductorBackup($turno);

        if ($conductorBackup) {
            $this->asignarConductorATurno($conductorBackup, $turno);

            return [
                'resuelto' => true,
                'accion' => 'ASIGNACION_BACKUP',
                'mensaje' => "Turno asignado a conductor backup {$conductorBackup->nombre}.",
                'detalles' => [
                    'turno_id' => $turno->id,
                    'conductor_backup' => $conductorBackup->id,
                    'es_backup' => true
                ],
                'requiere_atencion_humana' => true,
                'notificar' => true
            ];
        } else {
            // No hay conductores disponibles
            return [
                'resuelto' => false,
                'accion' => 'SIN_CONDUCTORES_DISPONIBLES',
                'mensaje' => "No hay conductores disponibles para turno " .
                            "{$turno->hora_salida} desde {$turno->origen}.",
                'detalles' => [
                    'turno_id' => $turno->id,
                    'hora_salida' => $turno->hora_salida,
                    'origen' => $turno->origen,
                    'conductores_evaluados' => $this->metricas['conductores_evaluados'] ?? 0
                ],
                'requiere_atencion_humana' => true,
                'notificar' => true,
                'prioridad_resolucion' => 'CRITICA'
            ];
        }
    }
}

/**
 * Resolver conductor próximo a descanso (5 días)
 */
private function resolverProximoDescanso($plantilla, $validacion)
{
    $conductor = $this->obtenerConductorDeValidacion($validacion);

    if (!$conductor) {
        return $this->crearResultadoError('Conductor no encontrado para validación de próximo descanso');
    }

    Log::info("   🔜 Resolviendo próximo descanso para conductor", [
        'conductor_id' => $conductor->id,
        'dias_acumulados' => $conductor->dias_acumulados
    ]);

    // Buscar conductor para cubrir cuando llegue el descanso
    $conductorCobertura = $this->buscarConductorParaCobertura($conductor, $plantilla);

    if ($conductorCobertura) {
        // Programar transición gradual
        $this->programarTransicionGradual($conductor, $conductorCobertura, $plantilla);

        return [
            'resuelto' => true,
            'accion' => 'TRANSICION_PROGRAMADA',
            'mensaje' => "Transición programada para conductor {$conductor->nombre}. " .
                        "Cobertura asignada a {$conductorCobertura->nombre}.",
            'detalles' => [
                'conductor_id' => $conductor->id,
                'conductor_cobertura' => $conductorCobertura->id,
                'dias_hasta_descanso' => 6 - $conductor->dias_acumulados,
                'transicion_programada' => true
            ],
            'requiere_atencion_humana' => false,
            'notificar' => true
        ];
    } else {
        // Alertar con tiempo para planificación manual
        return [
            'resuelto' => false,
            'accion' => 'ALERTA_PLANIFICACION_MANUAL',
            'mensaje' => "Conductor {$conductor->nombre} necesitará descanso en " .
                        (6 - $conductor->dias_acumulados) . " días. Planificar cobertura.",
            'detalles' => [
                'conductor_id' => $conductor->id,
                'dias_hasta_descanso' => 6 - $conductor->dias_acumulados,
                'fecha_descanso_estimada' => now()->addDays(6 - $conductor->dias_acumulados)->toDateString()
            ],
            'requiere_atencion_humana' => true,
            'notificar' => true,
            'prioridad_resolucion' => 'MEDIA'
        ];
    }
}

/**
 * Resolver conductor con horas excedidas (>12h)
 */
private function resolverHorasExcedidas($plantilla, $validacion)
{
    $conductor = $this->obtenerConductorDeValidacion($validacion);

    if (!$conductor) {
        return $this->crearResultadoError('Conductor no encontrado para validación de horas excedidas');
    }

    Log::info("   ⏱️ Resolviendo horas excedidas para conductor", [
        'conductor_id' => $conductor->id,
        'horas_acumuladas' => $conductor->horas_hombre
    ]);

    // Buscar turnos que se pueden reasignar
    $turnosReasignables = $this->identificarTurnosReasignables($conductor, $plantilla);

    if (!empty($turnosReasignables)) {
        $reasignaciones = $this->ejecutarReasignacionesTurnos($turnosReasignables);

        if ($reasignaciones['exitosas'] > 0) {
            return [
                'resuelto' => true,
                'accion' => 'REASIGNACION_REDUCCION_HORAS',
                'mensaje' => "Reducidas {$reasignaciones['horas_reducidas']} horas para conductor {$conductor->nombre}.",
                'detalles' => [
                    'conductor_id' => $conductor->id,
                    'horas_anteriores' => $conductor->horas_hombre,
                    'horas_reducidas' => $reasignaciones['horas_reducidas'],
                    'turnos_reasignados' => $reasignaciones['turnos']
                ],
                'requiere_atencion_humana' => false,
                'notificar' => true
            ];
        }
    }

    // Forzar descanso si no se puede reducir horas
    $this->cambiarConductorADescanso($conductor, 'Descanso forzado por exceso de horas');

    return [
        'resuelto' => true,
        'accion' => 'DESCANSO_FORZADO',
        'mensaje' => "Conductor {$conductor->nombre} enviado a descanso forzado por exceso de horas.",
        'detalles' => [
            'conductor_id' => $conductor->id,
            'horas_excedidas' => $conductor->horas_hombre,
            'tipo_descanso' => 'FORZADO_HORAS'
        ],
        'requiere_atencion_humana' => true,
        'notificar' => true
    ];
}

/**
 * =============================================================================
 * MÉTODOS DE APOYO PARA RESOLUCIÓN
 * =============================================================================
 */

private function normalizarValidacion($validacion)
{
    if (is_array($validacion)) {
        // Convertir array a objeto stdClass
        $obj = new \stdClass();
        foreach ($validacion as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    return $validacion; // Ya es objeto
}

private function obtenerConductorDeValidacion($validacion)
{
    $conductorId = $validacion->conductor_id ?? null;

    if (!$conductorId) {
        return null;
    }

    return Conductor::find($conductorId);
}

private function obtenerTurnoDeValidacion($validacion)
{
    $turnoId = $validacion->turno_id ?? $validacion->entidad_id ?? null;

    if (!$turnoId) {
        return null;
    }

    return Turno::find($turnoId);
}

private function buscarConductorReemplazo($conductorOriginal, $plantilla)
{
    return Conductor::where('estado', 'DISPONIBLE')
        ->where('id', '!=', $conductorOriginal->id)
        ->where('dias_acumulados', '<', 5) // No próximos a descanso
        ->where('eficiencia', '>=', 80)
        ->where('puntualidad', '>=', 85)
        ->orderByDesc('score_general')
        ->first();
}

private function reasignarTurnos($conductorOriginal, $conductorReemplazo, $plantilla)
{
    $turnos = Turno::where('conductor_id', $conductorOriginal->id)
        ->where('plantilla_id', $plantilla->id)
        ->get();

    $reasignados = 0;

    foreach ($turnos as $turno) {
        $turno->update(['conductor_id' => $conductorReemplazo->id]);
        $reasignados++;
    }

    return $reasignados;
}

private function cambiarConductorADescanso($conductor, $motivo)
{
    $conductor->update([
        'estado' => 'DESCANSO_FISICO',
        'dias_acumulados' => 0,
        'fecha_inicio_descanso' => now(),
        'motivo_descanso' => $motivo
    ]);

    // Registrar en historial
    Log::info("Conductor enviado a descanso", [
        'conductor_id' => $conductor->id,
        'motivo' => $motivo
    ]);
}

private function actualizarEstadoValidacion($validacion, $resultado)
{
    if ($validacion instanceof \App\Models\Validacion) {
        $validacion->update([
            'estado' => $resultado['resuelto'] ? 'RESUELTO' : 'REVISION_REQUERIDA',
            'accion_tomada' => $resultado['accion'],
            'observaciones' => $resultado['mensaje'],
            'fecha_resolucion' => now(),
            'resuelto_por' => 0, // Sistema automático
            'datos_resolucion' => $resultado['detalles'] ?? []
        ]);
    }
}

private function registrarResolucionEnMetricas($tipo, $resuelto)
{
    if (!isset($this->metricas['validaciones_resueltas'])) {
        $this->metricas['validaciones_resueltas'] = [];
    }

    if (!isset($this->metricas['validaciones_resueltas'][$tipo])) {
        $this->metricas['validaciones_resueltas'][$tipo] = [
            'total' => 0,
            'resueltas' => 0,
            'pendientes' => 0
        ];
    }

    $this->metricas['validaciones_resueltas'][$tipo]['total']++;

    if ($resuelto) {
        $this->metricas['validaciones_resueltas'][$tipo]['resueltas']++;
    } else {
        $this->metricas['validaciones_resueltas'][$tipo]['pendientes']++;
    }
}

private function enviarNotificacionResolucion($validacion, $resultado)
{
    // Integrar con NotificacionService cuando esté disponible
    if (class_exists('\App\Services\NotificacionService')) {
        $notificacionService = app(\App\Services\NotificacionService::class);

        $titulo = $resultado['resuelto'] ?
            "✅ Validación resuelta automáticamente" :
            "⚠️ Validación requiere atención";

        $notificacionService->enviar(
            $this->obtenerSupervisores(),
            \App\Services\NotificacionService::CATEGORIA_VALIDACION,
            'RESOLUCION_AUTOMATICA',
            $titulo,
            $resultado['mensaje'],
            [
                'validacion_id' => $validacion->id ?? null,
                'tipo_validacion' => $validacion->tipo,
                'accion_tomada' => $resultado['accion'],
                'requiere_atencion' => $resultado['requiere_atencion_humana'] ?? false
            ],
            $resultado['requiere_atencion_humana'] ?
                \App\Services\NotificacionService::PRIORIDAD_ALTA :
                \App\Services\NotificacionService::PRIORIDAD_NORMAL
        );
    }
}

private function crearResultadoError($mensaje)
{
    return [
        'resuelto' => false,
        'accion' => 'ERROR',
        'mensaje' => $mensaje,
        'requiere_atencion_humana' => true,
        'notificar' => true
    ];
}

private function marcarValidacionParaRevisionManual($validacion, $motivoError)
{
    if ($validacion instanceof \App\Models\Validacion) {
        $validacion->update([
            'estado' => 'ERROR_AUTOMATICO',
            'observaciones' => 'Error en resolución automática: ' . $motivoError,
            'requiere_revision_manual' => true,
            'fecha_error' => now()
        ]);
    }
}

private function resolverValidacionGenerica($plantilla, $validacion)
{
    // Para tipos de validación no implementados específicamente
    Log::warning("Tipo de validación no implementado", [
        'tipo' => $validacion->tipo
    ]);

    return [
        'resuelto' => false,
        'accion' => 'TIPO_NO_IMPLEMENTADO',
        'mensaje' => "Tipo de validación '{$validacion->tipo}' no tiene resolver específico implementado.",
        'requiere_atencion_humana' => true,
        'notificar' => false
    ];
}

/**
 * =============================================================================
 * MÉTODOS DE APOYO ADICIONALES
 * =============================================================================
 */

private function analizarTendenciaEficiencia($conductor)
{
    // Simular análisis de tendencia (implementar con datos históricos reales)
    $eficienciaHistorica = [85, 82, 79, 77]; // Últimos 4 registros

    $tendencia = count($eficienciaHistorica) >= 3;
    $esNegativa = $tendencia && end($eficienciaHistorica) < $eficienciaHistorica[0];

    return [
        'es_tendencia_negativa' => $esNegativa,
        'datos_historicos' => $eficienciaHistorica,
        'pendiente' => $esNegativa ? -2.5 : 1.2
    ];
}

private function buscarConductorMasCompatible($turno)
{
    $conductores = Conductor::where('estado', 'DISPONIBLE')
        ->get();

    $mejorConductor = null;
    $mejorScore = 0;

    foreach ($conductores as $conductor) {
        $score = $this->calcularCompatibilidad($conductor, $turno);

        if ($score > $mejorScore) {
            $mejorScore = $score;
            $mejorConductor = $conductor;
            $mejorConductor->score_compatibilidad = $score;
        }
    }

    return $mejorScore >= 60 ? $mejorConductor : null; // Mínimo 60% compatibilidad
}

private function calcularCompatibilidad($conductor, $turno)
{
    $score = 0;

    // Factor proximidad (30%)
    if ($conductor->origen === $turno->origen) {
        $score += 30;
    }

    // Factor puntualidad (25%)
    $score += ($conductor->puntualidad / 100) * 25;

    // Factor eficiencia (25%)
    $score += ($conductor->eficiencia / 100) * 25;

    // Factor disponibilidad (20%)
    if ($this->conductorDisponibleParaHora($conductor, $turno->hora_salida)) {
        $score += 20;
    }

    return round($score, 2);
}

private function conductorDisponibleParaHora($conductor, $horaSalida)
{
    // Verificar si el conductor está disponible para la hora específica
    // Implementar lógica específica según reglas de negocio
    return true; // Simplificado por ahora
}

private function obtenerSupervisores()
{
    // Retornar lista de supervisores para notificaciones
    return \App\Models\User::whereHas('roles', function($query) {
        $query->whereIn('name', ['admin', 'supervisor']);
    })->get();
}
