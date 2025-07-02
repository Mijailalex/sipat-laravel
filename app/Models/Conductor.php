<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon; // Importar Carbon para manejo de fechas

class Conductor extends Model
{
    // Nombre de la tabla en la base de datos
    protected $table = 'conductores';

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre',
        'codigo',
        'email',
        'telefono',
        'estado',
        'puntualidad',
        'eficiencia',
        'dias_acumulados',
        // Puedes agregar más campos según tu modelo
    ];

    // Definir tipos de casting para campos específicos
    protected $casts = [
        'puntualidad' => 'float',
        'eficiencia' => 'float',
        'dias_acumulados' => 'integer'
    ];

    // RELACIONES

    /**
     * Relación con Notificaciones
     * Un conductor puede tener múltiples notificaciones
     */
    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'conductor_id');
    }

    /**
     * Relación con Rutas Cortas
     * Un conductor puede tener múltiples rutas
     */
    public function rutasCortas()
    {
        return $this->hasMany(RutaCorta::class, 'conductor_id');
    }

    /**
     * Relación con Validaciones
     * Un conductor puede tener múltiples validaciones
     */
    public function validaciones()
    {
        return $this->hasMany(Validacion::class, 'conductor_id');
    }

    // MÉTODOS DE CONSULTA

    /**
     * Obtener notificaciones no leídas
     */
    public function notificacionesPendientes()
    {
        return $this->notificaciones()
                    ->where('leida', false)
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * Contar notificaciones no leídas
     */
    public function contarNotificacionesPendientes()
    {
        return $this->notificaciones()
                    ->where('leida', false)
                    ->count();
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasNotificacionesLeidas()
    {
        return $this->notificaciones()
                    ->where('leida', false)
                    ->update(['leida' => true]);
    }

    // SCOPES (FILTROS PREDEFINIDOS)

    /**
     * Filtrar conductores activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'DISPONIBLE');
    }

    /**
     * Filtrar conductores críticos
     */
    public function scopeCriticos($query)
    {
        return $query->where('dias_acumulados', '>=', 6);
    }

    /**
     * Filtrar conductores con baja eficiencia
     */
    public function scopaBajaEficiencia($query)
    {
        return $query->where('eficiencia', '<', 70);
    }

    // MÉTODOS DE LÓGICA DE NEGOCIO

    /**
     * Verificar si necesita descanso
     */
    public function necesitaDescanso()
    {
        return $this->dias_acumulados >= 5 ||
               $this->puntualidad < 80 ||
               $this->eficiencia < 70;
    }

    /**
     * Actualizar métricas del conductor
     */
    public function actualizarMetricas($puntualidad, $eficiencia)
    {
        $this->puntualidad = $puntualidad;
        $this->eficiencia = $eficiencia;
        $this->save();

        return $this;
    }

    /**
     * Obtener rutas completadas en un período
     */
    public function rutasCompletadasEnPeriodo($dias = 30)
    {
        $fechaInicio = Carbon::now()->subDays($dias);

        return $this->rutasCortas()
                    ->where('estado', 'COMPLETADA')
                    ->where('fecha_asignacion', '>=', $fechaInicio)
                    ->get();
    }

    /**
     * Calcular promedio de eficiencia en rutas
     */
    public function promedioEficienciaRutas($dias = 30)
    {
        $rutas = $this->rutasCompletadasEnPeriodo($dias);

        if ($rutas->isEmpty()) {
            return 0;
        }

        return $rutas->avg('eficiencia');
    }

    /**
     * Generar reporte de rendimiento
     */
    public function generarReporteRendimiento()
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'puntualidad' => $this->puntualidad,
            'eficiencia' => $this->eficiencia,
            'dias_acumulados' => $this->dias_acumulados,
            'notificaciones_pendientes' => $this->contarNotificacionesPendientes(),
            'necesita_descanso' => $this->necesitaDescanso()
        ];
    }
}
