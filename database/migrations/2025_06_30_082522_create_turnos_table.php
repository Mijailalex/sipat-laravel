<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas')->onDelete('cascade');
            $table->foreignId('conductor_id')->nullable()->constrained('conductores')->onDelete('set null');
            $table->date('fecha_turno');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('tipo_turno', 50);
            $table->string('ruta_asignada', 100)->nullable();
            $table->string('origen_conductor', 100)->nullable();
            $table->enum('estado', ['PROGRAMADO', 'EN_CURSO', 'COMPLETADO', 'CANCELADO'])->default('PROGRAMADO');
            $table->text('observaciones')->nullable();
            $table->decimal('horas_trabajadas', 5, 2)->nullable();
            $table->decimal('eficiencia_turno', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['fecha_turno', 'estado']);
            $table->index(['conductor_id', 'fecha_turno']);
            $table->unique(['conductor_id', 'fecha_turno', 'hora_inicio']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('turnos');
    }
};
