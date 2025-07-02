<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlantillasTable extends Migration
{
    public function up()
    {
        Schema::create('plantillas', function (Blueprint $table) {
            $table->id(); // Crea el campo id como clave primaria
            $table->string('nombre');
            // Otros campos necesarios
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plantillas');
    }
}
