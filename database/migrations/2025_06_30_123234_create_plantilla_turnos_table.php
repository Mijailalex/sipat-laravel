<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plantilla_turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas')->onDelete('cascade');
            $table->string('nombre_turno', 100);
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->enum('tipo', ['REGULAR', 'NOCTURNO', 'ESPECIAL', 'REFUERZO'])->default('REGULAR');
            $table->string('descripcion', 200)->nullable();
            $table->json('dias_semana');
            $table->integer('cantidad_conductores_requeridos')->default(1);
            $table->json('requisitos_especiales')->nullable();
            $table->decimal('factor_pago', 5, 2)->default(1.00);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['plantilla_id', 'activo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('plantilla_turnos');
    }
};
