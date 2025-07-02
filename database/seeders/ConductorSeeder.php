<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Conductor;
use Faker\Factory as Faker;

class ConductorSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_PE');

        $subempresas = ['Transportes Lima', 'Rutas del Sur', 'Express Norte', 'Servicios Unidos'];
        $origenes = ['Terminal Norte', 'Terminal Sur', 'Terminal Este', 'Terminal Central'];
        $estados = ['DISPONIBLE', 'DESCANSO_FISICO', 'DESCANSO_SEMANAL'];

        $conductores = [
            // Conductores específicos para testing
            [
                'codigo_conductor' => 'COND001',
                'nombre' => 'Juan Carlos',
                'apellido' => 'Pérez García',
                'dni' => '12345678',
                'licencia' => 'A1-123456',
                'categoria_licencia' => 'A-I',
                'fecha_vencimiento_licencia' => now()->addMonths(6),
                'telefono' => '987654321',
                'email' => 'juan.perez@sipat.com',
                'direccion' => 'Av. Principal 123, Lima',
                'fecha_nacimiento' => '1985-03-15',
                'genero' => 'M',
                'contacto_emergencia' => 'María Pérez',
                'telefono_emergencia' => '987654322',
                'fecha_ingreso' => '2023-01-15',
                'años_experiencia' => 8,
                'salario_base' => 2500.00,
                'certificaciones' => ['Conducción Defensiva', 'Primeros Auxilios'],
                'turno_preferido' => 'MAÑANA',
                'estado' => 'DISPONIBLE',
                'dias_acumulados' => 2,
                'eficiencia' => 92.5,
                'puntualidad' => 88.3,
                'score_general' => 90.4,
                'horas_hombre' => 45.5,
                'origen_conductor' => 'Terminal Norte',
                'subempresa' => 'Transportes Lima',
                'observaciones' => 'Conductor experimentado con excelente historial'
            ],
            [
                'codigo_conductor' => 'COND002',
                'nombre' => 'María Elena',
                'apellido' => 'Rodríguez Silva',
                'dni' => '87654321',
                'licencia' => 'A1-654321',
                'categoria_licencia' => 'A-I',
                'fecha_vencimiento_licencia' => now()->addMonths(3),
                'telefono' => '987654323',
                'email' => 'maria.rodriguez@sipat.com',
                'direccion' => 'Jr. Los Olivos 456, Lima',
                'fecha_nacimiento' => '1990-07-22',
                'genero' => 'F',
                'contacto_emergencia' => 'Carlos Rodríguez',
                'telefono_emergencia' => '987654324',
                'fecha_ingreso' => '2023-03-01',
                'años_experiencia' => 5,
                'salario_base' => 2200.00,
                'certificaciones' => ['Conducción Defensiva'],
                'turno_preferido' => 'TARDE',
                'estado' => 'DISPONIBLE',
                'dias_acumulados' => 5,
                'eficiencia' => 85.2,
                'puntualidad' => 92.1,
                'score_general' => 88.65,
                'horas_hombre' => 38.0,
                'origen_conductor' => 'Terminal Sur',
                'subempresa' => 'Rutas del Sur',
                'observaciones' => 'Conductora muy puntual'
            ],
            [
                'codigo_conductor' => 'COND003',
                'nombre' => 'Roberto',
                'apellido' => 'Mendoza López',
                'dni' => '45678912',
                'licencia' => 'A1-789123',
                'categoria_licencia' => 'A-IIa',
                'fecha_vencimiento_licencia' => now()->addMonths(8),
                'telefono' => '987654325',
                'fecha_ingreso' => '2022-11-15',
                'años_experiencia' => 12,
                'turno_preferido' => 'NOCTURNO',
                'estado' => 'DISPONIBLE',
                'dias_acumulados' => 7, // Crítico - necesita descanso
                'eficiencia' => 78.5, // Bajo - generará validación
                'puntualidad' => 82.3, // Bajo - generará validación
                'score_general' => 80.4,
                'origen_conductor' => 'Terminal Este',
                'subempresa' => 'Express Norte'
            ]
        ];

        // Crear conductores específicos
        foreach ($conductores as $conductorData) {
            $conductorData['score_general'] = ($conductorData['eficiencia'] + $conductorData['puntualidad']) / 2;
            Conductor::create($conductorData);
        }

        // Generar conductores adicionales con Faker
        for ($i = 4; $i <= 25; $i++) {
            $eficiencia = $faker->randomFloat(2, 70, 98);
            $puntualidad = $faker->randomFloat(2, 75, 96);

            Conductor::create([
                'codigo_conductor' => 'COND' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'nombre' => $faker->firstName,
                'apellido' => $faker->lastName . ' ' . $faker->lastName,
                'dni' => $faker->unique()->numerify('########'),
                'licencia' => 'A1-' . $faker->unique()->numerify('######'),
                'categoria_licencia' => $faker->randomElement(['A-I', 'A-IIa', 'A-IIb', 'A-III']),
                'fecha_vencimiento_licencia' => $faker->dateTimeBetween('now', '+2 years'),
                'telefono' => $faker->phoneNumber,
                'email' => $faker->optional(0.7)->email,
                'direccion' => $faker->optional(0.8)->address,
                'fecha_nacimiento' => $faker->dateTimeBetween('-55 years', '-25 years'),
                'genero' => $faker->randomElement(['M', 'F']),
                'contacto_emergencia' => $faker->optional(0.9)->name,
                'telefono_emergencia' => $faker->optional(0.9)->phoneNumber,
                'fecha_ingreso' => $faker->dateTimeBetween('-2 years', '-1 month'),
                'años_experiencia' => $faker->numberBetween(1, 20),
                'salario_base' => $faker->optional(0.8)->randomFloat(2, 1800, 3500),
                'certificaciones' => $faker->optional(0.6)->randomElements([
                    'Conducción Defensiva', 'Primeros Auxilios', 'Manejo de Carga',
                    'Mecánica Básica', 'Atención al Cliente'
                ], $faker->numberBetween(0, 3)),
                'turno_preferido' => $faker->randomElement(['MAÑANA', 'TARDE', 'NOCHE', 'ROTATIVO']),
                'estado' => $faker->randomElement($estados, [70, 20, 10]), // 70% disponible
                'dias_acumulados' => $faker->numberBetween(0, 8),
                'eficiencia' => $eficiencia,
                'puntualidad' => $puntualidad,
                'score_general' => ($eficiencia + $puntualidad) / 2,
                'horas_hombre' => $faker->randomFloat(2, 0, 60),
                'ultimo_servicio' => $faker->optional(0.8)->dateTimeBetween('-1 week', 'now'),
                'ultima_ruta_corta' => $faker->optional(0.6)->dateTimeBetween('-3 days', 'now'),
                'fecha_ultimo_descanso' => $faker->optional(0.5)->dateTimeBetween('-1 month', '-1 week'),
                'total_rutas_completadas' => $faker->numberBetween(0, 150),
                'total_ingresos_generados' => $faker->randomFloat(2, 0, 5000),
                'origen_conductor' => $faker->randomElement($origenes),
                'subempresa' => $faker->randomElement($subempresas),
                'observaciones' => $faker->optional(0.3)->sentence
            ]);
        }

        $this->command->info('✅ ' . Conductor::count() . ' conductores creados exitosamente');

        // Mostrar algunos con estados críticos para testing
        $criticos = Conductor::where('dias_acumulados', '>=', 6)->count();
        $bajosRendimiento = Conductor::where('eficiencia', '<', 80)->count();

        $this->command->line("   • {$criticos} conductores críticos (requieren descanso)");
        $this->command->line("   • {$bajosRendimiento} conductores con eficiencia baja");
    }
}
