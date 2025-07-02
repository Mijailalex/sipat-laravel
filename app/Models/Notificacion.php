<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'tipo',
        'titulo',
        'mensaje',
        'severidad',
        'datos_extra',
        'leida_en',
        'activa'
    ];

    protected $casts = [
        'datos_extra' => 'array',
        'leida_en' => 'datetime',
        'activa' => 'boolean'
    ];

    public function scopeNoLeidas($query)
    {
        return $query->whereNull('leida_en')->where('activa', true);
    }

    public function scopeRecientes($query)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays(7));
    }

    public function marcarComoLeida()
    {
        $this->update(['leida_en' => now()]);
    }

    public function getSeveridadColorAttribute()
    {
        return match($this->severidad) {
            'INFO' => 'info',
            'ADVERTENCIA' => 'warning',
            'CRITICA' => 'danger'
        };
    }

    public static function crear($tipo, $titulo, $mensaje, $severidad = 'INFO', $datos = null)
    {
        return self::create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'severidad' => $severidad,
            'datos_extra' => $datos
        ]);
    }
}
