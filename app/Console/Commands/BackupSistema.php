<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupSistema extends Command
{
    protected $signature = 'sipat:backup {--tipo=completo : Tipo de backup (completo|datos|estructura)}';
    protected $description = 'Crear backup del sistema SIPAT';

    public function handle()
    {
        $tipo = $this->option('tipo');
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        $this->info("🔄 Iniciando backup {$tipo} del sistema SIPAT...");

        try {
            switch ($tipo) {
                case 'completo':
                    $this->backupCompleto($timestamp);
                    break;
                case 'datos':
                    $this->backupDatos($timestamp);
                    break;
                case 'estructura':
                    $this->backupEstructura($timestamp);
                    break;
                default:
                    $this->error('Tipo de backup no válido. Use: completo, datos o estructura');
                    return Command::FAILURE;
            }

            $this->info("✅ Backup {$tipo} completado exitosamente!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error al crear backup: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function backupCompleto($timestamp)
    {
        $filename = "sipat_backup_completo_{$timestamp}.sql";
        $this->ejecutarMysqldump($filename, true, true);
        $this->crearManifiesto($filename, 'completo');
    }

    private function backupDatos($timestamp)
    {
        $filename = "sipat_backup_datos_{$timestamp}.sql";
        $this->ejecutarMysqldump($filename, true, false);
        $this->crearManifiesto($filename, 'datos');
    }

    private function backupEstructura($timestamp)
    {
        $filename = "sipat_backup_estructura_{$timestamp}.sql";
        $this->ejecutarMysqldump($filename, false, true);
        $this->crearManifiesto($filename, 'estructura');
    }

    private function ejecutarMysqldump($filename, $datos = true, $estructura = true)
    {
        $config = config('database.connections.mysql');
        $backupPath = storage_path('app/backups/');

        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $opciones = [];
        if (!$datos) $opciones[] = '--no-data';
        if (!$estructura) $opciones[] = '--no-create-info';

        $opcionesStr = implode(' ', $opciones);

        $comando = sprintf(
            'mysqldump -h%s -u%s -p%s %s %s > %s',
            $config['host'],
            $config['username'],
            $config['password'],
            $opcionesStr,
            $config['database'],
            $backupPath . $filename
        );

        exec($comando, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception('Error al ejecutar mysqldump');
        }
    }

    private function crearManifiesto($filename, $tipo)
    {
        $manifiesto = [
            'archivo' => $filename,
            'tipo' => $tipo,
            'fecha_creacion' => Carbon::now()->toISOString(),
            'version_sistema' => '1.0.0',
            'tablas_incluidas' => $this->obtenerTablasSistema(),
            'tamaño_archivo' => filesize(storage_path("app/backups/{$filename}")),
            'checksum' => md5_file(storage_path("app/backups/{$filename}"))
        ];

        $manifestoFile = str_replace('.sql', '_manifiesto.json', $filename);
        Storage::disk('local')->put(
            "backups/{$manifestoFile}",
            json_encode($manifiesto, JSON_PRETTY_PRINT)
        );
    }

    private function obtenerTablasSistema()
    {
        $tablas = DB::select('SHOW TABLES');
        $nombreTablas = [];

        foreach ($tablas as $tabla) {
            $nombreTablas[] = array_values((array) $tabla)[0];
        }

        return $nombreTablas;
    }
}
