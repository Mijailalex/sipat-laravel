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
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->date('fecha_salida');
            $table->string('numero_salida', 20);
            $table->time('hora_salida');
            $table->time('hora_llegada');
            $table->string('codigo_bus', 20);
            $table->enum('tipo_servicio', ['RUTERO', 'EXPRESS', 'NORMAL', 'ESPECIAL']);
            $table->string('origen_destino', 100);
            $table->enum('estado', ['PROGRAMADO', 'EN_CURSO', 'COMPLETADO', 'CANCELADO'])->default('PROGRAMADO');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('turnos');
    }
};
