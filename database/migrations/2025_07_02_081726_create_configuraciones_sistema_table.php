<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('configuraciones_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('categoria'); // VALIDACIONES, ALERTAS, REPORTES, GENERAL
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->text('descripcion');
            $table->string('tipo'); // STRING, INTEGER, BOOLEAN, JSON, TIME
            $table->text('valor');
            $table->text('valor_por_defecto');
            $table->json('opciones')->nullable(); // Para dropdowns
            $table->string('unidad')->nullable(); // días, horas, %, etc.
            $table->boolean('editable')->default(true);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['categoria', 'activa']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuraciones_sistema');
    }
};
