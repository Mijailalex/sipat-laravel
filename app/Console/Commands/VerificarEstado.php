<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class VerificarEstado extends Command
{
    protected $signature = 'sipat:verificar';
    protected $description = 'Verificar el estado actual de las tablas de SIPAT';

    public function handle()
    {
        $this->info('🔍 Verificando estado actual de SIPAT...');
        $this->info('');

        $tablasRequeridas = [
            'users' => ['id', 'name', 'email', 'password'],
            'subempresas' => ['id', 'nombre', 'codigo', 'activa'],
            'conductores' => ['id', 'nombre', 'apellido', 'cedula', 'estado', 'eficiencia', 'puntualidad'],
            'validaciones' => ['id', 'tipo', 'severidad', 'estado', 'descripcion'],
            'historial_planificaciones' => ['id', 'fecha_planificacion', 'estado'],
        ];

        $estadoGeneral = [];

        foreach ($tablasRequeridas as $tabla => $columnas) {
            $this->info("📋 Verificando tabla: {$tabla}");

            if (!Schema::hasTable($tabla)) {
                $this->error("   ❌ Tabla '{$tabla}' NO EXISTE");
                $estadoGeneral[$tabla] = 'no_existe';
                continue;
            }

            $this->info("   ✅ Tabla '{$tabla}' existe");

            $columnasFaltantes = [];
            foreach ($columnas as $columna) {
                if (!Schema::hasColumn($tabla, $columna)) {
                    $columnasFaltantes[] = $columna;
                }
            }

            if (empty($columnasFaltantes)) {
                $this->info("   ✅ Todas las columnas principales existen");
                $estadoGeneral[$tabla] = 'completa';
            } else {
                $this->warn("   ⚠️ Faltan columnas: " . implode(', ', $columnasFaltantes));
                $estadoGeneral[$tabla] = 'incompleta';
            }

            // Mostrar cuántos registros tiene
            try {
                $conteo = DB::table($tabla)->count();
                $this->info("   📊 Registros: {$conteo}");
            } catch (\Exception $e) {
                $this->warn("   ⚠️ Error contando registros: " . $e->getMessage());
            }

            $this->info('');
        }

        // Resumen
        $this->info('📊 RESUMEN DEL ESTADO:');
        $this->info('');

        foreach ($estadoGeneral as $tabla => $estado) {
            $icono = match($estado) {
                'completa' => '✅',
                'incompleta' => '⚠️',
                'no_existe' => '❌',
                default => '❓'
            };

            $this->info("   {$icono} {$tabla}: " . strtoupper($estado));
        }

        $this->info('');

        // Verificar migraciones pendientes
        $this->info('📝 Verificando migraciones pendientes...');
        try {
            $result = $this->call('migrate:status');
            $this->info('');
        } catch (\Exception $e) {
            $this->warn("Error verificando migraciones: " . $e->getMessage());
        }

        // Recomendaciones
        $this->info('💡 RECOMENDACIONES:');
        $this->info('');

        $tieneProblemas = in_array('incompleta', $estadoGeneral) || in_array('no_existe', $estadoGeneral);

        if ($tieneProblemas) {
            $this->warn('1. Ejecutar: php artisan sipat:reparar');
            $this->warn('2. Luego: php artisan sipat:setup --force');
        } else {
            $this->info('1. Sistema parece estar completo');
            $this->info('2. Ejecutar: php artisan sipat:setup');
        }

        return Command::SUCCESS;
    }
}
