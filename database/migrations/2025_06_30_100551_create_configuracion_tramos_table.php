<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('configuracion_tramos', function (Blueprint $table) {
            $table->id();
            $table->string('tramo', 50)->unique(); // LIMA-ICA, ICA-LIMA, etc.
            $table->enum('rumbo', ['NORTE', 'SUR']);
            $table->decimal('duracion_horas', 4, 2); // Duración en horas
            $table->boolean('es_ruta_corta')->default(true); // Configurable: corta o larga
            $table->decimal('ingreso_base', 8, 2)->default(0); // Ingreso base del tramo
            $table->boolean('activo')->default(true);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuracion_tramos');
    }
};
