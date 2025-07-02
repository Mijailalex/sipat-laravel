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
            $table->date('fecha')->unique();
            $table->decimal('cobertura_turnos', 5, 2)->default(0.00);
            $table->decimal('cumplimiento_regimen', 5, 2)->default(0.00);
            $table->decimal('eficiencia_asignacion', 5, 2)->default(0.00);
            $table->integer('conductores_activos')->default(0);
            $table->integer('violaciones_detectadas')->default(0);
            $table->integer('turnos_completados')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('metricas_diarias');
    }
};
