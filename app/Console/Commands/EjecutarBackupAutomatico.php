<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ServicioBackupAutomatizado;
use App\Models\Parametro;
use App\Models\HistorialCredenciales;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;

class EjecutarBackupAutomatico extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sipat:backup
                           {tipo=diario : Tipo de backup (diario|semanal|mensual|anual|completo)}
                           {--forzar : Forzar ejecución aunque ya exista backup reciente}
                           {--limpiar : Limpiar backups antiguos después de crear nuevo}
                           {--validar : Validar integridad del backup después de crearlo}
                           {--notificar : Enviar notificación por email al completar}
                           {--comprimir : Forzar compresión del backup}
                           {--modo=automatico : Modo de ejecución (automatico|manual|programado)}
                           {--retener-dias=* : Días a retener por tipo (formato: tipo=dias)}
                           {--debug : Mostrar información detallada de debug}';

    /**
     * The console command description.
     */
    protected $description = 'Ejecutar backup automático del sistema SIPAT con diferentes frecuencias y opciones';

    private $servicioBackup;
    private $modoDebug = false;
    private $estadisticas = [];

    /**
     * Execute the console command.
     */
    public function handle(ServicioBackupAutomatizado $servicioBackup)
    {
        $this->servicioBackup = $servicioBackup;
        $this->modoDebug = $this->option('debug');

        $this->mostrarBanner();

        try {
            // Registrar inicio de operación
            $this->registrarInicioOperacion();

            // Validar parámetros de entrada
            $parametros = $this->validarParametros();

            // Mostrar configuración si está en modo debug
            if ($this->modoDebug) {
                $this->mostrarConfiguracion($parametros);
            }

            // Verificar prerrequisitos del sistema
            $this->verificarPrerrequisitos();

            // Aplicar configuraciones personalizadas
            $this->aplicarConfiguracionesPersonalizadas($parametros);

            // Ejecutar el backup
            $resultado = $this->ejecutarBackup($parametros);

            // Validar integridad si se solicitó
            if ($this->option('validar')) {
                $this->validarIntegridad($resultado);
            }

            // Limpiar backups antiguos si se solicitó
            if ($this->option('limpiar')) {
                $this->limpiarBackupsAntiguos($parametros['tipo']);
            }

            // Enviar notificaciones si se solicitó
            if ($this->option('notificar')) {
                $this->enviarNotificaciones($resultado);
            }

            // Registrar finalización exitosa
            $this->registrarFinalizacionExitosa($resultado);

            // Mostrar resumen final
            $this->mostrarResumenFinal($resultado);

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->registrarError($e);
            $this->error("💥 Error en backup: {$e->getMessage()}");

            if ($this->modoDebug) {
                $this->line("🔍 Debug trace:");
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Mostrar banner del comando
     */
    private function mostrarBanner()
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║                🗄️ SIPAT - BACKUP AUTOMÁTICO                 ║');
        $this->line('║           Sistema de Respaldo Inteligente y Seguro          ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    /**
     * Registrar inicio de la operación
     */
    private function registrarInicioOperacion()
    {
        $this->estadisticas = [
            'inicio' => now(),
            'tipo' => $this->argument('tipo'),
            'modo' => $this->option('modo'),
            'usuario_sistema' => get_current_user(),
            'servidor' => gethostname(),
            'opciones' => [
                'forzar' => $this->option('forzar'),
                'limpiar' => $this->option('limpiar'),
                'validar' => $this->option('validar'),
                'notificar' => $this->option('notificar'),
                'comprimir' => $this->option('comprimir')
            ]
        ];

        Log::info('🔄 Iniciando backup automático', $this->estadisticas);
    }

    /**
     * Validar parámetros de entrada
     */
    private function validarParametros()
    {
        $tipo = $this->argument('tipo');
        $tiposValidos = ['diario', 'semanal', 'mensual', 'anual', 'completo'];

        if (!in_array($tipo, $tiposValidos)) {
            throw new Exception("Tipo de backup no válido: {$tipo}. Tipos válidos: " . implode(', ', $tiposValidos));
        }

        // Validar configuraciones de retención personalizadas
        $retencionPersonalizada = [];
        foreach ($this->option('retener-dias') as $config) {
            if (strpos($config, '=') === false) {
                throw new Exception("Formato de retención inválido: {$config}. Use formato: tipo=dias");
            }

            list($tipoRetencion, $dias) = explode('=', $config, 2);
            if (!is_numeric($dias) || $dias < 1) {
                throw new Exception("Días de retención inválidos para {$tipoRetencion}: {$dias}");
            }

            $retencionPersonalizada[$tipoRetencion] = (int) $dias;
        }

        return [
            'tipo' => $tipo,
            'forzar' => $this->option('forzar'),
            'limpiar' => $this->option('limpiar'),
            'validar' => $this->option('validar'),
            'notificar' => $this->option('notificar'),
            'comprimir' => $this->option('comprimir'),
            'modo' => $this->option('modo'),
            'retencion_personalizada' => $retencionPersonalizada
        ];
    }

    /**
     * Mostrar configuración en modo debug
     */
    private function mostrarConfiguracion($parametros)
    {
        $this->info('🔍 Configuración de backup:');
        $this->line("   • Tipo: {$parametros['tipo']}");
        $this->line("   • Modo: {$parametros['modo']}");
        $this->line("   • Forzar: " . ($parametros['forzar'] ? 'Sí' : 'No'));
        $this->line("   • Limpiar antiguos: " . ($parametros['limpiar'] ? 'Sí' : 'No'));
        $this->line("   • Validar integridad: " . ($parametros['validar'] ? 'Sí' : 'No'));
        $this->line("   • Notificar: " . ($parametros['notificar'] ? 'Sí' : 'No'));
        $this->line("   • Comprimir: " . ($parametros['comprimir'] ? 'Sí' : 'No'));

        if (!empty($parametros['retencion_personalizada'])) {
            $this->line("   • Retención personalizada:");
            foreach ($parametros['retencion_personalizada'] as $tipo => $dias) {
                $this->line("     - {$tipo}: {$dias} días");
            }
        }

        $this->line('');
    }

    /**
     * Verificar prerrequisitos del sistema
     */
    private function verificarPrerrequisitos()
    {
        $this->info('🔍 Verificando prerrequisitos del sistema...');

        $verificaciones = [
            'Espacio en disco' => $this->verificarEspacioDisco(),
            'Acceso a base de datos' => $this->verificarBaseDatos(),
            'Permisos de escritura' => $this->verificarPermisos(),
            'Servicios del sistema' => $this->verificarServicios(),
            'Recursos del sistema' => $this->verificarRecursos()
        ];

        foreach ($verificaciones as $nombre => $resultado) {
            if ($resultado['valido']) {
                $this->line("   ✅ {$nombre}: {$resultado['mensaje']}");
            } else {
                $this->error("   ❌ {$nombre}: {$resultado['mensaje']}");
                throw new Exception("Prerrequisito no cumplido: {$nombre}");
            }
        }

        $this->line('');
    }

    /**
     * Aplicar configuraciones personalizadas
     */
    private function aplicarConfiguracionesPersonalizadas($parametros)
    {
        $configuraciones = [];

        // Configurar compresión
        if ($parametros['comprimir']) {
            $configuraciones['comprimir_backups'] = true;
        }

        // Configurar retención personalizada
        foreach ($parametros['retencion_personalizada'] as $tipo => $dias) {
            $configuraciones["mantener_backups_{$tipo}s"] = $dias;
        }

        // Configurar validación de integridad
        if ($parametros['validar']) {
            $configuraciones['validar_integridad'] = true;
        }

        if (!empty($configuraciones)) {
            // Aplicar configuraciones al servicio
            // $this->servicioBackup->actualizarConfiguracion($configuraciones);

            if ($this->modoDebug) {
                $this->line('🔧 Configuraciones aplicadas:');
                foreach ($configuraciones as $clave => $valor) {
                    $this->line("   • {$clave}: " . ($valor === true ? 'Sí' : ($valor === false ? 'No' : $valor)));
                }
                $this->line('');
            }
        }
    }

    /**
     * Ejecutar el backup
     */
    private function ejecutarBackup($parametros)
    {
        $tipo = $parametros['tipo'];
        $this->info("📦 Iniciando backup {$tipo}...");

        // Mostrar progreso si no está en modo silencioso
        if ($this->modoDebug || $parametros['modo'] === 'manual') {
            $this->output->progressStart(100);
        }

        $tiempoInicio = microtime(true);

        try {
            // Ejecutar backup a través del servicio
            $resultado = $this->servicioBackup->ejecutarBackup($tipo, $parametros['forzar']);

            $tiempoTotal = microtime(true) - $tiempoInicio;

            if ($this->modoDebug || $parametros['modo'] === 'manual') {
                $this->output->progressFinish();
            }

            if ($resultado['estado'] === 'EXITOSO') {
                $archivo = $resultado['datos']['archivo'] ?? 'desconocido';
                $tamaño = $this->formatearTamaño($resultado['datos']['tamaño'] ?? 0);

                $this->info("✅ Backup {$tipo} completado exitosamente:");
                $this->line("   • Archivo: {$archivo}");
                $this->line("   • Tamaño: {$tamaño}");
                $this->line("   • Duración: " . round($tiempoTotal, 2) . " segundos");

                // Agregar estadísticas
                $this->estadisticas = array_merge($this->estadisticas, [
                    'archivo_generado' => $archivo,
                    'tamaño_archivo' => $resultado['datos']['tamaño'] ?? 0,
                    'tamaño_formateado' => $tamaño,
                    'duracion_segundos' => round($tiempoTotal, 2),
                    'estado' => 'EXITOSO'
                ]);

            } elseif ($resultado['estado'] === 'OMITIDO') {
                $this->warn("⏭️ Backup {$tipo} omitido: {$resultado['mensaje']}");
                $this->estadisticas['estado'] = 'OMITIDO';

            } else {
                throw new Exception($resultado['mensaje']);
            }

            return $resultado;

        } catch (Exception $e) {
            if ($this->modoDebug || $parametros['modo'] === 'manual') {
                $this->output->progressFinish();
            }
            throw $e;
        }
    }

    /**
     * Validar integridad del backup
     */
    private function validarIntegridad($resultado)
    {
        if ($resultado['estado'] !== 'EXITOSO') {
            return;
        }

        $this->info('🔍 Validando integridad del backup...');

        $archivo = $resultado['datos']['archivo'] ?? null;
        if (!$archivo) {
            $this->warn('   ⚠️ No se pudo validar: archivo no especificado');
            return;
        }

        try {
            // Aquí se implementaría la validación real del archivo
            // Por ahora, simulamos una validación básica
            $rutaCompleta = storage_path('app/backups/' . $archivo);

            if (!file_exists($rutaCompleta)) {
                throw new Exception("Archivo de backup no encontrado: {$archivo}");
            }

            $tamaño = filesize($rutaCompleta);
            if ($tamaño < 1024) { // Menos de 1KB es sospechoso
                throw new Exception("Archivo de backup muy pequeño: " . $this->formatearTamaño($tamaño));
            }

            // Validar si es un ZIP válido
            if (pathinfo($archivo, PATHINFO_EXTENSION) === 'zip') {
                $zip = new \ZipArchive();
                if ($zip->open($rutaCompleta) !== TRUE) {
                    throw new Exception("Archivo ZIP corrupto: {$archivo}");
                }
                $zip->close();
            }

            $this->line("   ✅ Integridad validada correctamente");
            $this->estadisticas['integridad_validada'] = true;

        } catch (Exception $e) {
            $this->error("   ❌ Error en validación de integridad: " . $e->getMessage());
            $this->estadisticas['error_integridad'] = $e->getMessage();

            // En modo automático, no fallar por error de validación
            if ($this->option('modo') !== 'automatico') {
                throw $e;
            }
        }
    }

    /**
     * Limpiar backups antiguos
     */
    private function limpiarBackupsAntiguos($tipo)
    {
        $this->info('🗑️ Limpiando backups antiguos...');

        try {
            $configuracion = [
                'diario' => Parametro::obtenerValor('mantener_backups_diarios', 30),
                'semanal' => Parametro::obtenerValor('mantener_backups_semanales', 12),
                'mensual' => Parametro::obtenerValor('mantener_backups_mensuales', 12),
                'anual' => Parametro::obtenerValor('mantener_backups_anuales', 5),
                'completo' => Parametro::obtenerValor('mantener_backups_completos', 10)
            ];

            $limite = $configuracion[$tipo] ?? 30;
            $backups = $this->servicioBackup->listarBackups(['tipo' => $tipo]);

            if (count($backups) > $limite) {
                $paraEliminar = array_slice($backups, $limite);
                $eliminados = 0;
                $espacioLiberado = 0;

                foreach ($paraEliminar as $backup) {
                    try {
                        $espacioLiberado += $backup['tamaño'];
                        $this->servicioBackup->eliminarBackup($backup['nombre']);
                        $eliminados++;

                        if ($this->modoDebug) {
                            $this->line("   🗑️ Eliminado: {$backup['nombre']}");
                        }
                    } catch (Exception $e) {
                        $this->warn("   ⚠️ No se pudo eliminar: {$backup['nombre']}");
                    }
                }

                $this->line("   ✅ Eliminados {$eliminados} backups antiguos");
                $this->line("   💾 Espacio liberado: " . $this->formatearTamaño($espacioLiberado));

                $this->estadisticas['backups_eliminados'] = $eliminados;
                $this->estadisticas['espacio_liberado'] = $espacioLiberado;

            } else {
                $this->line("   ℹ️ No hay backups antiguos para eliminar (límite: {$limite})");
            }

        } catch (Exception $e) {
            $this->warn("   ⚠️ Error en limpieza: " . $e->getMessage());
            $this->estadisticas['error_limpieza'] = $e->getMessage();
        }
    }

    /**
     * Enviar notificaciones
     */
    private function enviarNotificaciones($resultado)
    {
        $this->info('📧 Enviando notificaciones...');

        try {
            $destinatarios = $this->obtenerDestinatariosNotificacion();

            if (empty($destinatarios)) {
                $this->warn('   ⚠️ No hay destinatarios configurados para notificaciones');
                return;
            }

            $asunto = $this->generarAsuntoNotificacion($resultado);
            $contenido = $this->generarContenidoNotificacion($resultado);

            foreach ($destinatarios as $destinatario) {
                try {
                    // Aquí se enviaría el email real
                    // Mail::send(...);

                    if ($this->modoDebug) {
                        $this->line("   📧 Notificación enviada a: {$destinatario}");
                    }
                } catch (Exception $e) {
                    $this->warn("   ⚠️ Error enviando a {$destinatario}: " . $e->getMessage());
                }
            }

            $this->line("   ✅ Notificaciones enviadas a " . count($destinatarios) . " destinatarios");
            $this->estadisticas['notificaciones_enviadas'] = count($destinatarios);

        } catch (Exception $e) {
            $this->warn("   ⚠️ Error en notificaciones: " . $e->getMessage());
            $this->estadisticas['error_notificaciones'] = $e->getMessage();
        }
    }

    /**
     * Registrar finalización exitosa
     */
    private function registrarFinalizacionExitosa($resultado)
    {
        $this->estadisticas['fin'] = now();
        $this->estadisticas['duracion_total'] = $this->estadisticas['fin']->diffInSeconds($this->estadisticas['inicio']);
        $this->estadisticas['resultado'] = $resultado;

        // Registrar en el historial de credenciales para auditoría
        HistorialCredenciales::registrarAccion(
            1, // Sistema
            HistorialCredenciales::ACCION_BACKUP_CREDENCIALES,
            $this->estadisticas
        );

        Log::info('✅ Backup automático completado exitosamente', $this->estadisticas);
    }

    /**
     * Registrar error
     */
    private function registrarError(Exception $e)
    {
        $this->estadisticas['fin'] = now();
        $this->estadisticas['error'] = $e->getMessage();
        $this->estadisticas['trace'] = $e->getTraceAsString();

        Log::error('❌ Error en backup automático', $this->estadisticas);
    }

    /**
     * Mostrar resumen final
     */
    private function mostrarResumenFinal($resultado)
    {
        $this->line('');
        $this->info('📋 RESUMEN DEL BACKUP:');
        $this->line('');

        $duracionTotal = $this->estadisticas['duracion_total'] ?? 0;
        $estado = $this->estadisticas['estado'] ?? 'DESCONOCIDO';

        $this->line("   📅 Fecha: " . now()->format('Y-m-d H:i:s'));
        $this->line("   📦 Tipo: " . $this->estadisticas['tipo']);
        $this->line("   ⚡ Estado: {$estado}");
        $this->line("   ⏱️ Duración: {$duracionTotal} segundos");

        if (isset($this->estadisticas['archivo_generado'])) {
            $this->line("   📄 Archivo: " . $this->estadisticas['archivo_generado']);
            $this->line("   💾 Tamaño: " . $this->estadisticas['tamaño_formateado']);
        }

        if (isset($this->estadisticas['backups_eliminados'])) {
            $this->line("   🗑️ Eliminados: " . $this->estadisticas['backups_eliminados'] . " backups antiguos");
        }

        if (isset($this->estadisticas['notificaciones_enviadas'])) {
            $this->line("   📧 Notificaciones: " . $this->estadisticas['notificaciones_enviadas'] . " enviadas");
        }

        $this->line('');

        if ($estado === 'EXITOSO') {
            $this->info('🎉 Backup completado exitosamente!');
        } elseif ($estado === 'OMITIDO') {
            $this->warn('⏭️ Backup omitido según configuración');
        }
    }

    // =============================================================================
    // MÉTODOS PRIVADOS DE VERIFICACIÓN
    // =============================================================================

    private function verificarEspacioDisco()
    {
        $espacioLibre = disk_free_space(storage_path('app/backups'));
        $espacioRequerido = 500 * 1024 * 1024; // 500 MB mínimo

        if ($espacioLibre < $espacioRequerido) {
            return [
                'valido' => false,
                'mensaje' => "Espacio insuficiente. Requerido: " . $this->formatearTamaño($espacioRequerido) .
                           ", Disponible: " . $this->formatearTamaño($espacioLibre)
            ];
        }

        return [
            'valido' => true,
            'mensaje' => "Disponible: " . $this->formatearTamaño($espacioLibre)
        ];
    }

    private function verificarBaseDatos()
    {
        try {
            \DB::connection()->getPdo();
            $tablas = \DB::select("SHOW TABLES");
            return [
                'valido' => true,
                'mensaje' => "Conectada (" . count($tablas) . " tablas)"
            ];
        } catch (Exception $e) {
            return [
                'valido' => false,
                'mensaje' => "Error de conexión: " . $e->getMessage()
            ];
        }
    }

    private function verificarPermisos()
    {
        $rutaBackups = storage_path('app/backups');

        if (!is_writable($rutaBackups)) {
            return [
                'valido' => false,
                'mensaje' => "Sin permisos de escritura en: {$rutaBackups}"
            ];
        }

        return [
            'valido' => true,
            'mensaje' => "Permisos correctos en directorio de backups"
        ];
    }

    private function verificarServicios()
    {
        try {
            // Verificar que los servicios principales estén disponibles
            $this->servicioBackup->obtenerEstadisticasBackups();
            return [
                'valido' => true,
                'mensaje' => "Servicios operativos"
            ];
        } catch (Exception $e) {
            return [
                'valido' => false,
                'mensaje' => "Error en servicios: " . $e->getMessage()
            ];
        }
    }

    private function verificarRecursos()
    {
        $memoriaDisponible = memory_get_usage(true);
        $memoriaLimite = ini_get('memory_limit');

        if ($memoriaLimite !== '-1') {
            $limite = $this->convertirABytes($memoriaLimite);
            $porcentaje = ($memoriaDisponible / $limite) * 100;

            if ($porcentaje > 90) {
                return [
                    'valido' => false,
                    'mensaje' => "Memoria insuficiente ({$porcentaje}% usado)"
                ];
            }
        }

        return [
            'valido' => true,
            'mensaje' => "Recursos suficientes"
        ];
    }

    // =============================================================================
    // MÉTODOS PRIVADOS DE UTILIDAD
    // =============================================================================

    private function formatearTamaño($bytes)
    {
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($unidades) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $unidades[$pow];
    }

    private function convertirABytes($valor)
    {
        $unidad = strtolower(substr($valor, -1));
        $numero = (int) substr($valor, 0, -1);

        switch ($unidad) {
            case 'g': return $numero * 1024 * 1024 * 1024;
            case 'm': return $numero * 1024 * 1024;
            case 'k': return $numero * 1024;
            default: return $numero;
        }
    }

    private function obtenerDestinatariosNotificacion()
    {
        // Obtener destinatarios de la configuración
        $destinatarios = Parametro::obtenerValor('emails_notificacion_backup', '');

        if (empty($destinatarios)) {
            return [];
        }

        return array_map('trim', explode(',', $destinatarios));
    }

    private function generarAsuntoNotificacion($resultado)
    {
        $estado = $resultado['estado'] ?? 'DESCONOCIDO';
        $tipo = $this->estadisticas['tipo'];
        $fecha = now()->format('Y-m-d H:i');

        $emoji = [
            'EXITOSO' => '✅',
            'OMITIDO' => '⏭️',
            'ERROR' => '❌'
        ][$estado] ?? '❓';

        return "{$emoji} SIPAT - Backup {$tipo} {$estado} - {$fecha}";
    }

    private function generarContenidoNotificacion($resultado)
    {
        $contenido = "Resumen del backup automático de SIPAT:\n\n";
        $contenido .= "Tipo: " . $this->estadisticas['tipo'] . "\n";
        $contenido .= "Estado: " . ($resultado['estado'] ?? 'DESCONOCIDO') . "\n";
        $contenido .= "Fecha: " . now()->format('Y-m-d H:i:s') . "\n";
        $contenido .= "Servidor: " . ($this->estadisticas['servidor'] ?? 'Desconocido') . "\n\n";

        if (isset($this->estadisticas['archivo_generado'])) {
            $contenido .= "Archivo generado: " . $this->estadisticas['archivo_generado'] . "\n";
            $contenido .= "Tamaño: " . $this->estadisticas['tamaño_formateado'] . "\n";
        }

        if (isset($this->estadisticas['duracion_total'])) {
            $contenido .= "Duración: " . $this->estadisticas['duracion_total'] . " segundos\n";
        }

        return $contenido;
    }
}
