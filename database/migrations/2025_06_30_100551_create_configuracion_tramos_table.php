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
            $table->string('codigo_tramo', 20)->unique();
            $table->string('nombre', 200);
            $table->string('origen', 200);
            $table->string('destino', 200);
            $table->decimal('distancia_km', 8, 2);
            $table->decimal('tarifa_base', 8, 2);
            $table->decimal('tarifa_maxima', 8, 2)->nullable();
            $table->integer('tiempo_estimado_minutos');
            $table->enum('tipo_servicio', ['URBANO', 'INTERURBANO', 'ESPECIAL'])->default('URBANO');
            $table->boolean('activo')->default(true);
            $table->json('horarios_disponibles')->nullable();
            $table->json('restricciones')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->index(['activo', 'tipo_servicio']);
            $table->index('codigo_tramo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuracion_tramos');
    }
};
