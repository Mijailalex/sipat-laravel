<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RutaCorta;
use App\Models\ConfiguracionTramo;
use App\Models\Conductor;
use Carbon\Carbon;

class RutasCortasEjemploSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('📋 Creando rutas cortas de ejemplo...');

        // Solo ejecutar si hay conductores disponibles
        $conductores = Conductor::disponibles()->take(3)->get();

        if ($conductores->isEmpty()) {
            $this->command->warn('⚠️ No hay conductores disponibles para crear rutas de ejemplo.');
            return;
        }

        $tramosCortos = ConfiguracionTramo::where('es_ruta_corta', true)
            ->where('activo', true)
            ->take(5)
            ->get();

        if ($tramosCortos->isEmpty()) {
            $this->command->warn('⚠️ No hay tramos cortos configurados.');
            return;
        }

        $semanaActual = Carbon::now()->week;
        $añoActual = Carbon::now()->year;
        $rutasCreadas = 0;

        // Crear rutas de ejemplo para la semana actual
        foreach ($conductores as $conductor) {
            $this->command->line("📍 Creando rutas para: {$conductor->nombre}");

            // Crear 2-3 rutas por conductor
            $numRutas = rand(2, 3);

            for ($i = 0; $i < $numRutas; $i++) {
                $fecha = Carbon::now()->addDays($i + 1);

                // Saltar fines de semana
                if ($fecha->isWeekend()) {
                    continue;
                }

                $tramo = $tramosCortos->random();

                // Verificar si puede asignar la ruta
                $validacion = RutaCorta::puedeAsignarRutaCorta($conductor->id, $fecha);
                if (!$validacion['puede']) {
                    $this->command->line("   ⚠️ Saltando ruta para {$fecha->format('d/m')}: {$validacion['razon']}");
                    continue;
                }

                $rutaCorta = RutaCorta::create([
                    'conductor_id' => $conductor->id,
                    'tramo' => $tramo->tramo,
                    'rumbo' => $tramo->rumbo,
                    'fecha_asignacion' => $fecha,
                    'hora_inicio' => '06:00:00',
                    'hora_fin' => Carbon::parse('06:00:00')->addHours($tramo->duracion_horas)->format('H:i:s'),
                    'duracion_horas' => $tramo->duracion_horas,
                    'estado' => $i == 0 ? 'COMPLETADA' : 'PROGRAMADA',
                    'semana_numero' => $fecha->week,
                    'dia_semana' => $fecha->dayOfWeek,
                    'es_consecutiva' => false,
                    'ingreso_estimado' => $tramo->ingreso_base,
                    'observaciones' => 'Ruta de ejemplo generada automáticamente'
                ]);

                $rutasCreadas++;
                $this->command->line("   ✅ {$tramo->tramo} - {$fecha->format('d/m/Y')} - {$rutaCorta->estado}");
            }

            // Actualizar última ruta corta del conductor
            $conductor->update(['ultima_ruta_corta' => Carbon::now()]);
        }

        $this->command->info("✅ Se crearon {$rutasCreadas} rutas cortas de ejemplo.");
        $this->command->info("👥 Para {$conductores->count()} conductores.");
        $this->command->line('🎯 Puedes ver las rutas en: /rutas-cortas');
    }
}
