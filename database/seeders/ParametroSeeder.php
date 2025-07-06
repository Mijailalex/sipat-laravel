<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Parametro;
use Illuminate\Support\Facades\DB;

class ParametroSeeder extends Seeder
{
    public function run()
    {
        // Limpiar tabla antes de poblar (opcional)
        // DB::table('parametros')->truncate();

        $parametros = [
            // Parámetros de Validaciones
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'max_dias_validacion',
                'nombre' => 'Máximo días para validación',
                'descripcion' => 'Número máximo de días permitidos para completar una validación de conductor',
                'tipo' => 'INTEGER',
                'valor' => '7',
                'valor_por_defecto' => '5',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1
            ],
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'validacion_automatica',
                'nombre' => 'Validación automática activa',
                'descripcion' => 'Activar el sistema de validaciones automáticas para conductores',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'true',
                'opciones' => json_encode(['true', 'false']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'umbral_critico',
                'nombre' => 'Umbral crítico de validaciones',
                'descripcion' => 'Porcentaje de validaciones fallidas que activa alertas críticas',
                'tipo' => 'DECIMAL',
                'valor' => '15.50',
                'valor_por_defecto' => '20.00',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3
            ],
            [
                'categoria' => 'VALIDACIONES',
                'clave' => 'tipos_validacion_requeridos',
                'nombre' => 'Tipos de validación requeridos',
                'descripcion' => 'Lista de tipos de validación que son obligatorios',
                'tipo' => 'JSON',
                'valor' => '["LICENCIA", "ANTECEDENTES", "EXAMEN_MEDICO"]',
                'valor_por_defecto' => '["LICENCIA", "ANTECEDENTES"]',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 4
            ],

            // Parámetros de Reportes
            [
                'categoria' => 'REPORTES',
                'clave' => 'formato_fecha',
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
                'opciones' => json_encode(['true', 'false']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],
            [
                'categoria' => 'REPORTES',
                'clave' => 'hora_generacion_reportes',
                'nombre' => 'Hora de generación de reportes',
                'descripcion' => 'Hora diaria para generar reportes automáticos',
                'tipo' => 'TIME',
                'valor' => '06:00:00',
                'valor_por_defecto' => '06:00:00',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3
            ],
            [
                'categoria' => 'REPORTES',
                'clave' => 'incluir_graficos',
                'nombre' => 'Incluir gráficos en reportes',
                'descripcion' => 'Agregar gráficos estadísticos a los reportes PDF',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'false',
                'opciones' => json_encode(['true', 'false']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 4
            ],

            // Parámetros Generales
            [
                'categoria' => 'GENERAL',
                'clave' => 'nombre_empresa',
                'nombre' => 'Nombre de la empresa',
                'descripcion' => 'Nombre de la empresa que aparece en reportes y documentos',
                'tipo' => 'STRING',
                'valor' => 'SIPAT Transport',
                'valor_por_defecto' => 'SIPAT Transport',
                'opciones' => null,
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
                'clave' => 'timezone',
                'nombre' => 'Zona horaria del sistema',
                'descripcion' => 'Zona horaria utilizada por todo el sistema',
                'tipo' => 'STRING',
                'valor' => 'America/Lima',
                'valor_por_defecto' => 'America/Lima',
                'opciones' => json_encode(['America/Lima', 'UTC', 'America/New_York', 'Europe/Madrid']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3
            ],
            [
                'categoria' => 'GENERAL',
                'clave' => 'modo_mantenimiento',
                'nombre' => 'Modo de mantenimiento',
                'descripcion' => 'Activar modo de mantenimiento para el sistema',
                'tipo' => 'BOOLEAN',
                'valor' => 'false',
                'valor_por_defecto' => 'false',
                'opciones' => json_encode(['true', 'false']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 4
            ],

            // Parámetros de Conductores
            [
                'categoria' => 'CONDUCTORES',
                'clave' => 'max_dias_inactividad',
                'nombre' => 'Máximo días de inactividad',
                'descripcion' => 'Días máximos sin actividad antes de marcar conductor como inactivo',
                'tipo' => 'INTEGER',
                'valor' => '30',
                'valor_por_defecto' => '30',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1
            ],
            [
                'categoria' => 'CONDUCTORES',
                'clave' => 'renovacion_automatica_licencia',
                'nombre' => 'Renovación automática de licencia',
                'descripcion' => 'Enviar recordatorios automáticos para renovación de licencias',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'true',
                'opciones' => json_encode(['true', 'false']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],
            [
                'categoria' => 'CONDUCTORES',
                'clave' => 'score_minimo_operacion',
                'nombre' => 'Score mínimo para operación',
                'descripcion' => 'Puntuación mínima requerida para que un conductor pueda operar',
                'tipo' => 'DECIMAL',
                'valor' => '75.00',
                'valor_por_defecto' => '70.00',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3
            ],
            [
                'categoria' => 'CONDUCTORES',
                'clave' => 'edad_maxima_operacion',
                'nombre' => 'Edad máxima para operación',
                'descripcion' => 'Edad máxima permitida para conductores activos',
                'tipo' => 'INTEGER',
                'valor' => '65',
                'valor_por_defecto' => '65',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 4
            ],

            // Parámetros de Alertas
            [
                'categoria' => 'ALERTAS',
                'clave' => 'activar_notificaciones_email',
                'nombre' => 'Activar notificaciones por email',
                'descripcion' => 'Enviar alertas importantes por correo electrónico',
                'tipo' => 'BOOLEAN',
                'valor' => 'true',
                'valor_por_defecto' => 'true',
                'opciones' => json_encode(['true', 'false']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 1
            ],
            [
                'categoria' => 'ALERTAS',
                'clave' => 'nivel_alerta_defecto',
                'nombre' => 'Nivel de alerta por defecto',
                'descripcion' => 'Nivel de severidad por defecto para nuevas alertas',
                'tipo' => 'STRING',
                'valor' => 'ADVERTENCIA',
                'valor_por_defecto' => 'INFO',
                'opciones' => json_encode(['INFO', 'ADVERTENCIA', 'CRITICA']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],
            [
                'categoria' => 'ALERTAS',
                'clave' => 'tiempo_retencion_alertas',
                'nombre' => 'Tiempo de retención de alertas',
                'descripcion' => 'Días que se mantienen las alertas en el sistema antes de archivarse',
                'tipo' => 'INTEGER',
                'valor' => '90',
                'valor_por_defecto' => '90',
                'opciones' => null,
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 3
            ],
            [
                'categoria' => 'ALERTAS',
                'clave' => 'frecuencia_notificaciones',
                'nombre' => 'Frecuencia de notificaciones',
                'descripcion' => 'Con qué frecuencia enviar resúmenes de alertas',
                'tipo' => 'STRING',
                'valor' => 'DIARIA',
                'valor_por_defecto' => 'DIARIA',
                'opciones' => json_encode(['INMEDIATA', 'DIARIA', 'SEMANAL']),
                'modificable' => true,
                'visible_interfaz' => true,
                'orden_visualizacion' => 4
            ],

            // Parámetros del Sistema (No modificables)
            [
                'categoria' => 'SISTEMA',
                'clave' => 'version_aplicacion',
                'nombre' => 'Versión de la aplicación',
                'descripcion' => 'Versión actual del sistema SIPAT',
                'tipo' => 'STRING',
                'valor' => '1.0.0',
                'valor_por_defecto' => '1.0.0',
                'opciones' => null,
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
                'valor' => now()->format('Y-m-d'),
                'valor_por_defecto' => now()->format('Y-m-d'),
                'opciones' => null,
                'modificable' => false,
                'visible_interfaz' => true,
                'orden_visualizacion' => 2
            ],
            [
                'categoria' => 'SISTEMA',
                'clave' => 'clave_encriptacion',
                'nombre' => 'Clave de encriptación',
                'descripcion' => 'Clave utilizada para encriptar datos sensibles',
                'tipo' => 'STRING',
                'valor' => 'sistema_interno_no_modificar',
                'valor_por_defecto' => 'sistema_interno_no_modificar',
                'opciones' => null,
                'modificable' => false,
                'visible_interfaz' => false,
                'orden_visualizacion' => 99
            ]
        ];

        // Insertar parámetros uno por uno para manejar errores individualmente
        foreach ($parametros as $parametroData) {
            try {
                // Verificar si ya existe el parámetro
                $existe = Parametro::where('clave', $parametroData['clave'])->first();

                if (!$existe) {
                    // Agregar timestamp y usuario creador
                    $parametroData['created_at'] = now();
                    $parametroData['updated_at'] = now();
                    $parametroData['modificado_por'] = 1; // Asumiendo que el usuario admin tiene ID 1

                    Parametro::create($parametroData);

                    echo "✅ Parámetro creado: {$parametroData['clave']}\n";
                } else {
                    echo "⚠️  Parámetro ya existe: {$parametroData['clave']}\n";
                }

            } catch (\Exception $e) {
                echo "❌ Error creando parámetro {$parametroData['clave']}: " . $e->getMessage() . "\n";
            }
        }

        echo "\n🎉 Seeder de parámetros completado!\n";
        echo "📊 Total de parámetros en el sistema: " . Parametro::count() . "\n";
        echo "📋 Categorías disponibles: " . Parametro::distinct('categoria')->count() . "\n";

        // Mostrar resumen por categoría
        $resumen = Parametro::selectRaw('categoria, count(*) as total')
                            ->groupBy('categoria')
                            ->orderBy('categoria')
                            ->get();

        echo "\n📈 Resumen por categoría:\n";
        foreach ($resumen as $item) {
            echo "   • {$item->categoria}: {$item->total} parámetros\n";
        }
    }
}
