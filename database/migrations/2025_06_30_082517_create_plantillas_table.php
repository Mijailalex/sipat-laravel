<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('codigo', 20)->unique();
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['DIARIA', 'SEMANAL', 'MENSUAL', 'ESPECIAL'])->default('DIARIA');
            $table->json('configuracion_turnos');
            $table->json('parametros_especiales')->nullable();
            $table->boolean('activa')->default(true);
            $table->foreignId('creado_por')->constrained('users')->onDelete('cascade');
            $table->datetime('fecha_vigencia_desde')->nullable();
            $table->datetime('fecha_vigencia_hasta')->nullable();
            $table->timestamps();

            $table->index(['activa', 'tipo']);
            $table->index('codigo');
        });
    }

    public function down()
    {
        Schema::dropIfExists('plantillas');
    }
};
