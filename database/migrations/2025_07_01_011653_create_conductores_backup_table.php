<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conductores_backup', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductores');
            $table->enum('tipo_backup', ['RETENCION', 'EMERGENCIA', 'FLOTANTE']);
            $table->json('zonas_cobertura');
            $table->json('tipos_servicio_disponible');
            $table->integer('prioridad')->default(1);
            $table->boolean('disponible_24h')->default(false);
            $table->time('hora_inicio_disponibilidad')->nullable();
            $table->time('hora_fin_disponibilidad')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('conductores_backup');
    }
};
