<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;

class ConfiguracionSistemaSeeder extends Seeder
{
    public function run()
    {
        $configuraciones = [
            // Configuraciones de Validaciones
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'dias_maximos_sin_descanso',
                'nombre' => 'Días máximos sin descanso',
                'descripcion' => 'Número máximo de días que un conductor puede trabajar sin descanso',
                'tipo' => 'INTEGER',
                'valor' => '6',
                'valor_por_defecto' => '6',
                'unidad' => 'días'
            ],
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'eficiencia_minima_conductor',
                'nombre' => 'Eficiencia mínima del conductor',
                'descripcion' => 'Porcentaje mínimo de eficiencia para no generar alertas',
                'tipo' => 'INTEGER',
                'valor' => '80',
                'valor_por_defecto' => '80',
                'unidad' => '%'
            ],
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'puntualidad_minima_conductor',
                'nombre' => 'Puntualidad mínima del conductor',
                'descripcion' => 'Porcentaje mínimo de puntualidad para no generar alertas',
                'tipo' => 'INTEGER',
                'valor' => '85',
                'valor_por_defecto' => '85',
                'unidad' => '%'
            ],

            // Configuraciones de Alertas
            [
                'categoria' => 'ALERTAS',
                'clave' => 'enviar_notificaciones_email',
                'nombre' => 'Enviar notificaciones por email',
                'descripcion' => 'Activar el envío de notificaciones críticas por correo electrónico',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'true'
            ],
            [
                'categoria' => 'ALERTAS',
                'clave' => 'hora_ejecucion_validaciones',
                'nombre' => 'Hora de ejecución de validaciones',
                'descripcion' => 'Hora del día para ejecutar las validaciones automáticas',
                'tipo' => 'TIME',
                'valor' => '06:00',
                'valor_por_defecto' => '06:00',
                'unidad' => 'HH:MM'
            ],
            [
                'categoria' => 'ALERTAS',
                'clave' => 'retener_notificaciones_dias',
                'nombre' => 'Días de retención de notificaciones',
                'descripcion' => 'Número de días que se mantienen las notificaciones en el sistema',
                'tipo' => 'INTEGER',
                'valor' => '30',
                'valor_por_defecto' => '30',
                'unidad' => 'días'
            ],

            // Configuraciones de Reportes
            [
                'categoria' => 'REPORTES',
                'clave' => 'formato_fecha_reportes',
                'nombre' => 'Formato de fecha en reportes',
                'descripcion' => 'Formato de fecha utilizado en los reportes generados',
                'tipo' => 'STRING',
                'valor' => 'd/m/Y',
                'valor_por_defecto' => 'd/m/Y',
                'opciones' => ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y']
            ],
            [
                'categoria' => 'REPORTES',
                'clave' => 'generar_reportes_automaticos',
                'nombre' => 'Generar reportes automáticos',
                'descripcion' => 'Generar automáticamente reportes semanales y mensuales',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'true'
            ],

            // Configuraciones Generales
            [
                'categoria' => 'GENERAL',
                'clave' => 'nombre_empresa',
                'nombre' => 'Nombre de la empresa',
                'descripcion' => 'Nombre de la empresa que aparece en reportes y documentos',
                'tipo' => 'STRING',
                'valor' => 'SIPAT Transport',
                'valor_por_defecto' => 'SIPAT Transport'
            ],
            [
                'categoria' => 'GENERAL',
                'clave' => 'items_por_pagina',
                'nombre' => 'Items por página',
                'descripción' => 'Número de elementos mostrados por página en las listas',
                'tipo' => 'INTEGER',
                'valor' => '20',
                'valor_por_defecto' => '20',
                'opciones' => ['10', '20', '50', '100']
            ],
            [
                'categoria' => 'GENERAL',
                'clave' => 'zona_horaria',
                'nombre' => 'Zona horaria del sistema',
                'descripcion' => 'Zona horaria utilizada para fechas y horas del sistema',
                'tipo' => 'STRING',
                'valor' => 'America/Lima',
                'valor_por_defecto' => 'America/Lima',
                'opciones' => ['America/Lima', 'UTC', 'America/New_York', 'Europe/Madrid']
            ]
        ];

        foreach ($configuraciones as $config) {
            ConfiguracionSistema::create($config);
        }
    }
}
