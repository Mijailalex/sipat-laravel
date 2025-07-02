<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->string('categoria_licencia', 10)->nullable()->after('licencia');
            $table->date('fecha_nacimiento')->nullable()->after('direccion');
            $table->enum('genero', ['M', 'F', 'OTRO'])->nullable()->after('fecha_nacimiento');
            $table->string('contacto_emergencia', 100)->nullable()->after('telefono');
            $table->string('telefono_emergencia', 15)->nullable()->after('contacto_emergencia');
            $table->integer('años_experiencia')->default(0)->after('fecha_ingreso');
            $table->decimal('salario_base', 10, 2)->nullable()->after('años_experiencia');
            $table->json('certificaciones')->nullable()->after('salario_base');
            $table->enum('turno_preferido', ['MAÑANA', 'TARDE', 'NOCHE', 'ROTATIVO'])->default('ROTATIVO')->after('certificaciones');
            $table->datetime('fecha_ultimo_descanso')->nullable()->after('ultima_ruta_corta');
            $table->integer('total_rutas_completadas')->default(0)->after('fecha_ultimo_descanso');
            $table->decimal('total_ingresos_generados', 12, 2)->default(0.00)->after('total_rutas_completadas');
        });
    }

    public function down()
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->dropColumn([
                'categoria_licencia',
                'fecha_nacimiento',
                'genero',
                'contacto_emergencia',
                'telefono_emergencia',
                'años_experiencia',
                'salario_base',
                'certificaciones',
                'turno_preferido',
                'fecha_ultimo_descanso',
                'total_rutas_completadas',
                'total_ingresos_generados'
            ]);
        });
    }
};
