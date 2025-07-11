<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\DatabaseNotification;
use App\Models\User;
use App\Models\Notificacion;
use App\Models\Validacion;
use App\Models\Conductor;
use App\Models\Parametro;
use App\Mail\ValidacionCriticaMail;
use App\Mail\BackupCompletadoMail;
use App\Mail\MantenimientoSistemaMail;
use App\Mail\ConductorCriticoMail;
use App\Mail\ResumenDiarioMail;
use Carbon\Carbon;
use Exception;

class NotificacionService
{
    /**
     * Canales de notificación disponibles
     */
    private const CANALES = [
        'DATABASE' => 'database',
        'MAIL' => 'mail',
        'SMS' => 'sms',
        'PUSH' => 'push'
    ];

    /**
     * Tipos de notificación y sus configuraciones
     */
    private const TIPOS_NOTIFICACION = [
        'VALIDACION_CRITICA' => [
            'canales' => ['database', 'mail'],
            'prioridad' => 'ALTA',
            'reintento' => true,
            'ttl_horas' => 24
        ],
        'CONDUCTOR_CRITICO' => [
            'canales' => ['database', 'mail'],
            'prioridad' => 'ALTA',
            'reintento' => true,
            'ttl_horas' => 12
        ],
        'BACKUP_COMPLETADO' => [
            'canales' => ['database', 'mail'],
            'prioridad' => 'MEDIA',
            'reintento' => false,
            'ttl_horas' => 72
        ],
        'MANTENIMIENTO_SISTEMA' => [
            'canales' => ['database', 'mail'],
            'prioridad' => 'ALTA',
            'reintento' => true,
            'ttl_horas' => 6
        ],
        'TURNO_ASIGNADO' => [
            'canales' => ['database'],
            'prioridad' => 'MEDIA',
            'reintento' => false,
            'ttl_horas' => 48
        ],
        'EFICIENCIA_BAJA' => [
            'canales' => ['database'],
            'prioridad' => 'BAJA',
            'reintento' => false,
            'ttl_horas' => 168 // 1 semana
        ],
        'SISTEMA_ALERTA' => [
            'canales' => ['database', 'mail'],
            'prioridad' => 'ALTA',
            'reintento' => true,
            'ttl_horas' => 2
        ]
    ];

    /**
     * Roles y sus permisos de notificación
     */
    private const PERMISOS_ROLES = [
        'admin' => [
            'VALIDACION_CRITICA',
            'CONDUCTOR_CRITICO',
            'BACKUP_COMPLETADO',
            'MANTENIMIENTO_SISTEMA',
            'SISTEMA_ALERTA',
            'EFICIENCIA_BAJA',
            'TURNO_ASIGNADO'
        ],
        'supervisor' => [
            'VALIDACION_CRITICA',
            'CONDUCTOR_CRITICO',
            'EFICIENCIA_BAJA',
            'TURNO_ASIGNADO'
        ],
        'planificador' => [
            'VALIDACION_CRITICA',
            'CONDUCTOR_CRITICO',
            'TURNO_ASIGNADO'
        ],
        'operador' => [
            'TURNO_ASIGNADO'
        ]
    ];

