<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RutaCorta extends Model
{
    use HasFactory;

    protected $table = 'rutas_cortas';

    protected $fillable = [
        'conductor_id', 'tramo', 'rumbo', 'fecha_asignacion', 'hora_inicio',
        'hora_fin', 'duracion_horas', 'estado', 'semana_numero', 'dia_semana',
        'es_consecutiva', 'ingreso_estimado', 'observaciones'
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'duracion_horas' => 'decimal:2',
        'es_consecutiva' => 'boolean',
        'ingreso_estimado' => 'decimal:2',
    ];

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    // Scopes
    public function scopeProgramadas($query)
    {
        return $query->where('estado', 'PROGRAMADA');
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', 'COMPLETADA');
    }

    public function scopeSemanaActual($query)
    {
        return $query->where('semana_numero', Carbon::now()->week);
    }

    public function scopePorConductor($query, $conductorId)
    {
        return $query->where('conductor_id', $conductorId);
    }

    public function scopePorFecha($query, $fecha)
    {
        return $query->where('fecha_asignacion', $fecha);
    }

    public function scopeConsecutivas($query)
    {
        return $query->where('es_consecutiva', true);
    }

    // Método para validar si se puede asignar ruta corta
    public static function puedeAsignarRutaCorta($conductorId, $fecha)
    {
        $fecha = Carbon::parse($fecha);

        // Verificar máximo 2 rutas cortas por día
        $rutasDelDia = self::where('conductor_id', $conductorId)
            ->where('fecha_asignacion', $fecha)
            ->where('estado', '!=', 'CANCELADA')
            ->count();

        if ($rutasDelDia >= 2) {
            return [
                'puede' => false,
                'razon' => 'El conductor ya tiene 2 rutas cortas asignadas para este día.'
            ];
        }

        // Verificar no dos días consecutivos
        $fechaAnterior = $fecha->copy()->subDay();
        $rutaAnterior = self::where('conductor_id', $conductorId)
            ->where('fecha_asignacion', $fechaAnterior)
            ->where('estado', '!=', 'CANCELADA')
            ->exists();

        if ($rutaAnterior) {
            return [
                'puede' => false,
                'razon' => 'El conductor tuvo rutas cortas el día anterior. No puede tener días consecutivos.'
            ];
        }

        // Verificar rutas semanales (objetivo 3-4)
        $rutasSemanales = self::where('conductor_id', $conductorId)
            ->where('semana_numero', $fecha->week)
            ->where('estado', '!=', 'CANCELADA')
            ->count();

        if ($rutasSemanales >= 4) {
            return [
                'puede' => false,
                'razon' => "El conductor ya alcanzó el máximo de 4 rutas cortas esta semana."
            ];
        }

        return [
            'puede' => true,
            'razon' => 'Puede asignar ruta corta.'
        ];
    }

    // Obtener balance semanal
    public static function obtenerBalanceSemanal($conductorId, $semana = null, $año = null)
    {
        $semana = $semana ?? Carbon::now()->week;
        $año = $año ?? Carbon::now()->year;

        $rutas = self::where('conductor_id', $conductorId)
            ->where('semana_numero', $semana)
            ->whereYear('fecha_asignacion', $año)
            ->get();

        $programadas = $rutas->where('estado', 'PROGRAMADA')->count();
        $completadas = $rutas->where('estado', 'COMPLETADA')->count();
        $canceladas = $rutas->where('estado', 'CANCELADA')->count();
        $totalIngresos = $rutas->sum('ingreso_estimado');

        $total = $programadas + $completadas;
        $objetivoCumplido = $total >= 3 && $total <= 4;

        return [
            'conductor_id' => $conductorId,
            'semana' => $semana,
            'año' => $año,
            'programadas' => $programadas,
            'completadas' => $completadas,
            'canceladas' => $canceladas,
            'total' => $total,
            'objetivo_cumplido' => $objetivoCumplido,
            'total_ingresos' => $totalIngresos,
            'porcentaje_cumplimiento' => $total > 0 ? round(($completadas / $total) * 100, 1) : 0
        ];
    }

    // Accessors
    public function getEsRutaCortaAttribute()
    {
        return $this->duracion_horas < 5; // Rutas menores a 5 horas son cortas
    }

    public function getEstadoColorAttribute()
    {
        return match($this->estado) {
            'PROGRAMADA' => 'primary',
            'EN_CURSO' => 'warning',
            'COMPLETADA' => 'success',
            'CANCELADA' => 'danger',
            default => 'secondary'
        };
    }
}
