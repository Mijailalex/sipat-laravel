<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rutas_cortas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->foreignId('bus_id')->nullable()->constrained('buses')->onDelete('set null');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin')->nullable();
            $table->string('origen', 200);
            $table->string('destino', 200);
            $table->string('tramo', 100);
            $table->decimal('distancia_km', 8, 2)->nullable();
            $table->integer('pasajeros_transportados')->default(0);
            $table->decimal('tarifa_cobrada', 8, 2)->default(0.00);
            $table->decimal('ingreso_estimado', 10, 2)->default(0.00);
            $table->enum('estado', ['PROGRAMADA', 'EN_CURSO', 'COMPLETADA', 'CANCELADA'])->default('PROGRAMADA');
            $table->text('observaciones')->nullable();
            $table->json('datos_gps')->nullable();
            $table->decimal('calificacion_servicio', 3, 2)->nullable();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
            $table->index(['conductor_id', 'fecha']);
            $table->index('tramo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rutas_cortas');
    }
};
