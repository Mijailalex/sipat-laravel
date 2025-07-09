<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

class RepararSistema extends Command
{
    protected $signature = 'sipat:reparar {--force : Forzar reparación incluso si hay datos}';
    protected $description = 'Reparar estructura de tablas SIPAT en estado intermedio';

    public function handle()
    {
        $this->info('🔧 Iniciando reparación del sistema SIPAT...');
        $this->info('');

        try {
            // Paso 1: Manejar conflictos de migraciones
            $this->manejarConflictosMigraciones();

            // Paso 2: Reparar tabla conductores
            $this->repararTablaConductores();

            // Paso 3: Reparar otras tablas esenciales
            $this->repararTablasEsenciales();

            // Paso 4: Ejecutar migraciones faltantes de forma segura
            $this->ejecutarMigracionesSeguras();

            $this->info('');
            $this->info('✅ Reparación completada exitosamente!');
            $this->info('');
            $this->info('🚀 Ahora ejecuta: php artisan sipat:setup --force');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error durante reparación: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function manejarConflictosMigraciones()
    {
        $this->info('📋 Manejando conflictos de migraciones...');

        // Verificar si la migración problemática ya está marcada como ejecutada
        try {
            $migrationExists = DB::table('migrations')
                ->where('migration', 'like', '%create_historial_planificaciones_table%')
                ->exists();

            if (!$migrationExists && Schema::hasTable('historial_planificaciones')) {
                // La tabla existe pero la migración no está registrada
                $this->warn('   ⚠️ Tabla historial_planificaciones existe pero migración no registrada');

                if ($this->confirm('¿Marcar migración como ejecutada?', true)) {
                    // Buscar el archivo de migración exacto
                    $migrationFiles = glob(database_path('migrations/*create_historial_planificaciones_table.php'));

                    if (!empty($migrationFiles)) {
                        $migrationFile = basename($migrationFiles[0], '.php');

                        DB::table('migrations')->insert([
                            'migration' => $migrationFile,
                            'batch' => DB::table('migrations')->max('batch') + 1
                        ]);

                        $this->info("   ✅ Migración {$migrationFile} marcada como ejecutada");
                    }
                }
            } else {
                $this->info('   ✅ Estados de migraciones consistentes');
            }

        } catch (\Exception $e) {
            $this->warn("   ⚠️ Error verificando migraciones: " . $e->getMessage());
        }
    }

    private function repararTablaConductores()
    {
        $this->info('👤 Reparando tabla conductores...');

        if (!Schema::hasTable('conductores')) {
            $this->info('   📝 Creando tabla conductores completa...');

            Schema::create('conductores', function (Blueprint $table) {
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
                $table->index(['subempresa_id', 'estado']);
            });

            $this->info('   ✅ Tabla conductores creada');
            return;
        }

        // La tabla existe, verificar columnas faltantes
        $this->info('   🔍 Verificando columnas en tabla existente...');

        $columnasFaltantes = [];
        $columnasRequeridas = [
            'cedula' => 'string',
            'eficiencia' => 'decimal',
            'puntualidad' => 'decimal',
            'dias_acumulados' => 'integer',
            'score_general' => 'decimal',
            'estado' => 'enum',
            'activo' => 'boolean'
        ];

        foreach ($columnasRequeridas as $columna => $tipo) {
            if (!Schema::hasColumn('conductores', $columna)) {
                $columnasFaltantes[] = [$columna, $tipo];
            }
        }

        if (!empty($columnasFaltantes)) {
            $this->warn('   ⚠️ Faltan ' . count($columnasFaltantes) . ' columnas importantes');

            if ($this->confirm('¿Agregar columnas faltantes?', true)) {
                Schema::table('conductores', function (Blueprint $table) use ($columnasFaltantes) {
                    foreach ($columnasFaltantes as [$columna, $tipo]) {
                        switch ($columna) {
                            case 'cedula':
                                if (!Schema::hasColumn('conductores', 'cedula')) {
                                    $table->string('cedula', 20)->unique()->after('apellido');
                                }
                                break;
                            case 'eficiencia':
                                if (!Schema::hasColumn('conductores', 'eficiencia')) {
                                    $table->decimal('eficiencia', 5, 2)->default(85.00)->after('estado');
                                }
                                break;
                            case 'puntualidad':
                                if (!Schema::hasColumn('conductores', 'puntualidad')) {
                                    $table->decimal('puntualidad', 5, 2)->default(90.00)->after('eficiencia');
                                }
                                break;
                            case 'dias_acumulados':
                                if (!Schema::hasColumn('conductores', 'dias_acumulados')) {
                                    $table->integer('dias_acumulados')->default(0)->after('estado');
                                }
                                break;
                            case 'score_general':
                                if (!Schema::hasColumn('conductores', 'score_general')) {
                                    $table->decimal('score_general', 5, 2)->default(87.50)->after('puntualidad');
                                }
                                break;
                            case 'estado':
                                if (!Schema::hasColumn('conductores', 'estado')) {
                                    $table->enum('estado', [
                                        'DISPONIBLE',
                                        'DESCANSO_FISICO',
                                        'DESCANSO_SEMANAL',
                                        'VACACIONES',
                                        'SUSPENDIDO',
                                        'FALTO_OPERATIVO',
                                        'FALTO_NO_OPERATIVO'
                                    ])->default('DISPONIBLE');
                                }
                                break;
                            case 'activo':
                                if (!Schema::hasColumn('conductores', 'activo')) {
                                    $table->boolean('activo')->default(true);
                                }
                                break;
                        }
                    }
                });

                $this->info('   ✅ Columnas agregadas exitosamente');
            }
        } else {
            $this->info('   ✅ Tabla conductores tiene todas las columnas requeridas');
        }

        // Agregar índices si faltan
        $this->agregarIndicesFaltantes();
    }

    private function agregarIndicesFaltantes()
    {
        $this->info('   📊 Verificando índices...');

        try {
            // Los índices se agregan de forma segura (Laravel los ignora si ya existen)
            Schema::table('conductores', function (Blueprint $table) {
                $table->index(['estado', 'activo'], 'idx_conductores_estado_activo');
                $table->index(['dias_acumulados', 'estado'], 'idx_conductores_dias_estado');
                $table->index(['eficiencia'], 'idx_conductores_eficiencia');
                $table->index(['puntualidad'], 'idx_conductores_puntualidad');
                $table->index(['nombre', 'apellido'], 'idx_conductores_nombre');
            });
            $this->info('   ✅ Índices verificados/agregados');
        } catch (\Exception $e) {
            $this->warn('   ⚠️ Algunos índices pueden ya existir (normal)');
        }
    }

    private function repararTablasEsenciales()
    {
        $this->info('🏢 Verificando otras tablas esenciales...');

        // Tabla subempresas
        if (!Schema::hasTable('subempresas')) {
            $this->info('   📝 Creando tabla subempresas...');

            Schema::create('subempresas', function (Blueprint $table) {
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

        // Tabla validaciones
        if (!Schema::hasTable('validaciones')) {
            $this->info('   📝 Creando tabla validaciones...');

            Schema::create('validaciones', function (Blueprint $table) {
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
            });
        }

        $this->info('   ✅ Tablas esenciales verificadas');
    }

    private function ejecutarMigracionesSeguras()
    {
        $this->info('📝 Ejecutando migraciones pendientes de forma segura...');

        try {
            // Intentar ejecutar migraciones, pero capturar errores de tablas ya existentes
            $this->call('migrate', ['--force' => true]);
            $this->info('   ✅ Migraciones ejecutadas exitosamente');
        } catch (\Exception $e) {
            // Si fallan las migraciones por tablas existentes, es normal
            if (str_contains($e->getMessage(), 'already exists')) {
                $this->warn('   ⚠️ Algunas tablas ya existían (normal en reparación)');

                // Marcar migraciones como ejecutadas si las tablas existen
                $this->marcarMigracionesEjecutadas();
            } else {
                $this->error('   ❌ Error en migraciones: ' . $e->getMessage());
                throw $e;
            }
        }
    }

    private function marcarMigracionesEjecutadas()
    {
        $this->info('   📋 Sincronizando estado de migraciones...');

        $tablasExistentes = [
            'historial_planificaciones' => '*create_historial_planificaciones_table*',
            'conductores' => '*create_conductores_table*',
            'validaciones' => '*create_validaciones_table*',
            'subempresas' => '*create_subempresas_table*'
        ];

        foreach ($tablasExistentes as $tabla => $patron) {
            if (Schema::hasTable($tabla)) {
                // Buscar migración correspondiente
                $migrationExists = DB::table('migrations')
                    ->where('migration', 'like', $patron)
                    ->exists();

                if (!$migrationExists) {
                    // Buscar archivo real de migración
                    $files = glob(database_path("migrations/{$patron}.php"));
                    if (!empty($files)) {
                        $migrationName = basename($files[0], '.php');

                        DB::table('migrations')->insert([
                            'migration' => $migrationName,
                            'batch' => DB::table('migrations')->max('batch') + 1
                        ]);

                        $this->info("     ✅ Migración para {$tabla} marcada como ejecutada");
                    }
                }
            }
        }
    }
}
