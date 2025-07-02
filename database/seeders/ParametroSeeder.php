<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Parametro;

class ParametroSeeder extends Seeder
{
    public function run()
    {
        $parametros = [
            // Parámetros de Turnos
            [
                'clave' => 'turnos.cantidad_semanal',
                'valor' => '431',
                'tipo' => 'INTEGER',
                'descripcion' => 'Cantidad total de turnos semanales planificados',
                'categoria' => 'TURNOS',
                'editable' => true
            ],
            [
                'clave' => 'turnos.cobertura_objetivo',
                'valor' => '98.5',
                'tipo' => 'DECIMAL',
                'descripcion' => 'Porcentaje objetivo de cobertura de turnos',
                'categoria' => 'TURNOS',
                'editable' => true
            ],

            // Parámetros de Descansos
            [
                'clave' => 'descansos.regimen_6x1',
                'valor' => 'true',
                'tipo' => 'BOOLEAN',
                'descripcion' => 'Activar régimen de 6 días trabajados por 1 de descanso',
                'categoria' => 'DESCANSOS',
                'editable' => true
            ],
            [
                'clave' => 'descansos.regimen_26x4',
                'valor' => 'false',
                'tipo' => 'BOOLEAN',
                'descripcion' => 'Activar régimen de 26 días trabajados por 4 de descanso',
                'categoria' => 'DESCANSOS',
                'editable' => true
            ],
            [
                'clave' => 'descansos.horas_minimas_entre_turnos',
                'valor' => '12',
                'tipo' => 'INTEGER',
                'descripcion' => 'Horas mínimas de descanso entre turnos',
                'categoria' => 'DESCANSOS',
                'editable' => true
            ],

            // Parámetros de Rutas Cortas
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

            // Parámetros de Validaciones
            [
                'clave' => 'validaciones.auto_ejecutar',
                'valor' => 'true',
                'tipo' => 'BOOLEAN',
                'descripcion' => 'Ejecutar validaciones automáticamente al generar plantillas',
                'categoria' => 'VALIDACIONES',
                'editable' => true
            ],
            [
                'clave' => 'validaciones.notificar_criticas',
                'valor' => 'true',
                'tipo' => 'BOOLEAN',
                'descripcion' => 'Enviar notificaciones para validaciones críticas',
                'categoria' => 'VALIDACIONES',
                'editable' => true
            ]
        ];

        foreach ($parametros as $parametro) {
            Parametro::create($parametro);
        }
    }
}
