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
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->date('fecha_inicio_descanso');
            $table->date('fecha_fin_descanso');
            $table->enum('tipo_descanso', ['FISICO', 'SEMANAL', 'VACACIONES', 'MEDICO', 'PERSONAL'])->default('FISICO');
            $table->enum('estado', ['PLANIFICADO', 'ACTIVO', 'COMPLETADO', 'CANCELADO'])->default('PLANIFICADO');
            $table->text('motivo')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('fecha_aprobacion')->nullable();
            $table->foreignId('creado_por')->constrained('users')->onDelete('cascade');
            $table->text('observaciones')->nullable();
            $table->boolean('es_automatico')->default(false);
            $table->json('datos_adicionales')->nullable();
            $table->timestamps();

            $table->index(['conductor_id', 'estado']);
            $table->index(['fecha_inicio_descanso', 'fecha_fin_descanso']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('planificacion_descansos');
    }
};
