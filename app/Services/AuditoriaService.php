<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\AuditoriaLog;
use App\Models\User;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\Turno;
use Carbon\Carbon;
use Exception;

class AuditoriaService
{
    /**
     * Tipos de eventos auditables
     */
    private const TIPOS_EVENTOS = [
        // Eventos de usuarios
        'usuario_login' => 'Login de usuario',
        'usuario_logout' => 'Logout de usuario',
        'usuario_creado' => 'Usuario creado',
        'usuario_actualizado' => 'Usuario actualizado',
        'usuario_eliminado' => 'Usuario eliminado',
        'cambio_password' => 'Cambio de contraseña',
        'intento_acceso_fallido' => 'Intento de acceso fallido',

        // Eventos de conductores
        'conductor_creado' => 'Conductor creado',
        'conductor_actualizado' => 'Conductor actualizado',
        'conductor_eliminado' => 'Conductor eliminado',
        'conductor_cambio_estado' => 'Cambio de estado de conductor',
        'conductor_asignado_turno' => 'Conductor asignado a turno',
        'conductor_metricas_actualizadas' => 'Métricas de conductor actualizadas',

        // Eventos de validaciones
        'validacion_creada' => 'Validación creada',
        'validacion_resuelta' => 'Validación resuelta',
        'validacion_critica_creada' => 'Validación crítica creada',
        'validacion_masiva_ejecutada' => 'Validación masiva ejecutada',

        // Eventos de turnos
        'turno_creado' => 'Turno creado',
        'turno_actualizado' => 'Turno actualizado',
        'turno_completado' => 'Turno completado',
        'turno_cancelado' => 'Turno cancelado',
        'asignacion_automatica' => 'Asignación automática de turno',

        // Eventos del sistema
        'backup_iniciado' => 'Backup iniciado',
        'backup_completado' => 'Backup completado',
        'backup_fallido' => 'Backup fallido',
        'mantenimiento_iniciado' => 'Mantenimiento iniciado',
        'mantenimiento_completado' => 'Mantenimiento completado',
        'configuracion_actualizada' => 'Configuración actualizada',
        'sistema_alerta_generada' => 'Alerta del sistema generada',

        // Eventos de seguridad
        'acceso_no_autorizado' => 'Intento de acceso no autorizado',
        'modificacion_permisos' => 'Modificación de permisos',
        'exportacion_datos' => 'Exportación de datos',
        'importacion_datos' => 'Importación de datos',
        'eliminacion_masiva' => 'Eliminación masiva de datos'
    ];

    /**
     * Niveles de criticidad
     */
    private const NIVELES_CRITICIDAD = [
        'BAJA' => 1,
        'MEDIA' => 2,
        'ALTA' => 3,
        'CRITICA' => 4
    ];

    /**
     * Eventos que requieren retención extendida
     */
    private const EVENTOS_RETENCION_EXTENDIDA = [
        'usuario_eliminado',
        'conductor_eliminado',
        'backup_completado',
        'acceso_no_autorizado',
        'modificacion_permisos',
        'eliminacion_masiva'
    ];

