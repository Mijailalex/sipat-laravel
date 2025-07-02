<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique();
            $table->text('valor');
            $table->enum('tipo', ['STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'JSON']);
            $table->text('descripcion');
            $table->string('categoria', 50); // TURNOS, DESCANSOS, VALIDACIONES, etc.
            $table->boolean('editable')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('parametros');
    }
};
