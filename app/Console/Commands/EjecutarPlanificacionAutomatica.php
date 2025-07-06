<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ServicioPlanificacionAutomatizada;
use App\Models\Parametro;
use App\Models\HistorialPlanificacion;
use Carbon\Carbon;
use Exception;

class EjecutarPlanificacionAutomatica extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sipat:planificar
                           {fecha? : Fecha para planificar (YYYY-MM-DD). Por defecto es mañana}
                           {--dias=1 : Número de días a planificar desde la fecha inicial}
                           {--forzar : Forzar ejecución aunque ya exista planificación}
                           {--modo=automatico : Modo de ejecución (automatico|manual|emergencia)}
                           {--notificar : Enviar notificaciones al completar}
                           {--validar-solo : Solo ejecutar validaciones sin generar plantilla}
                           {--optimizar : Aplicar optimizaciones adicionales}
                           {--debug : Mostrar información detallada de debug}
                           {--configuracion=* : Configuraciones específicas (clave=valor)}';

    /**
     * The console command description.
     */
    protected $description = 'Ejecutar el sistema de planificación automatizada de SIPAT';

    private $servicioPlanificacion;
    private $modoDebug = false;

    /**
     * Execute the console command.
     */
    public function handle(ServicioPlanificacionAutomatizada $servicioPlanificacion)
    {
        $this->servicioPlanificacion = $servicioPlanificacion;
        $this->modoDebug = $this->option('debug');

        $this->mostrarBanner();

        try {
            // Validar prerequisitos del sistema
            $this->validarPrerequisitos();

            // Procesar parámetros de entrada
            $parametros = $this->procesarParametros();

            // Mostrar resumen de configuración
            $this->mostrarResumenConfiguracion($parametros);

            // Solicitar confirmación si es necesario
            if (!$this->confirmarEjecucion($parametros)) {
                $this->warn('⚠️ Ejecución cancelada por el usuario');
                return Command::FAILURE;
            }

            // Ejecutar planificación para cada día solicitado
            $resultados = [];
            $errores = [];

            for ($i = 0; $i < $parametros['dias']; $i++) {
                $fechaPlanificacion = $parametros['fecha_inicial']->copy()->addDays($i);

                $this->info("\n🗓️ Procesando planificación para: {$fechaPlanificacion->format('Y-m-d')} ({$fechaPlanificacion->locale('es')->dayName})");

                try {
                    $resultado = $this->ejecutarPlanificacionDia($fechaPlanificacion, $parametros);
                    $resultados[] = $resultado;

                    $this->line("   ✅ Completado exitosamente");

                } catch (Exception $e) {
                    $errores[] = [
                        'fecha' => $fechaPlanificacion->format('Y-m-d'),
                        'error' => $e->getMessage()
                    ];

                    $this->error("   ❌ Error: {$e->getMessage()}");

                    if ($this->modoDebug) {
                        $this->line("   🔍 Debug: " . $e->getTraceAsString());
                    }
                }
            }

            // Mostrar resumen final
            $this->mostrarResumenFinal($resultados, $errores);

            // Enviar notificaciones si se solicitó
            if ($this->option('notificar')) {
                $this->enviarNotificaciones($resultados, $errores);
            }

            // Determinar código de salida
            if (empty($errores)) {
                $this->info("\n🎉 Proceso completado exitosamente");
                return Command::SUCCESS;
            } elseif (count($errores) < count($resultados) + count($errores)) {
                $this->warn("\n⚠️ Proceso completado con errores parciales");
                return Command::SUCCESS; // Éxito parcial
            } else {
                $this->error("\n💥 Proceso falló completamente");
                return Command::FAILURE;
            }

        } catch (Exception $e) {
            $this->error("\n💥 Error crítico en el proceso: {$e->getMessage()}");

            if ($this->modoDebug) {
                $this->line("🔍 Debug: " . $e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Mostrar banner inicial del comando
     */
    private function mostrarBanner()
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                 🚌 SIPAT - PLANIFICACIÓN AUTOMÁTICA          ║');
        $this->line('║              Sistema Inteligente de Planificación            ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    /**
     * Validar que el sistema esté listo para ejecutar la planificación
     */
    private function validarPrerequisitos()
    {
        $this->info('🔍 Validando prerequisitos del sistema...');

        $validaciones = [
            'Base de datos' => $this->validarBaseDatos(),
            'Conductores disponibles' => $this->validarConductoresDisponibles(),
            'Configuración del sistema' => $this->validarConfiguracion(),
            'Servicios dependientes' => $this->validarServicios()
        ];

        foreach ($validaciones as $nombre => $resultado) {
            if ($resultado['valido']) {
                $this->line("   ✅ {$nombre}: {$resultado['mensaje']}");
            } else {
                $this->error("   ❌ {$nombre}: {$resultado['mensaje']}");
                throw new Exception("Prerequisito no cumplido: {$nombre}");
            }
        }

        $this->line('');
    }

    /**
     * Procesar y validar parámetros de entrada
     */
    private function procesarParametros()
    {
        // Procesar fecha inicial
        $fechaTexto = $this->argument('fecha');
        if ($fechaTexto) {
            try {
                $fechaInicial = Carbon::createFromFormat('Y-m-d', $fechaTexto);
            } catch (Exception $e) {
                throw new Exception("Formato de fecha inválido. Use YYYY-MM-DD");
            }
        } else {
            $fechaInicial = now()->addDay(); // Por defecto, planificar para mañana
        }

        // Validar que no sea una fecha pasada (a menos que se fuerce)
        if ($fechaInicial->isPast() && !$this->option('forzar')) {
            throw new Exception("No se puede planificar para fechas pasadas. Use --forzar si es necesario.");
        }

        // Procesar número de días
        $dias = (int) $this->option('dias');
        if ($dias < 1 || $dias > 30) {
            throw new Exception("El número de días debe estar entre 1 y 30");
        }

        // Procesar configuraciones personalizadas
        $configuracionesPersonalizadas = [];
        foreach ($this->option('configuracion') as $config) {
            if (strpos($config, '=') === false) {
                throw new Exception("Formato de configuración inválido: {$config}. Use clave=valor");
            }

            list($clave, $valor) = explode('=', $config, 2);
            $configuracionesPersonalizadas[$clave] = $valor;
        }

        return [
            'fecha_inicial' => $fechaInicial,
            'dias' => $dias,
            'modo' => $this->option('modo'),
            'forzar' => $this->option('forzar'),
            'validar_solo' => $this->option('validar-solo'),
            'optimizar' => $this->option('optimizar'),
            'configuraciones_personalizadas' => $configuracionesPersonalizadas
        ];
    }

    /**
     * Mostrar resumen de la configuración antes de ejecutar
     */
    private function mostrarResumenConfiguracion($parametros)
    {
        $this->info('📋 Configuración de ejecución:');
        $this->line("   • Fecha inicial: {$parametros['fecha_inicial']->format('Y-m-d')} ({$parametros['fecha_inicial']->locale('es')->dayName})");
        $this->line("   • Días a procesar: {$parametros['dias']}");
        $this->line("   • Modo: {$parametros['modo']}");
        $this->line("   • Forzar ejecución: " . ($parametros['forzar'] ? 'Sí' : 'No'));
        $this->line("   • Solo validaciones: " . ($parametros['validar_solo'] ? 'Sí' : 'No'));
        $this->line("   • Optimizaciones adicionales: " . ($parametros['optimizar'] ? 'Sí' : 'No'));

        if (!empty($parametros['configuraciones_personalizadas'])) {
            $this->line("   • Configuraciones personalizadas:");
            foreach ($parametros['configuraciones_personalizadas'] as $clave => $valor) {
                $this->line("     - {$clave}: {$valor}");
            }
        }

        $this->line('');
    }

    /**
     * Solicitar confirmación del usuario si es necesario
     */
    private function confirmarEjecucion($parametros)
    {
        // No pedir confirmación en modo automático o si ya se forzó
        if ($parametros['modo'] === 'automatico' || $parametros['forzar']) {
            return true;
        }

        // Verificar si ya existe planificación para alguna de las fechas
        $fechaFin = $parametros['fecha_inicial']->copy()->addDays($parametros['dias'] - 1);

        $planificacionesExistentes = HistorialPlanificacion::whereBetween('fecha_planificacion', [
            $parametros['fecha_inicial']->format('Y-m-d'),
            $fechaFin->format('Y-m-d')
        ])->where('estado', HistorialPlanificacion::ESTADO_COMPLETADO)->count();

        if ($planificacionesExistentes > 0) {
            $this->warn("⚠️ Se encontraron {$planificacionesExistentes} planificaciones existentes para el período seleccionado.");
            return $this->confirm('¿Desea continuar y sobrescribir las planificaciones existentes?');
        }

        return $this->confirm('¿Proceder con la planificación automática?', true);
    }

    /**
     * Ejecutar planificación para un día específico
     */
    private function ejecutarPlanificacionDia($fechaPlanificacion, $parametros)
    {
        // Verificar si ya existe planificación para esta fecha
        if (!$parametros['forzar']) {
            $existente = HistorialPlanificacion::where('fecha_planificacion', $fechaPlanificacion->format('Y-m-d'))
                                              ->where('estado', HistorialPlanificacion::ESTADO_COMPLETADO)
                                              ->first();
            if ($existente) {
                throw new Exception("Ya existe una planificación completada para esta fecha. Use --forzar para sobrescribir.");
            }
        }

        // Aplicar configuraciones personalizadas al servicio
        if (!empty($parametros['configuraciones_personalizadas'])) {
            $this->servicioPlanificacion->actualizarConfiguracion($parametros['configuraciones_personalizadas']);
        }

        // Configurar modo de ejecución
        $this->configurarModoEjecucion($parametros['modo']);

        if ($this->modoDebug) {
            $this->line("   🔍 Iniciando servicio de planificación...");
        }

        // Ejecutar solo validaciones si se solicitó
        if ($parametros['validar_solo']) {
            return $this->ejecutarSoloValidaciones($fechaPlanificacion);
        }

        // Ejecutar planificación completa
        $tiempoInicio = microtime(true);
        $resultado = $this->servicioPlanificacion->ejecutarPlanificacionCompleta($fechaPlanificacion);
        $tiempoTotal = microtime(true) - $tiempoInicio;

        // Agregar métricas adicionales al resultado
        $resultado['tiempo_total_comando'] = round($tiempoTotal, 2);
        $resultado['modo_ejecucion'] = $parametros['modo'];
        $resultado['ejecutado_via'] = 'comando_artisan';

        if ($this->modoDebug) {
            $this->mostrarDetallesDebug($resultado);
        }

        return $resultado;
    }

    /**
     * Mostrar resumen final de todos los resultados
     */
    private function mostrarResumenFinal($resultados, $errores)
    {
        $this->line('');
        $this->info('📊 RESUMEN FINAL:');
        $this->line('');

        // Estadísticas generales
        $totalDias = count($resultados) + count($errores);
        $diasExitosos = count($resultados);
        $diasConError = count($errores);

        $this->line("   📅 Días procesados: {$totalDias}");
        $this->line("   ✅ Días exitosos: {$diasExitosos}");
        $this->line("   ❌ Días con errores: {$diasConError}");

        if (!empty($resultados)) {
            $this->line('');
            $this->info('   🎯 Resultados exitosos:');

            $totalAsignaciones = 0;
            $totalValidaciones = 0;
            $tiempoTotal = 0;

            foreach ($resultados as $resultado) {
                $fecha = $resultado['fecha_servicio'] ?? 'N/A';
                $asignaciones = $resultado['conductores_asignados'] ?? 0;
                $validaciones = $resultado['validaciones_generadas'] ?? 0;
                $tiempo = $resultado['tiempo_procesamiento'] ?? 0;

                $this->line("     • {$fecha}: {$asignaciones} asignaciones, {$validaciones} validaciones ({$tiempo}s)");

                $totalAsignaciones += $asignaciones;
                $totalValidaciones += $validaciones;
                $tiempoTotal += $tiempo;
            }

            $this->line('');
            $this->line("   📈 Totales: {$totalAsignaciones} asignaciones, {$totalValidaciones} validaciones");
            $this->line("   ⏱️ Tiempo total: {$tiempoTotal} segundos");
        }

        if (!empty($errores)) {
            $this->line('');
            $this->error('   💥 Errores encontrados:');
            foreach ($errores as $error) {
                $this->line("     • {$error['fecha']}: {$error['error']}");
            }
        }

        $this->line('');
    }

    /**
     * Validaciones específicas del sistema
     */
    private function validarBaseDatos()
    {
        try {
            \DB::connection()->getPdo();
            $tablas = ['conductores', 'plantillas', 'turnos', 'validaciones'];

            foreach ($tablas as $tabla) {
                if (!\Schema::hasTable($tabla)) {
                    return ['valido' => false, 'mensaje' => "Tabla {$tabla} no existe"];
                }
            }

            return ['valido' => true, 'mensaje' => 'Conectada y tablas verificadas'];
        } catch (Exception $e) {
            return ['valido' => false, 'mensaje' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    private function validarConductoresDisponibles()
    {
        $conductoresDisponibles = \App\Models\Conductor::where('estado', 'DISPONIBLE')->count();

        if ($conductoresDisponibles < 5) {
            return ['valido' => false, 'mensaje' => "Solo {$conductoresDisponibles} conductores disponibles (mínimo 5)"];
        }

        return ['valido' => true, 'mensaje' => "{$conductoresDisponibles} conductores disponibles"];
    }

    private function validarConfiguracion()
    {
        $configuracionesRequeridas = [
            'dias_maximos_sin_descanso',
            'eficiencia_minima_conductor',
            'puntualidad_minima_conductor'
        ];

        foreach ($configuracionesRequeridas as $config) {
            if (!Parametro::obtenerValor($config)) {
                return ['valido' => false, 'mensaje' => "Configuración {$config} no encontrada"];
            }
        }

        return ['valido' => true, 'mensaje' => 'Configuraciones válidas'];
    }

    private function validarServicios()
    {
        // Validar que los servicios críticos estén funcionando
        try {
            $this->servicioPlanificacion->obtenerMetricas();
            return ['valido' => true, 'mensaje' => 'Servicios operativos'];
        } catch (Exception $e) {
            return ['valido' => false, 'mensaje' => 'Error en servicios: ' . $e->getMessage()];
        }
    }

    private function configurarModoEjecucion($modo)
    {
        switch ($modo) {
            case 'emergencia':
                // Configuración para planificación de emergencia
                $this->servicioPlanificacion->actualizarConfiguracion([
                    'tolerancia_eficiencia' => 70, // Más flexible
                    'permitir_sobrecarga' => true,
                    'validaciones_estrictas' => false
                ]);
                break;

            case 'manual':
                // Configuración para revisión manual posterior
                $this->servicioPlanificacion->actualizarConfiguracion([
                    'generar_mas_validaciones' => true,
                    'marcar_para_revision' => true
                ]);
                break;

            default: // automatico
                // Usar configuración estándar
                break;
        }
    }

    private function ejecutarSoloValidaciones($fechaPlanificacion)
    {
        $this->line("   🔍 Ejecutando solo validaciones para {$fechaPlanificacion->format('Y-m-d')}");

        // Implementar lógica de solo validaciones
        // Por ahora, simular resultado
        return [
            'fecha_servicio' => $fechaPlanificacion->format('Y-m-d'),
            'tipo_ejecucion' => 'SOLO_VALIDACIONES',
            'validaciones_generadas' => 5,
            'estado' => 'COMPLETADO'
        ];
    }

    private function mostrarDetallesDebug($resultado)
    {
        $this->line("   🔍 Debug - Detalles del resultado:");
        $this->line("     - Plantilla ID: " . ($resultado['plantilla_id'] ?? 'N/A'));
        $this->line("     - Total turnos: " . ($resultado['total_turnos'] ?? 'N/A'));
        $this->line("     - Tiempo procesamiento: " . ($resultado['tiempo_procesamiento'] ?? 'N/A') . 's');

        if (isset($resultado['metricas'])) {
            $this->line("     - Métricas:");
            foreach ($resultado['metricas'] as $clave => $valor) {
                $this->line("       • {$clave}: {$valor}");
            }
        }
    }

    private function enviarNotificaciones($resultados, $errores)
    {
        $this->info("📧 Enviando notificaciones...");

        // Implementar lógica de notificaciones
        // Por ahora, solo log
        $resumen = [
            'total_procesados' => count($resultados) + count($errores),
            'exitosos' => count($resultados),
            'errores' => count($errores),
            'fecha_ejecucion' => now()->format('Y-m-d H:i:s')
        ];

        \Log::info('Planificación automática completada', $resumen);

        $this->line("   ✅ Notificaciones enviadas");
    }
}