    /**
     * Crear y enviar notificación
     */
    public function enviarNotificacion(
        string $tipo,
        string $titulo,
        string $mensaje,
        array $datos = [],
        $destinatarios = null,
        string $severidad = 'INFO'
    ): array {
        try {
            // Validar tipo de notificación
            if (!isset(self::TIPOS_NOTIFICACION[$tipo])) {
                throw new Exception("Tipo de notificación no válido: {$tipo}");
            }

            $configuracion = self::TIPOS_NOTIFICACION[$tipo];

            // Determinar destinatarios si no se especifican
            if ($destinatarios === null) {
                $destinatarios = $this->obtenerDestinatarios($tipo);
            }

            if (empty($destinatarios)) {
                Log::warning("No hay destinatarios para notificación tipo: {$tipo}");
                return ['exito' => false, 'mensaje' => 'No hay destinatarios'];
            }

            // Verificar si las notificaciones están habilitadas
            if (!$this->notificacionesHabilitadas()) {
                Log::info("Notificaciones deshabilitadas, omitiendo: {$tipo}");
                return ['exito' => false, 'mensaje' => 'Notificaciones deshabilitadas'];
            }

            // Verificar límites de frecuencia
            if ($this->excedeLimiteFreuencia($tipo, $datos)) {
                Log::info("Límite de frecuencia excedido para: {$tipo}");
                return ['exito' => false, 'mensaje' => 'Límite de frecuencia excedido'];
            }

            $resultados = [];
            $notificacionId = null;

            // Crear registro en base de datos primero
            if (in_array('database', $configuracion['canales'])) {
                $notificacionId = $this->crearNotificacionBD(
                    $tipo,
                    $titulo,
                    $mensaje,
                    $datos,
                    $destinatarios,
                    $severidad,
                    $configuracion['ttl_horas']
                );
                $resultados['database'] = $notificacionId ? 'exitoso' : 'fallido';
            }

            // Enviar por email si está configurado
            if (in_array('mail', $configuracion['canales']) && $this->emailHabilitado()) {
                $resultadoEmail = $this->enviarEmail($tipo, $titulo, $mensaje, $datos, $destinatarios);
                $resultados['mail'] = $resultadoEmail ? 'exitoso' : 'fallido';
            }

            // Registrar envío para control de frecuencia
            $this->registrarEnvio($tipo, $datos);

            // Programar reintento si es necesario
            if ($configuracion['reintento'] && in_array('fallido', $resultados)) {
                $this->programarReintento($notificacionId, $tipo, $titulo, $mensaje, $datos, $destinatarios, $severidad);
            }

            $exitoso = !in_array('fallido', $resultados);

            return [
                'exito' => $exitoso,
                'notificacion_id' => $notificacionId,
                'resultados' => $resultados,
                'destinatarios_count' => is_array($destinatarios) ? count($destinatarios) : 1
            ];

        } catch (Exception $e) {
            Log::error("Error enviando notificación: " . $e->getMessage(), [
                'tipo' => $tipo,
                'titulo' => $titulo,
                'datos' => $datos
            ]);

            return [
                'exito' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enviar notificación de validación crítica
     */
    public function enviarNotificacionCritica(Validacion $validacion): bool
    {
        try {
            $conductor = $validacion->conductor;
            $titulo = "Validación Crítica: {$validacion->tipo}";

            $mensaje = "Se ha detectado una validación crítica que requiere atención inmediata:\n\n";
            $mensaje .= "• Tipo: {$validacion->tipo}\n";
            $mensaje .= "• Conductor: " . ($conductor ? $conductor->nombre_completo : 'N/A') . "\n";
            $mensaje .= "• Descripción: {$validacion->descripcion}\n";
            $mensaje .= "• Fecha: " . $validacion->created_at->format('d/m/Y H:i') . "\n";

            if ($validacion->solucion_recomendada) {
                $mensaje .= "• Solución recomendada: {$validacion->solucion_recomendada}\n";
            }

            $datos = [
                'validacion_id' => $validacion->id,
                'conductor_id' => $validacion->conductor_id,
                'tipo_validacion' => $validacion->tipo,
                'severidad' => $validacion->severidad
            ];

            $resultado = $this->enviarNotificacion(
                'VALIDACION_CRITICA',
                $titulo,
                $mensaje,
                $datos,
                null,
                'CRITICA'
            );

            return $resultado['exito'];

        } catch (Exception $e) {
            Log::error("Error enviando notificación de validación crítica: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar notificación de conductor crítico
     */
    public function enviarNotificacionConductorCritico(Conductor $conductor, array $razones): bool
    {
        try {
            $titulo = "Conductor Crítico: {$conductor->codigo}";

            $mensaje = "El conductor {$conductor->nombre_completo} requiere atención inmediata:\n\n";
            $mensaje .= "• Código: {$conductor->codigo}\n";
            $mensaje .= "• Estado: {$conductor->estado}\n";
            $mensaje .= "• Días acumulados: {$conductor->dias_acumulados}\n";
            $mensaje .= "• Eficiencia: {$conductor->eficiencia}%\n";
            $mensaje .= "• Puntualidad: {$conductor->puntualidad}%\n\n";
            $mensaje .= "Razones de criticidad:\n";

            foreach ($razones as $razon) {
                $mensaje .= "• {$razon}\n";
            }

            $datos = [
                'conductor_id' => $conductor->id,
                'codigo_conductor' => $conductor->codigo,
                'razones' => $razones,
                'dias_acumulados' => $conductor->dias_acumulados,
                'eficiencia' => $conductor->eficiencia,
                'puntualidad' => $conductor->puntualidad
            ];

            $resultado = $this->enviarNotificacion(
                'CONDUCTOR_CRITICO',
                $titulo,
                $mensaje,
                $datos,
                null,
                'CRITICA'
            );

            return $resultado['exito'];

        } catch (Exception $e) {
            Log::error("Error enviando notificación de conductor crítico: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notificar backup completado
     */
    public function notificarBackupCompletado(array $infoBackup): bool
    {
        try {
            $titulo = "Backup Completado Exitosamente";

            $mensaje = "Se ha completado el backup del sistema:\n\n";
            $mensaje .= "• Tipo: {$infoBackup['tipo']}\n";
            $mensaje .= "• Tamaño: " . $this->formatearBytes($infoBackup['tamano']) . "\n";
            $mensaje .= "• Duración: {$infoBackup['duracion']} segundos\n";
            $mensaje .= "• Archivo: {$infoBackup['archivo']}\n";
            $mensaje .= "• Fecha: " . now()->format('d/m/Y H:i') . "\n";

            $datos = $infoBackup;

            $resultado = $this->enviarNotificacion(
                'BACKUP_COMPLETADO',
                $titulo,
                $mensaje,
                $datos,
                $this->obtenerAdministradores(),
                'INFO'
            );

            return $resultado['exito'];

        } catch (Exception $e) {
            Log::error("Error notificando backup completado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notificar mantenimiento del sistema
     */
    public function notificarMantenimientoSistema(string $tipo = 'programado', array $detalles = []): bool
    {
        try {
            $titulo = "Mantenimiento del Sistema";

            $mensaje = "El sistema entrará en modo de mantenimiento:\n\n";
            $mensaje .= "• Tipo: " . ucfirst($tipo) . "\n";

            if (isset($detalles['inicio'])) {
                $mensaje .= "• Inicio: {$detalles['inicio']}\n";
            }
            if (isset($detalles['duracion_estimada'])) {
                $mensaje .= "• Duración estimada: {$detalles['duracion_estimada']}\n";
            }
            if (isset($detalles['motivo'])) {
                $mensaje .= "• Motivo: {$detalles['motivo']}\n";
            }

            $mensaje .= "\nDurante este período, el sistema no estará disponible.";

            $datos = array_merge($detalles, ['tipo_mantenimiento' => $tipo]);

            $resultado = $this->enviarNotificacion(
                'MANTENIMIENTO_SISTEMA',
                $titulo,
                $mensaje,
                $datos,
                null,
                'ADVERTENCIA'
            );

            return $resultado['exito'];

        } catch (Exception $e) {
            Log::error("Error notificando mantenimiento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar resumen diario a administradores
     */
    public function enviarResumenDiario(): bool
    {
        try {
            if (!$this->debeEnviarResumenDiario()) {
                return true;
            }

            $metricas = app(CacheMetricasService::class)->obtenerMetricasDashboard();

            $titulo = "Resumen Diario SIPAT - " . now()->format('d/m/Y');

            $mensaje = $this->construirMensajeResumenDiario($metricas);

            $datos = [
                'fecha' => now()->format('Y-m-d'),
                'metricas' => $metricas
            ];

            $resultado = $this->enviarNotificacion(
                'SISTEMA_ALERTA',
                $titulo,
                $mensaje,
                $datos,
                $this->obtenerAdministradores(),
                'INFO'
            );

            // Marcar como enviado
            Cache::put('resumen_diario_enviado_' . now()->format('Y-m-d'), true, 1440);

            return $resultado['exito'];

        } catch (Exception $e) {
            Log::error("Error enviando resumen diario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Construir mensaje de resumen diario
     */
    private function construirMensajeResumenDiario(array $metricas): string
    {
        $mensaje = "Resumen de actividades del " . now()->format('d/m/Y') . ":\n\n";

        // Conductores
        $conductores = $metricas['conductores'] ?? [];
        $mensaje .= "📊 CONDUCTORES:\n";
        $mensaje .= "• Total: {$conductores['total']}\n";
        $mensaje .= "• Disponibles: {$conductores['disponibles']} ({$conductores['porcentaje_disponibilidad']}%)\n";
        $mensaje .= "• Críticos: {$conductores['criticos']}\n";
        $mensaje .= "• Promedio eficiencia: {$conductores['promedio_eficiencia']}%\n\n";

        // Validaciones
        $validaciones = $metricas['validaciones'] ?? [];
        $mensaje .= "🔍 VALIDACIONES:\n";
        $mensaje .= "• Pendientes: {$validaciones['pendientes']}\n";
        $mensaje .= "• Críticas: {$validaciones['criticas']}\n";
        $mensaje .= "• Resueltas hoy: {$validaciones['resueltas_hoy']}\n";
        $mensaje .= "• Efectividad: {$validaciones['efectividad_diaria']}%\n\n";

        // Turnos
        $turnos = $metricas['turnos'] ?? [];
        $mensaje .= "🚌 TURNOS:\n";
        $mensaje .= "• Total del día: {$turnos['hoy']}\n";
        $mensaje .= "• Completados: {$turnos['completados']}\n";
        $mensaje .= "• Cobertura: {$turnos['cobertura_porcentaje']}%\n";
        $mensaje .= "• Horas hombre: {$turnos['horas_hombre_dia']}\n\n";

        // Alertas
        $alertas = $metricas['alertas'] ?? [];
        if ($alertas['total'] > 0) {
            $mensaje .= "⚠️ ALERTAS ACTIVAS: {$alertas['total']}\n";
            $mensaje .= "• Críticas: {$alertas['criticas']}\n";
            $mensaje .= "• Advertencias: {$alertas['advertencias']}\n\n";
        }

        $mensaje .= "Dashboard: " . url('/dashboard');

        return $mensaje;
    }

    /**
     * Obtener destinatarios según el tipo de notificación
     */
    private function obtenerDestinatarios(string $tipo): array
    {
        try {
            $usuarios = [];

            foreach (self::PERMISOS_ROLES as $rol => $tiposPermitidos) {
                if (in_array($tipo, $tiposPermitidos)) {
                    $usuariosRol = User::role($rol)->where('email_verified_at', '!=', null)->get();
                    $usuarios = array_merge($usuarios, $usuariosRol->toArray());
                }
            }

            // Eliminar duplicados por email
            $usuarios = collect($usuarios)->unique('email')->values()->toArray();

            return $usuarios;

        } catch (Exception $e) {
            Log::error("Error obteniendo destinatarios: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener usuarios administradores
     */
    private function obtenerAdministradores(): array
    {
        try {
            return User::role('admin')
                ->where('email_verified_at', '!=', null)
                ->get()
                ->toArray();
        } catch (Exception $e) {
            Log::error("Error obteniendo administradores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crear notificación en base de datos
     */
    private function crearNotificacionBD(
        string $tipo,
        string $titulo,
        string $mensaje,
        array $datos,
        array $destinatarios,
        string $severidad,
        int $ttlHoras
    ): ?int {
        try {
            $vencimiento = now()->addHours($ttlHoras);

            // Crear notificación general
            $notificacion = Notificacion::create([
                'tipo' => $tipo,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'datos' => json_encode($datos),
                'severidad' => $severidad,
                'fecha_vencimiento' => $vencimiento,
                'destinatarios_count' => count($destinatarios),
                'estado' => 'ACTIVA'
            ]);

            // Crear registros individuales para cada destinatario
            foreach ($destinatarios as $usuario) {
                DB::table('notificaciones_usuarios')->insert([
                    'notificacion_id' => $notificacion->id,
                    'user_id' => $usuario['id'],
                    'leida' => false,
                    'fecha_lectura' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return $notificacion->id;

        } catch (Exception $e) {
            Log::error("Error creando notificación en BD: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Enviar email
     */
    private function enviarEmail(
        string $tipo,
        string $titulo,
        string $mensaje,
        array $datos,
        array $destinatarios
    ): bool {
        try {
            $emailsEnviar = array_column($destinatarios, 'email');
            $emailsValidos = array_filter($emailsEnviar, 'filter_var', FILTER_VALIDATE_EMAIL);

            if (empty($emailsValidos)) {
                Log::warning("No hay emails válidos para enviar notificación: {$tipo}");
                return false;
            }

            // Seleccionar clase de mail según el tipo
            $mailClass = $this->obtenerClaseMail($tipo);

            foreach ($emailsValidos as $email) {
                try {
                    Mail::to($email)->send(new $mailClass($titulo, $mensaje, $datos));
                } catch (Exception $e) {
                    Log::error("Error enviando email a {$email}: " . $e->getMessage());
                }
            }

            return true;

        } catch (Exception $e) {
            Log::error("Error en envío de emails: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener clase de mail según el tipo
     */
    private function obtenerClaseMail(string $tipo): string
    {
        return match($tipo) {
            'VALIDACION_CRITICA' => ValidacionCriticaMail::class,
            'CONDUCTOR_CRITICO' => ConductorCriticoMail::class,
            'BACKUP_COMPLETADO' => BackupCompletadoMail::class,
            'MANTENIMIENTO_SISTEMA' => MantenimientoSistemaMail::class,
            default => ResumenDiarioMail::class
        };
    }

    /**
     * Contar notificaciones pendientes para un usuario
     */
    public function contarPendientes(int $usuarioId): int
    {
        try {
            return DB::table('notificaciones_usuarios')
                ->join('notificaciones', 'notificaciones.id', '=', 'notificaciones_usuarios.notificacion_id')
                ->where('notificaciones_usuarios.user_id', $usuarioId)
                ->where('notificaciones_usuarios.leida', false)
                ->where('notificaciones.estado', 'ACTIVA')
                ->where('notificaciones.fecha_vencimiento', '>', now())
                ->count();
        } catch (Exception $e) {
            Log::error("Error contando notificaciones pendientes: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener notificaciones recientes para un usuario
     */
    public function obtenerRecientes(int $usuarioId, int $limite = 10): \Illuminate\Support\Collection
    {
        try {
            return DB::table('notificaciones_usuarios')
                ->join('notificaciones', 'notificaciones.id', '=', 'notificaciones_usuarios.notificacion_id')
                ->where('notificaciones_usuarios.user_id', $usuarioId)
                ->where('notificaciones.estado', 'ACTIVA')
                ->where('notificaciones.fecha_vencimiento', '>', now())
                ->select(
                    'notificaciones.*',
                    'notificaciones_usuarios.leida',
                    'notificaciones_usuarios.fecha_lectura'
                )
                ->orderByDesc('notificaciones.created_at')
                ->limit($limite)
                ->get()
                ->map(function ($notificacion) {
                    $notificacion->datos = json_decode($notificacion->datos, true);
                    return $notificacion;
                });
        } catch (Exception $e) {
            Log::error("Error obteniendo notificaciones recientes: " . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarComoLeida(int $notificacionId, int $usuarioId): bool
    {
        try {
            $actualizado = DB::table('notificaciones_usuarios')
                ->where('notificacion_id', $notificacionId)
                ->where('user_id', $usuarioId)
                ->update([
                    'leida' => true,
                    'fecha_lectura' => now(),
                    'updated_at' => now()
                ]);

            return $actualizado > 0;

        } catch (Exception $e) {
            Log::error("Error marcando notificación como leída: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpiar notificaciones vencidas
     */
    public function limpiarNotificacionesVencidas(): int
    {
        try {
            $vencidas = Notificacion::where('fecha_vencimiento', '<', now())
                ->where('estado', 'ACTIVA')
                ->get();

            $contador = 0;
            foreach ($vencidas as $notificacion) {
                // Eliminar registros de usuarios
                DB::table('notificaciones_usuarios')
                    ->where('notificacion_id', $notificacion->id)
                    ->delete();

                // Marcar como vencida
                $notificacion->update(['estado' => 'VENCIDA']);
                $contador++;
            }

            // Eliminar notificaciones muy antiguas (más de retención configurada)
            $diasRetencion = sipat_config('retener_notificaciones_dias', 30);
            $eliminadas = Notificacion::where('created_at', '<', now()->subDays($diasRetencion))
                ->delete();

            Log::info("Limpieza de notificaciones: {$contador} vencidas, {$eliminadas} eliminadas");

            return $contador + $eliminadas;

        } catch (Exception $e) {
            Log::error("Error limpiando notificaciones: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verificar si las notificaciones están habilitadas
     */
    private function notificacionesHabilitadas(): bool
    {
        return sipat_config('enviar_notificaciones_email', true) == 'true';
    }

    /**
     * Verificar si el email está habilitado
     */
    private function emailHabilitado(): bool
    {
        return config('mail.default') !== null &&
               config('mail.mailers.' . config('mail.default') . '.host') !== null;
    }

    /**
     * Verificar límites de frecuencia
     */
    private function excedeLimiteFreuencia(string $tipo, array $datos): bool
    {
        try {
            $clave = "notificacion_limite_{$tipo}_" . md5(serialize($datos));
            $ultimoEnvio = Cache::get($clave);

            if (!$ultimoEnvio) {
                return false;
            }

            // Límites por tipo de notificación (en minutos)
            $limites = [
                'VALIDACION_CRITICA' => 30,    // No enviar la misma validación crítica más de 1 vez en 30 min
                'CONDUCTOR_CRITICO' => 60,     // No enviar el mismo conductor crítico más de 1 vez por hora
                'BACKUP_COMPLETADO' => 1440,   // No enviar más de 1 backup completado por día
                'MANTENIMIENTO_SISTEMA' => 360, // No enviar más de 1 mantenimiento cada 6 horas
            ];

            $limite = $limites[$tipo] ?? 60; // Por defecto 1 hora
            $tiempoTranscurrido = now()->diffInMinutes($ultimoEnvio);

            return $tiempoTranscurrido < $limite;

        } catch (Exception $e) {
            Log::error("Error verificando límite de frecuencia: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar envío para control de frecuencia
     */
    private function registrarEnvio(string $tipo, array $datos): void
    {
        try {
            $clave = "notificacion_limite_{$tipo}_" . md5(serialize($datos));
            Cache::put($clave, now(), 1440); // Mantener registro por 24 horas
        } catch (Exception $e) {
            Log::error("Error registrando envío: " . $e->getMessage());
        }
    }

    /**
     * Programar reintento
     */
    private function programarReintento(
        ?int $notificacionId,
        string $tipo,
        string $titulo,
        string $mensaje,
        array $datos,
        array $destinatarios,
        string $severidad
    ): void {
        try {
            // Programar reintento en 5 minutos
            $datos['reintento'] = true;
            $datos['intento_original'] = $notificacionId;

            Cache::put(
                "reintento_notificacion_" . uniqid(),
                [
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'datos' => $datos,
                    'destinatarios' => $destinatarios,
                    'severidad' => $severidad,
                    'programado_para' => now()->addMinutes(5)->toISOString()
                ],
                60 // 1 hora de TTL para el reintento
            );

        } catch (Exception $e) {
            Log::error("Error programando reintento: " . $e->getMessage());
        }
    }

    /**
     * Procesar reintentos pendientes
     */
    public function procesarReintentos(): int
    {
        // Este método sería llamado por un comando programado
        // Por ahora solo registramos que existe la funcionalidad
        Log::info("Procesando reintentos de notificaciones...");
        return 0;
    }

    /**
     * Verificar si debe enviar resumen diario
     */
    private function debeEnviarResumenDiario(): bool
    {
        $yaEnviado = Cache::has('resumen_diario_enviado_' . now()->format('Y-m-d'));
        $horaConfigurada = sipat_config('hora_generacion_reportes', '23:00');
        $horaActual = now()->format('H:i');

        return !$yaEnviado && $horaActual >= $horaConfigurada;
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
     * Obtener estadísticas del servicio
     */
    public function obtenerEstadisticas(): array
    {
        try {
            return [
                'notificaciones_activas' => Notificacion::where('estado', 'ACTIVA')->count(),
                'notificaciones_hoy' => Notificacion::whereDate('created_at', today())->count(),
                'usuarios_con_pendientes' => DB::table('notificaciones_usuarios')
                    ->where('leida', false)
                    ->distinct('user_id')
                    ->count('user_id'),
                'tipos_frecuentes' => Notificacion::whereDate('created_at', '>=', now()->subDays(7))
                    ->groupBy('tipo')
                    ->selectRaw('tipo, count(*) as total')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->pluck('total', 'tipo')
                    ->toArray(),
                'canales_habilitados' => $this->obtenerCanalesHabilitados(),
                'configuracion' => [
                    'email_habilitado' => $this->emailHabilitado(),
                    'notificaciones_habilitadas' => $this->notificacionesHabilitadas(),
                    'retencion_dias' => sipat_config('retener_notificaciones_dias', 30)
                ]
            ];
        } catch (Exception $e) {
            Log::error("Error obteniendo estadísticas de notificaciones: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener canales habilitados
     */
    private function obtenerCanalesHabilitados(): array
    {
        $canales = [];

        if ($this->emailHabilitado()) {
            $canales[] = 'mail';
        }

        $canales[] = 'database'; // Siempre habilitado

        return $canales;
    }
}
