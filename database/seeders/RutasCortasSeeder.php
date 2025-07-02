<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionTramo;
use App\Models\Parametro;

class RutasCortasSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('🛣️ Configurando parámetros de rutas cortas...');

        // Configurar parámetros específicos de rutas cortas
        $parametrosRutasCortas = [
            [
                'clave' => 'rutas_cortas.maximo_por_dia',
                'valor' => '2',
                'tipo' => 'INTEGER',
                'descripcion' => 'Máximo de rutas cortas por conductor por día',
                'categoria' => 'RUTAS',
                'editable' => true
            ],
            [
                'clave' => 'rutas_cortas.objetivo_semanal_min',
                'valor' => '3',
                'tipo' => 'INTEGER',
                'descripcion' => 'Mínimo de rutas cortas por conductor por semana',
                'categoria' => 'RUTAS',
                'editable' => true
            ],
            [
                'clave' => 'rutas_cortas.objetivo_semanal_max',
                'valor' => '4',
                'tipo' => 'INTEGER',
                'descripcion' => 'Máximo de rutas cortas por conductor por semana',
                'categoria' => 'RUTAS',
                'editable' => true
            ],
            [
                'clave' => 'rutas_cortas.prohibir_consecutivas',
                'valor' => 'true',
                'tipo' => 'BOOLEAN',
                'descripcion' => 'Prohibir rutas cortas en días consecutivos',
                'categoria' => 'RUTAS',
                'editable' => true
            ],
            [
                'clave' => 'rutas_cortas.limite_duracion_corta',
                'valor' => '5.0',
                'tipo' => 'DECIMAL',
                'descripcion' => 'Límite de horas para considerar una ruta como corta',
                'categoria' => 'RUTAS',
                'editable' => true
            ],
            [
                'clave' => 'rutas_cortas.ingreso_base_por_hora',
                'valor' => '25.00',
                'tipo' => 'DECIMAL',
                'descripcion' => 'Ingreso base por hora para rutas cortas',
                'categoria' => 'RUTAS',
                'editable' => true
            ]
        ];

        foreach ($parametrosRutasCortas as $parametro) {
            Parametro::updateOrCreate(
                ['clave' => $parametro['clave']],
                $parametro
            );
        }

        $this->command->info('📊 Configurando tramos según documentos...');

        // Configurar todos los tramos según la tabla de documentos
        $tramosConfiguracion = [
            // RUMBO SUR
            [
                'tramo' => 'LIMA-ICA',
                'rumbo' => 'SUR',
                'duracion_horas' => 5.5,
                'es_ruta_corta' => false, // RUTA LARGA según especificación - PERO CONFIGURABLE
                'ingreso_base' => 137.50,
                'activo' => true,
                'descripcion' => 'Ruta larga principal hacia el sur - CONFIGURABLE'
            ],
            [
                'tramo' => 'LIMA-CAÑETE',
                'rumbo' => 'SUR',
                'duracion_horas' => 2.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 62.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Cañete'
            ],
            [
                'tramo' => 'LIMA-CHINCHA',
                'rumbo' => 'SUR',
                'duracion_horas' => 3.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 87.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Chincha'
            ],
            [
                'tramo' => 'LIMA-PISCO',
                'rumbo' => 'SUR',
                'duracion_horas' => 4.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 112.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Pisco'
            ],
            [
                'tramo' => 'ICA-CAÑETE',
                'rumbo' => 'SUR',
                'duracion_horas' => 3.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 75.00,
                'activo' => true,
                'descripcion' => 'Ruta corta entre ICA y Cañete'
            ],
            [
                'tramo' => 'ICA-CHINCHA',
                'rumbo' => 'SUR',
                'duracion_horas' => 2.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 50.00,
                'activo' => true,
                'descripcion' => 'Ruta corta entre ICA y Chincha'
            ],
            [
                'tramo' => 'ICA-NAZCA',
                'rumbo' => 'SUR',
                'duracion_horas' => 2.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 62.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Nazca'
            ],
            [
                'tramo' => 'ICA-PISCO',
                'rumbo' => 'SUR',
                'duracion_horas' => 1.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 25.00,
                'activo' => true,
                'descripcion' => 'Ruta corta entre ICA y Pisco'
            ],
            [
                'tramo' => 'CAÑETE-CHINCHA',
                'rumbo' => 'SUR',
                'duracion_horas' => 1.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 25.00,
                'activo' => true,
                'descripcion' => 'Ruta corta entre Cañete y Chincha'
            ],

            // RUMBO NORTE
            [
                'tramo' => 'ICA-LIMA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 5.5,
                'es_ruta_corta' => false, // RUTA LARGA según especificación - PERO CONFIGURABLE
                'ingreso_base' => 137.50,
                'activo' => true,
                'descripcion' => 'Ruta larga principal hacia el norte - CONFIGURABLE'
            ],
            [
                'tramo' => 'CAÑETE-LIMA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 2.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 62.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Lima desde Cañete'
            ],
            [
                'tramo' => 'CHINCHA-LIMA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 3.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 87.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Lima desde Chincha'
            ],
            [
                'tramo' => 'PISCO-LIMA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 4.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 112.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Lima desde Pisco'
            ],
            [
                'tramo' => 'CAÑETE-ICA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 3.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 75.00,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia ICA desde Cañete'
            ],
            [
                'tramo' => 'CHINCHA-ICA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 2.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 50.00,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia ICA desde Chincha'
            ],
            [
                'tramo' => 'NAZCA-ICA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 2.5,
                'es_ruta_corta' => true,
                'ingreso_base' => 62.50,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia ICA desde Nazca'
            ],
            [
                'tramo' => 'PISCO-ICA',
                'rumbo' => 'NORTE',
                'duracion_horas' => 1.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 25.00,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia ICA desde Pisco'
            ],
            [
                'tramo' => 'CHINCHA-CAÑETE',
                'rumbo' => 'NORTE',
                'duracion_horas' => 1.0,
                'es_ruta_corta' => true,
                'ingreso_base' => 25.00,
                'activo' => true,
                'descripcion' => 'Ruta corta hacia Cañete desde Chincha'
            ]
        ];

        foreach ($tramosConfiguracion as $tramo) {
            ConfiguracionTramo::updateOrCreate(
                ['tramo' => $tramo['tramo']],
                $tramo
            );
        }

        $this->command->info('✅ Configuración de rutas cortas creada exitosamente.');
        $this->command->info('📊 Se configuraron ' . count($tramosConfiguracion) . ' tramos.');
        $this->command->info('⚙️ Se crearon ' . count($parametrosRutasCortas) . ' parámetros configurables.');
        $this->command->line('');
        $this->command->line('📋 RESUMEN DE CONFIGURACIÓN:');
        $this->command->line('   • Rutas LARGAS: LIMA-ICA, ICA-LIMA (CONFIGURABLES como especificaste)');
        $this->command->line('   • Rutas CORTAS: Todas las demás (' . (count($tramosConfiguracion) - 2) . ' tramos)');
        $this->command->line('   • Máximo por día: 2 rutas');
        $this->command->line('   • Objetivo semanal: 3-4 rutas');
        $this->command->line('   • Prohibidas consecutivas: SÍ');
    }
}
