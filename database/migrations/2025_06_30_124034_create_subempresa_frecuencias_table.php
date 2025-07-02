<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subempresa_frecuencias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_subempresa', 100);
            $table->string('codigo_frecuencia', 20);
            $table->string('ruta', 200);
            $table->time('hora_salida');
            $table->enum('dias_operacion', ['L-V', 'L-S', 'L-D', 'PERSONALIZADO']);
            $table->json('dias_personalizados')->nullable();
            $table->integer('conductores_requeridos')->default(1);
            $table->string('tipo_servicio', 50)->default('REGULAR');
            $table->boolean('activa')->default(true);
            $table->json('configuracion_especial')->nullable();
            $table->timestamps();

            $table->index(['nombre_subempresa', 'activa']);
            $table->unique(['codigo_frecuencia', 'hora_salida']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('subempresa_frecuencias');
    }
};
