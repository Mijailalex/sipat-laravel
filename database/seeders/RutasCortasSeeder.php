<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionTramo;

class RutasCortasSeeder extends Seeder
{
    public function run()
    {
        $tramos = [
            [
                'codigo_tramo' => 'TR001',
                'nombre' => 'Lima Centro - Callao',
                'origen' => 'Plaza San Martín',
                'destino' => 'Plaza de Armas del Callao',
                'distancia_km' => 15.5,
                'tarifa_base' => 3.50,
                'tarifa_maxima' => 5.00,
                'tiempo_estimado_minutos' => 45,
                'tipo_servicio' => 'URBANO',
                'activo' => true,
                'horarios_disponibles' => [
                    [
                        'dias' => [1, 2, 3, 4, 5], // Lunes a Viernes
                        'hora_inicio' => '05:00',
                        'hora_fin' => '23:00'
                    ],
                    [
                        'dias' => [6, 0], // Sábado y Domingo
                        'hora_inicio' => '06:00',
                        'hora_fin' => '22:00'
                    ]
                ],
                'restricciones' => [
                    'max_pasajeros' => 25,
                    'min_tarifa' => 2.50
                ],
                'descripcion' => 'Ruta principal que conecta el centro de Lima con el Callao'
            ],
            [
                'codigo_tramo' => 'TR002',
                'nombre' => 'Miraflores - San Isidro',
                'origen' => 'Parque Kennedy',
                'destino' => 'Centro Empresarial San Isidro',
                'distancia_km' => 8.2,
                'tarifa_base' => 2.50,
                'tarifa_maxima' => 4.00,
                'tiempo_estimado_minutos' => 25,
                'tipo_servicio' => 'URBANO',
                'activo' => true,
                'horarios_disponibles' => [
                    [
                        'dias' => [1, 2, 3, 4, 5],
                        'hora_inicio' => '06:00',
                        'hora_fin' => '22:00'
                    ]
                ],
                'descripcion' => 'Ruta ejecutiva entre distritos empresariales'
            ],
            [
                'codigo_tramo' => 'TR003',
                'nombre' => 'Ate - La Victoria',
                'origen' => 'Paradero Ate Vitarte',
                'destino' => 'Mercado Mayorista',
                'distancia_km' => 12.8,
                'tarifa_base' => 3.00,
                'tarifa_maxima' => 4.50,
                'tiempo_estimado_minutos' => 35,
                'tipo_servicio' => 'URBANO',
                'activo' => true,
                'descripcion' => 'Ruta comercial hacia mercados mayoristas'
            ],
            [
                'codigo_tramo' => 'TR004',
                'nombre' => 'Los Olivos - Independencia',
                'origen' => 'Megaplaza',
                'destino' => 'Plaza Túpac Amaru',
                'distancia_km' => 6.5,
                'tarifa_base' => 2.00,
                'tarifa_maxima' => 3.50,
                'tiempo_estimado_minutos' => 20,
                'tipo_servicio' => 'URBANO',
                'activo' => true
            ],
            [
                'codigo_tramo' => 'TR005',
                'nombre' => 'Villa El Salvador - Chorrillos',
                'origen' => 'Parque Industrial VES',
                'destino' => 'Playa La Herradura',
                'distancia_km' => 18.3,
                'tarifa_base' => 4.00,
                'tarifa_maxima' => 6.00,
                'tiempo_estimado_minutos' => 55,
                'tipo_servicio' => 'INTERURBANO',
                'activo' => true
            ],
            [
                'codigo_tramo' => 'TR006',
                'nombre' => 'Lima - Huacho',
                'origen' => 'Terminal Plaza Norte',
                'destino' => 'Terminal Huacho',
                'distancia_km' => 147.5,
                'tarifa_base' => 15.00,
                'tarifa_maxima' => 25.00,
                'tiempo_estimado_minutos' => 180,
                'tipo_servicio' => 'INTERURBANO',
                'activo' => true,
                'horarios_disponibles' => [
                    [
                        'dias' => [1, 2, 3, 4, 5, 6, 0],
                        'hora_inicio' => '05:00',
                        'hora_fin' => '20:00'
                    ]
                ],
                'restricciones' => [
                    'vehiculos_permitidos' => ['BUS_INTERPROVINCIAL'],
                    'conductores_autorizados' => 'categoria_A3'
                ]
            ],
            [
                'codigo_tramo' => 'TR007',
                'nombre' => 'Barranco - Miraflores',
                'origen' => 'Puente de los Suspiros',
                'destino' => 'Larcomar',
                'distancia_km' => 4.2,
                'tarifa_base' => 2.00,
                'tarifa_maxima' => 3.00,
                'tiempo_estimado_minutos' => 15,
                'tipo_servicio' => 'ESPECIAL',
                'activo' => true,
                'descripcion' => 'Ruta turística costera'
            ],
            [
                'codigo_tramo' => 'TR008',
                'nombre' => 'San Juan de Lurigancho - Cercado',
                'origen' => 'Paradero Canto Rey',
                'destino' => 'Plaza de Armas de Lima',
                'distancia_km' => 22.1,
                'tarifa_base' => 3.50,
                'tarifa_maxima' => 5.50,
                'tiempo_estimado_minutos' => 65,
                'tipo_servicio' => 'URBANO',
                'activo' => true
            ],
            [
                'codigo_tramo' => 'TR009',
                'nombre' => 'Comas - Lima Centro',
                'origen' => 'Universidad Nacional Mayor de San Marcos Norte',
                'destino' => 'Jirón de la Unión',
                'distancia_km' => 19.7,
                'tarifa_base' => 3.00,
                'tarifa_maxima' => 4.50,
                'tiempo_estimado_minutos' => 50,
                'tipo_servicio' => 'URBANO',
                'activo' => true
            ],
            [
                'codigo_tramo' => 'TR010',
                'nombre' => 'Aeropuerto - Miraflores',
                'origen' => 'Jorge Chávez International Airport',
                'destino' => 'Hotel Costa del Sol',
                'distancia_km' => 19.8,
                'tarifa_base' => 8.00,
                'tarifa_maxima' => 15.00,
                'tiempo_estimado_minutos' => 40,
                'tipo_servicio' => 'ESPECIAL',
                'activo' => true,
                'descripcion' => 'Servicio especial aeroportuario'
            ]
        ];

        foreach ($tramos as $tramo) {
            // Convertir arrays a JSON para los campos correspondientes
            if (isset($tramo['horarios_disponibles'])) {
                $tramo['horarios_disponibles'] = json_encode($tramo['horarios_disponibles']);
            }
            if (isset($tramo['restricciones'])) {
                $tramo['restricciones'] = json_encode($tramo['restricciones']);
            }

            ConfiguracionTramo::create($tramo);
        }

        $this->command->info('✅ ' . ConfiguracionTramo::count() . ' tramos configurados exitosamente');

        // Estadísticas
        $urbanos = ConfiguracionTramo::where('tipo_servicio', 'URBANO')->count();
        $interurbanos = ConfiguracionTramo::where('tipo_servicio', 'INTERURBANO')->count();
        $especiales = ConfiguracionTramo::where('tipo_servicio', 'ESPECIAL')->count();

        $this->command->line("   • {$urbanos} tramos urbanos");
        $this->command->line("   • {$interurbanos} tramos interurbanos");
        $this->command->line("   • {$especiales} tramos especiales");
    }
}
