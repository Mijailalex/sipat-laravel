<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bus;

class BusSeeder extends Seeder
{
    public function run()
    {
        $buses = [
            [
                'codigo' => 'B001',
                'placa' => 'ABC-123',
                'tipo' => 'PERU_BUS',
                'estado' => 'OPERATIVO',
                'origen_disponibilidad' => 'LIMA',
                'hora_disponibilidad' => '05:30:00'
            ],
            [
                'codigo' => 'B002',
                'placa' => 'DEF-456',
                'tipo' => 'PERU_BUS',
                'estado' => 'OPERATIVO',
                'origen_disponibilidad' => 'ICA',
                'hora_disponibilidad' => '06:00:00'
            ],
            [
                'codigo' => 'B003',
                'placa' => 'GHI-789',
                'tipo' => 'PERU_BUS_CHICO',
                'estado' => 'OPERATIVO',
                'origen_disponibilidad' => 'CHINCHA',
                'hora_disponibilidad' => '05:45:00'
            ],
            [
                'codigo' => 'B004',
                'placa' => 'JKL-012',
                'tipo' => 'PERU_BUS',
                'estado' => 'MANTENIMIENTO',
                'origen_disponibilidad' => 'PISCO',
                'hora_disponibilidad' => null
            ],
            [
                'codigo' => 'B005',
                'placa' => 'MNO-345',
                'tipo' => 'PERU_BUS_CHICO',
                'estado' => 'OPERATIVO',
                'origen_disponibilidad' => 'CAÑETE',
                'hora_disponibilidad' => '07:00:00'
            ]
        ];

        foreach ($buses as $bus) {
            Bus::create($bus);
        }
    }
}
