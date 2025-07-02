<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ConductorBackup;
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

        // Limpiar backups antiguos de conductores
        $this->info('🗂️ Limpiando backups antiguos de conductores...');
        try {
            $backupsEliminados = ConductorBackup::limpiarBackupsAntiguos(90);
            $this->line("   ✓ {$backupsEliminados} backups eliminados");
        } catch (\Exception $e) {
            $this->warn("   ⚠️ Error al limpiar backups: " . $e->getMessage());
        }
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

        // Limpiar cache de parámetros
        $this->info('🧹 Limpiando cache del sistema...');
        \Illuminate\Support\Facades\Cache::flush();
        $this->line("   ✓ Cache limpiado");
        $tareasCompletadas++;

        $this->info("✅ Mantenimiento completado. {$tareasCompletadas} tareas ejecutadas.");

        return Command::SUCCESS;
    }

    private function optimizarBaseDatos()
    {
        $tablas = [
            'conductores',
            'validaciones',
            'rutas_cortas',
            'metricas_diarias',
            'parametros',
            'plantillas',
            'turnos',
            'buses'
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
            storage_path('framework/cache/data'),
            storage_path('logs')
        ];

        $eliminados = 0;
        foreach ($directorios as $directorio) {
            if (is_dir($directorio)) {
                $archivos = glob($directorio . '/*');
                foreach ($archivos as $archivo) {
                    if (is_file($archivo) && filemtime($archivo) < strtotime('-7 days')) {
                        try {
                            unlink($archivo);
                            $eliminados++;
                        } catch (\Exception $e) {
                            $this->warn("No se pudo eliminar: {$archivo}");
                        }
                    }
                }
            }
        }

        return $eliminados;
    }

    private function verificarIntegridad()
    {
        $problemas = 0;

        try {
            // Verificar conductores críticos
            $conductoresCriticos = \App\Models\Conductor::where('dias_acumulados', '>=', 6)
                ->where('estado', 'DISPONIBLE')
                ->count();

            if ($conductoresCriticos > 0) {
                $this->warn("   ⚠️ {$conductoresCriticos} conductores requieren descanso");
                $problemas++;
            }

            // Verificar validaciones pendientes críticas
            $validacionesCriticas = \App\Models\Validacion::where('severidad', 'CRITICA')
                ->where('estado', 'PENDIENTE')
                ->count();

            if ($validacionesCriticas > 0) {
                $this->warn("   ⚠️ {$validacionesCriticas} validaciones críticas pendientes");
                $problemas++;
            }

            // Verificar integridad de base de datos
            $tablas = ['conductores', 'validaciones', 'rutas_cortas'];
            foreach ($tablas as $tabla) {
                try {
                    \DB::select("CHECK TABLE {$tabla}");
                } catch (\Exception $e) {
                    $this->warn("   ⚠️ Error en la tabla {$tabla}: " . $e->getMessage());
                    $problemas++;
                }
            }

        } catch (\Exception $e) {
            $this->warn("   ⚠️ Error verificando integridad: " . $e->getMessage());
            $problemas++;
        }

        return $problemas;
    }
}
