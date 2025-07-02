<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Validacion;
use App\Models\MetricaDiaria;
use App\Models\PlanificacionDescanso;

class EjecutarValidaciones extends Command
{
    protected $signature = 'sipat:validaciones {--forzar : Ejecutar sin confirmación}';
    protected $description = 'Ejecutar validaciones automáticas del sistema SIPAT';

    public function handle()
    {
        $this->info('🔍 Iniciando validaciones automáticas del sistema SIPAT...');

        if (!$this->option('forzar')) {
            if (!$this->confirm('¿Ejecutar validaciones automáticas?')) {
                $this->info('Validaciones canceladas.');
                return Command::SUCCESS;
            }
        }

        $validacionesCreadas = 0;
        $errores = [];

        try {
            // 1. Ejecutar validaciones de conductores
            $this->info('📋 Ejecutando validaciones de conductores...');
            $validacionesConductores = Validacion::ejecutarValidacionesAutomaticas();
            $validacionesCreadas += $validacionesConductores;
            $this->line("   ✓ {$validacionesConductores} validaciones de conductores creadas");

            // 2. Verificar descansos vencidos
            $this->info('🛏️ Verificando descansos vencidos...');
            $resultadoDescansos = PlanificacionDescanso::verificarDescansosVencidos();
            $this->line("   ✓ {$resultadoDescansos['completados_automaticamente']} descansos completados automáticamente");
            $this->line("   ✓ {$resultadoDescansos['iniciados_automaticamente']} descansos iniciados automáticamente");

            // 3. Generar métricas diarias
            $this->info('📊 Generando métricas diarias...');
            MetricaDiaria::generarMetricasHoy();
            $this->line("   ✓ Métricas diarias actualizadas");

            // 4. Estadísticas finales
            $this->info("✅ Validaciones completadas exitosamente!");
            $this->line("   • {$validacionesCreadas} nuevas validaciones creadas");
            $this->line("   • {$resultadoDescansos['completados_automaticamente']} descansos completados");
            $this->line("   • {$resultadoDescansos['iniciados_automaticamente']} descansos iniciados");

            if (!empty($errores)) {
                $this->warn("⚠️ Errores encontrados:");
                foreach ($errores as $error) {
                    $this->line("   • {$error}");
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error al ejecutar validaciones: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
