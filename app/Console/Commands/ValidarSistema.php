<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\AuditoriaService;
use App\Services\NotificacionService;
use App\Services\CacheMetricasService;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\Turno;
use App\Models\Parametro;
use Carbon\Carbon;
use Exception;

class ValidarSistema extends Command
{
    /**
     * Signature del comando
     */
    protected $signature = 'sipat:validar
                            {--automatico : Ejecutar en modo automático sin interacción}
                            {--criticas-solo : Solo validar condiciones críticas}
                            {--generar-reporte : Generar reporte detallado}
                            {--enviar-notificaciones : Enviar notificaciones por email}
                            {--conductor= : Validar conductor específico por ID}
                            {--desde= : Fecha desde para validaciones (Y-m-d)}
                            {--hasta= : Fecha hasta para validaciones (Y-m-d)}';

    /**
     * Descripción del comando
     */
    protected $description = 'Ejecutar validaciones completas del sistema SIPAT';

    /**
     * Servicios
     */
    protected AuditoriaService $auditoriaService;
    protected NotificacionService $notificacionService;
    protected CacheMetricasService $cacheService;

    /**
     * Contadores de validaciones
     */
    protected array $contadores = [
        'total_verificadas' => 0,
        'criticas_detectadas' => 0,
        'advertencias_detectadas' => 0,
        'validaciones_creadas' => 0,
        'conductores_afectados' => 0,
        'turnos_verificados' => 0,
        'errores_encontrados' => 0
    ];

    /**
     * Tipos de validación disponibles
     */
    private const TIPOS_VALIDACION = [
        'conductores_descanso' => 'Validar conductores que necesitan descanso',
        'conductores_rendimiento' => 'Validar rendimiento de conductores',
        'turnos_solapamiento' => 'Validar solapamientos de turnos',
        'turnos_sin_conductor' => 'Validar turnos sin conductor asignado',
        'validaciones_vencidas' => 'Validar validaciones vencidas',
        'integridad_datos' => 'Validar integridad de datos',
        'configuracion_sistema' => 'Validar configuración del sistema',
        'recursos_sistema' => 'Validar recursos del sistema'
    ];

    public function __construct(
        AuditoriaService $auditoriaService,
        NotificacionService $notificacionService,
        CacheMetricasService $cacheService
    ) {
        parent::__construct();
        $this->auditoriaService = $auditoriaService;
        $this->notificacionService = $notificacionService;
        $this->cacheService = $cacheService;
    }

