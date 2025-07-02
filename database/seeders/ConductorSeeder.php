<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Conductor;
use Faker\Factory as Faker;

class ConductorSeeder extends Seeder
{
    public function run()
    {
        // Limpiar tabla antes de insertar
        Conductor::truncate();

        // Inicializar Faker
        $faker = Faker::create('es_PE'); // Español de Perú para datos más locales

        // Estados posibles
        $estados = [
            'DISPONIBLE',
            'EN_RUTA',
            'DESCANSO',
            'INACTIVO'
        ];

        // Generar 260 conductores
        $conductores = [];

        for ($i = 1; $i <= 260; $i++) {
            // Generar datos aleatorios pero coherentes
            $puntualidad = $faker->randomFloat(2, 60, 100);
            $eficiencia = $faker->randomFloat(2, 65, 100);
            $diasAcumulados = $faker->numberBetween(0, 7);
            $estado = $estados[array_rand($estados)];

            // Ajustar estado según días acumulados
            if ($diasAcumulados >= 6) {
                $estado = 'DESCANSO';
            }

            $conductores[] = [
                'nombre' => $faker->name,
                'codigo' => 'C' . str_pad($i, 3, '0', STR_PAD_LEFT), // C001, C002, etc
                'email' => $faker->unique()->safeEmail,
                'telefono' => $faker->phoneNumber,
                'estado' => $estado,
                'puntualidad' => $puntualidad,
                'eficiencia' => $eficiencia,
                'dias_acumulados' => $diasAcumulados,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Insertar por lotes para no sobrecargar memoria
            if ($i % 50 == 0) {
                Conductor::insert($conductores);
                $conductores = []; // Reiniciar array
            }
        }

        // Insertar cualquier conductor restante
        if (!empty($conductores)) {
            Conductor::insert($conductores);
        }

        // Información de inserción
        $this->command->info('Se han insertado 260 conductores.');
    }
}
