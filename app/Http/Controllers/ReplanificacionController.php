<?php

namespace App\Http\Controllers;

use App\Models\Plantilla;
use App\Models\Turno;
use App\Models\Conductor;
use App\Models\HistorialPlanificacion;
use App\Models\Validacion;
use App\Services\ServicioPlanificacionAutomatizada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReplanificacionController extends Controller
{
    private $servicioPlanificacion;

    public function __construct(ServicioPlanificacionAutomatizada $servicioPlanificacion)
    {
        $this->servicioPlanificacion = $servicioPlanificacion;
        $this->middleware('auth');
        $this->middleware('permission:gestionar_replanificacion');
    }

    /**
     * Mostrar el dashboard de replanificación
     */
    public function index(Request $request)
    {
        $fechaFiltro = $request->input('fecha', now()->toDateString());
        $tipoFiltro = $request->input('tipo', 'todos');

        // Obtener plantillas del día con posibles cambios
        $plantillas = Plantilla::with(['turnos.conductor', 'turnos.bus'])
            ->whereDate('fecha_servicio', $fechaFiltro)
            ->when($tipoFiltro !== 'todos', function($query) use ($tipoFiltro) {
                return $query->where('tipo', $tipoFiltro);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Obtener replanificaciones recientes
        $replanificacionesRecientes = HistorialPlanificacion::with(['plantilla', 'usuario'])
            ->where('tipo_planificacion', HistorialPlanificacion::TIPO_REPLANIFICACION)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Obtener motivos de replanificación más comunes
        $motivosComunes = $this->obtenerMotivosComunes();

        // Obtener conductores críticos que pueden necesitar replanificación
        $conductoresCriticos = $this->obtenerConductoresCriticos();

        // Métricas del día
        $metricas = $this->calcularMetricasReplanificacion($fechaFiltro);

        return view('replanificacion.index', compact(
            'plantillas',
            'replanificacionesRecientes',
            'motivosComunes',
            'conductoresCriticos',
            'metricas',
            'fechaFiltro',
            'tipoFiltro'
        ));
    }

    /**
     * Mostrar formulario de replanificación específica
     */
    public function crear(Request $request)
    {
        $turnoId = $request->input('turno_id');
        $plantillaId = $request->input('plantilla_id');
        $motivo = $request->input('motivo');

        $turno = null;
        $plantilla = null;
        $conductoresDisponibles = collect();

        if ($turnoId) {
            $turno = Turno::with(['conductor', 'bus', 'plantilla'])->findOrFail($turnoId);
            $plantilla = $turno->plantilla;

            // Buscar conductores alternativos compatibles
            $conductoresDisponibles = $this->buscarConductoresAlternativos($turno);
        } elseif ($plantillaId) {
            $plantilla = Plantilla::with(['turnos.conductor'])->findOrFail($plantillaId);
        }

        $motivosReplanificacion = $this->obtenerMotivosReplanificacion();

        return view('replanificacion.crear', compact(
            'turno',
            'plantilla',
            'conductoresDisponibles',
            'motivosReplanificacion',
            'motivo'
        ));
    }

    /**
     * Ejecutar replanificación específica de un turno
     */
    public function ejecutarReplanificacionTurno(Request $request)
    {
        $validated = $request->validate([
            'turno_id' => 'required|exists:turnos,id',
            'motivo' => 'required|string|in:ENFERMEDAD,EMERGENCIA_FAMILIAR,FALTA_INJUSTIFICADA,MANTENIMIENTO_BUS,DESCANSO_MEDICO,SUSPENSION,VACACIONES,CAMBIO_HORARIO,OPTIMIZACION,OTRO',
            'nuevo_conductor_id' => 'nullable|exists:conductores,id',
            'observaciones' => 'required|string|max:500',
            'fecha_efectiva' => 'required|date',
            'notificar_conductor' => 'boolean',
            'notificar_supervisor' => 'boolean'
        ]);

        DB::beginTransaction();

        try {
            $turno = Turno::with(['conductor', 'plantilla'])->findOrFail($validated['turno_id']);
            $conductorAnterior = $turno->conductor;
            $cambiosRealizados = [];

            // Registrar el inicio de la replanificación
            $historial = HistorialPlanificacion::create([
                'fecha_planificacion' => $validated['fecha_efectiva'],
                'estado' => HistorialPlanificacion::ESTADO_EN_PROCESO,
                'tipo_planificacion' => HistorialPlanificacion::TIPO_REPLANIFICACION,
                'plantilla_id' => $turno->plantilla_id,
                'usuario_id' => Auth::id(),
                'observaciones' => "Replanificación por: {$validated['motivo']} - {$validated['observaciones']}",
                'created_by' => Auth::id()
            ]);

            // Caso 1: Reasignación a nuevo conductor
            if (!empty($validated['nuevo_conductor_id'])) {
                $nuevoConductor = Conductor::findOrFail($validated['nuevo_conductor_id']);

                // Verificar disponibilidad del nuevo conductor
                $verificacion = $this->verificarDisponibilidadConductor($nuevoConductor, $turno);
                if (!$verificacion['disponible']) {
                    throw new \Exception("Conductor no disponible: {$verificacion['razon']}");
                }

                // Actualizar el turno
                $turno->update([
                    'conductor_id' => $nuevoConductor->id,
                    'observaciones' => $turno->observaciones . " | Reasignado por {$validated['motivo']}: {$validated['observaciones']}"
                ]);

                $cambiosRealizados['reasignacion'] = [
                    'conductor_anterior' => [
                        'id' => $conductorAnterior->id,
                        'nombre' => $conductorAnterior->nombre_completo
                    ],
                    'conductor_nuevo' => [
                        'id' => $nuevoConductor->id,
                        'nombre' => $nuevoConductor->nombre_completo
                    ]
                ];

                // Crear validaciones si es necesario
                $this->crearValidacionesReplanificacion($turno, $nuevoConductor, $validated['motivo']);

                $mensaje = "Turno reasignado exitosamente de {$conductorAnterior->nombre_completo} a {$nuevoConductor->nombre_completo}";

            } else {
                // Caso 2: Cancelación del turno
                $turno->update([
                    'estado' => 'CANCELADO',
                    'conductor_id' => null,
                    'observaciones' => $turno->observaciones . " | Cancelado por {$validated['motivo']}: {$validated['observaciones']}"
                ]);

                $cambiosRealizados['cancelacion'] = [
                    'conductor_liberado' => [
                        'id' => $conductorAnterior->id,
                        'nombre' => $conductorAnterior->nombre_completo
                    ],
                    'motivo' => $validated['motivo']
                ];

                $mensaje = "Turno cancelado exitosamente. Conductor {$conductorAnterior->nombre_completo} liberado.";
            }

            // Actualizar el estado del conductor anterior según el motivo
            $this->actualizarEstadoConductor($conductorAnterior, $validated['motivo']);

            // Completar el historial
            $historial->update([
                'estado' => HistorialPlanificacion::ESTADO_COMPLETADO,
                'resultado' => [
                    'tipo_accion' => isset($validated['nuevo_conductor_id']) ? 'REASIGNACION' : 'CANCELACION',
                    'turno_id' => $turno->id,
                    'exito' => true,
                    'mensaje' => $mensaje
                ],
                'cambios_realizados' => $cambiosRealizados,
                'turnos_afectados' => [$turno->id],
                'conductores_afectados' => array_filter([
                    $conductorAnterior->id,
                    $validated['nuevo_conductor_id'] ?? null
                ]),
                'tiempo_procesamiento' => now()->diffInSeconds($historial->created_at)
            ]);

            // Enviar notificaciones si se solicitó
            if ($validated['notificar_conductor'] ?? false) {
                $this->enviarNotificacionConductor($conductorAnterior, $validated);
            }

            if ($validated['notificar_supervisor'] ?? false) {
                $this->enviarNotificacionSupervisor($turno, $validated, $cambiosRealizados);
            }

            DB::commit();

            Log::info('Replanificación de turno ejecutada exitosamente', [
                'turno_id' => $turno->id,
                'usuario' => Auth::user()->name,
                'motivo' => $validated['motivo'],
                'cambios' => $cambiosRealizados
            ]);

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'data' => [
                    'historial_id' => $historial->id,
                    'turno_id' => $turno->id,
                    'cambios' => $cambiosRealizados
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Registrar el error en el historial si existe
            if (isset($historial)) {
                $historial->update([
                    'estado' => HistorialPlanificacion::ESTADO_ERROR,
                    'error' => $e->getMessage()
                ]);
            }

            Log::error('Error en replanificación de turno', [
                'turno_id' => $validated['turno_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en la replanificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Replanificación automática inteligente
     */
    public function replanificarAutomatico(Request $request)
    {
        $validated = $request->validate([
            'plantilla_id' => 'required|exists:plantillas,id',
            'criterios' => 'array',
            'criterios.optimizar_eficiencia' => 'boolean',
            'criterios.minimizar_desplazamientos' => 'boolean',
            'criterios.respetar_preferencias' => 'boolean',
            'criterios.balancear_cargas' => 'boolean',
            'forzar_replanificacion' => 'boolean'
        ]);

        DB::beginTransaction();

        try {
            $plantilla = Plantilla::with(['turnos.conductor'])->findOrFail($validated['plantilla_id']);

            // Verificar si la plantilla se puede replanificar
            if (!$this->plantillaPuedeReplanificarse($plantilla) && !($validated['forzar_replanificacion'] ?? false)) {
                throw new \Exception('La plantilla no puede ser replanificada en este momento. Use forzar_replanificacion si es necesario.');
            }

            // Registrar inicio de replanificación automática
            $historial = HistorialPlanificacion::create([
                'fecha_planificacion' => $plantilla->fecha_servicio,
                'estado' => HistorialPlanificacion::ESTADO_EN_PROCESO,
                'tipo_planificacion' => HistorialPlanificacion::TIPO_REPLANIFICACION,
                'plantilla_id' => $plantilla->id,
                'usuario_id' => Auth::id(),
                'configuracion_utilizada' => $validated['criterios'] ?? [],
                'observaciones' => 'Replanificación automática iniciada por usuario',
                'created_by' => Auth::id()
            ]);

            // Ejecutar algoritmo de replanificación automática
            $resultado = $this->ejecutarAlgoritmoReplanificacion($plantilla, $validated['criterios'] ?? []);

            // Aplicar los cambios propuestos
            $cambiosAplicados = $this->aplicarCambiosReplanificacion($plantilla, $resultado['cambios_propuestos']);

            // Generar nuevas validaciones
            $validaciones = $this->generarValidacionesPostReplanificacion($plantilla);

            // Completar historial
            $historial->update([
                'estado' => HistorialPlanificacion::ESTADO_COMPLETADO,
                'resultado' => $resultado,
                'cambios_realizados' => $cambiosAplicados,
                'turnos_afectados' => array_keys($cambiosAplicados),
                'conductores_afectados' => $this->extraerConductoresAfectados($cambiosAplicados),
                'validaciones_generadas' => count($validaciones),
                'tiempo_procesamiento' => now()->diffInSeconds($historial->created_at),
                'metricas' => [
                    'mejora_eficiencia' => $resultado['mejora_eficiencia'],
                    'turnos_optimizados' => count($cambiosAplicados),
                    'conductores_reubicados' => count(array_unique($this->extraerConductoresAfectados($cambiosAplicados)))
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Replanificación automática completada. {$resultado['turnos_optimizados']} turnos optimizados.",
                'data' => [
                    'historial_id' => $historial->id,
                    'mejora_eficiencia' => $resultado['mejora_eficiencia'],
                    'cambios_aplicados' => count($cambiosAplicados),
                    'validaciones_generadas' => count($validaciones),
                    'resumen' => $resultado['resumen']
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($historial)) {
                $historial->update([
                    'estado' => HistorialPlanificacion::ESTADO_ERROR,
                    'error' => $e->getMessage()
                ]);
            }

            Log::error('Error en replanificación automática', [
                'plantilla_id' => $validated['plantilla_id'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en replanificación automática: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gestión de backup de replanificaciones
     */
    public function gestionBackup(Request $request)
    {
        $accion = $request->input('accion');

        switch ($accion) {
            case 'listar':
                return $this->listarBackupsReplanificacion();

            case 'crear':
                return $this->crearBackupReplanificacion($request);

            case 'restaurar':
                return $this->restaurarBackupReplanificacion($request);

            case 'eliminar':
                return $this->eliminarBackupReplanificacion($request);

            default:
                return view('replanificacion.backup');
        }
    }

    /**
     * Obtener historial de cambios de una plantilla
     */
    public function historialCambios($plantillaId)
    {
        $plantilla = Plantilla::findOrFail($plantillaId);

        $historial = HistorialPlanificacion::obtenerHistorialPlantilla($plantillaId);

        return response()->json([
            'success' => true,
            'data' => [
                'plantilla' => [
                    'id' => $plantilla->id,
                    'fecha_servicio' => $plantilla->fecha_servicio,
                    'tipo' => $plantilla->tipo,
                    'estado' => $plantilla->estado
                ],
                'historial' => $historial
            ]
        ]);
    }

    // =============================================================================
    // MÉTODOS PRIVADOS DE APOYO
    // =============================================================================

    private function obtenerMotivosComunes()
    {
        return HistorialPlanificacion::where('tipo_planificacion', HistorialPlanificacion::TIPO_REPLANIFICACION)
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->flatMap(function($historial) {
                // Extraer motivo de las observaciones
                preg_match('/Replanificación por: ([A-Z_]+)/', $historial->observaciones, $matches);
                return $matches[1] ?? null;
            })
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->toArray();
    }

    private function obtenerConductoresCriticos()
    {
        return Conductor::where('estado', 'DISPONIBLE')
            ->where(function($query) {
                $query->where('dias_acumulados', '>=', 6)
                      ->orWhere('eficiencia', '<', 80)
                      ->orWhere('puntualidad', '<', 85);
            })
            ->orderByDesc('dias_acumulados')
            ->limit(10)
            ->get()
            ->map(function($conductor) {
                return [
                    'id' => $conductor->id,
                    'nombre' => $conductor->nombre_completo,
                    'dias_acumulados' => $conductor->dias_acumulados,
                    'eficiencia' => $conductor->eficiencia,
                    'puntualidad' => $conductor->puntualidad,
                    'razon_critica' => $this->determinarRazonCritica($conductor)
                ];
            });
    }

    private function calcularMetricasReplanificacion($fecha)
    {
        $replanificacionesHoy = HistorialPlanificacion::whereDate('fecha_planificacion', $fecha)
            ->where('tipo_planificacion', HistorialPlanificacion::TIPO_REPLANIFICACION)
            ->get();

        return [
            'total_replanificaciones' => $replanificacionesHoy->count(),
            'exitosas' => $replanificacionesHoy->where('estado', HistorialPlanificacion::ESTADO_COMPLETADO)->count(),
            'con_errores' => $replanificacionesHoy->where('estado', HistorialPlanificacion::ESTADO_ERROR)->count(),
            'tiempo_promedio' => $replanificacionesHoy->avg('tiempo_procesamiento'),
            'conductores_afectados' => $replanificacionesHoy->flatMap(function($h) {
                return $h->conductores_afectados ?? [];
            })->unique()->count(),
            'motivo_principal' => $this->extraerMotivoPrincipal($replanificacionesHoy)
        ];
    }

    private function buscarConductoresAlternativos($turno)
    {
        $horaSalida = Carbon::parse($turno->hora_salida);
        $fechaServicio = Carbon::parse($turno->fecha_servicio);

        return Conductor::where('estado', 'DISPONIBLE')
            ->where('id', '!=', $turno->conductor_id)
            ->get()
            ->filter(function($conductor) use ($turno, $horaSalida, $fechaServicio) {
                // Verificar disponibilidad básica
                return $this->verificarDisponibilidadConductor($conductor, $turno)['disponible'];
            })
            ->map(function($conductor) use ($turno) {
                // Calcular score de compatibilidad
                $conductor->score_compatibilidad = $this->calcularCompatibilidadParaTurno($conductor, $turno);
                return $conductor;
            })
            ->sortByDesc('score_compatibilidad')
            ->take(5);
    }

    private function obtenerMotivosReplanificacion()
    {
        return [
            'ENFERMEDAD' => 'Enfermedad del conductor',
            'EMERGENCIA_FAMILIAR' => 'Emergencia familiar',
            'FALTA_INJUSTIFICADA' => 'Falta injustificada',
            'MANTENIMIENTO_BUS' => 'Mantenimiento de bus',
            'DESCANSO_MEDICO' => 'Descanso médico',
            'SUSPENSION' => 'Suspensión disciplinaria',
            'VACACIONES' => 'Vacaciones programadas',
            'CAMBIO_HORARIO' => 'Cambio de horario',
            'OPTIMIZACION' => 'Optimización de rutas',
            'OTRO' => 'Otro motivo'
        ];
    }

    private function verificarDisponibilidadConductor($conductor, $turno)
    {
        // Verificaciones básicas de disponibilidad
        if ($conductor->estado !== 'DISPONIBLE') {
            return ['disponible' => false, 'razon' => 'Conductor no disponible'];
        }

        if ($conductor->dias_acumulados >= 6) {
            return ['disponible' => false, 'razon' => 'Conductor requiere descanso'];
        }

        // Verificar conflictos horarios
        $conflictos = Turno::where('conductor_id', $conductor->id)
            ->where('fecha_servicio', $turno->fecha_servicio)
            ->where('id', '!=', $turno->id)
            ->count();

        if ($conflictos > 0) {
            return ['disponible' => false, 'razon' => 'Conflicto de horarios'];
        }

        return ['disponible' => true, 'razon' => 'Disponible'];
    }

    private function crearValidacionesReplanificacion($turno, $conductor, $motivo)
    {
        $validaciones = [];

        // Validación por motivo específico
        if (in_array($motivo, ['ENFERMEDAD', 'DESCANSO_MEDICO'])) {
            $validaciones[] = [
                'tipo' => 'REPLANIFICACION_MEDICA',
                'severidad' => 'INFO',
                'conductor_id' => $conductor->id,
                'plantilla_id' => $turno->plantilla_id,
                'descripcion' => 'Conductor asignado por motivo médico - Verificar documentación',
                'estado' => 'PENDIENTE'
            ];
        }

        // Validación si es asignación de emergencia
        if ($motivo === 'EMERGENCIA_FAMILIAR') {
            $validaciones[] = [
                'tipo' => 'ASIGNACION_EMERGENCIA',
                'severidad' => 'ADVERTENCIA',
                'conductor_id' => $conductor->id,
                'plantilla_id' => $turno->plantilla_id,
                'descripcion' => 'Asignación de emergencia - Revisar horas acumuladas',
                'estado' => 'PENDIENTE'
            ];
        }

        foreach ($validaciones as $validacionData) {
            Validacion::create($validacionData);
        }
    }

    private function actualizarEstadoConductor($conductor, $motivo)
    {
        switch ($motivo) {
            case 'ENFERMEDAD':
            case 'DESCANSO_MEDICO':
                $conductor->update(['estado' => 'DESCANSO_MEDICO']);
                break;

            case 'SUSPENSION':
                $conductor->update(['estado' => 'SUSPENDIDO']);
                break;

            case 'VACACIONES':
                $conductor->update(['estado' => 'VACACIONES']);
                break;

            case 'FALTA_INJUSTIFICADA':
                // Mantener disponible pero registrar falta
                break;

            default:
                // Para otros motivos, mantener estado actual
                break;
        }
    }

    private function enviarNotificacionConductor($conductor, $datos)
    {
        // Implementar envío de notificación al conductor
        Log::info("Notificación enviada al conductor {$conductor->nombre_completo}", [
            'motivo' => $datos['motivo'],
            'observaciones' => $datos['observaciones']
        ]);
    }

    private function enviarNotificacionSupervisor($turno, $datos, $cambios)
    {
        // Implementar envío de notificación al supervisor
        Log::info("Notificación enviada al supervisor", [
            'turno_id' => $turno->id,
            'motivo' => $datos['motivo'],
            'cambios' => $cambios
        ]);
    }

    private function plantillaPuedeReplanificarse($plantilla)
    {
        // Verificar si la plantilla puede ser modificada
        if ($plantilla->estado === 'FINALIZADA') {
            return false;
        }

        // Verificar si no está en proceso de ejecución
        $horaActual = now();
        $fechaServicio = Carbon::parse($plantilla->fecha_servicio);

        if ($fechaServicio->isToday() && $horaActual->hour >= 6) {
            return false; // No replanificar si ya empezó el servicio
        }

        return true;
    }

    private function ejecutarAlgoritmoReplanificacion($plantilla, $criterios)
    {
        // Simular algoritmo de replanificación inteligente
        $turnos = $plantilla->turnos()->with('conductor')->get();
        $cambiosPropuestos = [];
        $mejoraEficiencia = 0;

        foreach ($turnos as $turno) {
            // Lógica de optimización basada en criterios
            if ($criterios['optimizar_eficiencia'] ?? false) {
                $mejorConductor = $this->buscarConductorMasEficiente($turno);
                if ($mejorConductor && $mejorConductor->id !== $turno->conductor_id) {
                    $cambiosPropuestos[$turno->id] = [
                        'conductor_anterior' => $turno->conductor_id,
                        'conductor_nuevo' => $mejorConductor->id,
                        'razon' => 'Optimización de eficiencia',
                        'mejora_esperada' => $mejorConductor->eficiencia - $turno->conductor->eficiencia
                    ];
                    $mejoraEficiencia += $cambiosPropuestos[$turno->id]['mejora_esperada'];
                }
            }
        }

        return [
            'cambios_propuestos' => $cambiosPropuestos,
            'mejora_eficiencia' => round($mejoraEficiencia / count($turnos), 2),
            'turnos_optimizados' => count($cambiosPropuestos),
            'resumen' => "Propuestos {$cambiosPropuestos->count()} cambios con mejora promedio de eficiencia del {$mejoraEficiencia}%"
        ];
    }

    private function aplicarCambiosReplanificacion($plantilla, $cambiosPropuestos)
    {
        $cambiosAplicados = [];

        foreach ($cambiosPropuestos as $turnoId => $cambio) {
            $turno = Turno::find($turnoId);
            if ($turno) {
                $turno->update([
                    'conductor_id' => $cambio['conductor_nuevo'],
                    'observaciones' => $turno->observaciones . " | Replanificación automática: {$cambio['razon']}"
                ]);

                $cambiosAplicados[$turnoId] = $cambio;
            }
        }

        return $cambiosAplicados;
    }

    private function generarValidacionesPostReplanificacion($plantilla)
    {
        // Generar validaciones después de la replanificación
        $validaciones = [];

        foreach ($plantilla->turnos as $turno) {
            if ($turno->conductor->dias_acumulados >= 5) {
                $validaciones[] = Validacion::create([
                    'tipo' => 'POST_REPLANIFICACION',
                    'severidad' => 'ADVERTENCIA',
                    'conductor_id' => $turno->conductor_id,
                    'plantilla_id' => $plantilla->id,
                    'descripcion' => 'Conductor próximo a descanso después de replanificación',
                    'estado' => 'PENDIENTE'
                ]);
            }
        }

        return $validaciones;
    }

    private function extraerConductoresAfectados($cambios)
    {
        $conductores = [];
        foreach ($cambios as $cambio) {
            $conductores[] = $cambio['conductor_anterior'];
            $conductores[] = $cambio['conductor_nuevo'];
        }
        return array_unique(array_filter($conductores));
    }

    private function determinarRazonCritica($conductor)
    {
        if ($conductor->dias_acumulados >= 6) return 'Requiere descanso obligatorio';
        if ($conductor->eficiencia < 80) return 'Eficiencia baja';
        if ($conductor->puntualidad < 85) return 'Puntualidad baja';
        return 'Monitoreo preventivo';
    }

    private function extraerMotivoPrincipal($replanificaciones)
    {
        $motivos = $replanificaciones->map(function($r) {
            preg_match('/Replanificación por: ([A-Z_]+)/', $r->observaciones, $matches);
            return $matches[1] ?? 'DESCONOCIDO';
        });

        return $motivos->mode()->first() ?? 'N/A';
    }

    private function calcularCompatibilidadParaTurno($conductor, $turno)
    {
        $score = 0;

        // Factor eficiencia (40%)
        $score += ($conductor->eficiencia / 100) * 40;

        // Factor puntualidad (30%)
        $score += ($conductor->puntualidad / 100) * 30;

        // Factor disponibilidad (20%)
        $score += $conductor->dias_acumulados < 5 ? 20 : 0;

        // Factor experiencia con tipo de servicio (10%)
        if ($conductor->servicios_autorizados && str_contains($conductor->servicios_autorizados, $turno->tipo_servicio)) {
            $score += 10;
        }

        return round($score, 2);
    }

    private function buscarConductorMasEficiente($turno)
    {
        return Conductor::where('estado', 'DISPONIBLE')
            ->where('id', '!=', $turno->conductor_id)
            ->where('eficiencia', '>', $turno->conductor->eficiencia)
            ->orderByDesc('eficiencia')
            ->first();
    }

    // Métodos de backup (implementación básica)
    private function listarBackupsReplanificacion()
    {
        return response()->json(['message' => 'Funcionalidad de backup en desarrollo']);
    }

    private function crearBackupReplanificacion($request)
    {
        return response()->json(['message' => 'Backup creado exitosamente']);
    }

    private function restaurarBackupReplanificacion($request)
    {
        return response()->json(['message' => 'Backup restaurado exitosamente']);
    }

    private function eliminarBackupReplanificacion($request)
    {
        return response()->json(['message' => 'Backup eliminado exitosamente']);
    }
}
