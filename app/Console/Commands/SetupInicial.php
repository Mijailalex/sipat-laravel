<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class SetupInicial extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sipat:setup {--force : Forzar setup incluso si ya existe}';

    /**
     * The console command description.
     */
    protected $description = 'Configurar SIPAT inicialmente con datos mínimos para funcionamiento';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando configuración inicial de SIPAT...');

        try {
            // Verificar si ya está configurado
            if (!$this->option('force') && $this->yaEstaConfigurado()) {
                $this->warn('⚠️ SIPAT ya está configurado. Use --force para reconfigurar.');
                return Command::SUCCESS;
            }

            // Crear tabla users si no existe
            $this->crearTablaUsers();

            // Crear usuario administrador
            $this->crearUsuarioAdmin();

            // Crear tablas básicas si no existen
            $this->crearTablasBasicas();

            // Crear datos básicos
            $this->crearDatosBasicos();

            $this->info('✅ Configuración inicial completada exitosamente!');
            $this->info('');
            $this->info('📌 Credenciales de acceso:');
            $this->info('   Email: admin@sipat.com');
            $this->info('   Password: sipat2025');
            $this->info('');
            $this->info('🌐 Ahora puedes ejecutar: php artisan serve');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error en configuración inicial: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function yaEstaConfigurado()
    {
        try {
            // Verificar no solo que exista la tabla users, sino que tenga el usuario admin
            if (!Schema::hasTable('users')) {
                return false;
            }

            $adminExiste = DB::table('users')->where('email', 'admin@sipat.com')->exists();

            // Si el admin existe y las tablas básicas están, considerar configurado
            $tablasBasicas = ['users', 'subempresas', 'conductores', 'validaciones'];
            $todasExisten = true;

            foreach ($tablasBasicas as $tabla) {
                if (!Schema::hasTable($tabla)) {
                    $todasExisten = false;
                    break;
                }
            }

            return $adminExiste && $todasExisten;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function crearTablaUsers()
    {
        $this->info('👤 Verificando tabla de usuarios...');

        if (!Schema::hasTable('users')) {
            $this->info('📝 Creando tabla users...');

            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });

            $this->info('✅ Tabla users creada');
        } else {
            $this->info('✅ Tabla users ya existe');
        }
    }

    private function crearUsuarioAdmin()
    {
        $this->info('🔐 Creando usuario administrador...');

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@sipat.com'],
            [
                'name' => 'Administrador SIPAT',
                'email' => 'admin@sipat.com',
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make('sipat2025'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        );

        $this->info('✅ Usuario administrador creado/actualizado');
    }

    private function crearTablasBasicas()
    {
        $this->info('🗄️ Verificando tablas básicas...');

        // Tabla subempresas
        if (!Schema::hasTable('subempresas')) {
            $this->info('📝 Creando tabla subempresas...');

            Schema::create('subempresas', function ($table) {
                $table->id();
                $table->string('nombre', 100)->unique();
                $table->string('codigo', 20)->unique();
                $table->text('descripcion')->nullable();
                $table->integer('conductores_asignados')->default(0);
                $table->json('configuracion_operativa')->nullable();
                $table->boolean('activa')->default(true);
                $table->timestamps();

                $table->index(['activa']);
                $table->index(['nombre', 'activa']);
            });
        }

        // Tabla conductores
        if (!Schema::hasTable('conductores')) {
            $this->info('📝 Creando tabla conductores...');

            Schema::create('conductores', function ($table) {
                $table->id();

                // Información personal
                $table->string('nombre', 100)->index();
                $table->string('apellido', 100)->index();
                $table->string('cedula', 20)->unique();
                $table->string('telefono', 20)->nullable();
                $table->string('email', 255)->nullable()->unique();
                $table->date('fecha_nacimiento')->nullable();
                $table->date('fecha_ingreso')->nullable();

                // Estado operativo
                $table->enum('estado', [
                    'DISPONIBLE',
                    'DESCANSO_FISICO',
                    'DESCANSO_SEMANAL',
                    'VACACIONES',
                    'SUSPENDIDO',
                    'FALTO_OPERATIVO',
                    'FALTO_NO_OPERATIVO'
                ])->default('DISPONIBLE')->index();

                // Métricas de rendimiento
                $table->integer('dias_acumulados')->default(0);
                $table->decimal('eficiencia', 5, 2)->default(85.00);
                $table->decimal('puntualidad', 5, 2)->default(90.00);
                $table->decimal('score_general', 5, 2)->default(87.50);
                $table->decimal('horas_hombre', 8, 2)->default(0);

                // Información operativa
                $table->datetime('ultima_ruta_corta')->nullable();
                $table->datetime('ultimo_servicio')->nullable();
                $table->string('origen', 255)->nullable();

                // Información adicional
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('subempresa_id')->nullable();
                $table->boolean('activo')->default(true);

                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->index(['estado', 'activo']);
                $table->index(['dias_acumulados', 'estado']);
                $table->index(['eficiencia']);
                $table->index(['puntualidad']);
                $table->index(['score_general']);
                $table->index(['nombre', 'apellido']);

                // Foreign key si existe la tabla
                if (Schema::hasTable('subempresas')) {
                    $table->foreign('subempresa_id')->references('id')->on('subempresas')->onDelete('set null');
                }
            });
        }

        // Tabla validaciones
        if (!Schema::hasTable('validaciones')) {
            $this->info('📝 Creando tabla validaciones...');

            Schema::create('validaciones', function ($table) {
                $table->id();

                $table->string('tipo', 50)->index();
                $table->enum('severidad', ['INFO', 'ADVERTENCIA', 'CRITICA', 'BLOQUEANTE'])->index();
                $table->enum('estado', ['PENDIENTE', 'PROCESADA', 'RESUELTA', 'IGNORADA'])->default('PENDIENTE')->index();

                $table->text('descripcion');
                $table->json('datos_validacion')->nullable();

                $table->unsignedBigInteger('conductor_id')->nullable();
                $table->string('entidad_tipo', 50)->nullable();
                $table->unsignedBigInteger('entidad_id')->nullable();

                $table->text('accion_tomada')->nullable();
                $table->unsignedBigInteger('resuelto_por')->nullable();
                $table->timestamp('fecha_resolucion')->nullable();

                $table->integer('prioridad')->default(0)->index();
                $table->boolean('automatica')->default(true);
                $table->timestamp('fecha_vencimiento')->nullable();
                $table->json('configuracion_validacion')->nullable();

                $table->timestamps();

                // Índices
                $table->index(['estado', 'severidad']);
                $table->index(['conductor_id', 'estado']);
                $table->index(['tipo', 'estado']);
                $table->index(['fecha_resolucion']);
                $table->index(['prioridad', 'created_at']);

                // Foreign keys si existen las tablas
                if (Schema::hasTable('conductores')) {
                    $table->foreign('conductor_id')->references('id')->on('conductores')->onDelete('cascade');
                }
                if (Schema::hasTable('users')) {
                    $table->foreign('resuelto_por')->references('id')->on('users')->onDelete('set null');
                }
            });
        }

        $this->info('✅ Tablas básicas verificadas/creadas');
    }

    private function crearDatosBasicos()
    {
        $this->info('📊 Creando datos básicos de ejemplo...');

        // Crear subempresa de ejemplo si no existe
        if (Schema::hasTable('subempresas')) {
            try {
                DB::table('subempresas')->updateOrInsert(
                    ['codigo' => 'CENTRAL001'],
                    [
                        'nombre' => 'Transporte Central',
                        'codigo' => 'CENTRAL001',
                        'descripcion' => 'Subempresa principal para rutas centrales',
                        'conductores_asignados' => 0,
                        'configuracion_operativa' => json_encode([
                            'turnos_permitidos' => ['MAÑANA', 'TARDE', 'NOCHE'],
                            'horas_maximas_diarias' => 8
                        ]),
                        'activa' => true,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]
                );
                $this->info('   ✅ Subempresa de ejemplo creada');
            } catch (\Exception $e) {
                $this->warn('   ⚠️ Error creando subempresa: ' . $e->getMessage());
            }
        }

        // Crear conductor de ejemplo si no existe y la tabla tiene la estructura correcta
        if (Schema::hasTable('conductores') && Schema::hasColumn('conductores', 'cedula')) {
            try {
                $subempresaId = null;
                if (Schema::hasTable('subempresas')) {
                    $subempresa = DB::table('subempresas')->where('codigo', 'CENTRAL001')->first();
                    $subempresaId = $subempresa->id ?? null;
                }

                // Verificar que no existe ya
                $existe = DB::table('conductores')->where('cedula', '12345678')->exists();

                if (!$existe) {
                    // Preparar datos del conductor con verificación de columnas
                    $datosConductor = [
                        'nombre' => 'Juan',
                        'apellido' => 'Pérez González',
                        'cedula' => '12345678',
                        'created_at' => Carbon::now()->subDays(10),
                        'updated_at' => Carbon::now()
                    ];

                    // Agregar campos opcionales solo si las columnas existen
                    if (Schema::hasColumn('conductores', 'telefono')) {
                        $datosConductor['telefono'] = '+51987654321';
                    }
                    if (Schema::hasColumn('conductores', 'email')) {
                        $datosConductor['email'] = 'juan.perez@sipat.com';
                    }
                    if (Schema::hasColumn('conductores', 'fecha_nacimiento')) {
                        $datosConductor['fecha_nacimiento'] = Carbon::parse('1985-06-15');
                    }
                    if (Schema::hasColumn('conductores', 'fecha_ingreso')) {
                        $datosConductor['fecha_ingreso'] = Carbon::parse('2022-01-15');
                    }
                    if (Schema::hasColumn('conductores', 'estado')) {
                        $datosConductor['estado'] = 'DISPONIBLE';
                    }
                    if (Schema::hasColumn('conductores', 'dias_acumulados')) {
                        $datosConductor['dias_acumulados'] = 2;
                    }
                    if (Schema::hasColumn('conductores', 'eficiencia')) {
                        $datosConductor['eficiencia'] = 90.5;
                    }
                    if (Schema::hasColumn('conductores', 'puntualidad')) {
                        $datosConductor['puntualidad'] = 95.0;
                    }
                    if (Schema::hasColumn('conductores', 'score_general')) {
                        $datosConductor['score_general'] = 92.75;
                    }
                    if (Schema::hasColumn('conductores', 'horas_hombre')) {
                        $datosConductor['horas_hombre'] = 8.0;
                    }
                    if (Schema::hasColumn('conductores', 'origen')) {
                        $datosConductor['origen'] = 'Terminal Central';
                    }
                    if (Schema::hasColumn('conductores', 'subempresa_id')) {
                        $datosConductor['subempresa_id'] = $subempresaId;
                    }
                    if (Schema::hasColumn('conductores', 'activo')) {
                        $datosConductor['activo'] = true;
                    }

                    DB::table('conductores')->insert($datosConductor);
                    $this->info('   ✅ Conductor de ejemplo creado');
                } else {
                    $this->info('   ✅ Conductor de ejemplo ya existe');
                }
            } catch (\Exception $e) {
                $this->warn('   ⚠️ Error creando conductor: ' . $e->getMessage());
            }
        } else {
            $this->warn('   ⚠️ Tabla conductores no lista para datos (ejecutar sipat:reparar primero)');
        }

        // Crear validación de ejemplo si no existe
        if (Schema::hasTable('validaciones')) {
            try {
                $conductorId = null;
                if (Schema::hasTable('conductores') && Schema::hasColumn('conductores', 'cedula')) {
                    $conductor = DB::table('conductores')->where('cedula', '12345678')->first();
                    $conductorId = $conductor->id ?? null;
                }

                $existe = DB::table('validaciones')->where('tipo', 'SISTEMA_INICIADO')->exists();

                if (!$existe) {
                    DB::table('validaciones')->insert([
                        'tipo' => 'SISTEMA_INICIADO',
                        'severidad' => 'INFO',
                        'estado' => 'PENDIENTE',
                        'descripcion' => 'Sistema SIPAT configurado correctamente y listo para operar',
                        'datos_validacion' => json_encode([
                            'version' => '1.0.0',
                            'fecha_configuracion' => Carbon::now()->toISOString(),
                            'modo' => 'inicial'
                        ]),
                        'conductor_id' => $conductorId,
                        'entidad_tipo' => 'sistema',
                        'entidad_id' => null,
                        'prioridad' => 10,
                        'automatica' => true,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                    $this->info('   ✅ Validación de ejemplo creada');
                } else {
                    $this->info('   ✅ Validación de ejemplo ya existe');
                }
            } catch (\Exception $e) {
                $this->warn('   ⚠️ Error creando validación: ' . $e->getMessage());
            }
        }

        $this->info('✅ Datos básicos verificados/creados');
    }
}
