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
        Schema::create('historial_planificaciones', function (Blueprint $table) {
            $table->id();

            // Información básica de la planificación
            $table->date('fecha_planificacion')->index();
            $table->enum('estado', [
                'INICIADO',
                'EN_PROCESO',
                'COMPLETADO',
                'ERROR',
                'CANCELADO',
                'OPTIMIZADO'
            ])->default('INICIADO')->index();

            $table->enum('tipo_planificacion', [
                'AUTOMATICA',
                'MANUAL',
                'REPLANIFICACION',
                'AJUSTE',
                'EMERGENCIA'
            ])->default('AUTOMATICA')->index();

            // Referencias
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas')->onDelete('set null');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            // Resultados y datos de la ejecución
            $table->json('resultado')->nullable();
            $table->text('error')->nullable();
            $table->json('metricas')->nullable();
            $table->json('configuracion_utilizada')->nullable();

            // Tracking de cambios
            $table->json('cambios_realizados')->nullable();
            $table->json('turnos_afectados')->nullable();
            $table->json('conductores_afectados')->nullable();

            // Métricas de rendimiento
            $table->integer('validaciones_generadas')->default(0);
            $table->integer('tiempo_procesamiento')->nullable(); // en segundos
            $table->timestamp('fecha_inicio_proceso')->nullable();
            $table->timestamp('fecha_fin_proceso')->nullable();

            // Información adicional
            $table->text('observaciones')->nullable();
            $table->string('version_algoritmo')->default('1.0');
            $table->ipAddress('ip_origen')->nullable();
            $table->text('user_agent')->nullable();

            // Campos de auditoría
            $table->timestamps();
            $table->softDeletes();

            // Índices compuestos para optimizar consultas
            $table->index(['fecha_planificacion', 'estado']);
            $table->index(['tipo_planificacion', 'estado']);
            $table->index(['created_at', 'estado']);
            $table->index(['usuario_id', 'fecha_planificacion']);
            $table->index(['plantilla_id', 'tipo_planificacion']);

            // Índice para consultas de métricas
            $table->index(['fecha_planificacion', 'tipo_planificacion', 'estado']);
        });

        // Crear tabla para almacenar snapshots de configuración
        Schema::create('configuraciones_planificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historial_planificacion_id')->constrained('historial_planificaciones')->onDelete('cascade');
            $table->string('categoria', 50)->index();
            $table->string('clave', 100)->index();
            $table->text('valor');
            $table->text('valor_anterior')->nullable();
            $table->timestamp('fecha_cambio');
            $table->foreignId('cambiado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Índices
            $table->index(['categoria', 'clave']);
            $table->unique(['historial_planificacion_id', 'categoria', 'clave'], 'unique_config_per_historial');
        });

        // Crear tabla para métricas detalladas por paso
        Schema::create('metricas_pasos_planificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historial_planificacion_id')->constrained('historial_planificaciones')->onDelete('cascade');
            $table->tinyInteger('numero_paso')->unsigned(); // 1-10 para los 10 pasos
            $table->string('nombre_paso', 100);
            $table->enum('estado_paso', ['INICIADO', 'COMPLETADO', 'ERROR', 'OMITIDO'])->default('INICIADO');
            $table->timestamp('fecha_inicio');
            $table->timestamp('fecha_fin')->nullable();
            $table->integer('duracion_segundos')->nullable();
            $table->json('datos_entrada')->nullable();
            $table->json('datos_salida')->nullable();
            $table->json('metricas_paso')->nullable();
            $table->text('observaciones_paso')->nullable();
            $table->text('error_paso')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['historial_planificacion_id', 'numero_paso']);
            $table->index(['numero_paso', 'estado_paso']);
        });

        // Crear tabla para tracking de conductores en planificación
        Schema::create('conductores_planificacion_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historial_planificacion_id')->constrained('historial_planificaciones')->onDelete('cascade');
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->enum('accion', [
                'INCLUIDO',
                'EXCLUIDO',
                'ASIGNADO',
                'REASIGNADO',
                'LIBERADO',
                'VALIDADO',
                'RECHAZADO'
            ])->index();
            $table->string('motivo', 200)->nullable();
            $table->integer('paso_procesamiento')->nullable(); // En qué paso del algoritmo
            $table->json('datos_conductor')->nullable(); // Snapshot de datos relevantes
            $table->decimal('score_compatibilidad', 5, 2)->nullable();
            $table->json('criterios_evaluacion')->nullable();
            $table->timestamp('fecha_accion');
            $table->timestamps();

            // Índices
            $table->index(['historial_planificacion_id', 'conductor_id']);
            $table->index(['conductor_id', 'accion']);
            $table->index(['fecha_accion', 'accion']);
        });

        // Crear tabla para análisis de rendimiento del algoritmo
        Schema::create('rendimiento_algoritmo_planificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historial_planificacion_id')->constrained('historial_planificaciones')->onDelete('cascade');
            $table->string('metrica_nombre', 100)->index();
            $table->decimal('valor_metrica', 10, 4);
            $table->string('unidad_medida', 20)->nullable();
            $table->decimal('valor_objetivo', 10, 4)->nullable();
            $table->decimal('porcentaje_cumplimiento', 5, 2)->nullable();
            $table->enum('categoria_metrica', [
                'TIEMPO',
                'EFICIENCIA',
                'CALIDAD',
                'COBERTURA',
                'OPTIMIZACION'
            ])->index();
            $table->json('detalles_calculo')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['metrica_nombre', 'categoria_metrica']);
            $table->index(['historial_planificacion_id', 'categoria_metrica']);
        });

        // Crear tabla para almacenar validaciones específicas de cada planificación
        Schema::create('validaciones_planificacion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historial_planificacion_id')->constrained('historial_planificaciones')->onDelete('cascade');
            $table->foreignId('validacion_id')->nullable()->constrained('validaciones')->onDelete('set null');
            $table->string('tipo_validacion', 50)->index();
            $table->enum('severidad', ['INFO', 'ADVERTENCIA', 'CRITICA', 'BLOQUEANTE'])->index();
            $table->enum('estado_validacion', ['PENDIENTE', 'PROCESADA', 'RESUELTA', 'IGNORADA'])->default('PENDIENTE');
            $table->text('descripcion_validacion');
            $table->json('datos_validacion')->nullable();
            $table->text('accion_tomada')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['historial_planificacion_id', 'tipo_validacion']);
            $table->index(['severidad', 'estado_validacion']);
            $table->index(['fecha_resolucion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar en orden inverso para respetar las foreign keys
        Schema::dropIfExists('validaciones_planificacion_detalle');
        Schema::dropIfExists('rendimiento_algoritmo_planificacion');
        Schema::dropIfExists('conductores_planificacion_tracking');
        Schema::dropIfExists('metricas_pasos_planificacion');
        Schema::dropIfExists('configuraciones_planificacion');
        Schema::dropIfExists('historial_planificaciones');
    }
};
