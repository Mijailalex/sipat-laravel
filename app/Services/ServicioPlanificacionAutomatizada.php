<?php

namespace App\Services;

use App\Models\Conductor;
use App\Models\RutaCorta;
use App\Models\Plantilla;
use App\Models\Turno;
use App\Models\Validacion;
use App\Models\HistorialPlanificacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ServicioPlanificacionAutomatizada
{
    private $fechaPlanificacion;
    private $configuracion;
    private $metricas;

    public function __construct()
    {
        $this->configuracion = [
            'dias_filtro_rutas_cortas' => 2, // Últimos 2 días a excluir
            'meses_antigüedad_minima' => 1, // Excluir conductores nuevos (último mes)
            'eficiencia_minima' => 80,
            'puntualidad_minima' => 85,
            'horas_descanso_minimas' => 12,
            'max_dias_sin_descanso' => 6
        ];

        $this->metricas = [
            'conductores_procesados' => 0,
            'conductores_filtrados' => 0,
            'asignaciones_realizadas' => 0,
            'validaciones_generadas' => 0,
            'tiempo_inicio' => null,
            'errores' => []
        ];
    }

    /**
     * MÉTODO PRINCIPAL - Ejecuta todo el proceso de planificación automatizada
     */
    public function ejecutarPlanificacionCompleta($fechaPlanificacion = null)
    {
        $this->fechaPlanificacion = $fechaPlanificacion ?? Carbon::now()->addDay();
        $this->metricas['tiempo_inicio'] = now();

        Log::info('🚀 Iniciando planificación automatizada', [
            'fecha_planificacion' => $this->fechaPlanificacion->format('Y-m-d'),
            'configuracion' => $this->configuracion
        ]);

        DB::beginTransaction();

        try {
            // PASO 1: Balance de rutas cortas y filtros
            $conductoresDisponibles = $this->paso1_BalanceRutasCortas();

            // PASO 2: Tablero de rutas cortas semanales
            $conductoresConMetricas = $this->paso2_TableroRutasSemanales($conductoresDisponibles);

            // PASO 3: Verificación operatividad (simulado - en producción sería email)
            $conductoresOperativos = $this->paso3_VerificarOperatividad($conductoresConMetricas);

            // PASO 4: BI Conductores - disponibilidad y origen
            $conductoresBI = $this->paso4_AnalisisBIConductores($conductoresOperativos);

            // PASO 5: Generación de pre-plantilla con frecuencias
            $prePlantilla = $this->paso5_GenerarPrePlantilla($conductoresBI);

            // PASO 6: Asignación personal NAZCA para vacíos
            $plantillaConNazca = $this->paso6_AsignarPersonalNazca($prePlantilla);

            // PASO 7: Generación de plantilla final y revisión
            $plantillaFinal = $this->paso7_GenerarPlantillaFinal($plantillaConNazca);

            // PASO 8: Validaciones post generación
            $validaciones = $this->paso8_ValidacionesPost($plantillaFinal);

            // PASO 9: Optimización iterativa
            $plantillaOptimizada = $this->paso9_OptimizacionIterativa($plantillaFinal, $validaciones);

            // PASO 10: Finalización y notificaciones
            $resultado = $this->paso10_FinalizacionNotificaciones($plantillaOptimizada);

            DB::commit();

            $this->registrarHistorial('COMPLETADO', $resultado);

            Log::info('✅ Planificación automatizada completada exitosamente', [
                'metricas' => $this->metricas,
                'tiempo_total' => now()->diffInSeconds($this->metricas['tiempo_inicio']) . ' segundos'
            ]);

            return $resultado;

        } catch (\Exception $e) {
            DB::rollback();

            $this->metricas['errores'][] = $e->getMessage();
            $this->registrarHistorial('ERROR', null, $e->getMessage());

            Log::error('❌ Error en planificación automatizada', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * PASO 1: Balance de rutas cortas con filtros de exclusión
     */
    private function paso1_BalanceRutasCortas()
    {
        Log::info('📋 PASO 1: Aplicando filtros de rutas cortas');

        $fechaLimiteRutasCortas = $this->fechaPlanificacion->copy()->subDays($this->configuracion['dias_filtro_rutas_cortas']);
        $fechaLimiteAntiguedad = now()->subMonths($this->configuracion['meses_antigüedad_minima']);

        $conductores = Conductor::with(['rutasCortas' => function($query) use ($fechaLimiteRutasCortas) {
                $query->where('fecha_realizacion', '>=', $fechaLimiteRutasCortas);
            }])
            ->where('estado', 'DISPONIBLE')
            ->where('fecha_ingreso', '<=', $fechaLimiteAntiguedad) // Excluir conductores nuevos
            ->get()
            ->filter(function($conductor) {
                // Filtro: No deben tener rutas cortas en los últimos días
                return $conductor->rutasCortas->isEmpty();
            });

        $this->metricas['conductores_procesados'] = Conductor::where('estado', 'DISPONIBLE')->count();
        $this->metricas['conductores_filtrados'] = $conductores->count();

        Log::info("✓ PASO 1 completado", [
            'total_disponibles' => $this->metricas['conductores_procesados'],
            'post_filtros' => $this->metricas['conductores_filtrados']
        ]);

        return $conductores;
    }

    /**
     * PASO 2: Análisis de tablero de rutas semanales
     */
    private function paso2_TableroRutasSemanales($conductores)
    {
        Log::info('📊 PASO 2: Analizando tablero de rutas semanales');

        $inicioSemana = $this->fechaPlanificacion->copy()->startOfWeek();
        $finSemana = $this->fechaPlanificacion->copy()->endOfWeek();

        $conductoresConMetricas = $conductores->map(function($conductor) use ($inicioSemana, $finSemana) {
            // Contar rutas de la semana
            $rutasSemana = RutaCorta::where('conductor_id', $conductor->id)
                ->whereBetween('fecha_realizacion', [$inicioSemana, $finSemana])
                ->count();

            // Calcular ratio de rutas cortas vs largas del mes
            $rutasCortas30dias = RutaCorta::where('conductor_id', $conductor->id)
                ->where('fecha_realizacion', '>=', now()->subDays(30))
                ->count();

            $conductor->rutas_semana_actual = $rutasSemana;
            $conductor->ratio_rutas_mes = $rutasCortas30dias;
            $conductor->prioridad_asignacion = $this->calcularPrioridadAsignacion($conductor);

            return $conductor;
        })->sortBy('prioridad_asignacion'); // Ordenar por prioridad (menor = más prioritario)

        Log::info("✓ PASO 2 completado", [
            'conductores_analizados' => $conductoresConMetricas->count()
        ]);

        return $conductoresConMetricas;
    }

    /**
     * PASO 3: Verificación de operatividad (simulado)
     */
    private function paso3_VerificarOperatividad($conductores)
    {
        Log::info('🔍 PASO 3: Verificando operatividad de conductores');

        // En producción, aquí se integraría con el sistema de seguimiento de tripulación
        // Por ahora, simulamos la verificación basada en datos del sistema

        $conductoresOperativos = $conductores->filter(function($conductor) {
            // Verificaciones de operatividad
            $verificaciones = [
                'eficiencia_ok' => $conductor->eficiencia >= $this->configuracion['eficiencia_minima'],
                'puntualidad_ok' => $conductor->puntualidad >= $this->configuracion['puntualidad_minima'],
                'dias_descanso_ok' => $conductor->dias_acumulados < $this->configuracion['max_dias_sin_descanso'],
                'sin_suspensiones' => !$conductor->tiene_suspensiones_activas,
                'licencia_vigente' => $conductor->licencia_vigente
            ];

            $conductor->verificaciones_operatividad = $verificaciones;
            $conductor->operativo = !in_array(false, $verificaciones);

            return $conductor->operativo;
        });

        Log::info("✓ PASO 3 completado", [
            'conductores_operativos' => $conductoresOperativos->count(),
            'conductores_no_operativos' => $conductores->count() - $conductoresOperativos->count()
        ]);

        return $conductoresOperativos;
    }

    /**
     * PASO 4: Análisis BI de conductores (disponibilidad y origen)
     */
    private function paso4_AnalisisBIConductores($conductores)
    {
        Log::info('🧠 PASO 4: Análisis BI de conductores');

        $conductoresBI = $conductores->map(function($conductor) {
            // Determinar origen de disponibilidad
            $conductor->origen_disponibilidad = $this->determinarOrigenDisponibilidad($conductor);

            // Calcular score de compatibilidad para rutas
            $conductor->score_compatibilidad = $this->calcularScoreCompatibilidad($conductor);

            // Determinar disponibilidad para horarios específicos
            $conductor->disponibilidad_horarios = $this->analizarDisponibilidadHorarios($conductor);

            // Calcular métricas de rendimiento ponderadas
            $conductor->score_rendimiento = ($conductor->eficiencia * 0.6) + ($conductor->puntualidad * 0.4);

            return $conductor;
        })->sortByDesc('score_compatibilidad');

        Log::info("✓ PASO 4 completado", [
            'conductores_analizados' => $conductoresBI->count()
        ]);

        return $conductoresBI;
    }

    /**
     * PASO 5: Generación de pre-plantilla con sistema de frecuencias
     */
    private function paso5_GenerarPrePlantilla($conductores)
    {
        Log::info('📋 PASO 5: Generando pre-plantilla');

        // Obtener turnos que necesitan asignación para la fecha
        $turnosDisponibles = $this->obtenerTurnosDisponibles();

        $prePlantilla = [];
        $conductoresAsignados = collect();

        foreach ($turnosDisponibles as $turno) {
            // Buscar el mejor conductor para este turno
            $mejorConductor = $this->buscarMejorConductorParaTurno($turno, $conductores, $conductoresAsignados);

            if ($mejorConductor) {
                $asignacion = [
                    'turno_id' => $turno->id,
                    'conductor_id' => $mejorConductor->id,
                    'fecha_asignacion' => $this->fechaPlanificacion,
                    'score_compatibilidad' => $mejorConductor->score_compatibilidad,
                    'tipo_asignacion' => 'AUTOMATICA',
                    'observaciones' => 'Asignación automática basada en algoritmo de compatibilidad'
                ];

                $prePlantilla[] = $asignacion;
                $conductoresAsignados->push($mejorConductor);
                $this->metricas['asignaciones_realizadas']++;
            }
        }

        Log::info("✓ PASO 5 completado", [
            'turnos_procesados' => count($turnosDisponibles),
            'asignaciones_realizadas' => count($prePlantilla)
        ]);

        return $prePlantilla;
    }

    /**
     * PASO 6: Asignación de personal NAZCA para vacíos
     */
    private function paso6_AsignarPersonalNazca($prePlantilla)
    {
        Log::info('🎯 PASO 6: Asignando personal NAZCA');

        // Identificar turnos sin asignar (vacíos)
        $turnosSinAsignar = $this->identificarTurnosSinAsignar($prePlantilla);

        // Buscar conductores específicos para servicio NAZCA
        $conductoresNazca = Conductor::where('estado', 'DISPONIBLE')
            ->where('servicios_autorizados', 'LIKE', '%NAZCA%')
            ->where('origen', 'NAZCA')
            ->orderBy('score_general', 'desc')
            ->get();

        foreach ($turnosSinAsignar as $turno) {
            if ($turno->tipo_servicio === 'NAZCA' || $turno->destino === 'NAZCA') {
                $conductorNazca = $this->buscarMejorConductorNazca($turno, $conductoresNazca, $prePlantilla);

                if ($conductorNazca) {
                    $prePlantilla[] = [
                        'turno_id' => $turno->id,
                        'conductor_id' => $conductorNazca->id,
                        'fecha_asignacion' => $this->fechaPlanificacion,
                        'score_compatibilidad' => $conductorNazca->score_general,
                        'tipo_asignacion' => 'NAZCA_ESPECIALIZADA',
                        'observaciones' => 'Asignación especializada para servicio NAZCA'
                    ];
                }
            }
        }

        Log::info("✓ PASO 6 completado");

        return $prePlantilla;
    }

    /**
     * PASO 7: Generación de plantilla final
     */
    private function paso7_GenerarPlantillaFinal($prePlantilla)
    {
        Log::info('📝 PASO 7: Generando plantilla final');

        // Crear registro de plantilla
        $plantilla = Plantilla::create([
            'fecha_servicio' => $this->fechaPlanificacion,
            'tipo' => 'AUTOMATICA',
            'estado' => 'BORRADOR',
            'total_turnos' => count($prePlantilla),
            'created_by' => 1, // Sistema
            'observaciones' => 'Plantilla generada automáticamente por el sistema de planificación'
        ]);

        // Crear turnos de la plantilla
        foreach ($prePlantilla as $asignacion) {
            Turno::create([
                'plantilla_id' => $plantilla->id,
                'turno_id' => $asignacion['turno_id'],
                'conductor_id' => $asignacion['conductor_id'],
                'fecha_servicio' => $asignacion['fecha_asignacion'],
                'score_asignacion' => $asignacion['score_compatibilidad'],
                'tipo_asignacion' => $asignacion['tipo_asignacion'],
                'observaciones' => $asignacion['observaciones']
            ]);
        }

        Log::info("✓ PASO 7 completado", [
            'plantilla_id' => $plantilla->id,
            'turnos_asignados' => count($prePlantilla)
        ]);

        return $plantilla;
    }

    /**
     * PASO 8: Validaciones post generación
     */
    private function paso8_ValidacionesPost($plantilla)
    {
        Log::info('🔍 PASO 8: Ejecutando validaciones post generación');

        $validaciones = [];

        // Validación TIPO 1: Frescos con inicio antes de 12 p.m.
        $validaciones = array_merge($validaciones, $this->validarFrescosAntes12pm($plantilla));

        // Validación TIPO 2: MV con término antes de 9 a.m.
        $validaciones = array_merge($validaciones, $this->validarMVAntes9am($plantilla));

        // Validaciones adicionales del sistema
        $validaciones = array_merge($validaciones, $this->validacionesAdicionales($plantilla));

        // Crear registros de validación
        foreach ($validaciones as $validacionData) {
            $validacion = Validacion::create($validacionData);
            $this->metricas['validaciones_generadas']++;
        }

        Log::info("✓ PASO 8 completado", [
            'validaciones_generadas' => count($validaciones)
        ]);

        return $validaciones;
    }

    /**
     * PASO 9: Optimización iterativa
     */
    private function paso9_OptimizacionIterativa($plantilla, $validaciones)
    {
        Log::info('⚡ PASO 9: Ejecutando optimización iterativa');

        $iteracion = 0;
        $maxIteraciones = 3;
        $validacionesCriticas = collect($validaciones)->where('severidad', 'CRITICA');

        while ($validacionesCriticas->isNotEmpty() && $iteracion < $maxIteraciones) {
            $iteracion++;

            Log::info("   Iteración {$iteracion}: {$validacionesCriticas->count()} validaciones críticas");

            foreach ($validacionesCriticas as $validacion) {
                $this->resolverValidacionCritica($plantilla, $validacion);
            }

            // Re-evaluar validaciones
            $nuevasValidaciones = $this->paso8_ValidacionesPost($plantilla);
            $validacionesCriticas = collect($nuevasValidaciones)->where('severidad', 'CRITICA');
        }

        if ($validacionesCriticas->isNotEmpty()) {
            Log::warning("⚠️ Quedan {$validacionesCriticas->count()} validaciones críticas sin resolver");
        }

        Log::info("✓ PASO 9 completado", [
            'iteraciones_realizadas' => $iteracion,
            'validaciones_criticas_restantes' => $validacionesCriticas->count()
        ]);

        return $plantilla;
    }

    /**
     * PASO 10: Finalización y notificaciones
     */
    private function paso10_FinalizacionNotificaciones($plantilla)
    {
        Log::info('📤 PASO 10: Finalizando y enviando notificaciones');

        // Actualizar estado de la plantilla
        $plantilla->update([
            'estado' => 'GENERADA',
            'fecha_generacion' => now(),
            'metricas_generacion' => $this->metricas
        ]);

        // Preparar resumen para notificaciones
        $resumen = [
            'plantilla_id' => $plantilla->id,
            'fecha_servicio' => $plantilla->fecha_servicio,
            'total_turnos' => $plantilla->total_turnos,
            'conductores_asignados' => $plantilla->turnos->pluck('conductor_id')->unique()->count(),
            'validaciones_generadas' => $this->metricas['validaciones_generadas'],
            'tiempo_procesamiento' => now()->diffInSeconds($this->metricas['tiempo_inicio']),
            'estado' => 'COMPLETADO'
        ];

        // En producción, aquí se enviarían las notificaciones por email
        Log::info("📧 Notificaciones enviadas (simulado)");

        Log::info("✅ PASO 10 completado - Planificación finalizada", $resumen);

        return $resumen;
    }

    // =============================================================================
    // MÉTODOS AUXILIARES
    // =============================================================================

    private function calcularPrioridadAsignacion($conductor)
    {
        $prioridad = 0;

        // Menor cantidad de rutas cortas = mayor prioridad
        $prioridad += $conductor->ratio_rutas_mes * 10;

        // Mejor rendimiento = mayor prioridad (invertir para orden ascendente)
        $prioridad += (100 - $conductor->score_general) * 0.5;

        // Más días sin descanso = menor prioridad
        $prioridad += $conductor->dias_acumulados * 5;

        return round($prioridad, 2);
    }

    private function determinarOrigenDisponibilidad($conductor)
    {
        // Lógica para determinar desde dónde está disponible el conductor
        $ultimoServicio = $conductor->ultimoServicio();

        if ($ultimoServicio && $ultimoServicio->destino) {
            return $ultimoServicio->destino;
        }

        return $conductor->origen;
    }

    private function calcularScoreCompatibilidad($conductor)
    {
        $score = 0;

        // Factor eficiencia (30%)
        $score += ($conductor->eficiencia / 100) * 30;

        // Factor puntualidad (25%)
        $score += ($conductor->puntualidad / 100) * 25;

        // Factor experiencia (20%)
        $mesesExperiencia = Carbon::parse($conductor->fecha_ingreso)->diffInMonths(now());
        $score += min(20, $mesesExperiencia * 2);

        // Factor disponibilidad (15%)
        $score += $conductor->dias_acumulados < 5 ? 15 : 5;

        // Factor rutas recientes (10%)
        $score += $conductor->ratio_rutas_mes < 3 ? 10 : 0;

        return round($score, 2);
    }

    private function analizarDisponibilidadHorarios($conductor)
    {
        // Análisis de disponibilidad por franjas horarias
        return [
            'madrugada' => true, // 00:00 - 06:00
            'mañana' => true,    // 06:00 - 12:00
            'tarde' => true,     // 12:00 - 18:00
            'noche' => $conductor->autorizado_turnos_noche // 18:00 - 24:00
        ];
    }

    private function obtenerTurnosDisponibles()
    {
        // Simular turnos disponibles para la fecha
        // En producción, esto vendría de la base de datos de turnos programados
        return collect([
            (object)['id' => 1, 'hora_salida' => '06:00', 'destino' => 'LIMA', 'tipo_servicio' => 'ESTANDAR'],
            (object)['id' => 2, 'hora_salida' => '08:00', 'destino' => 'CALLAO', 'tipo_servicio' => 'ESTANDAR'],
            (object)['id' => 3, 'hora_salida' => '10:00', 'destino' => 'NAZCA', 'tipo_servicio' => 'NAZCA'],
            (object)['id' => 4, 'hora_salida' => '14:00', 'destino' => 'LIMA', 'tipo_servicio' => 'VIP']
        ]);
    }

    private function buscarMejorConductorParaTurno($turno, $conductores, $conductoresAsignados)
    {
        return $conductores
            ->whereNotIn('id', $conductoresAsignados->pluck('id'))
            ->filter(function($conductor) use ($turno) {
                // Verificar compatibilidad horaria
                $horaInicio = Carbon::parse($turno->hora_salida);
                $franjaHoraria = $this->obtenerFranjaHoraria($horaInicio);

                return $conductor->disponibilidad_horarios[$franjaHoraria] ?? true;
            })
            ->sortByDesc('score_compatibilidad')
            ->first();
    }

    private function obtenerFranjaHoraria($hora)
    {
        $hour = $hora->hour;

        if ($hour >= 0 && $hour < 6) return 'madrugada';
        if ($hour >= 6 && $hour < 12) return 'mañana';
        if ($hour >= 12 && $hour < 18) return 'tarde';
        return 'noche';
    }

    private function identificarTurnosSinAsignar($prePlantilla)
    {
        $turnosAsignados = collect($prePlantilla)->pluck('turno_id');
        $todosLosTurnos = $this->obtenerTurnosDisponibles();

        return $todosLosTurnos->whereNotIn('id', $turnosAsignados);
    }

    private function buscarMejorConductorNazca($turno, $conductoresNazca, $prePlantilla)
    {
        $conductoresAsignados = collect($prePlantilla)->pluck('conductor_id');

        return $conductoresNazca
            ->whereNotIn('id', $conductoresAsignados)
            ->first();
    }

    private function validarFrescosAntes12pm($plantilla)
    {
        $validaciones = [];

        foreach ($plantilla->turnos as $turno) {
            $horaSalida = Carbon::parse($turno->turno->hora_salida);
            $horaFin = Carbon::parse($turno->turno->hora_llegada);

            if ($turno->turno->numero_salidas == 1 && $horaFin->hour <= 12) {
                $validaciones[] = [
                    'tipo' => 'FRESCO_ANTES_12PM',
                    'severidad' => 'ADVERTENCIA',
                    'conductor_id' => $turno->conductor_id,
                    'plantilla_id' => $plantilla->id,
                    'descripcion' => 'Conductor fresco con término antes de 12 p.m. - Evaluar segunda salida',
                    'estado' => 'PENDIENTE',
                    'datos_adicionales' => [
                        'hora_fin_turno' => $horaFin->format('H:i'),
                        'puede_segunda_salida' => true
                    ]
                ];
            }
        }

        return $validaciones;
    }

    private function validarMVAntes9am($plantilla)
    {
        $validaciones = [];

        foreach ($plantilla->turnos as $turno) {
            $horaFin = Carbon::parse($turno->turno->hora_llegada);
            $conductor = $turno->conductor;

            if ($turno->turno->numero_salidas == 1 &&
                $horaFin->hour <= 9 &&
                $conductor->tuvo_servicio_ayer()) {

                $validaciones[] = [
                    'tipo' => 'MV_ANTES_9AM',
                    'severidad' => 'CRITICA',
                    'conductor_id' => $turno->conductor_id,
                    'plantilla_id' => $plantilla->id,
                    'descripcion' => 'Conductor MV con término antes de 9 a.m. - Verificar horas mínimas',
                    'estado' => 'PENDIENTE',
                    'datos_adicionales' => [
                        'hora_fin_turno' => $horaFin->format('H:i'),
                        'horas_acumuladas' => $conductor->calcular_horas_acumuladas()
                    ]
                ];
            }
        }

        return $validaciones;
    }

    private function validacionesAdicionales($plantilla)
    {
        $validaciones = [];

        // Validar conductores próximos a descanso obligatorio
        foreach ($plantilla->turnos as $turno) {
            if ($turno->conductor->dias_acumulados >= $this->configuracion['max_dias_sin_descanso'] - 1) {
                $validaciones[] = [
                    'tipo' => 'PROXIMO_DESCANSO',
                    'severidad' => 'ADVERTENCIA',
                    'conductor_id' => $turno->conductor_id,
                    'plantilla_id' => $plantilla->id,
                    'descripcion' => 'Conductor próximo a descanso obligatorio',
                    'estado' => 'PENDIENTE',
                    'datos_adicionales' => [
                        'dias_acumulados' => $turno->conductor->dias_acumulados
                    ]
                ];
            }
        }

        return $validaciones;
    }

    private function resolverValidacionCritica($plantilla, $validacion)
    {
        // Implementar lógica de resolución automática de validaciones críticas
        Log::info("🔧 Resolviendo validación crítica: {$validacion['tipo']}");

        // Por ahora, marcamos como revisada
        if ($validacion instanceof Validacion) {
            $validacion->update([
                'estado' => 'REVISADA',
                'observaciones' => 'Revisión automática del sistema de optimización'
            ]);
        }
    }

    private function registrarHistorial($estado, $resultado, $error = null)
    {
        HistorialPlanificacion::create([
            'fecha_planificacion' => $this->fechaPlanificacion,
            'estado' => $estado,
            'resultado' => $resultado,
            'error' => $error,
            'metricas' => $this->metricas,
            'configuracion_utilizada' => $this->configuracion,
            'created_by' => 1 // Sistema
        ]);
    }

    /**
     * Método público para obtener métricas de la última ejecución
     */
    public function obtenerMetricas()
    {
        return $this->metricas;
    }

    /**
     * Método público para actualizar configuración
     */
    public function actualizarConfiguracion($nuevaConfiguracion)
    {
        $this->configuracion = array_merge($this->configuracion, $nuevaConfiguracion);
    }
}
