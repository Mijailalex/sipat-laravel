<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('metricas_diarias', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->integer('total_conductores')->default(0);
            $table->integer('conductores_disponibles')->default(0);
            $table->integer('conductores_descanso')->default(0);
            $table->integer('conductores_suspendidos')->default(0);
            $table->integer('validaciones_pendientes')->default(0);
            $table->integer('validaciones_criticas')->default(0);
            $table->decimal('eficiencia_promedio', 5, 2)->default(0.00);
            $table->decimal('puntualidad_promedio', 5, 2)->default(0.00);
            $table->integer('turnos_programados')->default(0);
            $table->integer('turnos_completados')->default(0);
            $table->integer('rutas_cortas_completadas')->default(0);
            $table->decimal('ingresos_estimados_rutas', 10, 2)->default(0.00);
            $table->json('metricas_adicionales')->nullable();
            $table->timestamps();

            $table->unique('fecha');
            $table->index('fecha');
        });
    }

    public function down()
    {
        Schema::dropIfExists('metricas_diarias');
    }
};
