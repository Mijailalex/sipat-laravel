<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('🌱 Iniciando población de base de datos SIPAT...');

        // 1. Parámetros del sistema (primero)
        $this->command->line('📊 Creando parámetros del sistema...');
        $this->call([
            ParametroSeeder::class,
        ]);

        // 2. Configuración de rutas cortas
        $this->command->line('🛣️ Configurando tramos y rutas cortas...');
        $this->call([
            RutasCortasSeeder::class,
        ]);

        // 3. Conductores (después de parámetros)
        $this->command->line('👥 Creando conductores de ejemplo...');
        $this->call([
            ConductorSeeder::class,
        ]);

        // 4. Buses
        $this->command->line('🚌 Creando buses del sistema...');
        $this->call([
            BusSeeder::class,
        ]);

        // 5. Datos de ejemplo de rutas cortas (último)
        $this->command->line('📋 Creando rutas cortas de ejemplo...');
        $this->call([
            RutasCortasEjemploSeeder::class,
        ]);

        $this->command->info('✅ Base de datos poblada exitosamente!');
        $this->command->line('');
        $this->command->line('🎯 RESUMEN DE DATOS CREADOS:');

        // Mostrar estadísticas reales
        $conductores = \App\Models\Conductor::count();
        $tramos = \App\Models\ConfiguracionTramo::count();
        $buses = \App\Models\Bus::count();
        $rutas = class_exists('\App\Models\RutaCorta') ? \App\Models\RutaCorta::count() : 0;

        $this->command->line("   • {$conductores} conductores de ejemplo");
        $this->command->line("   • {$tramos} tramos configurados");
        $this->command->line("   • {$buses} buses registrados");
        $this->command->line("   • {$rutas} rutas cortas de ejemplo");
        $this->command->line('   • Parámetros configurables del sistema');
        $this->command->line('');
        $this->command->line('🚀 ¡SIPAT está listo para usar!');
        $this->command->line('   Accede a: http://localhost:8000/dashboard');
        $this->command->line('   Rutas Cortas: http://localhost:8000/rutas-cortas');
    }
}
