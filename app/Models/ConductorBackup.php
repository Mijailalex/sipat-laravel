<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'campos_modificados' => 'array'
    ];

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeConductor($query, $conductorId)
    {
        return $query->where('conductor_id', $conductorId);
    }

    public function scopeUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeRecientes($query, $dias = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    // Métodos de negocio
    public function getAccionClaseAttribute()
    {
        return match($this->accion) {
            'CREADO' => 'success',
            'ACTUALIZADO' => 'warning',
            'ELIMINADO' => 'danger',
            default => 'info'
        };
    }

    public function getAccionIconoAttribute()
    {
        return match($this->accion) {
            'CREADO' => 'fa-plus',
            'ACTUALIZADO' => 'fa-edit',
            'ELIMINADO' => 'fa-trash',
            default => 'fa-info'
        };
    }

    public function getCambiosResumenAttribute()
    {
        if (!$this->campos_modificados) {
            return 'Sin campos modificados';
        }

        $campos = collect($this->campos_modificados)->map(function ($campo) {
            return ucfirst(str_replace('_', ' ', $campo));
        });

        return $campos->join(', ');
    }

    public function getDetallesCambiosAttribute()
    {
        if (!$this->campos_modificados || !$this->datos_anteriores || !$this->datos_nuevos) {
            return [];
        }

        $detalles = [];

        foreach ($this->campos_modificados as $campo) {
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

    public static function crearBackup($conductorId, $accion, $datosAnteriores = null, $datosNuevos = null, $razon = null)
    {
        $camposModificados = [];

        if ($datosAnteriores && $datosNuevos) {
            foreach ($datosNuevos as $campo => $valor) {
                if (!array_key_exists($campo, $datosAnteriores) ||
                    $datosAnteriores[$campo] !== $valor) {
                    $camposModificados[] = $campo;
                }
            }
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
            'razon_cambio' => $razon
        ]);
    }

    public static function obtenerHistorialConductor($conductorId, $limite = 50)
    {
        return static::where('conductor_id', $conductorId)
            ->with('usuario:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }

    public static function obtenerEstadisticasAuditoria($dias = 30)
    {
        $fecha_desde = now()->subDays($dias);

        return [
            'total_cambios' => static::where('created_at', '>=', $fecha_desde)->count(),
            'por_accion' => static::where('created_at', '>=', $fecha_desde)
                ->selectRaw('accion, COUNT(*) as cantidad')
                ->groupBy('accion')
                ->pluck('cantidad', 'accion')
                ->toArray(),
            'por_usuario' => static::where('created_at', '>=', $fecha_desde)
                ->with('usuario:id,name')
                ->get()
                ->groupBy('usuario.name')
                ->map(function ($grupo) {
                    return $grupo->count();
                })
                ->toArray(),
            'conductores_mas_modificados' => static::where('created_at', '>=', $fecha_desde)
                ->selectRaw('conductor_id, COUNT(*) as modificaciones')
                ->with('conductor:id,codigo_conductor,nombre,apellido')
                ->groupBy('conductor_id')
                ->orderBy('modificaciones', 'desc')
                ->limit(10)
                ->get(),
            'campos_mas_modificados' => static::where('created_at', '>=', $fecha_desde)
                ->whereNotNull('campos_modificados')
                ->get()
                ->pluck('campos_modificados')
                ->flatten()
                ->countBy()
                ->sortDesc()
                ->take(10)
                ->toArray(),
            'actividad_por_dia' => static::where('created_at', '>=', $fecha_desde)
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as cambios')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->pluck('cambios', 'fecha')
                ->toArray()
        ];
    }

    public static function limpiarBackupsAntiguos($diasRetencion = 90)
    {
        $fechaLimite = now()->subDays($diasRetencion);

        $eliminados = static::where('created_at', '<', $fechaLimite)->count();

        static::where('created_at', '<', $fechaLimite)->delete();

        return $eliminados;
    }

    public static function exportarHistorialConductor($conductorId, $formato = 'json')
    {
        $historial = static::obtenerHistorialConductor($conductorId, 1000);

        $datos = $historial->map(function ($backup) {
            return [
                'fecha' => $backup->created_at->format('Y-m-d H:i:s'),
                'accion' => $backup->accion,
                'usuario' => $backup->usuario->name ?? 'Sistema',
                'campos_modificados' => $backup->campos_modificados,
                'detalles_cambios' => $backup->detalles_cambios,
                'razon' => $backup->razon_cambio,
                'ip' => $backup->ip_address
            ];
        });

        switch ($formato) {
            case 'csv':
                return static::generarCSV($datos);
            case 'excel':
                return static::generarExcel($datos);
            default:
                return $datos->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    private static function generarCSV($datos)
    {
        $csv = "Fecha,Acción,Usuario,Campos Modificados,Razón,IP\n";

        foreach ($datos as $registro) {
            $csv .= sprintf(
                "%s,%s,%s,\"%s\",%s,%s\n",
                $registro['fecha'],
                $registro['accion'],
                $registro['usuario'],
                implode('; ', $registro['campos_modificados'] ?? []),
                $registro['razon'] ?? '',
                $registro['ip'] ?? ''
            );
        }

        return $csv;
    }

    public static function buscarCambios($criterios = [])
    {
        $query = static::query();

        if (isset($criterios['conductor_id'])) {
            $query->where('conductor_id', $criterios['conductor_id']);
        }

        if (isset($criterios['accion'])) {
            $query->where('accion', $criterios['accion']);
        }

        if (isset($criterios['usuario_id'])) {
            $query->where('usuario_id', $criterios['usuario_id']);
        }

        if (isset($criterios['campo'])) {
            $query->whereJsonContains('campos_modificados', $criterios['campo']);
        }

        if (isset($criterios['fecha_desde'])) {
            $query->where('created_at', '>=', $criterios['fecha_desde']);
        }

        if (isset($criterios['fecha_hasta'])) {
            $query->where('created_at', '<=', $criterios['fecha_hasta']);
        }

        if (isset($criterios['ip_address'])) {
            $query->where('ip_address', $criterios['ip_address']);
        }

        return $query->with(['conductor:id,codigo_conductor,nombre,apellido', 'usuario:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($criterios['per_page'] ?? 50);
    }
}
