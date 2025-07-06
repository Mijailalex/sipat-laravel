<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;

class HistorialCredenciales extends Model
{
    use HasFactory;

    protected $table = 'historial_credenciales';

    protected $fillable = [
        'usuario_id',
        'administrador_id',
        'accion',
        'descripcion',
        'datos',
        'ip_address',
        'user_agent',
        'resultado',
        'severidad',
        'metadatos'
    ];

    protected $casts = [
        'datos' => 'array',
        'metadatos' => 'array'
    ];

    // Tipos de acciones del sistema
    const ACCION_USUARIO_CREADO = 'USUARIO_CREADO';
    const ACCION_USUARIO_ACTUALIZADO = 'USUARIO_ACTUALIZADO';
    const ACCION_USUARIO_ELIMINADO = 'USUARIO_ELIMINADO';
    const ACCION_PASSWORD_CAMBIADA = 'PASSWORD_CAMBIADA';
    const ACCION_ESTADO_CAMBIADO = 'ESTADO_CAMBIADO';
    const ACCION_ACCESO_EXITOSO = 'ACCESO_EXITOSO';
    const ACCION_ACCESO_FALLIDO = 'ACCESO_FALLIDO';
    const ACCION_LOGOUT = 'LOGOUT';
    const ACCION_SESION_EXPIRADA = 'SESION_EXPIRADA';
    const ACCION_ROL_ASIGNADO = 'ROL_ASIGNADO';
    const ACCION_PERMISO_OTORGADO = 'PERMISO_OTORGADO';
    const ACCION_PERMISO_REVOCADO = 'PERMISO_REVOCADO';
    const ACCION_BLOQUEO_AUTOMATICO = 'BLOQUEO_AUTOMATICO';
    const ACCION_DESBLOQUEO = 'DESBLOQUEO';
    const ACCION_RESET_PASSWORD = 'RESET_PASSWORD';
    const ACCION_BACKUP_CREDENCIALES = 'BACKUP_CREDENCIALES';
    const ACCION_RESTAURACION = 'RESTAURACION';

    // Niveles de severidad
    const SEVERIDAD_INFO = 'INFO';
    const SEVERIDAD_ADVERTENCIA = 'ADVERTENCIA';
    const SEVERIDAD_CRITICA = 'CRITICA';
    const SEVERIDAD_EMERGENCIA = 'EMERGENCIA';

