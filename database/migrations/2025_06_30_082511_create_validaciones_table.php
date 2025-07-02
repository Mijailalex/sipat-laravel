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
            $table->string('tipo', 50);
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->text('mensaje');
            $table->enum('severidad', ['CRITICA', 'ADVERTENCIA', 'INFO'])->default('INFO');
            $table->enum('estado', ['PENDIENTE', 'RESUELTO', 'VERIFICADO', 'IGNORADO'])->default('PENDIENTE');
            $table->timestamp('fecha_deteccion')->useCurrent();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->string('resuelto_por', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['conductor_id', 'estado']);
            $table->index(['tipo', 'estado']);
            $table->index(['severidad', 'estado']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('validaciones');
    }
};
