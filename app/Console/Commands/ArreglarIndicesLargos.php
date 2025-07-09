<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ArreglarIndicesLargos extends Command
{
    protected $signature = 'sipat:arreglar-indices';
    protected $description = 'Arreglar nombres de índices demasiado largos en migraciones';

    public function handle()
    {
        $this->info('🔧 Arreglando nombres de índices largos...');

        try {
            // Marcar la migración problemática como ejecutada sin ejecutarla
            $migrationName = '2025_07_05_130516_create_historial_credenciales_table';

            $exists = DB::table('migrations')->where('migration', $migrationName)->exists();

            if (!$exists) {
                $this->info('📝 Marcando migración problemática como ejecutada...');

                DB::table('migrations')->insert([
                    'migration' => $migrationName,
                    'batch' => DB::table('migrations')->max('batch') + 1
                ]);

                $this->info('✅ Migración marcada como ejecutada (saltada por índices largos)');
            } else {
                $this->info('✅ Migración ya está marcada como ejecutada');
            }

            // Crear las tablas manualmente con nombres de índices cortos
            $this->crearTablasHistorialCredenciales();

            $this->info('✅ Problema de índices largos solucionado');
            $this->info('🚀 Ahora ejecuta: php artisan sipat:setup --force');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function crearTablasHistorialCredenciales()
    {
        $this->info('📋 Creando tablas de historial con nombres cortos...');

        // Tabla principal de historial de credenciales
        if (!Schema::hasTable('historial_credenciales')) {
            Schema::create('historial_credenciales', function ($table) {
                $table->id();
                $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
                $table->enum('accion', [
                    'ACCESO_EXITOSO',
                    'ACCESO_FALLIDO',
                    'PASSWORD_CAMBIADA',
                    'USUARIO_CREADO',
                    'USUARIO_ACTUALIZADO',
                    'USUARIO_ELIMINADO',
                    'ESTADO_CAMBIADO',
                    'PERMISOS_MODIFICADOS',
                    'LOGOUT'
                ])->index();
                $table->json('datos')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                // Índices con nombres cortos
                $table->index(['usuario_id', 'accion'], 'idx_hist_cred_usuario_accion');
                $table->index(['accion', 'created_at'], 'idx_hist_cred_accion_fecha');
                $table->index(['ip_address'], 'idx_hist_cred_ip');
            });
            $this->info('   ✅ Tabla historial_credenciales creada');
        }

        // Tabla de sesiones de usuario
        if (!Schema::hasTable('sesiones_usuario_detalle')) {
            Schema::create('sesiones_usuario_detalle', function ($table) {
                $table->id();
                $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
                $table->string('session_id', 255)->index();
                $table->timestamp('inicio_sesion');
                $table->timestamp('fin_sesion')->nullable();
                $table->string('ip_address', 45);
                $table->text('user_agent');
                $table->integer('duracion_minutos')->nullable();
                $table->boolean('cerrada_correctamente')->default(false);
                $table->timestamps();

                $table->index(['usuario_id', 'inicio_sesion'], 'idx_sesiones_usuario_inicio');
                $table->index(['session_id'], 'idx_sesiones_session');
            });
            $this->info('   ✅ Tabla sesiones_usuario_detalle creada');
        }

        // Tabla de intentos fallidos (simplificada)
        if (!Schema::hasTable('intentos_acceso_fallidos')) {
            Schema::create('intentos_acceso_fallidos', function ($table) {
                $table->id();
                $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('email_intento', 255)->index();
                $table->string('ip_address', 45)->index();
                $table->enum('motivo_fallo', [
                    'CREDENCIALES_INVALIDAS',
                    'USUARIO_BLOQUEADO',
                    'USUARIO_INACTIVO',
                    'LIMITE_INTENTOS'
                ])->index();
                $table->timestamps();

                $table->index(['email_intento', 'created_at'], 'idx_intentos_email_fecha');
                $table->index(['ip_address', 'created_at'], 'idx_intentos_ip_fecha');
            });
            $this->info('   ✅ Tabla intentos_acceso_fallidos creada');
        }

        // Tabla de alertas de seguridad (simplificada)
        if (!Schema::hasTable('alertas_seguridad')) {
            Schema::create('alertas_seguridad', function ($table) {
                $table->id();
                $table->enum('tipo_alerta', [
                    'MULTIPLES_FALLOS_LOGIN',
                    'ACCESO_SOSPECHOSO',
                    'CAMBIO_CRITICO',
                    'PATRON_ANOMALO'
                ])->index();
                $table->enum('severidad', ['BAJA', 'MEDIA', 'ALTA', 'CRITICA'])->index();
                $table->enum('estado', ['NUEVA', 'REVISADA', 'RESUELTA', 'FALSA_ALARMA'])->default('NUEVA')->index();
                $table->text('descripcion');
                $table->json('datos_alerta')->nullable();
                $table->foreignId('usuario_afectado')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('asignado_a')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('fecha_deteccion')->index();
                $table->timestamp('fecha_resolucion')->nullable();
                $table->timestamps();

                $table->index(['tipo_alerta', 'estado'], 'idx_alertas_tipo_estado');
                $table->index(['severidad', 'estado'], 'idx_alertas_sev_estado');
            });
            $this->info('   ✅ Tabla alertas_seguridad creada');
        }
    }
}
