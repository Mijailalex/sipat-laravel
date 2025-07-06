<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Plantilla extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'tipo',
        'configuracion_turnos',
        'parametros_especiales',
        'activa',
        'creado_por',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta'
    ];

    protected $casts = [
        'configuracion_turnos' => 'array',
        'parametros_especiales' => 'array',
        'activa' => 'boolean',
        'fecha_vigencia_desde' => 'datetime',
        'fecha_vigencia_hasta' => 'datetime'
    ];

    // Relaciones
    public function creadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'creado_por');
    }

    public function turnos()
    {
        return $this->hasMany(\App\Models\Turno::class);
    }

    public function plantillaTurnos()
    {
        return $this->hasMany(\App\Models\PlantillaTurno::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeVigentes($query, $fecha = null)
    {
        $fecha = $fecha ?: now();

        return $query->where(function ($q) use ($fecha) {
            $q->whereNull('fecha_vigencia_desde')
              ->orWhere('fecha_vigencia_desde', '<=', $fecha);
        })->where(function ($q) use ($fecha) {
            $q->whereNull('fecha_vigencia_hasta')
              ->orWhere('fecha_vigencia_hasta', '>=', $fecha);
        });
    }

    public function scopeBuscarTexto($query, $texto)
    {
        return $query->where(function ($q) use ($texto) {
            $q->where('nombre', 'like', "%{$texto}%")
              ->orWhere('codigo', 'like', "%{$texto}%")
              ->orWhere('descripcion', 'like', "%{$texto}%");
        });
    }

    // Métodos de negocio
    public function estaVigente($fecha = null)
    {
        $fecha = $fecha ?: now();

        if ($this->fecha_vigencia_desde && $fecha < $this->fecha_vigencia_desde) {
            return false;
        }

        if ($this->fecha_vigencia_hasta && $fecha > $this->fecha_vigencia_hasta) {
            return false;
        }

        return $this->activa;
    }

    public function obtenerTurnosConfigurados()
    {
        return $this->configuracion_turnos ?: [];
    }

    public function obtenerParametrosEspeciales()
    {
        return $this->parametros_especiales ?: [];
    }

    public function validarConfiguracion()
    {
        $errores = [];

        if (!$this->configuracion_turnos || !is_array($this->configuracion_turnos)) {
            $errores[] = 'La configuración de turnos es requerida';
            return $errores;
        }

        foreach ($this->configuracion_turnos as $index => $turno) {
            if (!isset($turno['nombre']) || empty($turno['nombre'])) {
                $errores[] = "Turno #{$index}: Nombre requerido";
            }

            if (!isset($turno['hora_inicio'])) {
                $errores[] = "Turno #{$index}: Hora de inicio requerida";
            }

            if (!isset($turno['hora_fin'])) {
                $errores[] = "Turno #{$index}: Hora de fin requerida";
            }

            if (isset($turno['hora_inicio']) && isset($turno['hora_fin'])) {
                $inicio = Carbon::parse($turno['hora_inicio']);
                $fin = Carbon::parse($turno['hora_fin']);

                if ($fin <= $inicio) {
                    $errores[] = "Turno #{$index}: La hora de fin debe ser posterior a la hora de inicio";
                }
            }

            if (!isset($turno['conductores_requeridos']) || $turno['conductores_requeridos'] < 1) {
                $errores[] = "Turno #{$index}: Debe requerir al menos 1 conductor";
            }
        }

        if (!empty($errores)) {
            throw new \InvalidArgumentException(implode(', ', $errores));
        }

        return true;
    }

    public function calcularCostoEstimado()
    {
        $costoTotal = 0;
        $salarioBasePorHora = 15; // S/. por hora base

        foreach ($this->obtenerTurnosConfigurados() as $turno) {
            $horaInicio = Carbon::parse($turno['hora_inicio']);
            $horaFin = Carbon::parse($turno['hora_fin']);
            $horas = $horaInicio->diffInHours($horaFin);

            $conductores = $turno['conductores_requeridos'] ?? 1;
            $factor = $turno['factor_pago'] ?? 1.0;

            $costoTurno = $horas * $salarioBasePorHora * $conductores * $factor;
            $costoTotal += $costoTurno;
        }

        return $costoTotal;
    }

    public function filtrarConductoresPorParametros($conductores, $parametros = null)
    {
        if (!$parametros) {
            $parametros = $this->obtenerParametrosEspeciales();
        }

        $conductoresFiltrados = $conductores;

        foreach ($parametros as $parametro => $valor) {
            switch ($parametro) {
                case 'experiencia_minima':
                    $conductoresFiltrados = $conductoresFiltrados->where('experiencia_años', '>=', $valor);
                    break;
                case 'eficiencia_minima':
                    $conductoresFiltrados = $conductoresFiltrados->where('eficiencia', '>=', $valor);
                    break;
                case 'puntualidad_minima':
                    $conductoresFiltrados = $conductoresFiltrados->where('puntualidad', '>=', $valor);
                    break;
                case 'turno_preferido':
                    $conductoresFiltrados = $conductoresFiltrados->where('turno_preferido', $valor);
                    break;
                case 'licencia_requerida':
                    $conductoresFiltrados = $conductoresFiltrados->where('tipo_licencia', $valor);
                    break;
                case 'zona_residencia':
                    $conductoresFiltrados = $conductoresFiltrados->where('zona_residencia', $valor);
                    break;
                default:
                    // Parámetro no reconocido, ignorar
                    break;
            }
        }

        return $conductoresFiltrados;
    }

    public function obtenerEstadisticasUso($dias = 30)
    {
        try {
            $turnos = $this->turnos()
                ->where('fecha_turno', '>=', now()->subDays($dias))
                ->get();

            $turnosCompletados = $turnos->where('estado', 'COMPLETADO');

            return [
                'total_turnos_generados' => $turnos->count(),
                'turnos_completados' => $turnosCompletados->count(),
                'porcentaje_eficiencia' => $turnos->count() > 0
                    ? round(($turnosCompletados->count() / $turnos->count()) * 100, 2)
                    : 0,
                'conductores_diferentes' => $turnos->pluck('conductor_id')->unique()->count(),
                'dias_utilizados' => $turnos->pluck('fecha_turno')->unique()->count(),
                'promedio_turnos_dia' => $turnos->pluck('fecha_turno')->unique()->count() > 0
                    ? round($turnos->count() / $turnos->pluck('fecha_turno')->unique()->count(), 2)
                    : 0,
                'horas_totales_programadas' => $turnos->sum('horas_trabajadas'),
                'eficiencia_promedio_conductores' => $turnosCompletados->avg('eficiencia_turno') ?: 0
            ];
        } catch (\Exception $e) {
            return [
                'total_turnos_generados' => 0,
                'turnos_completados' => 0,
                'porcentaje_eficiencia' => 0,
                'conductores_diferentes' => 0,
                'dias_utilizados' => 0,
                'promedio_turnos_dia' => 0,
                'horas_totales_programadas' => 0,
                'eficiencia_promedio_conductores' => 0
            ];
        }
    }

    public static function obtenerMasUtilizadas($limite = 5, $dias = 30)
    {
        try {
            return static::withCount([
                    'turnos as total_turnos' => function ($query) use ($dias) {
                        $query->where('fecha_turno', '>=', now()->subDays($dias));
                    }
                ])
                ->activas()
                ->having('total_turnos', '>', 0)
                ->orderBy('total_turnos', 'desc')
                ->limit($limite)
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    public function clonar($nuevoNombre, $nuevoCodigo = null)
    {
        $nuevaPlantilla = $this->replicate();
        $nuevaPlantilla->nombre = $nuevoNombre;
        $nuevaPlantilla->codigo = $nuevoCodigo;
        $nuevaPlantilla->activa = false;
        $nuevaPlantilla->fecha_vigencia_desde = null;
        $nuevaPlantilla->fecha_vigencia_hasta = null;

        $nuevaPlantilla->save();

        // Clonar plantilla turnos si existen
        foreach ($this->plantillaTurnos as $plantillaTurno) {
            $nuevoPlantillaTurno = $plantillaTurno->replicate();
            $nuevoPlantillaTurno->plantilla_id = $nuevaPlantilla->id;
            $nuevoPlantillaTurno->save();
        }

        return $nuevaPlantilla;
    }

    public function generarTurnos($fechaInicio, $fechaFin, $conductores = null)
    {
        $turnosGenerados = [];
        $fechaActual = Carbon::parse($fechaInicio);
        $fechaLimite = Carbon::parse($fechaFin);

        if (!$conductores) {
            $conductores = \App\Models\Conductor::disponibles()->get();
        }

        while ($fechaActual <= $fechaLimite) {
            foreach ($this->obtenerTurnosConfigurados() as $turnoConfig) {
                $diasSemana = $turnoConfig['dias_semana'] ?? [1, 2, 3, 4, 5, 6, 7];

                if (in_array($fechaActual->dayOfWeek, $diasSemana)) {
                    $conductoresFiltrados = $this->filtrarConductoresPorParametros($conductores, $turnoConfig['parametros'] ?? []);

                    for ($i = 0; $i < ($turnoConfig['conductores_requeridos'] ?? 1); $i++) {
                        $conductorAsignado = $conductoresFiltrados->where('disponible_fecha', $fechaActual->toDateString())->first();

                        $turno = new \App\Models\Turno([
                            'plantilla_id' => $this->id,
                            'conductor_id' => $conductorAsignado ? $conductorAsignado->id : null,
                            'fecha_turno' => $fechaActual->toDateString(),
                            'hora_inicio' => $turnoConfig['hora_inicio'],
                            'hora_fin' => $turnoConfig['hora_fin'],
                            'tipo_turno' => $turnoConfig['tipo'] ?? 'REGULAR',
                            'ruta_asignada' => $turnoConfig['ruta'] ?? null,
                            'origen_conductor' => $turnoConfig['origen_conductor'] ?? null,
                            'estado' => 'PROGRAMADO'
                        ]);

                        $turno->save();
                        $turnosGenerados[] = $turno;
                    }
                }
            }

            $fechaActual->addDay();
        }

        return $turnosGenerados;
    }

    public function verificarSolapamientos()
    {
        $errores = [];
        $turnos = $this->obtenerTurnosConfigurados();

        for ($i = 0; $i < count($turnos); $i++) {
            for ($j = $i + 1; $j < count($turnos); $j++) {
                $turno1 = $turnos[$i];
                $turno2 = $turnos[$j];

                // Verificar si tienen días en común
                $diasComunes = array_intersect(
                    $turno1['dias_semana'] ?? [],
                    $turno2['dias_semana'] ?? []
                );

                if (!empty($diasComunes)) {
                    $inicio1 = Carbon::parse($turno1['hora_inicio']);
                    $fin1 = Carbon::parse($turno1['hora_fin']);
                    $inicio2 = Carbon::parse($turno2['hora_inicio']);
                    $fin2 = Carbon::parse($turno2['hora_fin']);

                    // Verificar solapamiento de horarios
                    if (($inicio1 < $fin2) && ($inicio2 < $fin1)) {
                        $errores[] = "Los turnos " . ($i + 1) . " y " . ($j + 1) . " tienen horarios solapados";
                    }
                }
            }
        }

        return $errores;
    }

    public function activar($fechaVigencia = null)
    {
        // Validar antes de activar
        $this->validarConfiguracion();

        // Verificar solapamientos
        $solapamientos = $this->verificarSolapamientos();
        if (!empty($solapamientos)) {
            throw new \InvalidArgumentException('No se puede activar: ' . implode(', ', $solapamientos));
        }

        $this->update([
            'activa' => true,
            'fecha_vigencia_desde' => $fechaVigencia ?: now()
        ]);

        return true;
    }

    public function desactivar($fechaFin = null)
    {
        $this->update([
            'activa' => false,
            'fecha_vigencia_hasta' => $fechaFin ?: now()
        ]);

        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plantilla) {
            // Generar código automático si no se proporciona
            if (!$plantilla->codigo) {
                $ultimo = static::max('id') + 1;
                $plantilla->codigo = 'PLANT' . str_pad($ultimo, 4, '0', STR_PAD_LEFT);
            }

            // Validar configuración antes de crear
            if ($plantilla->configuracion_turnos) {
                $temp = new static($plantilla->getAttributes());
                $temp->validarConfiguracion();
            }
        });

        static::updating(function ($plantilla) {
            // Validar configuración antes de actualizar
            if ($plantilla->isDirty('configuracion_turnos')) {
                $plantilla->validarConfiguracion();
            }
        });
    }
}
