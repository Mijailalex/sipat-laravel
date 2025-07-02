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
            $table->string('codigo_conductor', 20)->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('dni', 8)->unique();
            $table->string('licencia', 20)->unique();
            $table->date('fecha_vencimiento_licencia');
            $table->string('telefono', 15)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('direccion', 200)->nullable();
            $table->date('fecha_ingreso');
            $table->enum('estado', [
                'DISPONIBLE',
                'DESCANSO_FISICO',
                'DESCANSO_SEMANAL',
                'VACACIONES',
                'SUSPENDIDO',
                'FALTO_OPERATIVO',
                'FALTO_NO_OPERATIVO'
            ])->default('DISPONIBLE');
            $table->integer('dias_acumulados')->default(0);
            $table->decimal('eficiencia', 5, 2)->default(100.00);
            $table->decimal('puntualidad', 5, 2)->default(100.00);
            $table->decimal('score_general', 5, 2)->default(100.00);
            $table->decimal('horas_hombre', 8, 2)->default(0.00);
            $table->datetime('ultimo_servicio')->nullable();
            $table->datetime('ultima_ruta_corta')->nullable();
            $table->string('origen_conductor', 100)->nullable();
            $table->string('subempresa', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['estado', 'dias_acumulados']);
            $table->index(['eficiencia', 'puntualidad']);
            $table->index('codigo_conductor');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conductores');
    }
};
