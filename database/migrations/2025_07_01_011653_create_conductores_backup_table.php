<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('conductores_backup', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conductor_id');
            $table->string('accion', 50); // 'CREADO', 'ACTUALIZADO', 'ELIMINADO'
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->json('campos_modificados')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('razon_cambio')->nullable();
            $table->timestamps();

            $table->index(['conductor_id', 'accion']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('conductores_backup');
    }
};
