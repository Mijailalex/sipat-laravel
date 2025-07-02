<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conductores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 100);
            $table->string('email', 100)->unique();
            $table->string('telefono', 20);
            $table->enum('origen', ['LIMA', 'ICA', 'CHINCHA', 'PISCO', 'CAÑETE', 'NAZCA']);
            $table->enum('estado', ['DISPONIBLE', 'DESCANSO', 'VACACIONES', 'SUSPENDIDO'])->default('DISPONIBLE');
            $table->integer('dias_acumulados')->default(0);
            $table->date('ultima_ruta_corta')->nullable();
            $table->decimal('puntualidad', 5, 2)->default(95.00);
            $table->decimal('eficiencia', 5, 2)->default(93.00);
            $table->integer('rutas_completadas')->default(0);
            $table->integer('horas_trabajadas')->default(0);
            $table->integer('incidencias')->default(0);
            $table->date('fecha_ingreso');
            $table->string('licencia', 20);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('conductores');
    }
};
