<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Si la tabla no existe, crearla
        if (!Schema::hasTable('notificaciones')) {
            Schema::create('notificaciones', function (Blueprint $table) {
                $table->id();
                $table->string('tipo');
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
        } else {
            // Si existe, solo agregar columnas faltantes
            Schema::table('notificaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('notificaciones', 'tipo')) {
                    $table->string('tipo')->after('id');
                }
                if (!Schema::hasColumn('notificaciones', 'titulo')) {
                    $table->string('titulo')->after('tipo');
                }
                if (!Schema::hasColumn('notificaciones', 'mensaje')) {
                    $table->text('mensaje')->after('titulo');
                }
                if (!Schema::hasColumn('notificaciones', 'severidad')) {
                    $table->enum('severidad', ['INFO', 'ADVERTENCIA', 'CRITICA'])->after('mensaje');
                }
                if (!Schema::hasColumn('notificaciones', 'datos_extra')) {
                    $table->json('datos_extra')->nullable()->after('severidad');
                }
                if (!Schema::hasColumn('notificaciones', 'leida_en')) {
                    $table->timestamp('leida_en')->nullable()->after('datos_extra');
                }
                if (!Schema::hasColumn('notificaciones', 'activa')) {
                    $table->boolean('activa')->default(true)->after('leida_en');
                }
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
};
