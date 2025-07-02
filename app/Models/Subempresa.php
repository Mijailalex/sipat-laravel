<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subempresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'conductores_asignados',
        'configuracion_operativa',
        'activa'
    ];

    protected $casts = [
        'activa' => 'boolean',
        'configuracion_operativa' => 'array'
    ];

    // Relación con conductores (si existe la FK)
    public function conductores()
    {
        return $this->hasMany(Conductor::class, 'subempresa_id');
    }

    // Relación con asignaciones
    public function asignaciones()
    {
        return $this->hasMany(SubempresaAsignacion::class);
    }

    // Obtener turnos vacíos disponibles
    public function turnosVacios()
    {
        // Si existe tabla turnos con conductor_id nullable
        return \DB::table('turnos')
            ->whereNull('conductor_id')
            ->orWhereExists(function($query) {
                $query->select(\DB::raw(1))
                      ->from('conductores')
                      ->whereColumn('conductores.id', 'turnos.conductor_id')
                      ->where('conductores.estado', '!=', 'DISPONIBLE');
            })
            ->get();
    }
}

## 3. COMPLETAR TABLA SUBEMPRESA_ASIGNACIONES
# La tabla existe pero está vacía, necesita estructura:

# Ejecutar esta consulta SQL directamente:
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS subempresa_id BIGINT UNSIGNED;
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS conductor_id BIGINT UNSIGNED;
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS plantilla_turno_id BIGINT UNSIGNED;
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS fecha_asignacion DATE;
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS semana_numero INT;
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS estado VARCHAR(50) DEFAULT 'PENDIENTE';
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS score_compatibilidad DECIMAL(5,2);
ALTER TABLE subempresa_asignaciones ADD COLUMN IF NOT EXISTS observaciones TEXT;

# O crear una nueva migración solo para agregar columnas:
php artisan make:migration complete_subempresa_asignaciones_table --table=subempresa_asignaciones

## 4. SEEDER PARA SUBEMPRESAS
# Archivo: database/seeders/SubempresaSeeder.php

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subempresa;

class SubempresaSeeder extends Seeder
{
    public function run()
    {
        $subempresas = [
            [
                'codigo' => 'SE001',
                'nombre' => 'Subempresa Lima Norte',
                'descripcion' => 'Cobertura de rutas zona norte de Lima',
                'conductores_asignados' => 0,
                'configuracion_operativa' => [
                    'horario_inicio' => '05:00',
                    'horario_fin' => '23:00',
                    'zonas_cobertura' => ['LIMA_NORTE', 'SAN_MARTIN_PORRES'],
                    'tipos_servicio' => ['RUTERO', 'EXPRESS']
                ],
                'activa' => true
            ],
            [
                'codigo' => 'SE002',
                'nombre' => 'Subempresa Sur',
                'descripcion' => 'Cobertura ICA, CHINCHA, PISCO',
                'conductores_asignados' => 0,
                'configuracion_operativa' => [
                    'horario_inicio' => '04:30',
                    'horario_fin' => '22:30',
                    'zonas_cobertura' => ['ICA', 'CHINCHA', 'PISCO'],
                    'tipos_servicio' => ['RUTERO', 'NORMAL', 'ESPECIAL']
                ],
                'activa' => true
            ]
        ];

        foreach ($subempresas as $subempresa) {
            Subempresa::create($subempresa);
        }
    }
}

## 5. COMANDOS PARA EJECUTAR

# 1. Verificar que no tengas migraciones pendientes de subempresas
php artisan migrate:status

# 2. Si tienes una migración create_subempresas_table pendiente, elimínala:
# rm database/migrations/*create_subempresas_table.php

# 3. Ejecutar seeder corregido de conductores:
php artisan db:seed --class=ConductorSeeder

# 4. Crear y ejecutar seeder de subempresas:
php artisan make:seeder SubempresaSeeder
# (Copiar el contenido del seeder de arriba)
php artisan db:seed --class=SubempresaSeeder

# 5. Verificar datos:
php artisan tinker
>>> App\Models\Conductor::count();
>>> App\Models\Subempresa::count();
>>> exit
