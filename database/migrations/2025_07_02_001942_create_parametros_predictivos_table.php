<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateParametrosPredictivosTable extends Migration
{
    /**
     * Ejecutar migración
     */
    public function up()
    {
        Schema::create('parametros_predictivos', function (Blueprint $table) {
            // Columna ID principal
            $table->id();

            // Clave única para identificar parámetro
            $table->string('clave', 50)
                  ->unique()
                  ->comment('Identificador único del parámetro');

            // Configuración flexible en formato JSON
            $table->json('configuracion')
                  ->comment('Configuración personalizada del parámetro');

            // Tipo de predicción
            $table->enum('tipo_prediccion', [
                'RANGO',
                'FORMULA',
                'CONDICIONAL',
                'AUTOMATICO',
                'ML'
            ])->comment('Estrategia de predicción');

            // Descripción del parámetro
            $table->text('descripcion')
                  ->nullable()
                  ->comment('Descripción detallada');

            // Estado de activación
            $table->boolean('activo')
                  ->default(true)
                  ->comment('Parámetro activo/inactivo');

            // Prioridad del parámetro
            $table->integer('prioridad')
                  ->default(0)
                  ->comment('Prioridad en predicciones');

            // Umbral de confianza
            $table->decimal('umbral_confianza', 5, 2)
                  ->default(75.00)
                  ->comment('Porcentaje mínimo de confianza');

            // Campos JSON para datos adicionales
            $table->json('validaciones_asociadas')
                  ->nullable()
                  ->comment('Validaciones relacionadas');

            $table->json('historial_predicciones')
                  ->nullable()
                  ->comment('Historial de predicciones');

            $table->json('metricas_rendimiento')
                  ->nullable()
                  ->comment('Métricas de desempeño');

            // Timestamps para control de creación/actualización
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['activo', 'prioridad']);
            $table->index('tipo_prediccion');
        });

        // Agregar comentario a la tabla
        DB::statement("ALTER TABLE parametros_predictivos COMMENT 'Almacena parámetros para predicciones inteligentes'");
    }

    /**
     * Revertir migración
     */
    public function down()
    {
        Schema::dropIfExists('parametros_predictivos');
    }
}