    // =============================================================================
    // RELACIONES
    // =============================================================================

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function administrador()
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }

    // =============================================================================
    // SCOPES (FILTROS)
    // =============================================================================

    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopePorAdministrador($query, $adminId)
    {
        return $query->where('administrador_id', $adminId);
    }

    public function scopePorAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopePorSeveridad($query, $severidad)
    {
        return $query->where('severidad', $severidad);
    }

    public function scopeRecientes($query, $horas = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($horas));
    }

    public function scopeDelPeriodo($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
    }

    public function scopeCriticos($query)
    {
        return $query->whereIn('severidad', [self::SEVERIDAD_CRITICA, self::SEVERIDAD_EMERGENCIA]);
    }

    public function scopeAccesosFallidos($query)
    {
        return $query->where('accion', self::ACCION_ACCESO_FALLIDO);
    }

    public function scopeAccesosExitosos($query)
    {
        return $query->where('accion', self::ACCION_ACCESO_EXITOSO);
    }

    // =============================================================================
    // MÉTODOS ESTÁTICOS PARA REGISTRAR ACCIONES
    // =============================================================================

    /**
     * Método principal para registrar cualquier acción
     */
    public static function registrarAccion($usuarioId, $accion, $datos = [], $administradorId = null)
    {
        $registro = new static();
        $registro->usuario_id = $usuarioId;
        $registro->administrador_id = $administradorId ?: auth()->id();
        $registro->accion = $accion;
        $registro->descripcion = static::generarDescripcion($accion, $datos);
        $registro->datos = $datos;
        $registro->ip_address = Request::ip();
        $registro->user_agent = Request::userAgent();
        $registro->resultado = 'EXITOSO';
        $registro->severidad = static::determinarSeveridad($accion);
        $registro->metadatos = static::recopilarMetadatos();

        $registro->save();

        return $registro;
    }

    /**
     * Registrar acceso exitoso
     */
    public static function registrarAccesoExitoso($usuarioId, $datosAdicionales = [])
    {
        return static::registrarAccion($usuarioId, self::ACCION_ACCESO_EXITOSO, array_merge([
            'fecha_acceso' => now(),
            'tipo_dispositivo' => static::detectarTipoDispositivo(),
            'navegador' => static::detectarNavegador()
        ], $datosAdicionales));
    }

    /**
     * Registrar acceso fallido
     */
    public static function registrarAccesoFallido($usuarioId, $motivo, $datosAdicionales = [])
    {
        return static::registrarAccion($usuarioId, self::ACCION_ACCESO_FALLIDO, array_merge([
            'motivo_fallo' => $motivo,
            'fecha_intento' => now(),
            'intentos_consecutivos' => static::contarIntentosFallidos($usuarioId),
            'ip_origen' => Request::ip()
        ], $datosAdicionales));
    }

    /**
     * Registrar cierre de sesión
     */
    public static function registrarLogout($usuarioId, $tipoLogout = 'MANUAL')
    {
        return static::registrarAccion($usuarioId, self::ACCION_LOGOUT, [
            'tipo_logout' => $tipoLogout, // MANUAL, AUTOMATICO, FORZADO
            'duracion_sesion' => static::calcularDuracionSesion($usuarioId),
            'fecha_logout' => now()
        ]);
    }

    /**
     * Registrar cambio de contraseña
     */
    public static function registrarCambioPassword($usuarioId, $forzadoPorAdmin = false, $adminId = null)
    {
        return static::registrarAccion($usuarioId, self::ACCION_PASSWORD_CAMBIADA, [
            'forzado_por_admin' => $forzadoPorAdmin,
            'admin_id' => $adminId,
            'fecha_cambio' => now(),
            'fortaleza_password' => static::evaluarFortalezaPassword()
        ], $adminId);
    }

    /**
     * Registrar bloqueo automático
     */
    public static function registrarBloqueoAutomatico($usuarioId, $motivo)
    {
        return static::registrarAccion($usuarioId, self::ACCION_BLOQUEO_AUTOMATICO, [
            'motivo_bloqueo' => $motivo,
            'intentos_fallidos' => static::contarIntentosFallidos($usuarioId),
            'fecha_bloqueo' => now(),
            'bloqueo_automatico' => true
        ]);
    }

    // =============================================================================
    // MÉTODOS DE ANÁLISIS Y REPORTES
    // =============================================================================

    /**
     * Obtener estadísticas de seguridad por período
     */
    public static function obtenerEstadisticasSeguridad($fechaInicio = null, $fechaFin = null)
    {
        $fechaInicio = $fechaInicio ?: now()->subDays(30);
        $fechaFin = $fechaFin ?: now();

        $query = static::whereBetween('created_at', [$fechaInicio, $fechaFin]);

        return [
            'periodo' => [
                'inicio' => $fechaInicio->format('Y-m-d'),
                'fin' => $fechaFin->format('Y-m-d')
            ],
            'totales' => [
                'acciones_totales' => $query->count(),
                'accesos_exitosos' => $query->where('accion', self::ACCION_ACCESO_EXITOSO)->count(),
                'accesos_fallidos' => $query->where('accion', self::ACCION_ACCESO_FALLIDO)->count(),
                'cambios_password' => $query->where('accion', self::ACCION_PASSWORD_CAMBIADA)->count(),
                'usuarios_bloqueados' => $query->where('accion', self::ACCION_BLOQUEO_AUTOMATICO)->count()
            ],
            'por_severidad' => $query->selectRaw('severidad, COUNT(*) as total')
                                   ->groupBy('severidad')
                                   ->pluck('total', 'severidad')
                                   ->toArray(),
            'usuarios_mas_activos' => static::obtenerUsuariosMasActivos($fechaInicio, $fechaFin),
            'ips_mas_frecuentes' => static::obtenerIPsMasFrecuentes($fechaInicio, $fechaFin),
            'horarios_pico' => static::analizarHorariosPico($fechaInicio, $fechaFin),
            'alertas_seguridad' => static::detectarAnomalias($fechaInicio, $fechaFin)
        ];
    }

    /**
     * Detectar comportamientos anómalos de seguridad
     */
    public static function detectarAnomalias($fechaInicio, $fechaFin)
    {
        $anomalias = [];

        // Detectar múltiples accesos fallidos desde la misma IP
        $ipsSospechosas = static::whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->where('accion', self::ACCION_ACCESO_FALLIDO)
            ->selectRaw('ip_address, COUNT(*) as intentos')
            ->groupBy('ip_address')
            ->having('intentos', '>=', 10)
            ->get();

        foreach ($ipsSospechosas as $ip) {
            $anomalias[] = [
                'tipo' => 'IP_SOSPECHOSA',
                'descripcion' => "IP {$ip->ip_address} con {$ip->intentos} intentos fallidos",
                'severidad' => 'CRITICA',
                'datos' => ['ip' => $ip->ip_address, 'intentos' => $ip->intentos]
            ];
        }

        // Detectar accesos fuera de horario laboral
        $accesosNocturnos = static::whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->where('accion', self::ACCION_ACCESO_EXITOSO)
            ->whereRaw('HOUR(created_at) NOT BETWEEN 6 AND 22')
            ->count();

        if ($accesosNocturnos > 0) {
            $anomalias[] = [
                'tipo' => 'ACCESOS_FUERA_HORARIO',
                'descripcion' => "{$accesosNocturnos} accesos registrados fuera del horario laboral",
                'severidad' => 'ADVERTENCIA',
                'datos' => ['total_accesos' => $accesosNocturnos]
            ];
        }

        // Detectar usuarios con actividad administrativa elevada
        $adminsMuyActivos = static::whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->whereNotNull('administrador_id')
            ->selectRaw('administrador_id, COUNT(*) as acciones')
            ->groupBy('administrador_id')
            ->having('acciones', '>=', 50)
            ->with('administrador')
            ->get();

        foreach ($adminsMuyActivos as $admin) {
            $anomalias[] = [
                'tipo' => 'ADMIN_MUY_ACTIVO',
                'descripcion' => "Administrador {$admin->administrador->name} con {$admin->acciones} acciones administrativas",
                'severidad' => 'INFO',
                'datos' => [
                    'admin_id' => $admin->administrador_id,
                    'admin_nombre' => $admin->administrador->name,
                    'total_acciones' => $admin->acciones
                ]
            ];
        }

        return $anomalias;
    }

    /**
     * Generar reporte de actividad de usuario específico
     */
    public static function reporteActividadUsuario($usuarioId, $dias = 30)
    {
        $fechaInicio = now()->subDays($dias);
        $actividades = static::where('usuario_id', $usuarioId)
                             ->where('created_at', '>=', $fechaInicio)
                             ->orderBy('created_at', 'desc')
                             ->get();

        return [
            'usuario_id' => $usuarioId,
            'periodo_dias' => $dias,
            'resumen' => [
                'total_actividades' => $actividades->count(),
                'ultimo_acceso' => $actividades->where('accion', self::ACCION_ACCESO_EXITOSO)->first()?->created_at,
                'intentos_fallidos' => $actividades->where('accion', self::ACCION_ACCESO_FALLIDO)->count(),
                'cambios_password' => $actividades->where('accion', self::ACCION_PASSWORD_CAMBIADA)->count(),
                'ips_utilizadas' => $actividades->pluck('ip_address')->unique()->count()
            ],
            'actividades_por_dia' => $actividades->groupBy(function($item) {
                return $item->created_at->format('Y-m-d');
            })->map(function($grupo) {
                return $grupo->count();
            }),
            'patrones_horarios' => $actividades->groupBy(function($item) {
                return $item->created_at->hour;
            })->map(function($grupo) {
                return $grupo->count();
            }),
            'dispositivos_utilizados' => $actividades->pluck('datos.tipo_dispositivo')->filter()->countBy(),
            'ubicaciones_ip' => $actividades->pluck('ip_address')->unique()->values(),
            'actividades_criticas' => $actividades->where('severidad', self::SEVERIDAD_CRITICA)->values()
        ];
    }

    // =============================================================================
    // MÉTODOS PRIVADOS DE UTILIDAD
    // =============================================================================

    private static function generarDescripcion($accion, $datos)
    {
        switch ($accion) {
            case self::ACCION_USUARIO_CREADO:
                return "Usuario creado con rol: " . ($datos['rol_asignado'] ?? 'sin rol');

            case self::ACCION_ACCESO_EXITOSO:
                return "Acceso exitoso al sistema";

            case self::ACCION_ACCESO_FALLIDO:
                return "Intento de acceso fallido: " . ($datos['motivo_fallo'] ?? 'credenciales incorrectas');

            case self::ACCION_PASSWORD_CAMBIADA:
                return $datos['forzado_por_admin'] ?? false
                    ? "Contraseña cambiada por administrador"
                    : "Contraseña cambiada por el usuario";

            case self::ACCION_BLOQUEO_AUTOMATICO:
                return "Usuario bloqueado automáticamente: " . ($datos['motivo_bloqueo'] ?? 'exceso de intentos fallidos');

            default:
                return "Acción ejecutada: {$accion}";
        }
    }

    private static function determinarSeveridad($accion)
    {
        $severidadesCriticas = [
            self::ACCION_USUARIO_ELIMINADO,
            self::ACCION_BLOQUEO_AUTOMATICO,
            self::ACCION_PERMISO_REVOCADO
        ];

        $severidadesAdvertencia = [
            self::ACCION_ACCESO_FALLIDO,
            self::ACCION_PASSWORD_CAMBIADA,
            self::ACCION_ESTADO_CAMBIADO
        ];

        if (in_array($accion, $severidadesCriticas)) {
            return self::SEVERIDAD_CRITICA;
        }

        if (in_array($accion, $severidadesAdvertencia)) {
            return self::SEVERIDAD_ADVERTENCIA;
        }

        return self::SEVERIDAD_INFO;
    }

    private static function recopilarMetadatos()
    {
        return [
            'timestamp' => now()->timestamp,
            'timezone' => config('app.timezone'),
            'session_id' => session()->getId(),
            'request_id' => Request::header('X-Request-ID'),
            'sistema_operativo' => static::detectarSistemaOperativo(),
            'es_mobile' => static::esMobile()
        ];
    }

    private static function detectarTipoDispositivo()
    {
        $userAgent = Request::userAgent();

        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'MOBILE';
        } elseif (preg_match('/Tablet/', $userAgent)) {
            return 'TABLET';
        }

        return 'DESKTOP';
    }

    private static function detectarNavegador()
    {
        $userAgent = Request::userAgent();

        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';

        return 'Desconocido';
    }

    private static function detectarSistemaOperativo()
    {
        $userAgent = Request::userAgent();

        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac') !== false) return 'MacOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iOS') !== false) return 'iOS';

        return 'Desconocido';
    }

    private static function esMobile()
    {
        return preg_match('/Mobile|Android|iPhone/', Request::userAgent()) ? true : false;
    }

    private static function contarIntentosFallidos($usuarioId)
    {
        return static::where('usuario_id', $usuarioId)
                     ->where('accion', self::ACCION_ACCESO_FALLIDO)
                     ->where('created_at', '>=', now()->subHours(1))
                     ->count();
    }

    private static function calcularDuracionSesion($usuarioId)
    {
        $ultimoAcceso = static::where('usuario_id', $usuarioId)
                              ->where('accion', self::ACCION_ACCESO_EXITOSO)
                              ->latest()
                              ->first();

        return $ultimoAcceso ? now()->diffInMinutes($ultimoAcceso->created_at) : 0;
    }

    private static function evaluarFortalezaPassword()
    {
        // En producción, esto evaluaría la contraseña actual
        // Por seguridad, solo retornamos un indicador genérico
        return 'EVALUADA';
    }

    private static function obtenerUsuariosMasActivos($fechaInicio, $fechaFin)
    {
        return static::whereBetween('created_at', [$fechaInicio, $fechaFin])
                     ->selectRaw('usuario_id, COUNT(*) as total_actividades')
                     ->groupBy('usuario_id')
                     ->orderByDesc('total_actividades')
                     ->limit(10)
                     ->with('usuario:id,name,email')
                     ->get()
                     ->map(function($item) {
                         return [
                             'usuario' => $item->usuario->name ?? 'Usuario eliminado',
                             'email' => $item->usuario->email ?? 'N/A',
                             'actividades' => $item->total_actividades
                         ];
                     });
    }

    private static function obtenerIPsMasFrecuentes($fechaInicio, $fechaFin)
    {
        return static::whereBetween('created_at', [$fechaInicio, $fechaFin])
                     ->selectRaw('ip_address, COUNT(*) as total_accesos')
                     ->groupBy('ip_address')
                     ->orderByDesc('total_accesos')
                     ->limit(10)
                     ->pluck('total_accesos', 'ip_address')
                     ->toArray();
    }

    private static function analizarHorariosPico($fechaInicio, $fechaFin)
    {
        return static::whereBetween('created_at', [$fechaInicio, $fechaFin])
                     ->selectRaw('HOUR(created_at) as hora, COUNT(*) as total_actividades')
                     ->groupBy('hora')
                     ->orderBy('hora')
                     ->pluck('total_actividades', 'hora')
                     ->toArray();
    }

    // =============================================================================
    // MÉTODOS DE MANTENIMIENTO
    // =============================================================================

    /**
     * Limpiar registros antiguos
     */
    public static function limpiarRegistrosAntiguos($dias = 365)
    {
        $fechaLimite = now()->subDays($dias);

        // Mantener registros críticos por más tiempo
        $eliminados = static::where('created_at', '<', $fechaLimite)
                            ->where('severidad', '!=', self::SEVERIDAD_CRITICA)
                            ->delete();

        return $eliminados;
    }

    /**
     * Generar backup del historial de credenciales
     */
    public static function generarBackup($fechaInicio = null, $fechaFin = null)
    {
        $fechaInicio = $fechaInicio ?: now()->subYear();
        $fechaFin = $fechaFin ?: now();

        $registros = static::with(['usuario:id,name,email', 'administrador:id,name,email'])
                           ->whereBetween('created_at', [$fechaInicio, $fechaFin])
                           ->get()
                           ->map(function($registro) {
                               return [
                                   'id' => $registro->id,
                                   'usuario' => $registro->usuario?->email,
                                   'administrador' => $registro->administrador?->email,
                                   'accion' => $registro->accion,
                                   'descripcion' => $registro->descripcion,
                                   'severidad' => $registro->severidad,
                                   'ip_address' => $registro->ip_address,
                                   'fecha' => $registro->created_at,
                                   'datos' => $registro->datos
                               ];
                           });

        // Registrar la acción de backup
        static::registrarAccion(auth()->id(), self::ACCION_BACKUP_CREDENCIALES, [
            'periodo_backup' => [
                'inicio' => $fechaInicio->format('Y-m-d'),
                'fin' => $fechaFin->format('Y-m-d')
            ],
            'total_registros' => $registros->count(),
            'generado_por' => auth()->user()->email
        ]);

        return $registros;
    }

    /**
     * Obtener resumen ejecutivo de seguridad
     */
    public static function resumenEjecutivo($dias = 7)
    {
        $fechaInicio = now()->subDays($dias);

        return [
            'periodo' => "{$dias} días",
            'fecha_reporte' => now()->format('Y-m-d H:i:s'),
            'metricas_clave' => [
                'total_accesos' => static::where('created_at', '>=', $fechaInicio)
                                        ->where('accion', self::ACCION_ACCESO_EXITOSO)
                                        ->count(),
                'intentos_fallidos' => static::where('created_at', '>=', $fechaInicio)
                                             ->where('accion', self::ACCION_ACCESO_FALLIDO)
                                             ->count(),
                'usuarios_activos' => static::where('created_at', '>=', $fechaInicio)
                                            ->where('accion', self::ACCION_ACCESO_EXITOSO)
                                            ->distinct('usuario_id')
                                            ->count(),
                'alertas_criticas' => static::where('created_at', '>=', $fechaInicio)
                                            ->where('severidad', self::SEVERIDAD_CRITICA)
                                            ->count()
            ],
            'tendencias' => [
                'accesos_por_dia' => static::where('created_at', '>=', $fechaInicio)
                                           ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                                           ->groupBy('fecha')
                                           ->orderBy('fecha')
                                           ->pluck('total', 'fecha')
                                           ->toArray(),
                'top_usuarios' => static::obtenerUsuariosMasActivos($fechaInicio, now())->take(3),
                'horarios_activos' => static::analizarHorariosPico($fechaInicio, now())
            ],
            'recomendaciones' => static::generarRecomendacionesSeguridad($fechaInicio)
        ];
    }

    private static function generarRecomendacionesSeguridad($fechaInicio)
    {
        $recomendaciones = [];

        $intentosFallidos = static::where('created_at', '>=', $fechaInicio)
                                  ->where('accion', self::ACCION_ACCESO_FALLIDO)
                                  ->count();

        if ($intentosFallidos > 50) {
            $recomendaciones[] = "Alto número de intentos fallidos ({$intentosFallidos}). Considerar implementar CAPTCHA.";
        }

        $accesosNocturnos = static::where('created_at', '>=', $fechaInicio)
                                  ->where('accion', self::ACCION_ACCESO_EXITOSO)
                                  ->whereRaw('HOUR(created_at) BETWEEN 22 AND 6')
                                  ->count();

        if ($accesosNocturnos > 0) {
            $recomendaciones[] = "Detectados {$accesosNocturnos} accesos fuera de horario laboral. Revisar necesidad.";
        }

        return $recomendaciones ?: ['Sistema de seguridad operando normalmente.'];
    }
}
