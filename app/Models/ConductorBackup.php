<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ConductorBackup extends Model
{
    use HasFactory;

    protected $table = 'conductores_backup';

    protected $fillable = [
        'conductor_id',
        'accion',
        'datos_anteriores',
        'datos_nuevos',
        'campos_modificados',
        'usuario_id',
        'ip_address',
        'user_agent',
        'razon_cambio'
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'campos_modificados' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Scopes
    public function scopeAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeRecientes($query, $dias = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    // Métodos estáticos
    public static function crearBackup($conductorId, $accion, $datosAnteriores = null, $datosNuevos = null, $razonCambio = null)
    {
        $camposModificados = [];

        if ($datosAnteriores && $datosNuevos) {
            $camposModificados = array_keys(array_diff_assoc($datosNuevos, $datosAnteriores));
        }

        return static::create([
            'conductor_id' => $conductorId,
            'accion' => $accion,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'campos_modificados' => $camposModificados,
            'usuario_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'razon_cambio' => $razonCambio ?? "Acción: {$accion}"
        ]);
    }

    public static function obtenerHistorialConductor($conductorId, $limite = 20)
    {
        return static::where('conductor_id', $conductorId)
            ->with('usuario:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get()
            ->map(function ($backup) {
                return [
                    'id' => $backup->id,
                    'accion' => $backup->accion,
                    'fecha' => $backup->created_at->format('Y-m-d H:i:s'),
                    'hace' => $backup->created_at->diffForHumans(),
                    'usuario' => $backup->usuario?->name ?? 'Sistema',
                    'razon_cambio' => $backup->razon_cambio,
                    'campos_modificados' => $backup->campos_modificados,
                    'detalles_cambios' => $backup->obtenerDetallesCambios(),
                    'puede_restaurar' => $backup->accion === 'ACTUALIZADO' && $backup->datos_anteriores
                ];
            });
    }

    public static function limpiarBackupsAntiguos($diasRetencion = 90)
    {
        $fechaLimite = now()->subDays($diasRetencion);

        return static::where('created_at', '<', $fechaLimite)->delete();
    }

    public static function estadisticasBackups($dias = 30)
    {
        $fechaInicio = now()->subDays($dias);

        return [
            'total_backups' => static::where('created_at', '>=', $fechaInicio)->count(),
            'por_accion' => static::where('created_at', '>=', $fechaInicio)
                ->selectRaw('accion, COUNT(*) as total')
                ->groupBy('accion')
                ->pluck('total', 'accion')
                ->toArray(),
            'conductores_modificados' => static::where('created_at', '>=', $fechaInicio)
                ->distinct('conductor_id')
                ->count(),
            'promedio_diario' => round(
                static::where('created_at', '>=', $fechaInicio)->count() / $dias,
                2
            )
        ];
    }

    // Métodos de instancia
    public function obtenerDetallesCambios()
    {
        if (!$this->datos_anteriores || !$this->datos_nuevos) {
            return [];
        }

        $detalles = [];
        $camposModificados = $this->campos_modificados ?? [];

        foreach ($camposModificados as $campo) {
            $valorAnterior = $this->datos_anteriores[$campo] ?? null;
            $valorNuevo = $this->datos_nuevos[$campo] ?? null;

            $detalles[] = [
                'campo' => $campo,
                'campo_formateado' => ucfirst(str_replace('_', ' ', $campo)),
                'valor_anterior' => $this->formatearValor($valorAnterior),
                'valor_nuevo' => $this->formatearValor($valorNuevo),
                'tipo_cambio' => $this->determinarTipoCambio($valorAnterior, $valorNuevo)
            ];
        }

        return $detalles;
    }

    private function formatearValor($valor)
    {
        if (is_null($valor)) {
            return 'Sin valor';
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        if (is_array($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        if (is_numeric($valor) && (string)(float)$valor === (string)$valor) {
            return number_format((float)$valor, 2);
        }

        return (string)$valor;
    }

    private function determinarTipoCambio($anterior, $nuevo)
    {
        if (is_null($anterior) && !is_null($nuevo)) {
            return 'AGREGADO';
        }

        if (!is_null($anterior) && is_null($nuevo)) {
            return 'ELIMINADO';
        }

        if (is_numeric($anterior) && is_numeric($nuevo)) {
            if ($nuevo > $anterior) {
                return 'INCREMENTO';
            } elseif ($nuevo < $anterior) {
                return 'DECREMENTO';
            }
        }

        return 'MODIFICADO';
    }

    public function restaurarVersion()
    {
        if ($this->accion !== 'ACTUALIZADO' || !$this->datos_anteriores) {
            throw new \Exception('Solo se pueden restaurar versiones de actualizaciones');
        }

        $conductor = $this->conductor;
        if (!$conductor) {
            throw new \Exception('El conductor asociado ya no existe');
        }

        // Crear backup del estado actual antes de restaurar
        static::create([
            'conductor_id' => $conductor->id,
            'accion' => 'RESTAURADO',
            'datos_anteriores' => $conductor->getAttributes(),
            'datos_nuevos' => $this->datos_anteriores,
            'campos_modificados' => array_keys($this->datos_anteriores),
            'usuario_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'razon_cambio' => "Restauración desde backup ID: {$this->id}"
        ]);

        // Restaurar datos
        $conductor->update($this->datos_anteriores);

        return true;
    }

    public function puedeRestaurar()
    {
        return $this->accion === 'ACTUALIZADO'
            && !is_null($this->datos_anteriores)
            && !empty($this->datos_anteriores)
            && $this->conductor()->exists();
    }

    public function obtenerResumenCambio()
    {
        switch ($this->accion) {
            case 'CREADO':
                return 'Conductor creado en el sistema';
            case 'ACTUALIZADO':
                $campos = count($this->campos_modificados ?? []);
                return "Se modificaron {$campos} campos";
            case 'ELIMINADO':
                return 'Conductor eliminado del sistema';
            case 'RESTAURADO':
                return 'Se restauró una versión anterior';
            default:
                return $this->razon_cambio ?? 'Acción desconocida';
        }
    }
}
