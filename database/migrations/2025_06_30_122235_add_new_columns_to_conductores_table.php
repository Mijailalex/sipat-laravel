<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->timestamp('disponibilidad_llegada')->nullable()->after('ultima_ruta_corta');
            $table->timestamp('ultima_hora_servicio')->nullable()->after('disponibilidad_llegada');
            $table->enum('estado_operativo', ['DISPONIBLE', 'OCUPADO', 'DESCANSO', 'EN_RUTA'])->default('DISPONIBLE')->after('estado');
        });
    }

    public function down()
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->dropColumn(['disponibilidad_llegada', 'ultima_hora_servicio', 'estado_operativo']);
        });
    }
};
