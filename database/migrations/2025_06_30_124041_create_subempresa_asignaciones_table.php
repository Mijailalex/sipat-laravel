<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subempresa_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frecuencia_id')->constrained('subempresa_frecuencias')->onDelete('cascade');
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->foreignId('bus_id')->nullable()->constrained('buses')->onDelete('set null');
            $table->date('fecha_asignacion');
            $table->enum('estado', ['ASIGNADO', 'CONFIRMADO', 'COMPLETADO', 'CANCELADO'])->default('ASIGNADO');
            $table->time('hora_real_salida')->nullable();
            $table->time('hora_real_llegada')->nullable();
            $table->integer('pasajeros_transportados')->nullable();
            $table->decimal('ingresos_generados', 10, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['fecha_asignacion', 'estado']);
            // Nombre más corto para el índice único
            $table->unique(['frecuencia_id', 'conductor_id', 'fecha_asignacion'], 'sub_asig_freq_cond_fecha_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('subempresa_asignaciones');
    }
};
