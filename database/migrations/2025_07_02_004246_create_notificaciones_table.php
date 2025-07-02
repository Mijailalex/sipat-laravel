<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificacionesTable extends Migration
{
    /**
     * Método para crear la tabla cuando se ejecuta la migración
     */
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            // Columna ID única para cada notificación
            $table->id();  // Crea columna 'id' autoincremental

            // ID del conductor relacionado
            $table->unsignedBigInteger('conductor_id');

            // Tipo de notificación (texto corto)
            $table->string('tipo', 50);

            // Mensaje de la notificación (texto largo)
            $table->text('mensaje');

            // Datos adicionales en formato JSON (opcional)
            $table->json('datos_adicionales')->nullable();

            // Columna para marcar si la notificación fue leída
            $table->boolean('leida')->default(false);

            // Columnas de timestamp (created_at, updated_at)
            $table->timestamps();

            // Crear relación con tabla conductores
            $table->foreign('conductor_id')
                  ->references('id')
                  ->on('conductores')
                  ->onDelete('cascade');  // Si se elimina un conductor, se eliminan sus notificaciones

            // Índices para optimizar búsquedas
            $table->index(['conductor_id', 'leida']);
        });
    }

    /**
     * Método para eliminar la tabla si se revierte la migración
     */
    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
}
