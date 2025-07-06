<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Conductor;
use App\Models\Bus;
use App\Models\Parametro;
use App\Models\Plantilla;
use App\Models\Turno;
use App\Models\Validacion;
use App\Models\RutaCorta;
use App\Models\HistorialPlanificacion;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DatosInicialesSeeder extends Seeder
{
    private $faker;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->faker = Faker::create('es_ES');

        $this->command->info('🌱 Iniciando población de datos iniciales del sistema SIPAT...');

        // Deshabilitar verificación de claves foráneas temporalmente
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // 1. Crear roles y permisos del sistema
            $this->crearRolesYPermisos();

            // 2. Crear usuarios del sistema
            $this->crearUsuarios();

            // 3. Crear parámetros de configuración
            $this->crearParametrosConfiguracion();

            // 4. Crear buses del sistema
            $this->crearBuses();

            // 5. Crear conductores de ejemplo
            $this->crearConductores();

            // 6. Crear rutas cortas históricas
            $this->crearRutasCortas();

            // 7. Crear plantillas de ejemplo
            $this->crearPlantillas();

            // 8. Crear turnos para las plantillas
            $this->crearTurnos();

            // 9. Crear validaciones de ejemplo
            $this->crearValidaciones();

            // 10. Crear historial de planificaciones
            $this->crearHistorialPlanificaciones();

            // 11. Datos finales y verificación
            $this->verificarDatosCreados();

        } finally {
            // Rehabilitar verificación de claves foráneas
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->command->info('✅ Población de datos iniciales completada exitosamente!');
    }

    /**
     * Crear roles y permisos del sistema
     */
    private function crearRolesYPermisos()
    {
        $this->command->info('👥 Creando roles y permisos...');

        // Limpiar roles y permisos existentes
        DB::table('model_has_roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('role_has_permissions')->delete();
        Role::query()->delete();
        Permission::query()->delete();

        // Crear permisos
        $permisos = [
            // Gestión de conductores
            'ver_conductores' => 'Ver lista de conductores',
            'crear_conductores' => 'Crear nuevos conductores',
            'editar_conductores' => 'Editar información de conductores',
            'eliminar_conductores' => 'Eliminar conductores',
            'gestionar_estados_conductores' => 'Cambiar estados de conductores',

            // Planificación
            'ver_planificacion' => 'Ver planificaciones',
            'crear_planificacion' => 'Crear nuevas planificaciones',
            'editar_planificacion' => 'Editar planificaciones',
            'ejecutar_planificacion_automatica' => 'Ejecutar planificación automática',
            'aprobar_planificacion' => 'Aprobar planificaciones',

            // Replanificación
            'gestionar_replanificacion' => 'Gestionar replanificaciones',
            'replanificacion_emergencia' => 'Realizar replanificaciones de emergencia',

            // Validaciones
            'ver_validaciones' => 'Ver validaciones',
            'resolver_validaciones' => 'Resolver validaciones',
            'ignorar_validaciones' => 'Ignorar validaciones',
            'configurar_validaciones' => 'Configurar reglas de validación',

            // Reportes
            'ver_reportes' => 'Ver reportes',
            'generar_reportes' => 'Generar reportes',
            'exportar_reportes' => 'Exportar reportes',

            // Administración
            'administrar_usuarios' => 'Administrar usuarios del sistema',
            'gestionar_credenciales' => 'Gestionar credenciales y seguridad',
            'configurar_sistema' => 'Configurar parámetros del sistema',
            'gestionar_backups' => 'Gestionar backups del sistema',
            'ver_historial_completo' => 'Ver historial completo del sistema',
            'gestionar_sistema' => 'Gestión completa del sistema'
        ];

        foreach ($permisos as $nombre => $descripcion) {
            Permission::create([
                'name' => $nombre,
                'guard_name' => 'web',
                'description' => $descripcion
            ]);
        }

        // Crear roles
        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'description' => 'Administrador del sistema con acceso completo'
        ]);

        $supervisorRole = Role::create([
            'name' => 'supervisor',
            'guard_name' => 'web',
            'description' => 'Supervisor de planificación con permisos de gestión'
        ]);

        $operadorRole = Role::create([
            'name' => 'operador',
            'guard_name' => 'web',
            'description' => 'Operador del sistema con permisos básicos'
        ]);

        $auditorRole = Role::create([
            'name' => 'auditor',
            'guard_name' => 'web',
            'description' => 'Auditor con acceso de solo lectura'
        ]);

        // Asignar permisos a roles
        $adminRole->givePermissionTo(Permission::all());

        $supervisorRole->givePermissionTo([
            'ver_conductores', 'crear_conductores', 'editar_conductores', 'gestionar_estados_conductores',
            'ver_planificacion', 'crear_planificacion', 'editar_planificacion', 'ejecutar_planificacion_automatica', 'aprobar_planificacion',
            'gestionar_replanificacion',
            'ver_validaciones', 'resolver_validaciones', 'ignorar_validaciones',
            'ver_reportes', 'generar_reportes', 'exportar_reportes'
        ]);

        $operadorRole->givePermissionTo([
            'ver_conductores', 'editar_conductores',
            'ver_planificacion', 'crear_planificacion',
            'ver_validaciones', 'resolver_validaciones',
            'ver_reportes'
        ]);

        $auditorRole->givePermissionTo([
            'ver_conductores', 'ver_planificacion', 'ver_validaciones', 'ver_reportes', 'ver_historial_completo'
        ]);

        $this->command->line('   ✓ Creados 4 roles con ' . count($permisos) . ' permisos');
    }

    /**
     * Crear usuarios del sistema
     */
    private function crearUsuarios()
    {
        $this->command->info('👤 Creando usuarios del sistema...');

        // Usuario administrador principal
        $admin = User::create([
            'name' => 'Administrador SIPAT',
            'email' => 'admin@sipat.com',
            'password' => Hash::make('admin123456'),
            'activo' => true,
            'debe_cambiar_password' => false,
            'fecha_ultimo_acceso' => now(),
            'email_verified_at' => now()
        ]);
        $admin->assignRole('admin');

        // Supervisor de planificación
        $supervisor = User::create([
            'name' => 'Carlos Supervisor',
            'email' => 'supervisor@sipat.com',
            'password' => Hash::make('supervisor123'),
            'activo' => true,
            'debe_cambiar_password' => true,
            'fecha_ultimo_acceso' => now()->subHours(2)
        ]);
        $supervisor->assignRole('supervisor');

        // Operador del sistema
        $operador = User::create([
            'name' => 'María Operadora',
            'email' => 'operador@sipat.com',
            'password' => Hash::make('operador123'),
            'activo' => true,
            'debe_cambiar_password' => false,
            'fecha_ultimo_acceso' => now()->subMinutes(30)
        ]);
        $operador->assignRole('operador');

        // Auditor
        $auditor = User::create([
            'name' => 'Juan Auditor',
            'email' => 'auditor@sipat.com',
            'password' => Hash::make('auditor123'),
            'activo' => true,
            'debe_cambiar_password' => false,
            'fecha_ultimo_acceso' => now()->subDays(1)
        ]);
        $auditor->assignRole('auditor');

        // Usuarios adicionales para pruebas
        for ($i = 1; $i <= 3; $i++) {
            $usuario = User::create([
                'name' => $this->faker->name,
                'email' => "usuario{$i}@sipat.com",
                'password' => Hash::make('usuario123'),
                'activo' => $this->faker->boolean(80),
                'debe_cambiar_password' => $this->faker->boolean(30),
                'fecha_ultimo_acceso' => $this->faker->dateTimeBetween('-1 week', 'now')
            ]);
            $usuario->assignRole($this->faker->randomElement(['operador', 'auditor']));
        }

        $this->command->line('   ✓ Creados ' . User::count() . ' usuarios del sistema');
    }

    /**
     * Crear parámetros de configuración
     */
    private function crearParametrosConfiguracion()
    {
        $this->command->info('⚙️ Creando parámetros de configuración...');

        $parametros = [
            // Validaciones
            ['categoria' => 'VALIDACIONES', 'clave' => 'dias_maximos_sin_descanso', 'valor' => '6', 'tipo' => 'INTEGER'],
            ['categoria' => 'VALIDACIONES', 'clave' => 'eficiencia_minima_conductor', 'valor' => '80', 'tipo' => 'INTEGER'],
            ['categoria' => 'VALIDACIONES', 'clave' => 'puntualidad_minima_conductor', 'valor' => '85', 'tipo' => 'INTEGER'],
            ['categoria' => 'VALIDACIONES', 'clave' => 'horas_minimas_descanso', 'valor' => '12', 'tipo' => 'INTEGER'],

            // Alertas
            ['categoria' => 'ALERTAS', 'clave' => 'enviar_notificaciones_email', 'valor' => 'true', 'tipo' => 'BOOLEAN'],
            ['categoria' => 'ALERTAS', 'clave' => 'hora_ejecucion_validaciones', 'valor' => '06:00', 'tipo' => 'TIME'],
            ['categoria' => 'ALERTAS', 'clave' => 'emails_notificacion_backup', 'valor' => 'admin@sipat.com', 'tipo' => 'STRING'],
            ['categoria' => 'ALERTAS', 'clave' => 'retener_notificaciones_dias', 'valor' => '30', 'tipo' => 'INTEGER'],

            // Reportes
            ['categoria' => 'REPORTES', 'clave' => 'formato_fecha_reportes', 'valor' => 'd/m/Y', 'tipo' => 'STRING'],
            ['categoria' => 'REPORTES', 'clave' => 'generar_reportes_automaticos', 'valor' => 'true', 'tipo' => 'BOOLEAN'],
            ['categoria' => 'REPORTES', 'clave' => 'hora_generacion_reportes', 'valor' => '23:00', 'tipo' => 'TIME'],

            // General
            ['categoria' => 'GENERAL', 'clave' => 'nombre_empresa', 'valor' => 'SIPAT Transport', 'tipo' => 'STRING'],
            ['categoria' => 'GENERAL', 'clave' => 'items_por_pagina', 'valor' => '20', 'tipo' => 'INTEGER'],
            ['categoria' => 'GENERAL', 'clave' => 'zona_horaria', 'valor' => 'America/Lima', 'tipo' => 'STRING'],
            ['categoria' => 'GENERAL', 'clave' => 'version_sistema', 'valor' => '1.0.0', 'tipo' => 'STRING'],

            // Backup
            ['categoria' => 'BACKUP', 'clave' => 'mantener_backups_diarios', 'valor' => '30', 'tipo' => 'INTEGER'],
            ['categoria' => 'BACKUP', 'clave' => 'mantener_backups_semanales', 'valor' => '12', 'tipo' => 'INTEGER'],
            ['categoria' => 'BACKUP', 'clave' => 'mantener_backups_mensuales', 'valor' => '12', 'tipo' => 'INTEGER'],
            ['categoria' => 'BACKUP', 'clave' => 'comprimir_backups_automaticamente', 'valor' => 'true', 'tipo' => 'BOOLEAN'],

            // Planificación
            ['categoria' => 'PLANIFICACION', 'clave' => 'algoritmo_asignacion_version', 'valor' => 'v2.1', 'tipo' => 'STRING'],
            ['categoria' => 'PLANIFICACION', 'clave' => 'max_conductores_por_planificacion', 'valor' => '200', 'tipo' => 'INTEGER'],
            ['categoria' => 'PLANIFICACION', 'clave' => 'permitir_replanificacion_automatica', 'valor' => 'true', 'tipo' => 'BOOLEAN'],
            ['categoria' => 'PLANIFICACION', 'clave' => 'tiempo_limite_planificacion_minutos', 'valor' => '30', 'tipo' => 'INTEGER']
        ];

        foreach ($parametros as $param) {
            Parametro::create([
                'categoria' => $param['categoria'],
                'clave' => $param['clave'],
                'valor' => $param['valor'],
                'tipo' => $param['tipo'],
                'nombre' => ucfirst(str_replace('_', ' ', $param['clave'])),
                'descripcion' => 'Parámetro de configuración del sistema',
                'modificable' => true,
                'valor_por_defecto' => $param['valor']
            ]);
        }

        $this->command->line('   ✓ Creados ' . count($parametros) . ' parámetros de configuración');
    }

    /**
     * Crear buses del sistema
     */
    private function crearBuses()
    {
        $this->command->info('🚌 Creando buses del sistema...');

        $marcas = ['Mercedes-Benz', 'Volvo', 'Scania', 'Iveco', 'MAN'];
        $modelos = ['Citaro', 'B7R', 'K320', 'Crossway', 'Lion\'s City'];
        $combustibles = ['DIESEL', 'GNV', 'ELECTRICO'];
        $estados = ['OPERATIVO', 'MANTENIMIENTO', 'REPARACION'];
        $subempresas = ['SIPAT Norte', 'SIPAT Sur', 'SIPAT Centro', 'SIPAT Express'];

        for ($i = 1; $i <= 50; $i++) {
            $año = $this->faker->numberBetween(2015, 2023);
            $estado = $this->faker->randomElement($estados, [70, 20, 10]); // 70% operativo

            Bus::create([
                'numero_bus' => 'B' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'placa' => strtoupper($this->faker->bothify('???-###')),
                'marca' => $this->faker->randomElement($marcas),
                'modelo' => $this->faker->randomElement($modelos),
                'año' => $año,
                'capacidad_pasajeros' => $this->faker->numberBetween(20, 50),
                'tipo_combustible' => $this->faker->randomElement($combustibles, [60, 35, 5]),
                'estado' => $estado,
                'subempresa' => $this->faker->randomElement($subempresas),
                'kilometraje' => $this->faker->randomFloat(2, 50000, 300000),
                'fecha_ultima_revision' => $this->faker->dateTimeBetween('-6 months', '-1 week'),
                'fecha_proxima_revision' => $this->faker->dateTimeBetween('now', '+6 months'),
                'ubicacion_actual' => $this->faker->randomElement([
                    'Terminal Norte', 'Terminal Sur', 'Terminal Centro',
                    'Cochera Principal', 'Taller Central', 'Ruta'
                ]),
                'observaciones' => $estado === 'OPERATIVO' ? null : $this->faker->optional(0.7)->sentence
            ]);
        }

        $this->command->line('   ✓ Creados ' . Bus::count() . ' buses del sistema');
    }

    /**
     * Crear conductores de ejemplo
     */
    private function crearConductores()
    {
        $this->command->info('👨‍💼 Creando conductores de ejemplo...');

        $estados = ['DISPONIBLE', 'DESCANSO_FISICO', 'DESCANSO_SEMANAL', 'VACACIONES', 'SUSPENDIDO'];
        $origenes = ['LIMA', 'CALLAO', 'CHORRILLOS', 'SAN_MIGUEL', 'NAZCA', 'ICA'];
        $regimenes = ['26x4', '6x1']; // 26 días laborados por 4 descanso, 6 días por 1 descanso
        $servicios = ['ESTANDAR', 'VIP', 'NAZCA', 'ESTANDAR,VIP', 'ESTANDAR,NAZCA'];

        for ($i = 1; $i <= 150; $i++) {
            $fechaIngreso = $this->faker->dateTimeBetween('-5 years', '-1 month');
            $estado = $this->faker->randomElement($estados, [70, 10, 8, 7, 5]); // 70% disponible
            $diasAcumulados = $estado === 'DISPONIBLE' ? $this->faker->numberBetween(0, 8) : 0;

            // Ajustar eficiencia y puntualidad según experiencia
            $mesesExperiencia = Carbon::parse($fechaIngreso)->diffInMonths(now());
            $factorExperiencia = min(1.2, 1 + ($mesesExperiencia / 60)); // Más experiencia, mejor rendimiento

            $eficienciaBase = $this->faker->numberBetween(60, 95);
            $puntualidadBase = $this->faker->numberBetween(65, 98);

            $eficiencia = min(100, round($eficienciaBase * $factorExperiencia));
            $puntualidad = min(100, round($puntualidadBase * $factorExperiencia));

            Conductor::create([
                'codigo_conductor' => 'C' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'nombres' => $this->faker->firstName,
                'apellidos' => $this->faker->lastName . ' ' . $this->faker->lastName,
                'dni' => $this->faker->unique()->numerify('########'),
                'fecha_nacimiento' => $this->faker->dateTimeBetween('-65 years', '-25 years'),
                'telefono' => $this->faker->phoneNumber,
                'email' => $this->faker->optional(0.6)->safeEmail,
                'direccion' => $this->faker->address,
                'fecha_ingreso' => $fechaIngreso,
                'numero_licencia' => $this->faker->unique()->bothify('??#######'),
                'categoria_licencia' => $this->faker->randomElement(['A-IIa', 'A-IIb', 'A-IIIa', 'A-IIIb']),
                'fecha_vencimiento_licencia' => $this->faker->dateTimeBetween('now', '+2 years'),
                'estado' => $estado,
                'origen' => $this->faker->randomElement($origenes),
                'regimen' => $this->faker->randomElement($regimenes, [80, 20]), // 80% régimen normal
                'servicios_autorizados' => $this->faker->randomElement($servicios, [50, 20, 15, 10, 5]),
                'eficiencia' => $eficiencia,
                'puntualidad' => $puntualidad,
                'dias_acumulados' => $diasAcumulados,
                'score_general' => round(($eficiencia + $puntualidad) / 2, 1),
                'observaciones' => $this->faker->optional(0.3)->sentence,
                'activo' => $this->faker->boolean(95), // 95% activos
                'autorizado_turnos_noche' => $this->faker->boolean(70),
                'tiene_suspensiones_activas' => $estado === 'SUSPENDIDO',
                'licencia_vigente' => $this->faker->boolean(98)
            ]);
        }

        // Crear algunos conductores específicos críticos para pruebas
        $conductoresCriticos = [
            ['nombres' => 'Pedro', 'apellidos' => 'Crítico Uno', 'dias_acumulados' => 7, 'eficiencia' => 75],
            ['nombres' => 'Ana', 'apellidos' => 'Crítica Dos', 'dias_acumulados' => 6, 'eficiencia' => 78],
            ['nombres' => 'Carlos', 'apellidos' => 'Crítico Tres', 'dias_acumulados' => 8, 'eficiencia' => 70]
        ];

        foreach ($conductoresCriticos as $index => $critico) {
            Conductor::create([
                'codigo_conductor' => 'CRIT' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
                'nombres' => $critico['nombres'],
                'apellidos' => $critico['apellidos'],
                'dni' => $this->faker->unique()->numerify('########'),
                'fecha_nacimiento' => $this->faker->dateTimeBetween('-50 years', '-30 years'),
                'telefono' => $this->faker->phoneNumber,
                'fecha_ingreso' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
                'numero_licencia' => $this->faker->unique()->bothify('??#######'),
                'categoria_licencia' => 'A-IIa',
                'fecha_vencimiento_licencia' => $this->faker->dateTimeBetween('now', '+1 year'),
                'estado' => 'DISPONIBLE',
                'origen' => 'LIMA',
                'regimen' => '26x4',
                'servicios_autorizados' => 'ESTANDAR',
                'eficiencia' => $critico['eficiencia'],
                'puntualidad' => $this->faker->numberBetween(75, 85),
                'dias_acumulados' => $critico['dias_acumulados'],
                'score_general' => $critico['eficiencia'],
                'observaciones' => 'Conductor crítico para pruebas del sistema',
                'activo' => true,
                'autorizado_turnos_noche' => true,
                'tiene_suspensiones_activas' => false,
                'licencia_vigente' => true
            ]);
        }

        $this->command->line('   ✓ Creados ' . Conductor::count() . ' conductores (incluyendo 3 críticos para pruebas)');
    }

    /**
     * Crear rutas cortas históricas
     */
    private function crearRutasCortas()
    {
        $this->command->info('🛣️ Creando historial de rutas cortas...');

        $conductores = Conductor::where('activo', true)->get();
        $destinos = ['LIMA_CENTRO', 'CALLAO', 'CHORRILLOS', 'SAN_MIGUEL', 'MIRAFLORES'];
        $tiposServicio = ['ESTANDAR', 'VIP', 'NAZCA'];

        // Crear rutas cortas para los últimos 30 días
        for ($dia = 0; $dia < 30; $dia++) {
            $fecha = now()->subDays($dia);

            // Cada día, algunos conductores realizan rutas cortas
            $conductoresDelDia = $conductores->random($this->faker->numberBetween(5, 20));

            foreach ($conductoresDelDia as $conductor) {
                // Algunos conductores pueden tener múltiples rutas cortas en el día
                $numeroRutas = $this->faker->numberBetween(1, 3);

                for ($ruta = 0; $ruta < $numeroRutas; $ruta++) {
                    $horaInicio = $this->faker->dateTimeBetween(
                        $fecha->copy()->setTime(6, 0),
                        $fecha->copy()->setTime(20, 0)
                    );

                    $duracionMinutos = $this->faker->numberBetween(30, 180);
                    $horaFin = $horaInicio->copy()->addMinutes($duracionMinutos);

                    RutaCorta::create([
                        'conductor_id' => $conductor->id,
                        'fecha_realizacion' => $fecha->toDateString(),
                        'hora_inicio' => $horaInicio->format('H:i:s'),
                        'hora_fin' => $horaFin->format('H:i:s'),
                        'origen' => $conductor->origen,
                        'destino' => $this->faker->randomElement($destinos),
                        'tipo_servicio' => $this->faker->randomElement($tiposServicio, [70, 20, 10]),
                        'numero_pasajeros' => $this->faker->numberBetween(15, 40),
                        'ingreso_estimado' => $this->faker->randomFloat(2, 50, 200),
                        'kilometros_recorridos' => $this->faker->randomFloat(2, 20, 80),
                        'combustible_consumido' => $this->faker->randomFloat(2, 5, 15),
                        'estado' => $this->faker->randomElement(['COMPLETADA', 'CANCELADA'], [95, 5]),
                        'observaciones' => $this->faker->optional(0.2)->sentence
                    ]);
                }
            }
        }

        $this->command->line('   ✓ Creadas ' . RutaCorta::count() . ' rutas cortas históricas');
    }

    /**
     * Crear plantillas de ejemplo
     */
    private function crearPlantillas()
    {
        $this->command->info('📋 Creando plantillas de ejemplo...');

        $tipos = ['AUTOMATICA', 'MANUAL', 'EMERGENCIA'];
        $estados = ['BORRADOR', 'GENERADA', 'APROBADA', 'FINALIZADA'];

        // Crear plantillas para los últimos 15 días y próximos 7 días
        for ($dia = -15; $dia <= 7; $dia++) {
            $fecha = now()->addDays($dia);

            // No todas las fechas tienen plantilla
            if ($this->faker->boolean(80)) {
                $tipo = $dia <= 0 ?
                    $this->faker->randomElement($tipos, [70, 25, 5]) :
                    $this->faker->randomElement(['AUTOMATICA', 'MANUAL'], [80, 20]);

                $estado = $dia < -7 ? 'FINALIZADA' :
                         ($dia < 0 ? $this->faker->randomElement(['APROBADA', 'FINALIZADA'], [30, 70]) :
                         ($dia === 0 ? $this->faker->randomElement(['GENERADA', 'APROBADA'], [60, 40]) :
                         'BORRADOR'));

                $totalTurnos = $this->faker->numberBetween(20, 60);
                $turnosAsignados = $estado === 'BORRADOR' ?
                    $this->faker->numberBetween(0, $totalTurnos) :
                    $totalTurnos;

                Plantilla::create([
                    'fecha_servicio' => $fecha->toDateString(),
                    'tipo' => $tipo,
                    'estado' => $estado,
                    'total_turnos' => $totalTurnos,
                    'turnos_asignados' => $turnosAsignados,
                    'turnos_pendientes' => $totalTurnos - $turnosAsignados,
                    'observaciones' => $tipo === 'EMERGENCIA' ?
                        'Plantilla de emergencia por ' . $this->faker->randomElement(['falta masiva', 'mantenimiento buses', 'huelga']) :
                        $this->faker->optional(0.3)->sentence,
                    'aprobada_por' => $estado === 'APROBADA' || $estado === 'FINALIZADA' ?
                        User::role('supervisor')->inRandomOrder()->first()?->id : null,
                    'fecha_aprobacion' => $estado === 'APROBADA' || $estado === 'FINALIZADA' ?
                        $fecha->copy()->subHours($this->faker->numberBetween(2, 12)) : null,
                    'created_by' => $tipo === 'AUTOMATICA' ?
                        1 : // Sistema
                        User::inRandomOrder()->first()->id,
                    'created_at' => $fecha->copy()->subDays(1)->setTime(
                        $this->faker->numberBetween(18, 23),
                        $this->faker->numberBetween(0, 59)
                    )
                ]);
            }
        }

        $this->command->line('   ✓ Creadas ' . Plantilla::count() . ' plantillas de ejemplo');
    }

    /**
     * Crear turnos para las plantillas
     */
    private function crearTurnos()
    {
        $this->command->info('🕐 Creando turnos para las plantillas...');

        $plantillas = Plantilla::all();
        $conductores = Conductor::where('activo', true)->where('estado', 'DISPONIBLE')->get();
        $buses = Bus::where('estado', 'OPERATIVO')->get();

        $tiposServicio = ['ESTANDAR', 'VIP', 'NAZCA'];
        $horariosTurnos = [
            '05:30', '06:00', '06:30', '07:00', '07:30', '08:00', '08:30', '09:00',
            '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
            '18:00', '19:00', '20:00', '21:00', '22:00'
        ];

        foreach ($plantillas as $plantilla) {
            for ($turno = 1; $turno <= $plantilla->total_turnos; $turno++) {
                $horaSalida = $this->faker->randomElement($horariosTurnos);
                $duracionHoras = $this->faker->numberBetween(4, 10);
                $horaLlegada = Carbon::parse($horaSalida)->addHours($duracionHoras)->format('H:i');

                // Asignar conductor y bus solo si la plantilla tiene asignaciones
                $conductorId = null;
                $busId = null;

                if ($plantilla->turnos_asignados > 0 && $turno <= $plantilla->turnos_asignados) {
                    $conductorId = $conductores->random()->id;
                    $busId = $buses->random()->id;
                }

                Turno::create([
                    'plantilla_id' => $plantilla->id,
                    'numero_turno' => str_pad($turno, 3, '0', STR_PAD_LEFT),
                    'fecha_servicio' => $plantilla->fecha_servicio,
                    'hora_salida' => $horaSalida,
                    'hora_llegada' => $horaLlegada,
                    'conductor_id' => $conductorId,
                    'bus_id' => $busId,
                    'tipo_servicio' => $this->faker->randomElement($tiposServicio, [70, 20, 10]),
                    'origen' => $this->faker->randomElement(['LIMA', 'CALLAO', 'CHORRILLOS']),
                    'destino' => $this->faker->randomElement(['NAZCA', 'ICA', 'CHINCHA', 'PISCO']),
                    'numero_salidas' => $this->faker->numberBetween(1, 2),
                    'precio_estimado' => $this->faker->randomFloat(2, 25, 80),
                    'estado' => $plantilla->estado === 'FINALIZADA' ?
                        $this->faker->randomElement(['COMPLETADO', 'CANCELADO'], [95, 5]) : 'PROGRAMADO',
                    'observaciones' => $this->faker->optional(0.2)->sentence
                ]);
            }
        }

        $this->command->line('   ✓ Creados ' . Turno::count() . ' turnos para las plantillas');
    }

    /**
     * Crear validaciones de ejemplo
     */
    private function crearValidaciones()
    {
        $this->command->info('✅ Creando validaciones de ejemplo...');

        $conductores = Conductor::all();
        $plantillas = Plantilla::all();
        $usuarios = User::all();

        $tiposValidacion = [
            'DESCANSO_001', 'EFICIENCIA_002', 'PUNTUALIDAD_003',
            'FRESCO_ANTES_12PM', 'MV_ANTES_9AM', 'HORAS_MINIMAS',
            'PROXIMO_DESCANSO', 'REPLANIFICACION_MEDICA'
        ];

        $severidades = ['INFO', 'ADVERTENCIA', 'CRITICA'];
        $estados = ['PENDIENTE', 'PROCESADA', 'RESUELTA', 'IGNORADA'];

        // Crear validaciones pendientes (actuales)
        for ($i = 1; $i <= 25; $i++) {
            $conductor = $conductores->random();
            $plantilla = $plantillas->random();
            $tipo = $this->faker->randomElement($tiposValidacion);

            // Ajustar severidad según el tipo
            $severidad = match($tipo) {
                'DESCANSO_001', 'MV_ANTES_9AM', 'HORAS_MINIMAS' => 'CRITICA',
                'EFICIENCIA_002', 'PUNTUALIDAD_003', 'PROXIMO_DESCANSO' => 'ADVERTENCIA',
                default => $this->faker->randomElement($severidades)
            };

            $estado = $this->faker->randomElement(['PENDIENTE', 'PROCESADA'], [70, 30]);

            Validacion::create([
                'tipo' => $tipo,
                'severidad' => $severidad,
                'conductor_id' => $conductor->id,
                'plantilla_id' => $plantilla->id,
                'descripcion' => $this->generarDescripcionValidacion($tipo, $conductor),
                'estado' => $estado,
                'datos_adicionales' => [
                    'conductor_codigo' => $conductor->codigo_conductor,
                    'conductor_nombre' => $conductor->nombre_completo,
                    'dias_acumulados' => $conductor->dias_acumulados,
                    'eficiencia' => $conductor->eficiencia,
                    'puntualidad' => $conductor->puntualidad
                ],
                'fecha_deteccion' => $this->faker->dateTimeBetween('-7 days', 'now'),
                'prioridad' => $this->calcularPrioridad($severidad, $tipo),
                'asignado_a' => $estado === 'PROCESADA' ? $usuarios->random()->id : null,
                'fecha_resolucion' => $estado === 'PROCESADA' ?
                    $this->faker->dateTimeBetween('-3 days', 'now') : null,
                'observaciones_resolucion' => $estado === 'PROCESADA' ?
                    $this->faker->sentence : null
            ]);
        }

        // Crear validaciones históricas resueltas
        for ($i = 1; $i <= 40; $i++) {
            $conductor = $conductores->random();
            $plantilla = $plantillas->where('estado', 'FINALIZADA')->random();
            $tipo = $this->faker->randomElement($tiposValidacion);
            $severidad = $this->faker->randomElement($severidades, [20, 60, 20]);

            $fechaDeteccion = $this->faker->dateTimeBetween('-30 days', '-8 days');
            $fechaResolucion = $this->faker->dateTimeBetween($fechaDeteccion, '-7 days');

            Validacion::create([
                'tipo' => $tipo,
                'severidad' => $severidad,
                'conductor_id' => $conductor->id,
                'plantilla_id' => $plantilla->id,
                'descripcion' => $this->generarDescripcionValidacion($tipo, $conductor),
                'estado' => $this->faker->randomElement(['RESUELTA', 'IGNORADA'], [85, 15]),
                'datos_adicionales' => [
                    'conductor_codigo' => $conductor->codigo_conductor,
                    'conductor_nombre' => $conductor->nombre_completo
                ],
                'fecha_deteccion' => $fechaDeteccion,
                'prioridad' => $this->calcularPrioridad($severidad, $tipo),
                'asignado_a' => $usuarios->random()->id,
                'fecha_resolucion' => $fechaResolucion,
                'observaciones_resolucion' => $this->faker->sentence,
                'resuelto_por' => $usuarios->random()->id
            ]);
        }

        $this->command->line('   ✓ Creadas ' . Validacion::count() . ' validaciones del sistema');
    }

    /**
     * Crear historial de planificaciones
     */
    private function crearHistorialPlanificaciones()
    {
        $this->command->info('📊 Creando historial de planificaciones...');

        $plantillas = Plantilla::where('estado', '!=', 'BORRADOR')->get();
        $usuarios = User::all();

        $tipos = ['AUTOMATICA', 'MANUAL', 'REPLANIFICACION', 'AJUSTE'];
        $estados = ['COMPLETADO', 'ERROR', 'OPTIMIZADO'];

        foreach ($plantillas as $plantilla) {
            // Historial principal de la plantilla
            $fechaEjecucion = $plantilla->created_at ?? now()->subDays(rand(1, 30));

            HistorialPlanificacion::create([
                'fecha_planificacion' => $plantilla->fecha_servicio,
                'estado' => $this->faker->randomElement($estados, [80, 15, 5]),
                'tipo_planificacion' => $plantilla->tipo === 'EMERGENCIA' ? 'REPLANIFICACION' : $plantilla->tipo,
                'plantilla_id' => $plantilla->id,
                'usuario_id' => $plantilla->created_by,
                'resultado' => [
                    'plantilla_id' => $plantilla->id,
                    'total_turnos' => $plantilla->total_turnos,
                    'conductores_asignados' => $plantilla->turnos_asignados,
                    'exito' => true
                ],
                'metricas' => [
                    'conductores_procesados' => $this->faker->numberBetween(100, 200),
                    'conductores_filtrados' => $this->faker->numberBetween(80, 150),
                    'asignaciones_realizadas' => $plantilla->turnos_asignados,
                    'validaciones_generadas' => $this->faker->numberBetween(0, 10),
                    'tiempo_inicio' => $fechaEjecucion,
                    'errores' => []
                ],
                'configuracion_utilizada' => [
                    'dias_filtro_rutas_cortas' => 2,
                    'meses_antigüedad_minima' => 1,
                    'eficiencia_minima' => 80,
                    'puntualidad_minima' => 85
                ],
                'cambios_realizados' => $this->faker->randomElement([[], [
                    'reasignaciones' => $this->faker->numberBetween(1, 5),
                    'nuevas_asignaciones' => $this->faker->numberBetween(5, 15)
                ]]),
                'turnos_afectados' => range(1, $plantilla->turnos_asignados),
                'conductores_afectados' => $plantilla->turnos()->pluck('conductor_id')->filter()->toArray(),
                'validaciones_generadas' => $this->faker->numberBetween(0, 10),
                'tiempo_procesamiento' => $this->faker->numberBetween(30, 300),
                'observaciones' => $this->faker->optional(0.3)->sentence,
                'created_at' => $fechaEjecucion
            ]);

            // Algunas plantillas tienen múltiples historiales (replanificaciones)
            if ($this->faker->boolean(30)) {
                $fechaReplanificacion = $fechaEjecucion->copy()->addHours($this->faker->numberBetween(2, 48));

                HistorialPlanificacion::create([
                    'fecha_planificacion' => $plantilla->fecha_servicio,
                    'estado' => 'COMPLETADO',
                    'tipo_planificacion' => 'REPLANIFICACION',
                    'plantilla_id' => $plantilla->id,
                    'usuario_id' => $usuarios->random()->id,
                    'resultado' => [
                        'tipo_accion' => 'REPLANIFICACION_PARCIAL',
                        'turnos_modificados' => $this->faker->numberBetween(1, 5),
                        'motivo' => $this->faker->randomElement(['ENFERMEDAD', 'EMERGENCIA_FAMILIAR', 'MANTENIMIENTO_BUS'])
                    ],
                    'metricas' => [
                        'conductores_reasignados' => $this->faker->numberBetween(1, 5),
                        'tiempo_procesamiento' => $this->faker->numberBetween(10, 60)
                    ],
                    'cambios_realizados' => [
                        'reasignaciones' => $this->faker->numberBetween(1, 3),
                        'conductores_afectados' => $this->faker->numberBetween(1, 5)
                    ],
                    'observaciones' => 'Replanificación por cambios operativos',
                    'created_at' => $fechaReplanificacion
                ]);
            }
        }

        $this->command->line('   ✓ Creados ' . HistorialPlanificacion::count() . ' registros de historial');
    }

    /**
     * Verificar que todos los datos se crearon correctamente
     */
    private function verificarDatosCreados()
    {
        $this->command->info('🔍 Verificando integridad de datos creados...');

        $verificaciones = [
            'Usuarios' => User::count(),
            'Roles' => Role::count(),
            'Permisos' => Permission::count(),
            'Parámetros' => Parametro::count(),
            'Buses' => Bus::count(),
            'Conductores' => Conductor::count(),
            'Plantillas' => Plantilla::count(),
            'Turnos' => Turno::count(),
            'Validaciones' => Validacion::count(),
            'Rutas Cortas' => RutaCorta::count(),
            'Historial Planificaciones' => HistorialPlanificacion::count()
        ];

        foreach ($verificaciones as $tabla => $cantidad) {
            $this->command->line("   ✓ {$tabla}: {$cantidad} registros");
        }

        // Verificaciones de integridad
        $conductoresConTurnos = Turno::whereNotNull('conductor_id')->distinct('conductor_id')->count();
        $plantillasConTurnos = Plantilla::has('turnos')->count();
        $validacionesPendientes = Validacion::where('estado', 'PENDIENTE')->count();

        $this->command->line('');
        $this->command->info('📈 Estadísticas adicionales:');
        $this->command->line("   • Conductores con turnos asignados: {$conductoresConTurnos}");
        $this->command->line("   • Plantillas con turnos: {$plantillasConTurnos}");
        $this->command->line("   • Validaciones pendientes: {$validacionesPendientes}");

        // Crear usuario de prueba adicional con credenciales conocidas
        $userPrueba = User::create([
            'name' => 'Usuario Prueba',
            'email' => 'prueba@sipat.com',
            'password' => Hash::make('123456'),
            'activo' => true,
            'debe_cambiar_password' => false,
            'email_verified_at' => now()
        ]);
        $userPrueba->assignRole('operador');

        $this->command->line('');
        $this->command->info('🔑 Credenciales de acceso creadas:');
        $this->command->line('   • Admin: admin@sipat.com / admin123456');
        $this->command->line('   • Supervisor: supervisor@sipat.com / supervisor123');
        $this->command->line('   • Operador: operador@sipat.com / operador123');
        $this->command->line('   • Auditor: auditor@sipat.com / auditor123');
        $this->command->line('   • Prueba: prueba@sipat.com / 123456');
    }

    // =============================================================================
    // MÉTODOS AUXILIARES
    // =============================================================================

    private function generarDescripcionValidacion($tipo, $conductor)
    {
        return match($tipo) {
            'DESCANSO_001' => "Conductor {$conductor->codigo_conductor} ha superado {$conductor->dias_acumulados} días sin descanso",
            'EFICIENCIA_002' => "Conductor {$conductor->codigo_conductor} con eficiencia del {$conductor->eficiencia}% por debajo del mínimo",
            'PUNTUALIDAD_003' => "Conductor {$conductor->codigo_conductor} con puntualidad del {$conductor->puntualidad}% por debajo del mínimo",
            'FRESCO_ANTES_12PM' => "Conductor fresco con término de turno antes de 12 p.m. - Evaluar segunda salida",
            'MV_ANTES_9AM' => "Conductor MV con término antes de 9 a.m. - Verificar horas mínimas acumuladas",
            'PROXIMO_DESCANSO' => "Conductor próximo a requerir descanso obligatorio en los próximos días",
            default => "Validación de tipo {$tipo} para conductor {$conductor->codigo_conductor}"
        };
    }

    private function calcularPrioridad($severidad, $tipo)
    {
        $base = match($severidad) {
            'CRITICA' => 80,
            'ADVERTENCIA' => 50,
            'INFO' => 20,
            default => 30
        };

        $ajuste = match($tipo) {
            'DESCANSO_001', 'MV_ANTES_9AM' => 20,
            'EFICIENCIA_002', 'PUNTUALIDAD_003' => 10,
            default => 0
        };

        return min(100, $base + $ajuste);
    }
}
