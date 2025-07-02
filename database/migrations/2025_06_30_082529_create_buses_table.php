<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('numero_bus', 20)->unique();
            $table->string('placa', 10)->unique();
            $table->string('marca', 50);
            $table->string('modelo', 50);
            $table->year('año');
            $table->integer('capacidad_pasajeros');
            $table->enum('tipo_combustible', ['DIESEL', 'GAS', 'ELECTRICO', 'HIBRIDO'])->default('DIESEL');
            $table->enum('estado', ['OPERATIVO', 'MANTENIMIENTO', 'FUERA_SERVICIO', 'ACCIDENTADO'])->default('OPERATIVO');
            $table->string('subempresa', 100)->nullable();
            $table->decimal('kilometraje', 10, 2)->default(0.00);
            $table->date('fecha_ultima_revision')->nullable();
            $table->date('fecha_proxima_revision')->nullable();
            $table->string('ubicacion_actual', 200)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['estado', 'subempresa']);
            $table->index('numero_bus');
            $table->index('placa');
        });
    }

    public function down()
    {
        Schema::dropIfExists('buses');
    }
};
