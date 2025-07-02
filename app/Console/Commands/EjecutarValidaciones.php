<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Conductor;
use App\Models\Validacion;

class EjecutarValidaciones extends Command
{
    protected $signature = 'sipat:validaciones';
    protected $description = 'Ejecutar validaciones automáticas del sistema SIPAT';

    public function handle()
    {
        $this->info('Ejecutando validaciones automáticas...');

        $nuevasValidaciones = 0;

        // Validar conductores críticos
        $conductoresCriticos = Conductor::where('dias_acumulados', '>=', 6)
            ->where('estado', '!=', 'DESCANSO')
            ->get();

        foreach ($conductoresCriticos as $conductor) {
            $existeValidacion = Validacion::where('conductor_id', $conductor->id)
                ->where('tipo', 'DESCANSO_001')
                ->where('estado', 'PENDIENTE')
                ->exists();

            if (!$existeValidacion) {
                Validacion::create([
                    'tipo' => 'DESCANSO_001',
                    'conductor_id' => $conductor->id,
                    'mensaje' => 'Conductor requiere descanso obligatorio (' . $conductor->dias_acumulados . ' días trabajados)',
                    'severidad' => 'CRITICA',
                    'estado' => 'PENDIENTE'
                ]);
                $nuevasValidaciones++;
            }
        }

        $this->info("Validaciones completadas. {$nuevasValidaciones} nuevas validaciones generadas.");

        return Command::SUCCESS;
    }
}
