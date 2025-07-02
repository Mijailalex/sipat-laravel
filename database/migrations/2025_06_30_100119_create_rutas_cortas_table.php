<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rutas_cortas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->string('tramo', 50); // LIMA-CHINCHA, ICA-PISCO, etc.
            $table->enum('rumbo', ['NORTE', 'SUR']);
            $table->date('fecha_asignacion');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->decimal('duracion_horas', 4, 2); // Duración en horas
            $table->enum('estado', ['PROGRAMADA', 'EN_CURSO', 'COMPLETADA', 'CANCELADA'])->default('PROGRAMADA');
            $table->integer('semana_numero'); // Número de semana del año
            $table->integer('dia_semana'); // 1=Lunes, 7=Domingo
            $table->boolean('es_consecutiva')->default(false); // Si es consecutiva al día anterior
            $table->decimal('ingreso_estimado', 8, 2)->nullable(); // Ingreso por la ruta
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Índices para optimización
            $table->index(['conductor_id', 'fecha_asignacion']);
            $table->index(['fecha_asignacion', 'estado']);
            $table->index(['semana_numero', 'conductor_id']);
            $table->index('tramo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rutas_cortas');
    }
};
