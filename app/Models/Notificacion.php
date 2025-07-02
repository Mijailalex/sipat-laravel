<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'conductor_id',
        'tipo',
        'mensaje',
        'datos_adicionales',
        'leida'
    ];

    protected $casts = [
        'datos_adicionales' => 'array',
        'leida' => 'boolean'
    ];

    // Relación con Conductor
    public function conductor()
    {
        return $this->belongsTo(Conductor::class);
    }

    // Scope para notificaciones no leídas
    public function scopeNoLeidas($query)
    {
        return $query->where('leida', false);
    }
}
