<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('planificacion_descansos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductores');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('tipo_descanso', ['DIARIO', 'SEMANAL', 'MENSUAL', 'VACACIONES', 'MEDICO']);
            $table->enum('estado', ['PROGRAMADO', 'ACTIVO', 'COMPLETADO', 'CANCELADO']);
            $table->text('motivo')->nullable();
            $table->json('configuracion_especial')->nullable();
            $table->boolean('es_automatico')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('planificacion_descansos');
    }
};