    /**
     * Registrar evento de auditoría
     */
    public function registrarEvento(
        string $tipoEvento,
        array $datos = [],
        ?int $usuarioId = null,
        string $criticidad = 'MEDIA',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ?int {
        try {
            // Validar tipo de evento
            if (!isset(self::TIPOS_EVENTOS[$tipoEvento])) {
                Log::warning("Tipo de evento de auditoría no reconocido: {$tipoEvento}");
                $tipoEvento = 'evento_personalizado';
            }

            // Obtener información del usuario actual si no se proporciona
            if ($usuarioId === null && Auth::check()) {
                $usuarioId = Auth::id();
            }

            // Obtener información de la request actual si no se proporciona
            if (app()->bound('request')) {
                $request = app('request');
                $ipAddress = $ipAddress ?: $request->ip();
                $userAgent = $userAgent ?: $request->userAgent();
            }

            // Preparar datos adicionales del contexto
            $datosContexto = $this->prepararDatosContexto($datos);

            // Determinar fecha de retención
            $fechaRetencion = $this->calcularFechaRetencion($tipoEvento);

            // Crear registro de auditoría
            $auditoriaLog = AuditoriaLog::create([
                'tipo_evento' => $tipoEvento,
                'descripcion' => self::TIPOS_EVENTOS[$tipoEvento] ?? $tipoEvento,
                'usuario_id' => $usuarioId,
                'datos' => json_encode($datosContexto),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'criticidad' => $criticidad,
                'fecha_retencion' => $fechaRetencion,
                'session_id' => session()->getId(),
                'url' => request()->fullUrl() ?? null,
                'metodo_http' => request()->method() ?? null
            ]);

            // Registrar en logs del sistema si es crítico
            if ($criticidad === 'CRITICA') {
                Log::channel('sipat_auditoria')->critical("Evento crítico: {$tipoEvento}", [
                    'auditoria_id' => $auditoriaLog->id,
                    'usuario_id' => $usuarioId,
                    'datos' => $datosContexto
                ]);
            }

            // Generar alertas si es necesario
            $this->procesarAlertas($tipoEvento, $auditoriaLog, $datosContexto);

            return $auditoriaLog->id;

        } catch (Exception $e) {
            Log::error("Error registrando evento de auditoría: " . $e->getMessage(), [
                'tipo_evento' => $tipoEvento,
                'datos' => $datos
            ]);
            return null;
        }
    }

    /**
     * Preparar datos del contexto
     */
    private function prepararDatosContexto(array $datos): array
    {
        $contexto = [
            'timestamp' => now()->toISOString(),
            'ambiente' => app()->environment(),
            'version_sistema' => config('sipat.version'),
            'datos_evento' => $datos
        ];

        // Agregar información del usuario si está autenticado
        if (Auth::check()) {
            $usuario = Auth::user();
            $contexto['usuario_info'] = [
                'id' => $usuario->id,
                'email' => $usuario->email,
                'roles' => $usuario->getRoleNames()->toArray()
            ];
        }

        // Agregar información de memoria y rendimiento
        $contexto['sistema_info'] = [
            'memoria_uso' => memory_get_usage(true),
            'memoria_pico' => memory_get_peak_usage(true),
            'tiempo_ejecucion' => microtime(true) - LARAVEL_START
        ];

        return $contexto;
    }

    /**
     * Calcular fecha de retención según el tipo de evento
     */
    private function calcularFechaRetencion(string $tipoEvento): Carbon
    {
        // Eventos con retención extendida (2 años)
        if (in_array($tipoEvento, self::EVENTOS_RETENCION_EXTENDIDA)) {
            return now()->addYears(2);
        }

        // Eventos de seguridad (1 año)
        if (str_contains($tipoEvento, 'acceso') || str_contains($tipoEvento, 'login') || str_contains($tipoEvento, 'permisos')) {
            return now()->addYear();
        }

        // Eventos de sistema (6 meses)
        if (str_contains($tipoEvento, 'backup') || str_contains($tipoEvento, 'mantenimiento') || str_contains($tipoEvento, 'configuracion')) {
            return now()->addMonths(6);
        }

        // Eventos estándar (90 días)
        return now()->addDays(90);
    }

    /**
     * Procesar alertas basadas en eventos
     */
    private function procesarAlertas(string $tipoEvento, AuditoriaLog $log, array $datos): void
    {
        try {
            // Alertas por múltiples intentos de acceso fallidos
            if ($tipoEvento === 'intento_acceso_fallido') {
                $this->verificarIntentosAccesoSospechosos($datos['ip_address'] ?? null);
            }

            // Alertas por cambios críticos
            if (in_array($tipoEvento, ['usuario_eliminado', 'conductor_eliminado', 'eliminacion_masiva'])) {
                $this->alertaCambioCritico($tipoEvento, $log, $datos);
            }

            // Alertas por actividad de administración fuera de horario
            if ($this->esEventoAdministrativo($tipoEvento) && $this->esFueraHorarioLaboral()) {
                $this->alertaActividadFueraHorario($tipoEvento, $log);
            }

        } catch (Exception $e) {
            Log::error("Error procesando alertas de auditoría: " . $e->getMessage());
        }
    }

    /**
     * Verificar intentos de acceso sospechosos
     */
    private function verificarIntentosAccesoSospechosos(?string $ipAddress): void
    {
        if (!$ipAddress) return;

        $intentosRecientes = AuditoriaLog::where('tipo_evento', 'intento_acceso_fallido')
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count();

        if ($intentosRecientes >= 5) {
            // Generar alerta de seguridad
            app(NotificacionService::class)->enviarNotificacion(
                'SISTEMA_ALERTA',
                'Actividad Sospechosa Detectada',
                "Se han detectado {$intentosRecientes} intentos de acceso fallidos desde la IP {$ipAddress} en los últimos 30 minutos.",
                ['ip_address' => $ipAddress, 'intentos' => $intentosRecientes],
                null,
                'CRITICA'
            );

            // Registrar evento adicional
            $this->registrarEvento(
                'acceso_no_autorizado',
                ['ip_address' => $ipAddress, 'intentos_recientes' => $intentosRecientes],
                null,
                'CRITICA'
            );
        }
    }

    /**
     * Alerta por cambio crítico
     */
    private function alertaCambioCritico(string $tipoEvento, AuditoriaLog $log, array $datos): void
    {
        app(NotificacionService::class)->enviarNotificacion(
            'SISTEMA_ALERTA',
            'Cambio Crítico en el Sistema',
            "Se ha registrado un cambio crítico: " . self::TIPOS_EVENTOS[$tipoEvento] . "\n\nDetalles del evento registrados en auditoría ID: {$log->id}",
            ['auditoria_id' => $log->id, 'tipo_evento' => $tipoEvento],
            null,
            'CRITICA'
        );
    }

    /**
     * Verificar si es evento administrativo
     */
    private function esEventoAdministrativo(string $tipoEvento): bool
    {
        $eventosAdministrativos = [
            'usuario_creado', 'usuario_eliminado', 'modificacion_permisos',
            'configuracion_actualizada', 'backup_iniciado', 'mantenimiento_iniciado'
        ];

        return in_array($tipoEvento, $eventosAdministrativos);
    }

    /**
     * Verificar si es fuera de horario laboral
     */
    private function esFueraHorarioLaboral(): bool
    {
        $horaActual = now()->hour;
        $esFinDeSemana = now()->isWeekend();

        // Fuera de horario: antes de 7 AM, después de 7 PM, o fin de semana
        return $horaActual < 7 || $horaActual > 19 || $esFinDeSemana;
    }

    /**
     * Alerta por actividad fuera de horario
     */
    private function alertaActividadFueraHorario(string $tipoEvento, AuditoriaLog $log): void
    {
        $usuario = $log->usuario;
        $nombreUsuario = $usuario ? $usuario->name : 'Usuario desconocido';

        app(NotificacionService::class)->enviarNotificacion(
            'SISTEMA_ALERTA',
            'Actividad Administrativa Fuera de Horario',
            "Se ha detectado actividad administrativa fuera del horario laboral:\n\n" .
            "• Usuario: {$nombreUsuario}\n" .
            "• Acción: " . self::TIPOS_EVENTOS[$tipoEvento] . "\n" .
            "• Hora: " . $log->created_at->format('d/m/Y H:i:s') . "\n" .
            "• IP: {$log->ip_address}",
            ['auditoria_id' => $log->id],
            null,
            'ADVERTENCIA'
        );
    }

    /**
     * Obtener historial de auditoría con filtros
     */
    public function obtenerHistorial(array $filtros = [], int $limite = 50): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        try {
            $query = AuditoriaLog::with('usuario');

            // Aplicar filtros
            if (isset($filtros['tipo_evento'])) {
                $query->where('tipo_evento', $filtros['tipo_evento']);
            }

            if (isset($filtros['usuario_id'])) {
                $query->where('usuario_id', $filtros['usuario_id']);
            }

            if (isset($filtros['criticidad'])) {
                $query->where('criticidad', $filtros['criticidad']);
            }

            if (isset($filtros['fecha_desde'])) {
                $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
            }

            if (isset($filtros['fecha_hasta'])) {
                $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
            }

            if (isset($filtros['ip_address'])) {
                $query->where('ip_address', $filtros['ip_address']);
            }

            if (isset($filtros['busqueda'])) {
                $busqueda = $filtros['busqueda'];
                $query->where(function ($q) use ($busqueda) {
                    $q->where('descripcion', 'like', "%{$busqueda}%")
                      ->orWhere('tipo_evento', 'like', "%{$busqueda}%")
                      ->orWhereRaw("JSON_EXTRACT(datos, '$.datos_evento') like ?", ["%{$busqueda}%"]);
                });
            }

            return $query->orderByDesc('created_at')
                        ->paginate($limite);

        } catch (Exception $e) {
            Log::error("Error obteniendo historial de auditoría: " . $e->getMessage());
            return AuditoriaLog::query()->paginate(0);
        }
    }

