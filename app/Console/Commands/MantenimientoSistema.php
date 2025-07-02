<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Notificacion;
use App\Models\AuditoriaLog;
use App\Models\ConfiguracionSistema;
use Carbon\Carbon;

class MantenimientoSistema extends Command
{
    protected $signature = 'sipat:mantenimiento {--forzar : Ejecutar sin confirmación}';
    protected $description = 'Ejecutar tareas de mantenimiento del sistema SIPAT';

    public function handle()
    {
        $this->info('🔧 Iniciando mantenimiento del sistema SIPAT...');

        if (!$this->option('forzar')) {
            if (!$this->confirm('¿Continuar con las tareas de mantenimiento?')) {
                $this->info('Mantenimiento cancelado.');
                return Command::SUCCESS;
            }
        }

        $tareasCompletadas = 0;

        // Limpiar notificaciones antiguas
        $this->info('📧 Limpiando notificaciones antiguas...');
        $diasRetencion = ConfiguracionSistema::obtenerValor('retener_notificaciones_dias', 30);
        $notificacionesEliminadas = $this->limpiarNotificacionesAntiguas($diasRetencion);
        $this->line("   ✓ {$notificacionesEliminadas} notificaciones eliminadas");
        $tareasCompletadas++;

        // Limpiar logs de auditoría antiguos
        $this->info('📋 Limpiando logs de auditoría antiguos...');
        $logsEliminados = $this->limpiarLogsAuditoria(90); // 90 días
        $this->line("   ✓ {$logsEliminados} logs eliminados");
        $tareasCompletadas++;

        // Optimizar base de datos
        $this->info('🗃️ Optimizando tablas de base de datos...');
        $tablasOptimizadas = $this->optimizarBaseDatos();
        $this->line("   ✓ {$tablasOptimizadas} tablas optimizadas");
        $tareasCompletadas++;

        // Limpiar archivos temporales
        $this->info('🗂️ Limpiando archivos temporales...');
        $archivosEliminados = $this->limpiarArchivosTemporales();
        $this->line("   ✓ {$archivosEliminados} archivos eliminados");
        $tareasCompletadas++;

        // Verificar integridad del sistema
        $this->info('🔍 Verificando integridad del sistema...');
        $problemas = $this->verificarIntegridad();
        if ($problemas === 0) {
            $this->line("   ✓ Sistema íntegro");
        } else {
            $this->warn("   ⚠️ {$problemas} problemas detectados");
        }
        $tareasCompletadas++;

        $this->info("✅ Mantenimiento completado. {$tareasCompletadas} tareas ejecutadas.");

        // Crear notificación de mantenimiento
        Notificacion::crear(
            'SISTEMA',
            'Mantenimiento completado',
            "Mantenimiento automático ejecutado. {$tareasCompletadas} tareas completadas.",
            'INFO'
        );

        return Command::SUCCESS;
    }

    private function limpiarNotificacionesAntiguas($dias)
    {
        $fechaLimite = Carbon::now()->subDays($dias);
        return Notificacion::where('created_at', '<', $fechaLimite)->delete();
    }

    private function limpiarLogsAuditoria($dias)
    {
        $fechaLimite = Carbon::now()->subDays($dias);
        return AuditoriaLog::where('created_at', '<', $fechaLimite)->delete();
    }

    private function optimizarBaseDatos()
    {
        $tablas = [
            'conductores',
            'validaciones',
            'notificaciones',
            'auditoria_logs',
            'rutas_cortas'
        ];

        $optimizadas = 0;
        foreach ($tablas as $tabla) {
            try {
                \DB::statement("OPTIMIZE TABLE {$tabla}");
                $optimizadas++;
            } catch (\Exception $e) {
                $this->warn("No se pudo optimizar la tabla {$tabla}: " . $e->getMessage());
            }
        }

        return $optimizadas;
    }

    private function limpiarArchivosTemporales()
    {
        $directorios = [
            storage_path('app/temp'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views')
        ];

        $eliminados = 0;
        foreach ($directorios as $directorio) {
            if (is_dir($directorio)) {
                $archivos = glob($directorio . '/*');
                foreach ($archivos as $archivo) {
                    if (is_file($archivo) && filemtime($archivo) < strtotime('-1 day')) {
                        unlink($archivo);
                        $eliminados++;
                    }
                }
            }
        }

        return $eliminados;
    }

    private function verificarIntegridad()
    {
        $problemas = 0;

        // Verificar conductores sin validaciones críticas resueltas
        $conductoresCriticos = \App\Models\Conductor::where('dias_acumulados', '>=', 6)
            ->where('estado', '!=', 'DESCANSO')
            ->count();

        if ($conductoresCriticos > 0) {
            $this->warn("   ⚠️ {$conductoresCriticos} conductores requieren descanso");
            $problemas++;
        }

        // Verificar validaciones pendientes muy antiguas
        $validacionesAntiguas = \App\Models\Validacion::where('estado', 'PENDIENTE')
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->count();

        if ($validacionesAntiguas > 0) {
            $this->warn("   ⚠️ {$validacionesAntiguas} validaciones pendientes muy antiguas");
            $problemas++;
        }

        return $problemas;
    }
}
