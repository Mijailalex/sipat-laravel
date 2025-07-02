<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Parametro;

class ParametroSeeder extends Seeder
{
    public function run()
    {
        $parametros = [
            // Configuraciones de Validaciones
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'dias_maximos_sin_descanso',
                'nombre' => 'Días máximos sin descanso',
                'descripcion' => 'Número máximo de días que un conductor puede trabajar sin descanso obligatorio',
                'tipo' => 'INTEGER',
                'valor' => '6',
                'valor_por_defecto' => '6',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1,
                'validaciones' => json_encode(['min' => 1, 'max' => 15])
            ],
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'eficiencia_minima_conductor',
                'nombre' => 'Eficiencia mínima del conductor (%)',
                'descripcion' => 'Porcentaje mínimo de eficiencia requerido para conductores',
                'tipo' => 'DECIMAL',
                'valor' => '80.0',
                'valor_por_defecto' => '80.0',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2,
                'validaciones' => json_encode(['min' => 50.0, 'max' => 100.0])
            ],
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'puntualidad_minima_conductor',
                'nombre' => 'Puntualidad mínima del conductor (%)',
                'descripcion' => 'Porcentaje mínimo de puntualidad requerido para conductores',
                'tipo' => 'DECIMAL',
                'valor' => '85.0',
                'valor_por_defecto' => '85.0',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3,
                'validaciones' => json_encode(['min' => 50.0, 'max' => 100.0])
            ],

            // Configuraciones de Alertas
            [
                'categoria' => 'ALERTAS',
                'clave' => 'enviar_notificaciones_email',
                'nombre' => 'Enviar notificaciones por email',
                'descripcion' => 'Activar o desactivar el envío de notificaciones por correo electrónico',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'true',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1
            ],
            [
                'categoria' => 'ALERTAS',
                'clave' => 'hora_ejecucion_validaciones',
                'nombre' => 'Hora de ejecución de validaciones',
                'descripcion' => 'Hora diaria para ejecutar validaciones automáticas',
                'tipo' => 'TIME',
                'valor' => '08:00',
                'valor_por_defecto' => '08:00',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],
            [
                'categoria' => 'ALERTAS',
                'clave' => 'retener_notificaciones_dias',
                'nombre' => 'Días de retención de notificaciones',
                'descripcion' => 'Número de días para mantener notificaciones antes de eliminarlas automáticamente',
                'tipo' => 'INTEGER',
                'valor' => '30',
                'valor_por_defecto' => '30',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3,
                'validaciones' => json_encode(['min' => 7, 'max' => 365])
            ],

            // Configuraciones de Reportes
            [
                'categoria' => 'REPORTES',
                'clave' => 'formato_fecha_reportes',
                'nombre' => 'Formato de fecha en reportes',
                'descripcion' => 'Formato de fecha utilizado en los reportes generados',
                'tipo' => 'STRING',
                'valor' => 'Y-m-d',
                'valor_por_defecto' => 'Y-m-d',
                'opciones' => json_encode(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1
            ],
            [
                'categoria' => 'REPORTES',
                'clave' => 'generar_reportes_automaticos',
                'nombre' => 'Generar reportes automáticos',
                'descripcion' => 'Generar automáticamente reportes semanales y mensuales',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'true',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],

            // Configuraciones Generales
            [
                'categoria' => 'GENERAL',
                'clave' => 'nombre_empresa',
                'nombre' => 'Nombre de la empresa',
                'descripcion' => 'Nombre de la empresa que aparece en reportes y documentos',
                'tipo' => 'STRING',
                'valor' => 'SIPAT Transport',
                'valor_por_defecto' => 'SIPAT Transport',
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1
            ],
            [
                'categoria' => 'GENERAL',
                'clave' => 'items_por_pagina',
                'nombre' => 'Items por página',
                'descripcion' => 'Número de elementos mostrados por página en las listas',
                'tipo' => 'INTEGER',
                'valor' => '20',
                'valor_por_defecto' => '20',
                'opciones' => json_encode(['10', '20', '50', '100']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],
            [
                'categoria' => 'GENERAL',
                'clave' => 'zona_horaria',
                'nombre' => 'Zona horaria del sistema',
                'descripcion' => 'Zona horaria utilizada para fechas y horas del sistema',
                'tipo' => 'STRING',
                'valor' => 'America/Lima',
                'valor_por_defecto' => 'America/Lima',
                'opciones' => json_encode(['America/Lima', 'UTC', 'America/New_York', 'Europe/Madrid']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3
            ],

            // Configuraciones de Sistema (no modificables)
            [
                'categoria' => 'SISTEMA',
                'clave' => 'version_sistema',
                'nombre' => 'Versión del sistema',
                'descripcion' => 'Versión actual del sistema SIPAT',
                'tipo' => 'STRING',
                'valor' => '1.0.0',
                'valor_por_defecto' => '1.0.0',
                'modificable' => false,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1
            ],
            [
                'categoria' => 'SISTEMA',
                'clave' => 'fecha_instalacion',
                'nombre' => 'Fecha de instalación',
                'descripcion' => 'Fecha en que se instaló el sistema',
                'tipo' => 'DATE',
                'valor' => now()->toDateString(),
                'valor_por_defecto' => now()->toDateString(),
                'modificable' => false,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ]
        ];

        foreach ($parametros as $parametro) {
            Parametro::create($parametro);
        }

        $this->command->info('✅ Parámetros del sistema creados exitosamente');
    }
}