    /**
     * Obtener estadísticas de auditoría
     */
    public function obtenerEstadisticas(int $dias = 30): array
    {
        try {
            $fechaDesde = now()->subDays($dias);

            return Cache::remember("auditoria_stats_{$dias}", 3600, function () use ($fechaDesde) {
                $eventos = AuditoriaLog::where('created_at', '>=', $fechaDesde)->get();

                return [
                    'total_eventos' => $eventos->count(),
                    'eventos_por_dia' => $this->calcularEventosPorDia($eventos),
                    'eventos_por_tipo' => $eventos->groupBy('tipo_evento')
                        ->map->count()
                        ->sortDesc()
                        ->take(10)
                        ->toArray(),
                    'eventos_por_criticidad' => $eventos->groupBy('criticidad')
                        ->map->count()
                        ->toArray(),
                    'usuarios_mas_activos' => $this->obtenerUsuariosMasActivos($eventos),
                    'ips_frecuentes' => $eventos->whereNotNull('ip_address')
                        ->groupBy('ip_address')
                        ->map->count()
                        ->sortDesc()
                        ->take(10)
                        ->toArray(),
                    'eventos_criticos_recientes' => $eventos->where('criticidad', 'CRITICA')
                        ->sortByDesc('created_at')
                        ->take(5)
                        ->map(function ($evento) {
                            return [
                                'id' => $evento->id,
                                'tipo' => $evento->tipo_evento,
                                'descripcion' => $evento->descripcion,
                                'fecha' => $evento->created_at->format('d/m/Y H:i:s'),
                                'usuario' => $evento->usuario->name ?? 'Sistema'
                            ];
                        })
                        ->values()
                        ->toArray(),
                    'tendencias' => $this->calcularTendencias($eventos)
                ];
            });

        } catch (Exception $e) {
            Log::error("Error obteniendo estadísticas de auditoría: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcular eventos por día
     */
    private function calcularEventosPorDia($eventos): array
    {
        $eventosPorDia = [];
        $fechaInicio = now()->subDays(29);

        for ($i = 0; $i < 30; $i++) {
            $fecha = $fechaInicio->copy()->addDays($i);
            $eventosPorDia[$fecha->format('Y-m-d')] = $eventos->filter(function ($evento) use ($fecha) {
                return $evento->created_at->isSameDay($fecha);
            })->count();
        }

        return $eventosPorDia;
    }

    /**
     * Obtener usuarios más activos
     */
    private function obtenerUsuariosMasActivos($eventos): array
    {
        return $eventos->whereNotNull('usuario_id')
            ->groupBy('usuario_id')
            ->map(function ($eventosUsuario) {
                $primerEvento = $eventosUsuario->first();
                return [
                    'usuario_id' => $primerEvento->usuario_id,
                    'nombre' => $primerEvento->usuario->name ?? 'Usuario eliminado',
                    'email' => $primerEvento->usuario->email ?? 'N/A',
                    'total_eventos' => $eventosUsuario->count(),
                    'ultimo_evento' => $eventosUsuario->sortByDesc('created_at')->first()->created_at->format('d/m/Y H:i:s')
                ];
            })
            ->sortByDesc('total_eventos')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Calcular tendencias
     */
    private function calcularTendencias($eventos): array
    {
        $hoy = $eventos->filter(fn($e) => $e->created_at->isToday())->count();
        $ayer = $eventos->filter(fn($e) => $e->created_at->isYesterday())->count();

        $cambioDaily = $ayer > 0 ? round((($hoy - $ayer) / $ayer) * 100, 1) : 0;

        $estaSemanana = $eventos->filter(fn($e) => $e->created_at->isCurrentWeek())->count();
        $semanaAnterior = $eventos->filter(function ($e) {
            return $e->created_at >= now()->subWeeks(2)->startOfWeek() &&
                   $e->created_at < now()->subWeek()->startOfWeek();
        })->count();

        $cambioWeekly = $semanaAnterior > 0 ? round((($estaSemanana - $semanaAnterior) / $semanaAnterior) * 100, 1) : 0;

        return [
            'eventos_hoy' => $hoy,
            'eventos_ayer' => $ayer,
            'cambio_diario_porcentaje' => $cambioDaily,
            'eventos_esta_semana' => $estaSemanana,
            'eventos_semana_anterior' => $semanaAnterior,
            'cambio_semanal_porcentaje' => $cambioWeekly,
            'tendencia_general' => $this->determinarTendenciaGeneral($cambioDaily, $cambioWeekly)
        ];
    }

    /**
     * Determinar tendencia general
     */
    private function determinarTendenciaGeneral(float $cambioDaily, float $cambioWeekly): string
    {
        $promedio = ($cambioDaily + $cambioWeekly) / 2;

        if ($promedio > 20) {
            return 'INCREMENTO_SIGNIFICATIVO';
        } elseif ($promedio > 5) {
            return 'INCREMENTO_MODERADO';
        } elseif ($promedio > -5) {
            return 'ESTABLE';
        } elseif ($promedio > -20) {
            return 'DISMINUCION_MODERADA';
        } else {
            return 'DISMINUCION_SIGNIFICATIVA';
        }
    }

    /**
     * Exportar auditoría a CSV
     */
    public function exportarAuditoria(array $filtros = []): string
    {
        try {
            $query = AuditoriaLog::with('usuario');

            // Aplicar filtros similares al historial
            if (isset($filtros['fecha_desde'])) {
                $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
            }

            if (isset($filtros['fecha_hasta'])) {
                $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
            }

            if (isset($filtros['tipo_evento'])) {
                $query->where('tipo_evento', $filtros['tipo_evento']);
            }

            if (isset($filtros['criticidad'])) {
                $query->where('criticidad', $filtros['criticidad']);
            }

            $eventos = $query->orderByDesc('created_at')->get();

            // Crear archivo CSV
            $nombreArchivo = 'auditoria_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $rutaCompleta = storage_path('app/exports/' . $nombreArchivo);

            // Crear directorio si no existe
            if (!file_exists(dirname($rutaCompleta))) {
                mkdir(dirname($rutaCompleta), 0755, true);
            }

            $archivo = fopen($rutaCompleta, 'w');

            // Escribir encabezados
            fputcsv($archivo, [
                'ID',
                'Fecha/Hora',
                'Tipo de Evento',
                'Descripción',
                'Usuario',
                'Email Usuario',
                'IP Address',
                'Criticidad',
                'Datos Adicionales',
                'User Agent'
            ]);

            // Escribir datos
            foreach ($eventos as $evento) {
                fputcsv($archivo, [
                    $evento->id,
                    $evento->created_at->format('d/m/Y H:i:s'),
                    $evento->tipo_evento,
                    $evento->descripcion,
                    $evento->usuario->name ?? 'Sistema',
                    $evento->usuario->email ?? '',
                    $evento->ip_address ?? '',
                    $evento->criticidad,
                    $evento->datos,
                    $evento->user_agent ?? ''
                ]);
            }

            fclose($archivo);

            // Registrar la exportación
            $this->registrarEvento(
                'exportacion_datos',
                [
                    'tipo_exportacion' => 'auditoria_csv',
                    'archivo' => $nombreArchivo,
                    'registros_exportados' => $eventos->count(),
                    'filtros_aplicados' => $filtros
                ],
                null,
                'MEDIA'
            );

            return $rutaCompleta;

        } catch (Exception $e) {
            Log::error("Error exportando auditoría: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Limpiar registros de auditoría vencidos
     */
    public function limpiarRegistrosVencidos(): int
    {
        try {
            $eliminados = AuditoriaLog::where('fecha_retencion', '<', now())->delete();

            if ($eliminados > 0) {
                Log::info("Limpieza de auditoría: {$eliminados} registros eliminados");

                $this->registrarEvento(
                    'mantenimiento_completado',
                    [
                        'tipo_mantenimiento' => 'limpieza_auditoria',
                        'registros_eliminados' => $eliminados
                    ],
                    null,
                    'BAJA'
                );
            }

            return $eliminados;

        } catch (Exception $e) {
            Log::error("Error limpiando registros de auditoría: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener resumen de actividad de usuario
     */
    public function obtenerResumenActividad(int $usuarioId, int $dias = 30): array
    {
        try {
            $eventos = AuditoriaLog::where('usuario_id', $usuarioId)
                ->where('created_at', '>=', now()->subDays($dias))
                ->get();

            $usuario = User::find($usuarioId);

            return [
                'usuario' => [
                    'id' => $usuarioId,
                    'nombre' => $usuario->name ?? 'Usuario no encontrado',
                    'email' => $usuario->email ?? 'N/A'
                ],
                'periodo' => [
                    'desde' => now()->subDays($dias)->format('d/m/Y'),
                    'hasta' => now()->format('d/m/Y'),
                    'dias' => $dias
                ],
                'resumen' => [
                    'total_eventos' => $eventos->count(),
                    'primer_actividad' => $eventos->min('created_at')?->format('d/m/Y H:i:s'),
                    'ultima_actividad' => $eventos->max('created_at')?->format('d/m/Y H:i:s'),
                    'eventos_por_tipo' => $eventos->groupBy('tipo_evento')->map->count()->toArray(),
                    'eventos_por_criticidad' => $eventos->groupBy('criticidad')->map->count()->toArray(),
                    'dias_activos' => $eventos->groupBy(fn($e) => $e->created_at->format('Y-m-d'))->count(),
                    'promedio_eventos_por_dia' => round($eventos->count() / max($dias, 1), 2)
                ],
                'actividad_por_dia' => $this->calcularActividadPorDia($eventos, $dias),
                'eventos_recientes' => $eventos->sortByDesc('created_at')
                    ->take(10)
                    ->map(function ($evento) {
                        return [
                            'tipo' => $evento->tipo_evento,
                            'descripcion' => $evento->descripcion,
                            'fecha' => $evento->created_at->format('d/m/Y H:i:s'),
                            'criticidad' => $evento->criticidad,
                            'ip' => $evento->ip_address
                        ];
                    })
                    ->values()
                    ->toArray()
            ];

        } catch (Exception $e) {
            Log::error("Error obteniendo resumen de actividad: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcular actividad por día para un usuario
     */
    private function calcularActividadPorDia($eventos, int $dias): array
    {
        $actividadPorDia = [];
        $fechaInicio = now()->subDays($dias - 1);

        for ($i = 0; $i < $dias; $i++) {
            $fecha = $fechaInicio->copy()->addDays($i);
            $actividadPorDia[$fecha->format('Y-m-d')] = [
                'fecha' => $fecha->format('d/m/Y'),
                'eventos' => $eventos->filter(function ($evento) use ($fecha) {
                    return $evento->created_at->isSameDay($fecha);
                })->count()
            ];
        }

        return array_values($actividadPorDia);
    }

    /**
     * Verificar integridad de auditoría
     */
    public function verificarIntegridad(): array
    {
        try {
            $resultado = [
                'estado' => 'OK',
                'verificaciones' => [],
                'problemas' => [],
                'recomendaciones' => []
            ];

            // Verificar consistencia de datos
            $totalRegistros = AuditoriaLog::count();
            $registrosConUsuario = AuditoriaLog::whereNotNull('usuario_id')->count();
            $registrosSinUsuario = $totalRegistros - $registrosConUsuario;

            $resultado['verificaciones'][] = [
                'nombre' => 'Consistencia de datos',
                'total_registros' => $totalRegistros,
                'con_usuario' => $registrosConUsuario,
                'sin_usuario' => $registrosSinUsuario,
                'porcentaje_sin_usuario' => $totalRegistros > 0 ? round(($registrosSinUsuario / $totalRegistros) * 100, 2) : 0
            ];

            // Verificar registros huérfanos (usuarios eliminados)
            $usuariosHuerfanos = AuditoriaLog::whereNotNull('usuario_id')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('users')
                          ->whereColumn('users.id', 'auditoria_logs.usuario_id');
                })
                ->count();

            if ($usuariosHuerfanos > 0) {
                $resultado['problemas'][] = [
                    'tipo' => 'Registros huérfanos',
                    'descripcion' => "{$usuariosHuerfanos} registros referencian usuarios eliminados"
                ];
            }

            // Verificar crecimiento anormal
            $eventosHoy = AuditoriaLog::whereDate('created_at', today())->count();
            $promedioSemanal = AuditoriaLog::whereDate('created_at', '>=', now()->subDays(7))->count() / 7;

            if ($eventosHoy > ($promedioSemanal * 3)) {
                $resultado['problemas'][] = [
                    'tipo' => 'Crecimiento anormal',
                    'descripcion' => "Eventos hoy ({$eventosHoy}) supera 3x el promedio semanal (" . round($promedioSemanal, 1) . ")"
                ];
                $resultado['estado'] = 'ADVERTENCIA';
            }

            // Verificar espacio de almacenamiento
            $tamanoTabla = $this->obtenerTamanoTablaAuditoria();
            if ($tamanoTabla > 1024 * 1024 * 1024) { // > 1GB
                $resultado['recomendaciones'][] = [
                    'tipo' => 'Optimización de almacenamiento',
                    'descripcion' => "La tabla de auditoría ocupa " . $this->formatearBytes($tamanoTabla) . ". Considerar archivado de registros antiguos."
                ];
            }

            // Verificar retención de datos
            $registrosVencidos = AuditoriaLog::where('fecha_retencion', '<', now())->count();
            if ($registrosVencidos > 0) {
                $resultado['recomendaciones'][] = [
                    'tipo' => 'Limpieza de datos',
                    'descripcion' => "{$registrosVencidos} registros han superado su período de retención"
                ];
            }

            return $resultado;

        } catch (Exception $e) {
            Log::error("Error verificando integridad de auditoría: " . $e->getMessage());
            return [
                'estado' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener tamaño de la tabla de auditoría
     */
    private function obtenerTamanoTablaAuditoria(): int
    {
        try {
            $resultado = DB::select("
                SELECT
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'tamano_mb'
                FROM information_schema.tables
                WHERE table_schema = ?
                AND table_name = 'auditoria_logs'
            ", [config('database.connections.' . config('database.default') . '.database')]);

            return isset($resultado[0]) ? ($resultado[0]->tamano_mb * 1024 * 1024) : 0;

        } catch (Exception $e) {
            Log::error("Error obteniendo tamaño de tabla: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Formatear bytes
     */
    private function formatearBytes(int $bytes): string
    {
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($unidades) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $unidades[$pow];
    }

    /**
     * Obtener métricas rápidas para dashboard
     */
    public function obtenerMetricasRapidas(): array
    {
        return Cache::remember('auditoria_metricas_rapidas', 300, function () {
            try {
                return [
                    'eventos_hoy' => AuditoriaLog::whereDate('created_at', today())->count(),
                    'eventos_criticos_pendientes' => AuditoriaLog::where('criticidad', 'CRITICA')
                        ->whereDate('created_at', '>=', now()->subHours(24))
                        ->count(),
                    'usuarios_activos_hoy' => AuditoriaLog::whereDate('created_at', today())
                        ->whereNotNull('usuario_id')
                        ->distinct('usuario_id')
                        ->count(),
                    'ips_unicas_hoy' => AuditoriaLog::whereDate('created_at', today())
                        ->whereNotNull('ip_address')
                        ->distinct('ip_address')
                        ->count(),
                    'total_registros' => AuditoriaLog::count(),
                    'ultimo_evento' => AuditoriaLog::latest()->first()?->created_at?->diffForHumans()
                ];
            } catch (Exception $e) {
                Log::error("Error obteniendo métricas rápidas de auditoría: " . $e->getMessage());
                return array_fill_keys([
                    'eventos_hoy', 'eventos_criticos_pendientes',
                    'usuarios_activos_hoy', 'ips_unicas_hoy', 'total_registros'
                ], 0) + ['ultimo_evento' => 'N/A'];
            }
        });
    }
}
