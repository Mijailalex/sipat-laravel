<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('balance_rutas_cortas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('tramo', 100);
            $table->integer('total_rutas')->default(0);
            $table->integer('rutas_completadas')->default(0);
            $table->integer('rutas_canceladas')->default(0);
            $table->integer('total_pasajeros')->default(0);
            $table->decimal('ingreso_total', 12, 2)->default(0.00);
            $table->decimal('ingreso_promedio_ruta', 10, 2)->default(0.00);
            $table->decimal('ocupacion_promedio', 5, 2)->default(0.00);
            $table->integer('conductores_participantes')->default(0);
            $table->decimal('eficiencia_promedio', 5, 2)->default(0.00);
            $table->json('metricas_adicionales')->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'tramo']);
            $table->index(['fecha', 'tramo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('balance_rutas_cortas');
    }
};
