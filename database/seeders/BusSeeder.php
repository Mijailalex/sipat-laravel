<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bus;
use Faker\Factory as Faker;

class BusSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $marcas = ['Mercedes-Benz', 'Volvo', 'Scania', 'Iveco', 'Man'];
        $modelos = ['Sprinter', 'Irizar', 'Marcopolo', 'Busscar', 'Comil'];
        $subempresas = ['Transportes Lima', 'Rutas del Sur', 'Express Norte', 'Servicios Unidos'];
        $estados = ['OPERATIVO', 'MANTENIMIENTO', 'FUERA_SERVICIO'];
        $tiposCombustible = ['DIESEL', 'GAS', 'ELECTRICO'];

        // Buses específicos para testing
        $buses = [
            [
                'numero_bus' => 'B001',
                'placa' => 'ABC-123',
                'marca' => 'Mercedes-Benz',
                'modelo' => 'Sprinter',
                'año' => 2022,
                'capacidad_pasajeros' => 19,
                'tipo_combustible' => 'DIESEL',
                'estado' => 'OPERATIVO',
                'subempresa' => 'Transportes Lima',
                'kilometraje' => 25000.50,
                'fecha_ultima_revision' => now()->subMonths(2),
                'fecha_proxima_revision' => now()->addMonths(4),
                'ubicacion_actual' => 'Terminal Norte',
                'observaciones' => 'Bus en excelente estado'
            ],
            [
                'numero_bus' => 'B002',
                'placa' => 'DEF-456',
                'marca' => 'Volvo',
                'modelo' => 'Irizar',
                'año' => 2021,
                'capacidad_pasajeros' => 25,
                'tipo_combustible' => 'DIESEL',
                'estado' => 'OPERATIVO',
                'subempresa' => 'Rutas del Sur',
                'kilometraje' => 45000.75,
                'fecha_ultima_revision' => now()->subMonths(1),
                'fecha_proxima_revision' => now()->addMonths(5),
                'ubicacion_actual' => 'Terminal Sur'
            ],
            [
                'numero_bus' => 'B003',
                'placa' => 'GHI-789',
                'marca' => 'Scania',
                'modelo' => 'Marcopolo',
                'año' => 2020,
                'capacidad_pasajeros' => 30,
                'tipo_combustible' => 'GAS',
                'estado' => 'MANTENIMIENTO',
                'subempresa' => 'Express Norte',
                'kilometraje' => 78000.25,
                'fecha_ultima_revision' => now()->subMonths(3),
                'fecha_proxima_revision' => now()->addMonths(3),
                'ubicacion_actual' => 'Taller Central',
                'observaciones' => 'En mantenimiento preventivo'
            ]
        ];

        // Crear buses específicos
        foreach ($buses as $busData) {
            Bus::create($busData);
        }

        // Generar buses adicionales
        for ($i = 4; $i <= 30; $i++) {
            $marca = $faker->randomElement($marcas);
            $modelo = $faker->randomElement($modelos);
            $año = $faker->numberBetween(2015, 2023);
            $estado = $faker->randomElement($estados, [80, 15, 5]); // 80% operativo

            // Calcular fechas de revisión basadas en el estado
            if ($estado === 'OPERATIVO') {
                $ultimaRevision = $faker->dateTimeBetween('-6 months', '-1 month');
                $proximaRevision = $faker->dateTimeBetween('now', '+6 months');
            } else {
                $ultimaRevision = $faker->dateTimeBetween('-1 year', '-2 months');
                $proximaRevision = $faker->dateTimeBetween('-1 month', '+3 months');
            }

            Bus::create([
                'numero_bus' => 'B' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'placa' => strtoupper($faker->bothify('???-###')),
                'marca' => $marca,
                'modelo' => $modelo,
                'año' => $año,
                'capacidad_pasajeros' => $faker->numberBetween(15, 45),
                'tipo_combustible' => $faker->randomElement($tiposCombustible, [70, 25, 5]),
                'estado' => $estado,
                'subempresa' => $faker->randomElement($subempresas),
                'kilometraje' => $faker->randomFloat(2, 10000, 150000),
                'fecha_ultima_revision' => $ultimaRevision,
                'fecha_proxima_revision' => $proximaRevision,
                'ubicacion_actual' => $faker->randomElement([
                    'Terminal Norte', 'Terminal Sur', 'Terminal Este', 'Terminal Central',
                    'Taller Central', 'Ruta Lima-Callao', 'Cochera'
                ]),
                'observaciones' => $faker->optional(0.3)->sentence
            ]);
        }

        $this->command->info('✅ ' . Bus::count() . ' buses creados exitosamente');

        // Estadísticas
        $operativos = Bus::where('estado', 'OPERATIVO')->count();
        $mantenimiento = Bus::where('estado', 'MANTENIMIENTO')->count();
        $revisionVencida = Bus::where('fecha_proxima_revision', '<', now())->count();

        $this->command->line("   • {$operativos} buses operativos");
        $this->command->line("   • {$mantenimiento} buses en mantenimiento");
        $this->command->line("   • {$revisionVencida} buses con revisión vencida");
    }
}
