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
            $table->string('categoria', 50);
            $table->string('clave', 100)->unique();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['STRING', 'INTEGER', 'DECIMAL', 'BOOLEAN', 'JSON', 'DATE', 'TIME'])->default('STRING');
            $table->text('valor');
            $table->text('valor_por_defecto');
            $table->json('opciones')->nullable();
            $table->json('validaciones')->nullable();
            $table->boolean('modificable')->default(true);
            $table->boolean('visible_interfaz')->default(true);
            $table->integer('orden_visualizacion')->default(0);
            $table->foreignId('modificado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['categoria', 'visible_interfaz']);
            $table->index('clave');
        });
    }

    public function down()
    {
        Schema::dropIfExists('parametros');
    }
};
