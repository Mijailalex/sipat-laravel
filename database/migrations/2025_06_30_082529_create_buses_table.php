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
            $table->string('codigo', 20)->unique();
            $table->string('placa', 20)->unique();
            $table->enum('tipo', ['PERU_BUS', 'PERU_BUS_CHICO']);
            $table->enum('estado', ['OPERATIVO', 'MANTENIMIENTO', 'FUERA_SERVICIO'])->default('OPERATIVO');
            $table->string('origen_disponibilidad', 50);
            $table->time('hora_disponibilidad')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('buses');
    }
};