    /**
     * Ejecutar el comando
     */
    public function handle(): int
    {
        try {
            $tiempoInicio = microtime(true);

            $this->mostrarCabecera();

            // Registrar inicio de validación
            $this->auditoriaService->registrarEvento(
                'validacion_masiva_ejecutada',
                [
                    'tipo' => 'inicio',
                    'modo' => $this->option('automatico') ? 'automatico' : 'manual',
                    'opciones' => $this->options()
                ],
                null,
                'MEDIA'
            );

            // Ejecutar validaciones según las opciones
            if ($this->option('criticas-solo')) {
                $this->ejecutarValidacionesCriticas();
            } else {
                $this->ejecutarValidacionesCompletas();
            }

            $tiempoTotal = round((microtime(true) - $tiempoInicio), 2);

            // Mostrar resumen
            $this->mostrarResumen($tiempoTotal);

            // Generar reporte si se solicita
            if ($this->option('generar-reporte')) {
                $this->generarReporte($tiempoTotal);
            }

            // Enviar notificaciones si se solicita
            if ($this->option('enviar-notificaciones')) {
                $this->enviarNotificaciones();
            }

            // Registrar finalización
            $this->auditoriaService->registrarEvento(
                'validacion_masiva_ejecutada',
                [
                    'tipo' => 'completado',
                    'tiempo_total_segundos' => $tiempoTotal,
                    'contadores' => $this->contadores
                ],
                null,
                'MEDIA'
            );

            // Determinar código de salida
            if ($this->contadores['criticas_detectadas'] > 0) {
                return Command::FAILURE; // Hay problemas críticos
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Error durante la validación: " . $e->getMessage());
            Log::error("Error en ValidarSistema: " . $e->getMessage());

            $this->auditoriaService->registrarEvento(
                'validacion_masiva_ejecutada',
                [
                    'tipo' => 'error',
                    'error' => $e->getMessage()
                ],
                null,
                'CRITICA'
            );

            return Command::FAILURE;
        }
    }

    /**
     * Mostrar cabecera del comando
     */
    private function mostrarCabecera(): void
    {
        if (!$this->option('automatico')) {
            $this->info('');
            $this->info('🔍 SIPAT - Sistema de Validación Integral');
            $this->info('═══════════════════════════════════════');
            $this->info('Fecha: ' . now()->format('d/m/Y H:i:s'));
            $this->info('Modo: ' . ($this->option('automatico') ? 'Automático' : 'Manual'));
            $this->info('');
        }
    }

    /**
     * Ejecutar solo validaciones críticas
     */
    private function ejecutarValidacionesCriticas(): void
    {
        $this->info('🚨 Ejecutando validaciones críticas...');

        $this->validarConductoresDescanso();
        $this->validarTurnosSinConductor();
        $this->validarValidacionesVencidas();
        $this->validarRecursosSistema();
    }

    /**
     * Ejecutar validaciones completas
     */
    private function ejecutarValidacionesCompletas(): void
    {
        $this->info('🔍 Ejecutando validaciones completas...');

        foreach (self::TIPOS_VALIDACION as $metodo => $descripcion) {
            if (!$this->option('automatico')) {
                $this->info("📋 {$descripcion}...");
            }

            $metodoCamelCase = str_replace('_', '', ucwords($metodo, '_'));
            $metodoValidacion = 'validar' . $metodoCamelCase;

            if (method_exists($this, $metodoValidacion)) {
                try {
                    $this->$metodoValidacion();
                } catch (Exception $e) {
                    $this->error("Error en validación {$descripcion}: " . $e->getMessage());
                    $this->contadores['errores_encontrados']++;
                }
            }
        }
    }

    /**
     * Validar conductores que necesitan descanso
     */
    private function validarConductoresDescanso(): void
    {
        $limiteDias = sipat_config('dias_maximos_sin_descanso', 6);

        $conductoresCriticos = Conductor::where('dias_acumulados', '>=', $limiteDias)
            ->where('estado', '!=', 'DESCANSO FISICO')
            ->where('estado', '!=', 'DESCANSO SEMANAL')
            ->get();

        foreach ($conductoresCriticos as $conductor) {
            $this->contadores['total_verificadas']++;

            // Verificar si ya existe validación similar reciente
            $validacionExistente = Validacion::where('conductor_id', $conductor->id)
                ->where('tipo', 'DESCANSO_001')
                ->where('estado', 'PENDIENTE')
                ->where('created_at', '>=', now()->subHours(24))
                ->first();

            if (!$validacionExistente) {
                Validacion::create([
                    'conductor_id' => $conductor->id,
                    'tipo' => 'DESCANSO_001',
                    'descripcion' => "Conductor {$conductor->codigo} necesita descanso urgente - {$conductor->dias_acumulados} días consecutivos trabajados",
                    'severidad' => 'CRITICA',
                    'estado' => 'PENDIENTE',
                    'solucion_recomendada' => 'Asignar descanso inmediatamente y verificar turnos futuros',
                    'datos_adicionales' => json_encode([
                        'dias_acumulados' => $conductor->dias_acumulados,
                        'limite_maximo' => $limiteDias,
                        'validacion_automatica' => true,
                        'comando_validacion' => true
                    ])
                ]);

                $this->contadores['criticas_detectadas']++;
                $this->contadores['validaciones_creadas']++;
                $this->contadores['conductores_afectados']++;

                if (!$this->option('automatico')) {
                    $this->warn("  ⚠️  Conductor {$conductor->codigo}: {$conductor->dias_acumulados} días sin descanso");
                }
            }
        }
    }

    /**
     * Validar rendimiento de conductores
     */
    private function validarConductoresRendimiento(): void
    {
        $eficienciaMinima = sipat_config('eficiencia_minima_conductor', 80);
        $puntualidadMinima = sipat_config('puntualidad_minima_conductor', 85);

        // Conductores con eficiencia baja
        $conductoresBajaEficiencia = Conductor::where('eficiencia', '<', $eficienciaMinima)
            ->where('estado', 'DISPONIBLE')
            ->get();

        foreach ($conductoresBajaEficiencia as $conductor) {
            $this->contadores['total_verificadas']++;

            $validacionExistente = Validacion::where('conductor_id', $conductor->id)
                ->where('tipo', 'EFICIENCIA_002')
                ->where('estado', 'PENDIENTE')
                ->where('created_at', '>=', now()->subDays(7))
                ->first();

            if (!$validacionExistente) {
                Validacion::create([
                    'conductor_id' => $conductor->id,
                    'tipo' => 'EFICIENCIA_002',
                    'descripcion' => "Conductor {$conductor->codigo} tiene eficiencia por debajo del mínimo ({$conductor->eficiencia}% < {$eficienciaMinima}%)",
                    'severidad' => 'ADVERTENCIA',
                    'estado' => 'PENDIENTE',
                    'solucion_recomendada' => 'Programar capacitación y seguimiento de rendimiento',
                    'datos_adicionales' => json_encode([
                        'eficiencia_actual' => $conductor->eficiencia,
                        'eficiencia_minima' => $eficienciaMinima,
                        'validacion_automatica' => true
                    ])
                ]);

                $this->contadores['advertencias_detectadas']++;
                $this->contadores['validaciones_creadas']++;
            }
        }

        // Conductores con puntualidad baja
        $conductoresBajaPuntualidad = Conductor::where('puntualidad', '<', $puntualidadMinima)
            ->where('estado', 'DISPONIBLE')
            ->get();

        foreach ($conductoresBajaPuntualidad as $conductor) {
            $this->contadores['total_verificadas']++;

            $validacionExistente = Validacion::where('conductor_id', $conductor->id)
                ->where('tipo', 'PUNTUALIDAD_003')
                ->where('estado', 'PENDIENTE')
                ->where('created_at', '>=', now()->subDays(7))
                ->first();

            if (!$validacionExistente) {
                Validacion::create([
                    'conductor_id' => $conductor->id,
                    'tipo' => 'PUNTUALIDAD_003',
                    'descripcion' => "Conductor {$conductor->codigo} tiene puntualidad por debajo del mínimo ({$conductor->puntualidad}% < {$puntualidadMinima}%)",
                    'severidad' => 'ADVERTENCIA',
                    'estado' => 'PENDIENTE',
                    'solucion_recomendada' => 'Seguimiento de puntualidad y capacitación en gestión del tiempo',
                    'datos_adicionales' => json_encode([
                        'puntualidad_actual' => $conductor->puntualidad,
                        'puntualidad_minima' => $puntualidadMinima,
                        'validacion_automatica' => true
                    ])
                ]);

                $this->contadores['advertencias_detectadas']++;
                $this->contadores['validaciones_creadas']++;
            }
        }
    }

    /**
     * Validar solapamientos de turnos
     */
    private function validarTurnosSolapamiento(): void
    {
        $fechaDesde = $this->option('desde') ?
            Carbon::parse($this->option('desde')) :
            now()->startOfDay();

        $fechaHasta = $this->option('hasta') ?
            Carbon::parse($this->option('hasta')) :
            now()->addDays(7)->endOfDay();

        $turnos = Turno::whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->whereNotNull('conductor_id')
            ->where('estado', '!=', 'CANCELADO')
            ->orderBy('conductor_id')
            ->orderBy('fecha')
            ->orderBy('hora_salida')
            ->get();

        $turnosPorConductor = $turnos->groupBy('conductor_id');

        foreach ($turnosPorConductor as $conductorId => $turnosConductor) {
            for ($i = 0; $i < $turnosConductor->count() - 1; $i++) {
                $this->contadores['total_verificadas']++;

                $turnoActual = $turnosConductor[$i];
                $turnoSiguiente = $turnosConductor[$i + 1];

                // Solo verificar turnos del mismo día
                if ($turnoActual->fecha !== $turnoSiguiente->fecha) {
                    continue;
                }

                if ($this->hayeSolapamiento($turnoActual, $turnoSiguiente)) {
                    $validacionExistente = Validacion::where('conductor_id', $conductorId)
                        ->where('tipo', 'SOLAPAMIENTO_001')
                        ->where('estado', 'PENDIENTE')
                        ->whereJsonContains('datos_adicionales->turnos_solapados', $turnoActual->id)
                        ->first();

                    if (!$validacionExistente) {
                        Validacion::create([
                            'conductor_id' => $conductorId,
                            'tipo' => 'SOLAPAMIENTO_001',
                            'descripcion' => "Solapamiento de turnos detectado para conductor {$turnoActual->conductor->codigo}: {$turnoActual->codigo} y {$turnoSiguiente->codigo}",
                            'severidad' => 'CRITICA',
                            'estado' => 'PENDIENTE',
                            'solucion_recomendada' => 'Ajustar horarios de turnos o reasignar conductor',
                            'datos_adicionales' => json_encode([
                                'turnos_solapados' => [$turnoActual->id, $turnoSiguiente->id],
                                'fecha' => $turnoActual->fecha,
                                'validacion_automatica' => true
                            ])
                        ]);

                        $this->contadores['criticas_detectadas']++;
                        $this->contadores['validaciones_creadas']++;

                        if (!$this->option('automatico')) {
                            $this->warn("  ⚠️  Solapamiento: {$turnoActual->codigo} y {$turnoSiguiente->codigo}");
                        }
                    }
                }
            }
        }
    }

    /**
     * Verificar si hay solapamiento entre dos turnos
     */
    private function hayeSolapamiento(Turno $turno1, Turno $turno2): bool
    {
        $inicio1 = Carbon::parse($turno1->fecha . ' ' . $turno1->hora_salida);
        $fin1 = Carbon::parse($turno1->fecha . ' ' . $turno1->hora_llegada);

        $inicio2 = Carbon::parse($turno2->fecha . ' ' . $turno2->hora_salida);
        $fin2 = Carbon::parse($turno2->fecha . ' ' . $turno2->hora_llegada);

        // Si la llegada es menor que la salida, el turno termina al día siguiente
        if ($fin1->lt($inicio1)) {
            $fin1->addDay();
        }
        if ($fin2->lt($inicio2)) {
            $fin2->addDay();
        }

        return $inicio1->lt($fin2) && $fin1->gt($inicio2);
    }

    /**
     * Validar turnos sin conductor asignado
     */
    private function validarTurnosSinConductor(): void
    {
        $fechaLimite = now()->addDays(2); // Turnos en próximos 2 días

        $turnosSinConductor = Turno::whereNull('conductor_id')
            ->where('estado', 'PENDIENTE')
            ->where('fecha', '<=', $fechaLimite)
            ->where('fecha', '>=', now()->startOfDay())
            ->get();

        foreach ($turnosSinConductor as $turno) {
            $this->contadores['total_verificadas']++;
            $this->contadores['turnos_verificados']++;

            $validacionExistente = Validacion::where('tipo', 'ASIGNACION_002')
                ->where('estado', 'PENDIENTE')
                ->whereJsonContains('datos_adicionales->turno_id', $turno->id)
                ->first();

            if (!$validacionExistente) {
                $diasHastaFecha = Carbon::parse($turno->fecha)->diffInDays(now(), false);
                $severidad = $diasHastaFecha <= 0 ? 'CRITICA' : 'ADVERTENCIA';

                Validacion::create([
                    'tipo' => 'ASIGNACION_002',
                    'descripcion' => "Turno {$turno->codigo} sin conductor asignado (fecha: {$turno->fecha})",
                    'severidad' => $severidad,
                    'estado' => 'PENDIENTE',
                    'solucion_recomendada' => 'Asignar conductor disponible o ejecutar asignación automática',
                    'datos_adicionales' => json_encode([
                        'turno_id' => $turno->id,
                        'fecha_turno' => $turno->fecha,
                        'dias_hasta_fecha' => $diasHastaFecha,
                        'validacion_automatica' => true
                    ])
                ]);

                if ($severidad === 'CRITICA') {
                    $this->contadores['criticas_detectadas']++;
                } else {
                    $this->contadores['advertencias_detectadas']++;
                }
                $this->contadores['validaciones_creadas']++;

                if (!$this->option('automatico')) {
                    $mensaje = "  ⚠️  Turno sin conductor: {$turno->codigo} ({$turno->fecha})";
                    if ($severidad === 'CRITICA') {
                        $this->error($mensaje);
                    } else {
                        $this->warn($mensaje);
                    }
                }
            }
        }
    }

    /**
     * Validar validaciones vencidas
     */
    private function validarValidacionesVencidas(): void
    {
        $validacionesVencidas = Validacion::where('estado', 'PENDIENTE')
            ->where('fecha_limite', '<', now())
            ->get();

        foreach ($validacionesVencidas as $validacion) {
            $this->contadores['total_verificadas']++;

            $horasVencida = $validacion->fecha_limite->diffInHours(now());

            // Escalar validaciones vencidas hace más de 24 horas
            if ($horasVencida > 24 && $validacion->severidad !== 'CRITICA') {
                $validacion->update([
                    'severidad' => 'CRITICA',
                    'descripcion' => $validacion->descripcion . " [ESCALADA POR VENCIMIENTO: {$horasVencida}h]"
                ]);

                $this->contadores['criticas_detectadas']++;

                if (!$this->option('automatico')) {
                    $this->error("  🔥 Validación escalada: {$validacion->codigo} (vencida hace {$horasVencida}h)");
                }
            }
        }
    }

    /**
     * Validar integridad de datos
     */
    private function validarIntegridadDatos(): void
    {
        // Conductores con datos inconsistentes
        $conductoresInconsistentes = Conductor::where('score_general', '!=',
            DB::raw('(eficiencia + puntualidad) / 2'))
            ->get();

        foreach ($conductoresInconsistentes as $conductor) {
            $scoreCalculado = ($conductor->eficiencia + $conductor->puntualidad) / 2;
            $conductor->update(['score_general' => $scoreCalculado]);
            $this->contadores['total_verificadas']++;
        }

        // Turnos con duración inconsistente
        $turnosInconsistentes = Turno::whereNotNull('hora_salida')
            ->whereNotNull('hora_llegada')
            ->where('horas_estimadas', '!=',
                DB::raw('TIME_TO_SEC(TIMEDIFF(hora_llegada, hora_salida)) / 3600'))
            ->get();

        foreach ($turnosInconsistentes as $turno) {
            $duracionCorrecta = $this->calcularDuracionHoras($turno->hora_salida, $turno->hora_llegada);
            $turno->update(['horas_estimadas' => $duracionCorrecta]);
            $this->contadores['total_verificadas']++;
        }
    }

    /**
     * Calcular duración en horas
     */
    private function calcularDuracionHoras(string $horaSalida, string $horaLlegada): float
    {
        try {
            $salida = Carbon::createFromFormat('H:i:s', $horaSalida);
            $llegada = Carbon::createFromFormat('H:i:s', $horaLlegada);

            if ($llegada->lt($salida)) {
                $llegada->addDay();
            }

            return $salida->diffInMinutes($llegada) / 60;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Validar configuración del sistema
     */
    private function validarConfiguracionSistema(): void
    {
        $parametrosRequeridos = [
            'dias_maximos_sin_descanso',
            'eficiencia_minima_conductor',
            'puntualidad_minima_conductor',
            'horas_minimas_descanso'
        ];

        foreach ($parametrosRequeridos as $parametro) {
            $this->contadores['total_verificadas']++;

            $valor = Parametro::where('clave', $parametro)->first();

            if (!$valor) {
                if (!$this->option('automatico')) {
                    $this->error("  ❌ Parámetro faltante: {$parametro}");
                }
                $this->contadores['errores_encontrados']++;
            }
        }
    }

    /**
     * Validar recursos del sistema
     */
    private function validarRecursosSistema(): void
    {
        $this->contadores['total_verificadas']++;

        // Memoria
        $memoriaUsada = memory_get_usage(true);
        $memoriaLimite = $this->obtenerLimiteMemoria();
        $porcentajeMemoria = ($memoriaUsada / $memoriaLimite) * 100;

        if ($porcentajeMemoria > 90) {
            $this->contadores['criticas_detectadas']++;
            if (!$this->option('automatico')) {
                $this->error("  🔥 Memoria crítica: {$porcentajeMemoria}%");
            }
        } elseif ($porcentajeMemoria > 80) {
            $this->contadores['advertencias_detectadas']++;
            if (!$this->option('automatico')) {
                $this->warn("  ⚠️  Memoria alta: {$porcentajeMemoria}%");
            }
        }

        // Base de datos
        try {
            $tiempoInicio = microtime(true);
            DB::select('SELECT 1');
            $tiempoRespuesta = (microtime(true) - $tiempoInicio) * 1000;

            if ($tiempoRespuesta > 2000) {
                $this->contadores['advertencias_detectadas']++;
                if (!$this->option('automatico')) {
                    $this->warn("  ⚠️  BD lenta: {$tiempoRespuesta}ms");
                }
            }
        } catch (Exception $e) {
            $this->contadores['criticas_detectadas']++;
            if (!$this->option('automatico')) {
                $this->error("  🔥 Error BD: " . $e->getMessage());
            }
        }
    }

    /**
     * Obtener límite de memoria
     */
    private function obtenerLimiteMemoria(): int
    {
        $limite = ini_get('memory_limit');
        if ($limite === '-1') return 2 * 1024 * 1024 * 1024;

        $valor = (int) $limite;
        $unidad = strtolower(substr($limite, -1));

        return match($unidad) {
            'g' => $valor * 1024 * 1024 * 1024,
            'm' => $valor * 1024 * 1024,
            'k' => $valor * 1024,
            default => $valor
        };
    }

    /**
     * Mostrar resumen de validaciones
     */
    private function mostrarResumen(float $tiempoTotal): void
    {
        if ($this->option('automatico')) {
            return;
        }

        $this->info('');
        $this->info('📊 RESUMEN DE VALIDACIONES');
        $this->info('═══════════════════════════');
        $this->info("⏱️  Tiempo total: {$tiempoTotal}s");
        $this->info("🔍 Total verificadas: {$this->contadores['total_verificadas']}");
        $this->info("🔥 Críticas detectadas: {$this->contadores['criticas_detectadas']}");
        $this->info("⚠️  Advertencias detectadas: {$this->contadores['advertencias_detectadas']}");
        $this->info("✅ Validaciones creadas: {$this->contadores['validaciones_creadas']}");
        $this->info("👥 Conductores afectados: {$this->contadores['conductores_afectados']}");
        $this->info("🚌 Turnos verificados: {$this->contadores['turnos_verificados']}");

        if ($this->contadores['errores_encontrados'] > 0) {
            $this->error("❌ Errores encontrados: {$this->contadores['errores_encontrados']}");
        }

        $this->info('');

        if ($this->contadores['criticas_detectadas'] > 0) {
            $this->error('🚨 ATENCIÓN: Se detectaron problemas críticos que requieren atención inmediata');
        } elseif ($this->contadores['advertencias_detectadas'] > 0) {
            $this->warn('⚠️  Se detectaron advertencias que deben ser revisadas');
        } else {
            $this->info('✅ Sistema en buen estado - No se detectaron problemas');
        }
    }

    /**
     * Generar reporte detallado
     */
    private function generarReporte(float $tiempoTotal): void
    {
        try {
            $nombreArchivo = 'validacion_sistema_' . now()->format('Y-m-d_H-i-s') . '.json';
            $rutaArchivo = storage_path('app/reportes/' . $nombreArchivo);

            if (!file_exists(dirname($rutaArchivo))) {
                mkdir(dirname($rutaArchivo), 0755, true);
            }

            $reporte = [
                'metadata' => [
                    'fecha_ejecucion' => now()->toISOString(),
                    'tiempo_total_segundos' => $tiempoTotal,
                    'modo' => $this->option('automatico') ? 'automatico' : 'manual',
                    'opciones' => $this->options()
                ],
                'contadores' => $this->contadores,
                'validaciones_criticas' => Validacion::where('severidad', 'CRITICA')
                    ->where('estado', 'PENDIENTE')
                    ->with('conductor')
                    ->get()->toArray(),
                'estado_sistema' => [
                    'memoria_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'memoria_pico_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'conductores_totales' => Conductor::count(),
                    'conductores_disponibles' => Conductor::where('estado', 'DISPONIBLE')->count(),
                    'turnos_pendientes' => Turno::where('estado', 'PENDIENTE')->count(),
                    'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count()
                ]
            ];

            file_put_contents($rutaArchivo, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("📄 Reporte generado: {$rutaArchivo}");

        } catch (Exception $e) {
            $this->error("Error generando reporte: " . $e->getMessage());
        }
    }

    /**
     * Enviar notificaciones
     */
    private function enviarNotificaciones(): void
    {
        if ($this->contadores['criticas_detectadas'] > 0) {
            try {
                $this->notificacionService->enviarNotificacion(
                    'SISTEMA_ALERTA',
                    'Validaciones Críticas Detectadas',
                    "Se han detectado {$this->contadores['criticas_detectadas']} validación(es) crítica(s) durante la validación automática del sistema.",
                    [
                        'contadores' => $this->contadores,
                        'fecha_validacion' => now()->toISOString()
                    ],
                    null,
                    'CRITICA'
                );

                $this->info("📧 Notificaciones enviadas");
            } catch (Exception $e) {
                $this->error("Error enviando notificaciones: " . $e->getMessage());
            }
        }
    }
}
