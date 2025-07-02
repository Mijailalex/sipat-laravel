<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // VALIDACION, TURNO, SISTEMA
            $table->string('titulo');
            $table->text('mensaje');
            $table->enum('severidad', ['INFO', 'ADVERTENCIA', 'CRITICA']);
            $table->json('datos_extra')->nullable();
            $table->timestamp('leida_en')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['tipo', 'activa']);
            $table->index(['created_at', 'leida_en']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
};
