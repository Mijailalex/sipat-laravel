<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // =============================================================================
        // PLANIFICACIÓN AUTOMÁTICA
        // =============================================================================

        // Planificación automática diaria para el día siguiente
        // Se ejecuta todos los días a las 18:00 (6:00 PM)
        $schedule->command('sipat:planificar --modo=automatico --notificar')
                 ->dailyAt('18:00')
                 ->name('planificacion-diaria-automatica')
                 ->description('Planificación automática diaria para el día siguiente')
                 ->onSuccess(function () {
                     \Log::info('✅ Planificación automática diaria completada exitosamente');
                 })
                 ->onFailure(function () {
                     \Log::error('❌ Error en planificación automática diaria');
                     // Enviar notificación de error a administradores
                     $this->notificarErrorCritico('Planificación automática diaria falló');
                 })
                 ->emailOutputOnFailure(['admin@sipat.com'])
                 ->runInBackground();

        // Planificación de fin de semana (viernes para sábado y domingo)
        $schedule->command('sipat:planificar --dias=2 --modo=automatico --notificar')
                 ->fridays()
                 ->at('19:00')
                 ->name('planificacion-fin-semana')
                 ->description('Planificación automática para fin de semana')
                 ->onSuccess(function () {
                     \Log::info('✅ Planificación de fin de semana completada');
                 })
                 ->runInBackground();

        // =============================================================================
        // SISTEMA DE BACKUPS
        // =============================================================================

        // Backup diario - todos los días a las 02:00 AM
        $schedule->command('sipat:backup diario --limpiar --validar --notificar')
                 ->dailyAt('02:00')
                 ->name('backup-diario')
                 ->description('Backup diario automático del sistema')
                 ->onSuccess(function () {
                     \Log::info('✅ Backup diario completado exitosamente');
                 })
                 ->onFailure(function () {
                     \Log::error('❌ Error en backup diario');
                     $this->notificarErrorCritico('Backup diario falló');
                 })
                 ->emailOutputOnFailure(['admin@sipat.com'])
                 ->runInBackground();

        // Backup semanal - domingos a las 03:00 AM
        $schedule->command('sipat:backup semanal --limpiar --validar --comprimir --notificar')
                 ->weeklyOn(0, '03:00') // Domingo
                 ->name('backup-semanal')
                 ->description('Backup semanal completo del sistema')
                 ->onSuccess(function () {
                     \Log::info('✅ Backup semanal completado exitosamente');
                 })
                 ->runInBackground();

        // Backup mensual - primer día del mes a las 04:00 AM
        $schedule->command('sipat:backup mensual --limpiar --validar --comprimir --notificar')
                 ->monthlyOn(1, '04:00')
                 ->name('backup-mensual')
                 ->description('Backup mensual completo del sistema')
                 ->onSuccess(function () {
                     \Log::info('✅ Backup mensual completado exitosamente');
                 })
                 ->runInBackground();

        // Backup anual - 1 de enero a las 05:00 AM
        $schedule->command('sipat:backup anual --validar --comprimir --notificar')
                 ->yearlyOn(1, 1, '05:00') // 1 de enero
                 ->name('backup-anual')
                 ->description('Backup anual completo del sistema')
                 ->runInBackground();

        // =============================================================================
        // VALIDACIONES Y CONTROL DE CALIDAD
        // =============================================================================

        // Ejecutar validaciones automáticas cada 2 horas durante horario laboral
        $schedule->command('validaciones:ejecutar-automaticas')
                 ->hourlyAt(0)
                 ->between('06:00', '22:00')
                 ->name('validaciones-automaticas')
                 ->description('Ejecución automática de validaciones del sistema')
                 ->onSuccess(function () {
                     \Log::info('✅ Validaciones automáticas ejecutadas');
                 })
                 ->runInBackground();

        // Validar conductores críticos cada 30 minutos durante horario operativo
        $schedule->command('conductores:validar-criticos')
                 ->cron('*/30 6-22 * * *') // Cada 30 minutos de 6 AM a 10 PM
                 ->name('validacion-conductores-criticos')
                 ->description('Validación de conductores en estado crítico')
                 ->onSuccess(function () {
                     \Log::debug('✅ Validación de conductores críticos completada');
                 })
                 ->runInBackground();

        // =============================================================================
        // MANTENIMIENTO DEL SISTEMA
        // =============================================================================

        // Mantenimiento completo del sistema - domingos a las 03:30 AM
        $schedule->command('sipat:mantenimiento --forzar')
                 ->weeklyOn(0, '03:30')
                 ->name('mantenimiento-semanal')
                 ->description('Mantenimiento automático semanal del sistema')
                 ->onSuccess(function () {
                     \Log::info('✅ Mantenimiento semanal completado');
                 })
                 ->onFailure(function () {
                     \Log::warning('⚠️ Advertencias en mantenimiento semanal');
                 })
                 ->runInBackground();

        // Limpiar logs antiguos - todos los días a las 01:00 AM
        $schedule->command('logs:limpiar --dias=30')
                 ->dailyAt('01:00')
                 ->name('limpieza-logs')
                 ->description('Limpieza automática de logs antiguos')
                 ->onSuccess(function () {
                     \Log::info('✅ Logs antiguos limpiados');
                 })
                 ->runInBackground();

        // Optimizar base de datos - miércoles a las 02:30 AM
        $schedule->command('db:optimize-tables')
                 ->weeklyOn(3, '02:30') // Miércoles
                 ->name('optimizacion-db')
                 ->description('Optimización automática de tablas de base de datos')
                 ->onSuccess(function () {
                     \Log::info('✅ Base de datos optimizada');
                 })
                 ->runInBackground();

        // =============================================================================
        // REPORTES AUTOMÁTICOS
        // =============================================================================

        // Reporte diario de operaciones - todos los días a las 23:00
        $schedule->command('reportes:generar-diario')
                 ->dailyAt('23:00')
                 ->name('reporte-diario')
                 ->description('Generación automática de reporte diario')
                 ->onSuccess(function () {
                     \Log::info('✅ Reporte diario generado');
                 })
                 ->runInBackground();

        // Reporte semanal - lunes a las 08:00 AM
        $schedule->command('reportes:generar-semanal')
                 ->weeklyOn(1, '08:00') // Lunes
                 ->name('reporte-semanal')
                 ->description('Generación automática de reporte semanal')
                 ->emailOutputTo(['supervisor@sipat.com', 'admin@sipat.com'])
                 ->runInBackground();

        // Reporte mensual - primer día del mes a las 09:00 AM
        $schedule->command('reportes:generar-mensual')
                 ->monthlyOn(1, '09:00')
                 ->name('reporte-mensual')
                 ->description('Generación automática de reporte mensual')
                 ->emailOutputTo(['admin@sipat.com', 'direccion@sipat.com'])
                 ->runInBackground();

        // =============================================================================
        // MONITOREO Y ALERTAS
        // =============================================================================

        // Monitoreo del estado del sistema cada 15 minutos
        $schedule->command('sistema:verificar-estado')
                 ->cron('*/15 * * * *') // Cada 15 minutos
                 ->name('monitoreo-sistema')
                 ->description('Monitoreo continuo del estado del sistema')
                 ->onSuccess(function () {
                     \Log::debug('✅ Estado del sistema verificado');
                 })
                 ->onFailure(function () {
                     \Log::error('❌ Error en monitoreo del sistema');
                     $this->notificarErrorCritico('Error en monitoreo del sistema');
                 })
                 ->runInBackground();

        // Verificar conductores próximos a descanso cada hora
        $schedule->command('conductores:verificar-descansos-proximos')
                 ->hourly()
                 ->name('verificacion-descansos')
                 ->description('Verificación de conductores próximos a descanso obligatorio')
                 ->onSuccess(function () {
                     \Log::debug('✅ Verificación de descansos completada');
                 })
                 ->runInBackground();

        // =============================================================================
        // SEGURIDAD Y AUDITORÍA
        // =============================================================================

        // Análisis de seguridad diario - todos los días a las 05:00 AM
        $schedule->command('seguridad:analizar-patrones')
                 ->dailyAt('05:00')
                 ->name('analisis-seguridad')
                 ->description('Análisis automático de patrones de seguridad')
                 ->onSuccess(function () {
                     \Log::info('✅ Análisis de seguridad completado');
                 })
                 ->onFailure(function () {
                     \Log::error('❌ Error en análisis de seguridad');
                     $this->notificarErrorCritico('Error en análisis de seguridad');
                 })
                 ->runInBackground();

        // Limpiar intentos de acceso fallidos antiguos - diario a las 04:30 AM
        $schedule->command('seguridad:limpiar-intentos-antiguos --dias=7')
                 ->dailyAt('04:30')
                 ->name('limpieza-intentos-acceso')
                 ->description('Limpieza de intentos de acceso fallidos antiguos')
                 ->runInBackground();

        // Generar reporte de seguridad semanal - viernes a las 17:00
        $schedule->command('seguridad:reporte-semanal')
                 ->fridays()
                 ->at('17:00')
                 ->name('reporte-seguridad-semanal')
                 ->description('Reporte semanal de seguridad del sistema')
                 ->emailOutputTo(['admin@sipat.com', 'seguridad@sipat.com'])
                 ->runInBackground();

        // =============================================================================
        // TAREAS DE NOTIFICACIÓN
        // =============================================================================

        // Recordatorios de validaciones pendientes - cada 4 horas durante horario laboral
        $schedule->command('notificaciones:recordatorios-validaciones')
                 ->cron('0 */4 6-18 * *') // Cada 4 horas de 6 AM a 6 PM
                 ->name('recordatorios-validaciones')
                 ->description('Envío de recordatorios de validaciones pendientes')
                 ->runInBackground();

        // Notificaciones de planificación del día siguiente - todos los días a las 19:30
        $schedule->command('notificaciones:planificacion-manana')
                 ->dailyAt('19:30')
                 ->name('notificacion-planificacion')
                 ->description('Notificación de planificación para el día siguiente')
                 ->runInBackground();

        // Resumen semanal para supervisores - viernes a las 18:00
        $schedule->command('notificaciones:resumen-semanal')
                 ->fridays()
                 ->at('18:00')
                 ->name('resumen-semanal')
                 ->description('Envío de resumen semanal a supervisores')
                 ->runInBackground();

        // =============================================================================
        // TAREAS DE EMERGENCIA Y CONTINGENCIA
        // =============================================================================

        // Verificación de servicios críticos cada 5 minutos
        $schedule->command('sistema:verificar-servicios-criticos')
                 ->cron('*/5 * * * *') // Cada 5 minutos
                 ->name('verificacion-servicios-criticos')
                 ->description('Verificación de servicios críticos del sistema')
                 ->onFailure(function () {
                     \Log::critical('💥 Servicios críticos fallando');
                     $this->notificarEmergencia('Servicios críticos del sistema fallando');
                 })
                 ->runInBackground();

        // Backup de emergencia si se detectan problemas críticos
        $schedule->command('sipat:backup completo --forzar')
                 ->when(function () {
                     return $this->detectarProblemasGraves();
                 })
                 ->name('backup-emergencia')
                 ->description('Backup de emergencia automático')
                 ->runInBackground();

        // =============================================================================
        // TAREAS DE DESARROLLO Y PRUEBAS (SOLO EN ENTORNO LOCAL)
        // =============================================================================

        if (app()->environment('local')) {
            // Generar datos de prueba cada hora en desarrollo
            $schedule->command('dev:generar-datos-prueba')
                     ->hourly()
                     ->name('datos-prueba-desarrollo')
                     ->description('Generación de datos de prueba en desarrollo')
                     ->runInBackground();

            // Limpiar datos de prueba antiguos cada día
            $schedule->command('dev:limpiar-datos-prueba --dias=3')
                     ->dailyAt('00:00')
                     ->name('limpieza-datos-prueba')
                     ->description('Limpieza de datos de prueba antiguos')
                     ->runInBackground();
        }

        // =============================================================================
        // CONFIGURACIÓN GLOBAL DE PROGRAMACIÓN
        // =============================================================================

        // Configurar zona horaria para todas las tareas
        $schedule->useTimezone('America/Lima');

        // Evitar superposición de tareas críticas
        $schedule->preventOverlapsOn([
            'planificacion-diaria-automatica',
            'backup-diario',
            'backup-semanal',
            'backup-mensual',
            'mantenimiento-semanal'
        ]);

        // Configurar timeout para tareas pesadas
        $schedule->timeout([
            'planificacion-diaria-automatica' => 1800, // 30 minutos
            'backup-diario' => 3600, // 1 hora
            'backup-semanal' => 7200, // 2 horas
            'backup-mensual' => 10800, // 3 horas
            'mantenimiento-semanal' => 3600 // 1 hora
        ]);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Determinar si hay problemas graves en el sistema que requieren backup de emergencia
     */
    private function detectarProblemasGraves(): bool
    {
        try {
            // Verificar si hay errores críticos recientes
            $erroresCriticos = \DB::table('historial_credenciales')
                ->where('severidad', 'CRITICA')
                ->where('created_at', '>=', now()->subHours(1))
                ->count();

            // Verificar si el último backup fue hace más de 48 horas
            $ultimoBackup = \DB::table('historial_credenciales')
                ->where('accion', 'BACKUP_CREDENCIALES')
                ->where('created_at', '>=', now()->subHours(48))
                ->exists();

            // Verificar uso de disco crítico
            $espacioLibre = disk_free_space(storage_path());
            $espacioTotal = disk_total_space(storage_path());
            $porcentajeUso = (($espacioTotal - $espacioLibre) / $espacioTotal) * 100;

            return $erroresCriticos > 5 || !$ultimoBackup || $porcentajeUso > 95;

        } catch (\Exception $e) {
            \Log::error('Error verificando problemas graves del sistema: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notificar error crítico a administradores
     */
    private function notificarErrorCritico(string $mensaje): void
    {
        try {
            \Log::critical("🚨 ERROR CRÍTICO: {$mensaje}");

            // Registrar en historial de credenciales para auditoría
            \App\Models\HistorialCredenciales::registrarAccion(
                1, // Sistema
                'ALERTA_CRITICA',
                [
                    'mensaje' => $mensaje,
                    'timestamp' => now(),
                    'servidor' => gethostname(),
                    'tipo_alerta' => 'ERROR_CRITICO'
                ]
            );

            // En producción, aquí se enviarían notificaciones push, SMS, etc.
            // Por ahora solo se registra en logs

        } catch (\Exception $e) {
            \Log::emergency("Error al notificar error crítico: {$e->getMessage()}");
        }
    }

    /**
     * Notificar emergencia del sistema
     */
    private function notificarEmergencia(string $mensaje): void
    {
        try {
            \Log::emergency("🆘 EMERGENCIA DEL SISTEMA: {$mensaje}");

            // Registrar emergencia
            \App\Models\HistorialCredenciales::registrarAccion(
                1, // Sistema
                'EMERGENCIA_SISTEMA',
                [
                    'mensaje' => $mensaje,
                    'timestamp' => now(),
                    'servidor' => gethostname(),
                    'requiere_atencion_inmediata' => true
                ]
            );

            // En producción, aquí se activarían todos los canales de emergencia:
            // - SMS a administradores
            // - Llamadas telefónicas automáticas
            // - Notificaciones push
            // - Alerts en sistemas de monitoreo externos

        } catch (\Exception $e) {
            \Log::emergency("Error al notificar emergencia: {$e->getMessage()}");

            // Como última medida, intentar escribir en un archivo
            try {
                file_put_contents(
                    storage_path('logs/emergencias.log'),
                    "[" . now() . "] EMERGENCIA: {$mensaje}\n",
                    FILE_APPEND | LOCK_EX
                );
            } catch (\Exception $e2) {
                // Si todo falla, al menos intentamos con error_log de PHP
                error_log("SIPAT EMERGENCIA: {$mensaje}");
            }
        }
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     */
    protected function scheduleTimezone(): string
    {
        return 'America/Lima';
    }
}
