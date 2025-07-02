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
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->integer('semana_numero'); // Número de semana del año
            $table->integer('año');
            $table->integer('rutas_completadas')->default(0);
            $table->integer('rutas_programadas')->default(0);
            $table->boolean('objetivo_cumplido')->default(false); // Si cumplió el objetivo de 3-4 rutas
            $table->decimal('total_ingresos', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['conductor_id', 'semana_numero', 'año']);
            $table->index(['semana_numero', 'año']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('balance_rutas_cortas');
    }
};
