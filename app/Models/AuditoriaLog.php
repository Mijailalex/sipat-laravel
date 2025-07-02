<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditoriaLog extends Model
{
    protected $table = 'auditoria_logs';

    protected $fillable = [
        'usuario',
        'accion',
        'modelo',
        'modelo_id',
        'datos_anteriores',
        'datos_nuevos',
        'ip_address',
        'user_agent',
        'descripcion'
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array'
    ];

    public static function registrar($accion, $modelo, $modelo_id = null, $datosAnteriores = null, $datosNuevos = null, $descripcion = null)
    {
        $request = request();

        return self::create([
            'usuario' => 'Sistema', // Aquí podrías usar auth()->user()->name si tienes autenticación
            'accion' => $accion,
            'modelo' => class_basename($modelo),
            'modelo_id' => $modelo_id,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->userAgent() : null,
            'descripcion' => $descripcion
        ]);
    }

    public function getAccionColorAttribute()
    {
        return match($this->accion) {
            'CREATE' => 'success',
            'UPDATE' => 'warning',
            'DELETE' => 'danger',
            'VIEW' => 'info'
        };
    }
}
