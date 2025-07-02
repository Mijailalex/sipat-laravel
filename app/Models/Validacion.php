<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Validacion extends Model
{
    use HasFactory;

    protected $table = 'validaciones';

    protected $fillable = [
        'conductor_id',
        'tipo',
        'severidad',
        'titulo',
        'descripcion',
        'detalles_adicionales',
        'estado',
        'accion_realizada',
        'resuelto_por',
        'fecha_resolucion',
        'prioridad_calculada'
    ];

    protected $casts = [
        'detalles_adicionales' => 'array',
        'fecha_resolucion' => 'datetime',
        'prioridad_calculada' => 'decimal:2'
    ];

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function resueltoBy()
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    public function scopeCriticas($query)
    {
        return $query->where('severidad', 'CRITICA');
    }

    public function scopePorPrioridad($query)
    {
        return $query->orderBy('prioridad_calculada', 'desc')
                    ->orderBy('created_at', 'asc');
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeResueltas($query)
    {
        return $query->where('estado', 'RESUELTO');
    }

    // Métodos de negocio
    public function getDiasPendienteAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    public function getClaseSeveridadAttribute()
    {
        return match($this->severidad) {
            'CRITICA' => 'danger',
            'ADVERTENCIA' => 'warning',
            'INFO' => 'info',
            default => 'secondary'
        };
    }

    public function getIconoTipoAttribute()
    {
        return match($this->tipo) {
            'DESCANSO_001' => 'fa-bed',
            'EFICIENCIA_002' => 'fa-chart-line',
            'PUNTUALIDAD_003' => 'fa-clock',
            default => 'fa-exclamation-triangle'
        };
    }

    public function resolver($accion, $usuario_id = null)
    {
        $this->estado = 'RESUELTO';
        $this->accion_realizada = $accion;
        $this->resuelto_por = $usuario_id ?? auth()->id();
        $this->fecha_resolucion = now();
        $this->save();

        // Ejecutar acción específica según el tipo
        $this->ejecutarAccionResolucion();

        return true;
    }

    public function omitir($motivo = null)
    {
        $this->estado = 'OMITIDO';
        $this->accion_realizada = $motivo ?? 'Validación omitida';
        $this->resuelto_por = auth()->id();
        $this->fecha_resolucion = now();
        $this->save();

        return true;
    }

    private function ejecutarAccionResolucion()
    {
        switch ($this->tipo) {
            case 'DESCANSO_001':
                if (strpos($this->accion_realizada, 'descanso') !== false) {
                    $this->conductor->enviarADescanso('FISICO', 'Resuelto por validación');
                }
                break;

            case 'EFICIENCIA_002':
                // Marcar para seguimiento especial
                $this->conductor->update([
                    'observaciones' => $this->conductor->observaciones . "\n" .
                        "Seguimiento eficiencia: " . now()->format('Y-m-d') . " - " . $this->accion_realizada
                ]);
                break;

            case 'PUNTUALIDAD_003':
                // Marcar para seguimiento especial
                $this->conductor->update([
                    'observaciones' => $this->conductor->observaciones . "\n" .
                        "Seguimiento puntualidad: " . now()->format('Y-m-d') . " - " . $this->accion_realizada
                ]);
                break;
        }
    }

    public static function ejecutarValidacionesAutomaticas()
    {
        $conductores = Conductor::whereIn('estado', ['DISPONIBLE', 'DESCANSO_FISICO'])->get();
        $validacionesCreadas = 0;

        foreach ($conductores as $conductor) {
            $validacionesCreadas += $conductor->validarDatos();
        }

        return $validacionesCreadas;
    }

    public static function obtenerEstadisticas()
    {
        return [
            'total' => static::count(),
            'pendientes' => static::where('estado', 'PENDIENTE')->count(),
            'criticas' => static::where('severidad', 'CRITICA')->where('estado', 'PENDIENTE')->count(),
            'resueltas_hoy' => static::where('fecha_resolucion', '>=', now()->startOfDay())->count(),
            'promedio_resolucion' => static::whereNotNull('fecha_resolucion')
                ->selectRaw('AVG(DATEDIFF(fecha_resolucion, created_at)) as promedio')
                ->value('promedio') ?? 0
        ];
    }

    public static function obtenerTendencias($dias = 30)
    {
        return static::selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($dias))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($validacion) {
            // Calcular prioridad automáticamente si no se proporciona
            if (!$validacion->prioridad_calculada) {
                $validacion->prioridad_calculada = static::calcularPrioridad($validacion);
            }
        });
    }

    private static function calcularPrioridad($validacion)
    {
        $prioridad = 0;

        // Severidad (40%)
        $prioridad += match($validacion->severidad) {
            'CRITICA' => 40,
            'ADVERTENCIA' => 25,
            'INFO' => 10
        };

        // Tipo de validación (30%)
        $prioridad += match($validacion->tipo) {
            'DESCANSO_001' => 30,
            'EFICIENCIA_002' => 20,
            'PUNTUALIDAD_003' => 15,
            default => 10
        };

        // Factor conductor (30% - basado en días acumulados)
        if ($validacion->conductor) {
            $prioridad += min(30, $validacion->conductor->dias_acumulados * 5);
        } else {
            $prioridad += 15; // Valor por defecto
        }

        return min(100, $prioridad);
    }
}
