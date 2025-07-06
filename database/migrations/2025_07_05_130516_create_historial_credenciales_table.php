<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historial_credenciales', function (Blueprint $table) {
            $table->id();

            // Referencias de usuario
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('administrador_id')->nullable()->constrained('users')->onDelete('set null');

            // Información de la acción
            $table->string('accion', 50)->index();
            $table->text('descripcion');
            $table->json('datos')->nullable();

            // Información de red y dispositivo
            $table->ipAddress('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->string('session_id', 100)->nullable()->index();

            // Metadatos de la acción
            $table->enum('resultado', ['EXITOSO', 'FALLIDO', 'BLOQUEADO'])->default('EXITOSO')->index();
            $table->enum('severidad', ['INFO', 'ADVERTENCIA', 'CRITICA', 'EMERGENCIA'])->default('INFO')->index();
            $table->json('metadatos')->nullable();

            // Información geográfica y de dispositivo
            $table->string('pais', 2)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->string('dispositivo_tipo', 20)->nullable(); // DESKTOP, MOBILE, TABLET
            $table->string('navegador', 50)->nullable();
            $table->string('sistema_operativo', 50)->nullable();

            // Información de seguridad adicional
            $table->boolean('ip_sospechosa')->default(false)->index();
            $table->boolean('acceso_fuera_horario')->default(false)->index();
            $table->integer('intentos_previos')->default(0);
            $table->timestamp('fecha_ultimo_acceso_exitoso')->nullable();

            // Campos de auditoría y seguimiento
            $table->timestamps();
            $table->index('created_at');

            // Índices compuestos para consultas de seguridad
            $table->index(['usuario_id', 'accion']);
            $table->index(['usuario_id', 'created_at']);
            $table->index(['accion', 'resultado']);
            $table->index(['accion', 'severidad']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['resultado', 'severidad', 'created_at']);

            // Índices para análisis de patrones
            $table->index(['ip_sospechosa', 'created_at']);
            $table->index(['acceso_fuera_horario', 'created_at']);
            $table->index(['administrador_id', 'accion', 'created_at']);
        });

        // Tabla para tracking de sesiones de usuario
        Schema::create('sesiones_usuario_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade');
            $table->string('session_id', 100)->unique();
            $table->timestamp('inicio_sesion');
            $table->timestamp('fin_sesion')->nullable();
            $table->integer('duracion_minutos')->nullable();
            $table->ipAddress('ip_inicio');
            $table->ipAddress('ip_fin')->nullable();
            $table->string('navegador_inicio', 100)->nullable();
            $table->enum('tipo_cierre', [
                'LOGOUT_MANUAL',
                'LOGOUT_AUTOMATICO',
                'EXPIRACION',
                'FORZADO_ADMIN',
                'CAMBIO_PASSWORD',
                'BLOQUEO_USUARIO'
            ])->nullable();
            $table->boolean('sesion_sospechosa')->default(false)->index();
            $table->json('actividades_sesion')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['usuario_id', 'inicio_sesion']);
            $table->index(['session_id']);
            $table->index(['sesion_sospechosa', 'inicio_sesion']);
            $table->index(['tipo_cierre']);
        });

        // Tabla para tracking de intentos de acceso fallidos
        Schema::create('intentos_acceso_fallidos', function (Blueprint $table) {
            $table->id();
            $table->string('email_intento', 255)->index();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->ipAddress('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->enum('motivo_fallo', [
                'PASSWORD_INCORRECTO',
                'USUARIO_NO_EXISTE',
                'CUENTA_BLOQUEADA',
                'CUENTA_INACTIVA',
                'DEMASIADOS_INTENTOS',
                'IP_BLOQUEADA',
                'CAPTCHA_FALLIDO',
                'OTRO'
            ])->index();
            $table->string('password_hasheado', 60)->nullable(); // Para análisis de patrones
            $table->boolean('ip_bloqueada_automaticamente')->default(false);
            $table->timestamp('fecha_intento')->index();
            $table->json('detalles_adicionales')->nullable();
            $table->timestamps();

            // Índices para detección de ataques
            $table->index(['ip_address', 'fecha_intento']);
            $table->index(['email_intento', 'fecha_intento']);
            $table->index(['motivo_fallo', 'fecha_intento']);
            $table->index(['ip_bloqueada_automaticamente']);
        });

        // Tabla para tracking de cambios de permisos
        Schema::create('cambios_permisos_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historial_credencial_id')->constrained('historial_credenciales')->onDelete('cascade');
            $table->foreignId('usuario_afectado_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('administrador_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo_cambio', [
                'ROL_ASIGNADO',
                'ROL_REMOVIDO',
                'PERMISO_OTORGADO',
                'PERMISO_REVOCADO',
                'PERMISO_MODIFICADO'
            ])->index();
            $table->string('nombre_rol_permiso', 100)->nullable();
            $table->json('permisos_anteriores')->nullable();
            $table->json('permisos_nuevos')->nullable();
            $table->text('justificacion')->nullable();
            $table->boolean('cambio_temporal')->default(false);
            $table->timestamp('fecha_expiracion')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['usuario_afectado_id', 'tipo_cambio']);
            $table->index(['administrador_id', 'created_at']);
            $table->index(['tipo_cambio', 'created_at']);
            $table->index(['cambio_temporal', 'fecha_expiracion']);
        });

        // Tabla para análisis de patrones de seguridad
        Schema::create('patrones_seguridad', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_patron', 50)->index();
            $table->string('descripcion_patron', 200);
            $table->enum('nivel_riesgo', ['BAJO', 'MEDIO', 'ALTO', 'CRITICO'])->index();
            $table->json('criterios_deteccion');
            $table->json('parametros_threshold');
            $table->boolean('activo')->default(true)->index();
            $table->integer('veces_detectado')->default(0);
            $table->timestamp('ultima_deteccion')->nullable();
            $table->json('acciones_automaticas')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['tipo_patron', 'activo']);
            $table->index(['nivel_riesgo', 'activo']);
            $table->index(['ultima_deteccion']);
        });

        // Tabla para alertas de seguridad generadas
        Schema::create('alertas_seguridad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patron_seguridad_id')->nullable()->constrained('patrones_seguridad')->onDelete('set null');
            $table->string('tipo_alerta', 50)->index();
            $table->enum('severidad', ['INFO', 'ADVERTENCIA', 'CRITICA', 'EMERGENCIA'])->index();
            $table->string('titulo', 200);
            $table->text('descripcion');
            $table->json('datos_alerta');
            $table->enum('estado', [
                'NUEVA',
                'EN_REVISION',
                'INVESTIGANDO',
                'RESUELTA',
                'FALSO_POSITIVO',
                'IGNORADA'
            ])->default('NUEVA')->index();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('fecha_deteccion')->index();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->text('notas_resolucion')->nullable();
            $table->json('acciones_tomadas')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['tipo_alerta', 'estado']);
            $table->index(['severidad', 'estado']);
            $table->index(['fecha_deteccion', 'estado']);
            $table->index(['asignado_a', 'estado']);
        });

        // Tabla para backup de configuraciones de seguridad
        Schema::create('configuraciones_seguridad_backup', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_configuracion', 100)->index();
            $table->json('configuracion_actual');
            $table->json('configuracion_anterior')->nullable();
            $table->foreignId('modificado_por')->constrained('users')->onDelete('cascade');
            $table->text('motivo_cambio')->nullable();
            $table->boolean('configuracion_activa')->default(true);
            $table->timestamp('fecha_aplicacion')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['nombre_configuracion', 'configuracion_activa']);
            $table->index(['modificado_por', 'created_at']);
        });

        // Tabla para métricas de seguridad agregadas por período
        Schema::create('metricas_seguridad_agregadas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_metrica')->index();
            $table->enum('periodo', ['DIARIO', 'SEMANAL', 'MENSUAL'])->index();
            $table->integer('total_accesos_exitosos')->default(0);
            $table->integer('total_accesos_fallidos')->default(0);
            $table->integer('total_usuarios_unicos')->default(0);
            $table->integer('total_ips_unicas')->default(0);
            $table->integer('total_alertas_generadas')->default(0);
            $table->integer('total_cambios_permisos')->default(0);
            $table->decimal('tasa_exito_accesos', 5, 2)->default(0);
            $table->decimal('tiempo_promedio_sesion', 8, 2)->default(0); // en minutos
            $table->integer('pico_accesos_simultaneos')->default(0);
            $table->json('horarios_pico')->nullable();
            $table->json('ips_mas_activas')->nullable();
            $table->json('usuarios_mas_activos')->nullable();
            $table->timestamps();

            // Índices
            $table->unique(['fecha_metrica', 'periodo']);
            $table->index(['periodo', 'fecha_metrica']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar en orden inverso para respetar foreign keys
        Schema::dropIfExists('metricas_seguridad_agregadas');
        Schema::dropIfExists('configuraciones_seguridad_backup');
        Schema::dropIfExists('alertas_seguridad');
        Schema::dropIfExists('patrones_seguridad');
        Schema::dropIfExists('cambios_permisos_detalle');
        Schema::dropIfExists('intentos_acceso_fallidos');
        Schema::dropIfExists('sesiones_usuario_detalle');
        Schema::dropIfExists('historial_credenciales');
    }
};
