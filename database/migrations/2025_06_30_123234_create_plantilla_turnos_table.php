<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlantillaTurnosTable extends Migration
{
    public function up()
    {
        Schema::create('plantilla_turnos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plantilla_id'); // Asegúrate de que el tipo coincida con el id de plantillas
            $table->string('nombre_turno');
            // Otros campos necesarios
            $table->timestamps();

            // Añade la restricción de clave foránea
            $table->foreign('plantilla_id')
                  ->references('id')
                  ->on('plantillas')
                  ->onDelete('cascade'); // O 'restrict' si prefieres
        });
    }

    public function down()
    {
        Schema::table('plantilla_turnos', function (Blueprint $table) {
            $table->dropForeign(['plantilla_id']); // Elimina la restricción de clave foránea
        });
        Schema::dropIfExists('plantilla_turnos');
    }
}
