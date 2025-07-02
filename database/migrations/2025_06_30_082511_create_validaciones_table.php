<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('validaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->string('tipo', 50);
            $table->enum('severidad', ['INFO', 'ADVERTENCIA', 'CRITICA'])->default('INFO');
            $table->string('titulo', 200);
            $table->text('descripcion');
            $table->json('detalles_adicionales')->nullable();
            $table->enum('estado', ['PENDIENTE', 'EN_PROCESO', 'RESUELTO', 'OMITIDO'])->default('PENDIENTE');
            $table->text('accion_realizada')->nullable();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('fecha_resolucion')->nullable();
            $table->decimal('prioridad_calculada', 5, 2)->default(0.00);
            $table->timestamps();

            $table->index(['estado', 'severidad', 'prioridad_calculada']);
            $table->index(['conductor_id', 'tipo']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('validaciones');
    }
};
