<?php
/**
 * =============================================================================
 * MIGRACIÓN COMPLETA PARA TABLA SUBEMPRESA_ASIGNACIONES
 * =============================================================================
 * Archivo: database/migrations/2025_01_15_000001_complete_subempresa_asignaciones_table.php
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Verificar si la tabla existe, si no, crearla desde cero
        if (!Schema::hasTable('subempresa_asignaciones')) {
            $this->crearTablaCompleta();
        } else {
            $this->completarTablaExistente();
        }
    }

    private function crearTablaCompleta()
    {
        Schema::create('subempresa_asignaciones', function (Blueprint $table) {
            $table->id();

            // Relaciones principales
            $table->unsignedBigInteger('subempresa_id');
            $table->unsignedBigInteger('conductor_id');
            $table->unsignedBigInteger('plantilla_turno_id')->nullable();
            $table->unsignedBigInteger('bus_id')->nullable();

            // Información temporal
            $table->date('fecha_asignacion');
            $table->date('fecha_salida');
            $table->time('hora_salida');
            $table->time('hora_llegada');
            $table->integer('numero_salida')->nullable();
            $table->integer('semana_numero')->nullable();

            // Estados y control
            $table->enum('estado', [
                'PENDIENTE',
                'ASIGNADO',
                'CONFIRMADO',
                'EN_CURSO',
                'COMPLETADO',
                'CANCELADO',
                'REAGENDADO'
            ])->default('PENDIENTE');

            $table->enum('tipo_asignacion', [
                'AUTOMATICA',
                'MANUAL',
                'REASIGNACION',
                'EMERGENCIA'
            ])->default('AUTOMATICA');

            // Información del servicio
            $table->string('tipo_servicio', 50)->nullable(); // ESTANDAR, NAZCA, VIP, EXPRESS
            $table->string('codigo_servicio', 20)->nullable(); // 01, 39, etc.
            $table->string('origen_destino', 255)->nullable();
            $table->string('origen_conductor', 100)->nullable();

            // Métricas y evaluación
            $table->decimal('score_compatibilidad', 5, 2)->default(0.00);
            $table->decimal('eficiencia_esperada', 5, 2)->nullable();
            $table->integer('prioridad')->default(0);
            $table->boolean('es_media_vuelta')->default(false);
            $table->boolean('es_fresco')->default(false);

            // Información operativa real
            $table->time('hora_real_salida')->nullable();
            $table->time('hora_real_llegada')->nullable();
            $table->integer('pasajeros_transportados')->nullable();
            $table->decimal('ingresos_generados', 10, 2)->nullable();
            $table->text('observaciones')->nullable();

            // Control de disponibilidad
            $table->datetime('disponible_desde')->nullable();
            $table->datetime('proximo_turno')->nullable();
            $table->decimal('horas_trabajadas', 8, 2)->default(0.00);
            $table->boolean('requiere_descanso')->default(false);

            // Auditoría y seguimiento
            $table->unsignedBigInteger('asignado_por')->nullable();
            $table->unsignedBigInteger('modificado_por')->nullable();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamp('fecha_inicio_real')->nullable();
            $table->timestamp('fecha_fin_real')->nullable();
            $table->json('historial_cambios')->nullable();

            // Información adicional de la planificación
            $table->string('regimen_conductor', 20)->nullable(); // 26x4, 6x1
            $table->integer('dias_acumulados_conductor')->default(0);
            $table->boolean('es_back_up')->default(false);
            $table->string('motivo_asignacion', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices para optimización de consultas
            $table->index(['fecha_asignacion', 'estado'], 'idx_fecha_estado');
            $table->index(['conductor_id', 'fecha_asignacion'], 'idx_conductor_fecha');
            $table->index(['subempresa_id', 'estado'], 'idx_subempresa_estado');
            $table->index(['fecha_salida', 'hora_salida'], 'idx_salida_completa');
            $table->index(['estado', 'tipo_asignacion'], 'idx_estado_tipo');
            $table->index(['score_compatibilidad'], 'idx_score');
            $table->index(['es_media_vuelta', 'es_fresco'], 'idx_tipo_conductor');

            // Índice único para evitar duplicados
            $table->unique([
                'conductor_id',
                'fecha_salida',
                'hora_salida'
            ], 'uk_conductor_fecha_hora');

            // Foreign keys (si las tablas existen)
            if (Schema::hasTable('subempresas')) {
                $table->foreign('subempresa_id')->references('id')->on('subempresas')->onDelete('cascade');
            }
            if (Schema::hasTable('conductores')) {
                $table->foreign('conductor_id')->references('id')->on('conductores')->onDelete('cascade');
            }
            if (Schema::hasTable('buses')) {
                $table->foreign('bus_id')->references('id')->on('buses')->onDelete('set null');
            }
            if (Schema::hasTable('users')) {
                $table->foreign('asignado_por')->references('id')->on('users')->onDelete('set null');
                $table->foreign('modificado_por')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    private function completarTablaExistente()
    {
        Schema::table('subempresa_asignaciones', function (Blueprint $table) {
            // Verificar y agregar columnas faltantes una por una

            if (!Schema::hasColumn('subempresa_asignaciones', 'subempresa_id')) {
                $table->unsignedBigInteger('subempresa_id');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'conductor_id')) {
                $table->unsignedBigInteger('conductor_id');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'plantilla_turno_id')) {
                $table->unsignedBigInteger('plantilla_turno_id')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'bus_id')) {
                $table->unsignedBigInteger('bus_id')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'fecha_asignacion')) {
                $table->date('fecha_asignacion');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'fecha_salida')) {
                $table->date('fecha_salida');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'hora_salida')) {
                $table->time('hora_salida');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'hora_llegada')) {
                $table->time('hora_llegada');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'numero_salida')) {
                $table->integer('numero_salida')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'semana_numero')) {
                $table->integer('semana_numero')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'estado')) {
                $table->enum('estado', [
                    'PENDIENTE', 'ASIGNADO', 'CONFIRMADO', 'EN_CURSO',
                    'COMPLETADO', 'CANCELADO', 'REAGENDADO'
                ])->default('PENDIENTE');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'tipo_asignacion')) {
                $table->enum('tipo_asignacion', [
                    'AUTOMATICA', 'MANUAL', 'REASIGNACION', 'EMERGENCIA'
                ])->default('AUTOMATICA');
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'tipo_servicio')) {
                $table->string('tipo_servicio', 50)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'codigo_servicio')) {
                $table->string('codigo_servicio', 20)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'origen_destino')) {
                $table->string('origen_destino', 255)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'origen_conductor')) {
                $table->string('origen_conductor', 100)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'score_compatibilidad')) {
                $table->decimal('score_compatibilidad', 5, 2)->default(0.00);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'eficiencia_esperada')) {
                $table->decimal('eficiencia_esperada', 5, 2)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'prioridad')) {
                $table->integer('prioridad')->default(0);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'es_media_vuelta')) {
                $table->boolean('es_media_vuelta')->default(false);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'es_fresco')) {
                $table->boolean('es_fresco')->default(false);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'hora_real_salida')) {
                $table->time('hora_real_salida')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'hora_real_llegada')) {
                $table->time('hora_real_llegada')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'pasajeros_transportados')) {
                $table->integer('pasajeros_transportados')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'ingresos_generados')) {
                $table->decimal('ingresos_generados', 10, 2)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'observaciones')) {
                $table->text('observaciones')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'disponible_desde')) {
                $table->datetime('disponible_desde')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'proximo_turno')) {
                $table->datetime('proximo_turno')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'horas_trabajadas')) {
                $table->decimal('horas_trabajadas', 8, 2)->default(0.00);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'requiere_descanso')) {
                $table->boolean('requiere_descanso')->default(false);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'asignado_por')) {
                $table->unsignedBigInteger('asignado_por')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'modificado_por')) {
                $table->unsignedBigInteger('modificado_por')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'fecha_confirmacion')) {
                $table->timestamp('fecha_confirmacion')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'fecha_inicio_real')) {
                $table->timestamp('fecha_inicio_real')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'fecha_fin_real')) {
                $table->timestamp('fecha_fin_real')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'historial_cambios')) {
                $table->json('historial_cambios')->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'regimen_conductor')) {
                $table->string('regimen_conductor', 20)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'dias_acumulados_conductor')) {
                $table->integer('dias_acumulados_conductor')->default(0);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'es_back_up')) {
                $table->boolean('es_back_up')->default(false);
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'motivo_asignacion')) {
                $table->string('motivo_asignacion', 255)->nullable();
            }

            if (!Schema::hasColumn('subempresa_asignaciones', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Agregar índices si no existen
        $this->agregarIndicesFaltantes();
    }

    private function agregarIndicesFaltantes()
    {
        try {
            Schema::table('subempresa_asignaciones', function (Blueprint $table) {
                // Los índices se agregan de forma segura (Laravel los ignora si ya existen)
                $table->index(['fecha_asignacion', 'estado'], 'idx_fecha_estado');
                $table->index(['conductor_id', 'fecha_asignacion'], 'idx_conductor_fecha');
                $table->index(['subempresa_id', 'estado'], 'idx_subempresa_estado');
                $table->index(['fecha_salida', 'hora_salida'], 'idx_salida_completa');
                $table->index(['estado', 'tipo_asignacion'], 'idx_estado_tipo');
                $table->index(['score_compatibilidad'], 'idx_score');
                $table->index(['es_media_vuelta', 'es_fresco'], 'idx_tipo_conductor');
            });
        } catch (\Exception $e) {
            // Continuar si hay errores con índices (pueden ya existir)
        }
    }

    public function down()
    {
        Schema::dropIfExists('subempresa_asignaciones');
    }
};

/**
 * =============================================================================
 * MODELO ELOQUENT COMPLETO PARA SUBEMPRESA_ASIGNACIONES
 * =============================================================================
 * Archivo: app/Models/SubempresaAsignacion.php
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class SubempresaAsignacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'subempresa_asignaciones';

    protected $fillable = [
        'subempresa_id',
        'conductor_id',
        'plantilla_turno_id',
        'bus_id',
        'fecha_asignacion',
        'fecha_salida',
        'hora_salida',
        'hora_llegada',
        'numero_salida',
        'semana_numero',
        'estado',
        'tipo_asignacion',
        'tipo_servicio',
        'codigo_servicio',
        'origen_destino',
        'origen_conductor',
        'score_compatibilidad',
        'eficiencia_esperada',
        'prioridad',
        'es_media_vuelta',
        'es_fresco',
        'hora_real_salida',
        'hora_real_llegada',
        'pasajeros_transportados',
        'ingresos_generados',
        'observaciones',
        'disponible_desde',
        'proximo_turno',
        'horas_trabajadas',
        'requiere_descanso',
        'asignado_por',
        'modificado_por',
        'fecha_confirmacion',
        'fecha_inicio_real',
        'fecha_fin_real',
        'historial_cambios',
        'regimen_conductor',
        'dias_acumulados_conductor',
        'es_back_up',
        'motivo_asignacion'
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
        'fecha_salida' => 'date',
        'hora_salida' => 'datetime:H:i',
        'hora_llegada' => 'datetime:H:i',
        'hora_real_salida' => 'datetime:H:i',
        'hora_real_llegada' => 'datetime:H:i',
        'score_compatibilidad' => 'decimal:2',
        'eficiencia_esperada' => 'decimal:2',
        'horas_trabajadas' => 'decimal:2',
        'ingresos_generados' => 'decimal:2',
        'es_media_vuelta' => 'boolean',
        'es_fresco' => 'boolean',
        'requiere_descanso' => 'boolean',
        'es_back_up' => 'boolean',
        'disponible_desde' => 'datetime',
        'proximo_turno' => 'datetime',
        'fecha_confirmacion' => 'datetime',
        'fecha_inicio_real' => 'datetime',
        'fecha_fin_real' => 'datetime',
        'historial_cambios' => 'array'
    ];

    // Constantes para estados
    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_ASIGNADO = 'ASIGNADO';
    const ESTADO_CONFIRMADO = 'CONFIRMADO';
    const ESTADO_EN_CURSO = 'EN_CURSO';
    const ESTADO_COMPLETADO = 'COMPLETADO';
    const ESTADO_CANCELADO = 'CANCELADO';
    const ESTADO_REAGENDADO = 'REAGENDADO';

    // Constantes para tipos de asignación
    const TIPO_AUTOMATICA = 'AUTOMATICA';
    const TIPO_MANUAL = 'MANUAL';
    const TIPO_REASIGNACION = 'REASIGNACION';
    const TIPO_EMERGENCIA = 'EMERGENCIA';

    // Constantes para tipos de servicio
    const SERVICIO_ESTANDAR = 'ESTANDAR';
    const SERVICIO_NAZCA = 'NAZCA';
    const SERVICIO_VIP = 'VIP';
    const SERVICIO_EXPRESS = 'EXPRESS';

    // =============================================================================
    // RELACIONES ELOQUENT
    // =============================================================================

    public function subempresa()
    {
        return $this->belongsTo(Subempresa::class);
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function asignadoPor()
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function modificadoPor()
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }

    // =============================================================================
    // SCOPES PARA CONSULTAS
    // =============================================================================

    public function scopeEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeActivas($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_ASIGNADO,
            self::ESTADO_CONFIRMADO,
            self::ESTADO_EN_CURSO
        ]);
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', self::ESTADO_COMPLETADO);
    }

    public function scopeFecha($query, $fecha)
    {
        return $query->whereDate('fecha_salida', $fecha);
    }

    public function scopeSemana($query, $semana, $ano = null)
    {
        $ano = $ano ?? date('Y');
        return $query->where('semana_numero', $semana)
                    ->whereYear('fecha_salida', $ano);
    }

    public function scopeMediasVueltas($query)
    {
        return $query->where('es_media_vuelta', true);
    }

    public function scopeFrescos($query)
    {
        return $query->where('es_fresco', true);
    }

    public function scopeBackUps($query)
    {
        return $query->where('es_back_up', true);
    }

    public function scopeTipoServicio($query, $tipo)
    {
        return $query->where('tipo_servicio', $tipo);
    }

    public function scopeRangoScore($query, $minimo, $maximo = 100)
    {
        return $query->whereBetween('score_compatibilidad', [$minimo, $maximo]);
    }

    // =============================================================================
    // MÉTODOS DE NEGOCIO
    // =============================================================================

    public function confirmar($usuarioId = null)
    {
        $this->update([
            'estado' => self::ESTADO_CONFIRMADO,
            'fecha_confirmacion' => now(),
            'modificado_por' => $usuarioId
        ]);

        $this->registrarCambio('confirmacion', 'Asignación confirmada', $usuarioId);

        return $this;
    }

    public function iniciar($usuarioId = null)
    {
        $this->update([
            'estado' => self::ESTADO_EN_CURSO,
            'fecha_inicio_real' => now(),
            'hora_real_salida' => now()->format('H:i'),
            'modificado_por' => $usuarioId
        ]);

        $this->registrarCambio('inicio', 'Servicio iniciado', $usuarioId);

        return $this;
    }

    public function completar($datosFinalizacion = [], $usuarioId = null)
    {
        $datosActualizacion = [
            'estado' => self::ESTADO_COMPLETADO,
            'fecha_fin_real' => now(),
            'hora_real_llegada' => now()->format('H:i'),
            'modificado_por' => $usuarioId
        ];

        // Agregar datos opcionales de finalización
        if (isset($datosFinalizacion['pasajeros'])) {
            $datosActualizacion['pasajeros_transportados'] = $datosFinalizacion['pasajeros'];
        }

        if (isset($datosFinalizacion['ingresos'])) {
            $datosActualizacion['ingresos_generados'] = $datosFinalizacion['ingresos'];
        }

        if (isset($datosFinalizacion['observaciones'])) {
            $datosActualizacion['observaciones'] = $datosFinalizacion['observaciones'];
        }

        // Calcular horas trabajadas
        if ($this->hora_real_salida) {
            $inicio = Carbon::createFromFormat('H:i', $this->hora_real_salida);
            $fin = Carbon::now();
            $datosActualizacion['horas_trabajadas'] = $fin->diffInHours($inicio, true);
        }

        $this->update($datosActualizacion);
        $this->registrarCambio('completado', 'Servicio completado', $usuarioId, $datosFinalizacion);

        return $this;
    }

    public function cancelar($motivo = null, $usuarioId = null)
    {
        $this->update([
            'estado' => self::ESTADO_CANCELADO,
            'observaciones' => $motivo,
            'modificado_por' => $usuarioId
        ]);

        $this->registrarCambio('cancelacion', $motivo ?? 'Asignación cancelada', $usuarioId);

        return $this;
    }

    public function reagendar($nuevaFecha, $nuevaHora, $motivo = null, $usuarioId = null)
    {
        $datosAnteriores = [
            'fecha_anterior' => $this->fecha_salida,
            'hora_anterior' => $this->hora_salida
        ];

        $this->update([
            'estado' => self::ESTADO_REAGENDADO,
            'fecha_salida' => $nuevaFecha,
            'hora_salida' => $nuevaHora,
            'observaciones' => $motivo,
            'modificado_por' => $usuarioId
        ]);

        $this->registrarCambio('reagendamiento', $motivo ?? 'Asignación reagendada', $usuarioId, $datosAnteriores);

        return $this;
    }

    public function calcularDuracionEsperada()
    {
        if (!$this->hora_salida || !$this->hora_llegada) {
            return 0;
        }

        $salida = Carbon::createFromFormat('H:i', $this->hora_salida);
        $llegada = Carbon::createFromFormat('H:i', $this->hora_llegada);

        // Manejar caso de llegada al día siguiente
        if ($llegada->lt($salida)) {
            $llegada->addDay();
        }

        return $llegada->diffInHours($salida, true);
    }

    public function calcularDuracionReal()
    {
        if (!$this->hora_real_salida || !$this->hora_real_llegada) {
            return 0;
        }

        $salida = Carbon::createFromFormat('H:i', $this->hora_real_salida);
        $llegada = Carbon::createFromFormat('H:i', $this->hora_real_llegada);

        if ($llegada->lt($salida)) {
            $llegada->addDay();
        }

        return $llegada->diffInHours($salida, true);
    }

    public function esPuntual($toleranciaMinutos = 15)
    {
        if (!$this->hora_salida || !$this->hora_real_salida) {
            return null; // No se puede determinar
        }

        $esperada = Carbon::createFromFormat('H:i', $this->hora_salida);
        $real = Carbon::createFromFormat('H:i', $this->hora_real_salida);

        return abs($esperada->diffInMinutes($real)) <= $toleranciaMinutos;
    }

    public function obtenerEficiencia()
    {
        $duracionEsperada = $this->calcularDuracionEsperada();
        $duracionReal = $this->calcularDuracionReal();

        if ($duracionEsperada == 0 || $duracionReal == 0) {
            return null;
        }

        return ($duracionEsperada / $duracionReal) * 100;
    }

    public function actualizarScore($nuevoScore, $motivo = null, $usuarioId = null)
    {
        $scoreAnterior = $this->score_compatibilidad;

        $this->update([
            'score_compatibilidad' => $nuevoScore,
            'modificado_por' => $usuarioId
        ]);

        $this->registrarCambio('actualizacion_score', $motivo ?? 'Score de compatibilidad actualizado', $usuarioId, [
            'score_anterior' => $scoreAnterior,
            'score_nuevo' => $nuevoScore
        ]);

        return $this;
    }

    public function esConflictiva()
    {
        // Una asignación es conflictiva si tiene score bajo o muchos cambios
        return $this->score_compatibilidad < 50 ||
               count($this->historial_cambios ?? []) > 3;
    }

    public function puedeSerModificada()
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_ASIGNADO
        ]);
    }

    public function registrarCambio($tipo, $descripcion, $usuarioId = null, $datosAdicionales = [])
    {
        $historial = $this->historial_cambios ?? [];

        $historial[] = [
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'usuario_id' => $usuarioId,
            'timestamp' => now()->toISOString(),
            'datos_adicionales' => $datosAdicionales
        ];

        $this->update(['historial_cambios' => $historial]);
    }

    public function obtenerUltimoCambio()
    {
        $historial = $this->historial_cambios ?? [];
        return end($historial) ?: null;
    }

    public function obtenerResumenEstado()
    {
        $duracionEsperada = $this->calcularDuracionEsperada();
        $puntualidad = $this->esPuntual();
        $eficiencia = $this->obtenerEficiencia();

        return [
            'estado' => $this->estado,
            'conductor' => $this->conductor->nombre ?? 'N/A',
            'fecha_salida' => $this->fecha_salida->format('d/m/Y'),
            'hora_salida' => $this->hora_salida,
            'duracion_esperada' => $duracionEsperada . ' horas',
            'score_compatibilidad' => $this->score_compatibilidad,
            'es_puntual' => $puntualidad,
            'eficiencia' => $eficiencia ? round($eficiencia, 1) . '%' : 'N/A',
            'tipo_conductor' => $this->es_media_vuelta ? 'Media Vuelta' : ($this->es_fresco ? 'Fresco' : 'Regular'),
            'requiere_atencion' => $this->esConflictiva()
        ];
    }

    // =============================================================================
    // MÉTODOS ESTÁTICOS PARA CONSULTAS COMPLEJAS
    // =============================================================================

    public static function obtenerAsignacionesPorSemana($semana, $ano = null)
    {
        return self::semana($semana, $ano)
                  ->with(['conductor', 'subempresa'])
                  ->orderBy('fecha_salida')
                  ->orderBy('hora_salida')
                  ->get();
    }

    public static function obtenerConflictos($fecha = null)
    {
        $query = self::query()
                    ->where('score_compatibilidad', '<', 50)
                    ->orWhere('estado', self::ESTADO_CANCELADO);

        if ($fecha) {
            $query->whereDate('fecha_salida', $fecha);
        }

        return $query->with(['conductor', 'subempresa'])->get();
    }

    public static function obtenerMetricasDelDia($fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();

        $asignaciones = self::fecha($fecha)->get();

        return [
            'total_asignaciones' => $asignaciones->count(),
            'completadas' => $asignaciones->where('estado', self::ESTADO_COMPLETADO)->count(),
            'en_curso' => $asignaciones->where('estado', self::ESTADO_EN_CURSO)->count(),
            'canceladas' => $asignaciones->where('estado', self::ESTADO_CANCELADO)->count(),
            'score_promedio' => $asignaciones->avg('score_compatibilidad'),
            'medias_vueltas' => $asignaciones->where('es_media_vuelta', true)->count(),
            'frescos' => $asignaciones->where('es_fresco', true)->count(),
            'ingresos_totales' => $asignaciones->sum('ingresos_generados')
        ];
    }
}
