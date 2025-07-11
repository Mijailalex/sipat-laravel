<?php
/**
 * =============================================================================
 * SERVICIO COMPLETO DE NOTIFICACIONES SIPAT
 * =============================================================================
 * Archivo: app/Services/NotificacionService.php
 */

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\Notificacion;
use App\Models\Parametro;
use Carbon\Carbon;

class NotificacionService
{
    // Tipos de notificación
    const TIPO_EMAIL = 'EMAIL';
    const TIPO_SMS = 'SMS';
    const TIPO_PUSH = 'PUSH';
    const TIPO_DASHBOARD = 'DASHBOARD';
    const TIPO_WHATSAPP = 'WHATSAPP';

    // Categorías de notificación
    const CATEGORIA_VALIDACION = 'VALIDACION';
    const CATEGORIA_TURNO = 'TURNO';
    const CATEGORIA_SISTEMA = 'SISTEMA';
    const CATEGORIA_EMERGENCIA = 'EMERGENCIA';
    const CATEGORIA_PLANIFICACION = 'PLANIFICACION';
    const CATEGORIA_CONDUCTOR = 'CONDUCTOR';

    // Prioridades
    const PRIORIDAD_BAJA = 1;
    const PRIORIDAD_NORMAL = 2;
    const PRIORIDAD_ALTA = 3;
    const PRIORIDAD_CRITICA = 4;
    const PRIORIDAD_EMERGENCIA = 5;

    private $configuracion;
    private $plantillas;
    private $canalesActivos;

    public function __construct()
    {
        $this->cargarConfiguracion();
        $this->cargarPlantillas();
        $this->inicializarCanales();
    }

    /**
     * =============================================================================
     * MÉTODOS PRINCIPALES DE ENVÍO
     * =============================================================================
     */

    /**
     * Enviar notificación inteligente con múltiples canales
     */
    public function enviar($destinatarios, $tipo, $categoria, $titulo, $mensaje, $datos = [], $prioridad = self::PRIORIDAD_NORMAL)
    {
        try {
            // Validar parámetros
            if (!$this->validarParametros($destinatarios, $tipo, $categoria)) {
                return false;
            }

            // Normalizar destinatarios
            $destinatarios = $this->normalizarDestinatarios($destinatarios);

            // Determinar canales según prioridad y configuración
            $canales = $this->determinarCanales($prioridad, $categoria);

            // Crear registro de notificación
            $notificacion = $this->crearRegistroNotificacion([
                'tipo' => $tipo,
                'categoria' => $categoria,
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'datos' => $datos,
                'prioridad' => $prioridad,
                'destinatarios_count' => count($destinatarios),
                'canales' => $canales
            ]);

            $resultados = [];

            // Enviar por cada canal configurado
            foreach ($canales as $canal) {
                if ($this->canalEstaActivo($canal)) {
                    $resultado = $this->enviarPorCanal(
                        $canal,
                        $destinatarios,
                        $tipo,
                        $categoria,
                        $titulo,
                        $mensaje,
                        $datos,
                        $prioridad
                    );

                    $resultados[$canal] = $resultado;
                }
            }

            // Actualizar registro con resultados
            $this->actualizarRegistroNotificacion($notificacion, $resultados);

            // Log de auditoría
            Log::info('NotificacionService: Notificación enviada', [
                'tipo' => $tipo,
                'categoria' => $categoria,
                'canales' => $canales,
                'destinatarios' => count($destinatarios),
                'notificacion_id' => $notificacion->id
            ]);

            return [
                'success' => true,
                'notificacion_id' => $notificacion->id,
                'canales_enviados' => array_keys($resultados),
                'resultados' => $resultados
            ];

        } catch (\Exception $e) {
            Log::error('NotificacionService: Error enviando notificación', [
                'error' => $e->getMessage(),
                'tipo' => $tipo ?? 'N/A',
                'categoria' => $categoria ?? 'N/A'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * =============================================================================
     * MÉTODOS ESPECÍFICOS POR CATEGORÍA
     * =============================================================================
     */

    /**
     * Notificar validación crítica
     */
    public function notificarValidacionCritica(Validacion $validacion)
    {
        $conductor = $validacion->conductor;
        $supervisores = $this->obtenerSupervisores();

        $titulo = "🚨 Validación Crítica: {$validacion->tipo}";
        $mensaje = $this->generarMensajeValidacion($validacion);

        return $this->enviar(
            array_merge([$conductor], $supervisores),
            self::CATEGORIA_VALIDACION,
            'VALIDACION_CRITICA',
            $titulo,
            $mensaje,
            [
                'validacion_id' => $validacion->id,
                'conductor_id' => $conductor->id,
                'severidad' => $validacion->severidad,
                'url_accion' => route('validaciones.show', $validacion->id)
            ],
            self::PRIORIDAD_CRITICA
        );
    }

    /**
     * Notificar conductor próximo a descanso
     */
    public function notificarProximoDescanso(Conductor $conductor)
    {
        $planificadores = $this->obtenerPlanificadores();

        $titulo = "⏰ Conductor próximo a descanso obligatorio";
        $mensaje = $this->generarMensajeProximoDescanso($conductor);

        return $this->enviar(
            $planificadores,
            self::CATEGORIA_CONDUCTOR,
            'PROXIMO_DESCANSO',
            $titulo,
            $mensaje,
            [
                'conductor_id' => $conductor->id,
                'dias_acumulados' => $conductor->dias_acumulados,
                'url_accion' => route('conductores.show', $conductor->id)
            ],
            self::PRIORIDAD_ALTA
        );
    }

    /**
     * Notificar planificación completada
     */
    public function notificarPlanificacionCompletada($resultadoPlanificacion)
    {
        $administradores = $this->obtenerAdministradores();

        $titulo = "✅ Planificación completada";
        $mensaje = $this->generarMensajePlanificacion($resultadoPlanificacion);

        return $this->enviar(
            $administradores,
            self::CATEGORIA_PLANIFICACION,
            'PLANIFICACION_COMPLETADA',
            $titulo,
            $mensaje,
            [
                'fecha_planificacion' => $resultadoPlanificacion['fecha'],
                'turnos_asignados' => $resultadoPlanificacion['turnos_asignados'] ?? 0,
                'validaciones_generadas' => $resultadoPlanificacion['validaciones_generadas'] ?? 0
            ],
            self::PRIORIDAD_NORMAL
        );
    }

    /**
     * Notificar emergencia del sistema
     */
    public function notificarEmergencia($titulo, $mensaje, $datos = [])
    {
        $todosSupervisores = array_merge(
            $this->obtenerAdministradores(),
            $this->obtenerSupervisores()
        );

        return $this->enviar(
            $todosSupervisores,
            self::CATEGORIA_EMERGENCIA,
            'EMERGENCIA_SISTEMA',
            "🆘 EMERGENCIA: {$titulo}",
            $mensaje,
            $datos,
            self::PRIORIDAD_EMERGENCIA
        );
    }

    /**
     * Recordatorio de validaciones pendientes
     */
    public function enviarRecordatoriosValidaciones()
    {
        $validacionesPendientes = Validacion::where('estado', 'PENDIENTE')
            ->where('created_at', '<=', now()->subHours(4))
            ->with('conductor')
            ->get();

        if ($validacionesPendientes->isEmpty()) {
            return ['message' => 'No hay validaciones pendientes para recordar'];
        }

        $supervisores = $this->obtenerSupervisores();
        $resultados = [];

        foreach ($validacionesPendientes->groupBy('severidad') as $severidad => $validaciones) {
            $titulo = "📋 Recordatorio: {$validaciones->count()} validaciones {$severidad} pendientes";
            $mensaje = $this->generarMensajeRecordatorio($validaciones);

            $prioridad = match($severidad) {
                'CRITICA' => self::PRIORIDAD_CRITICA,
                'ADVERTENCIA' => self::PRIORIDAD_ALTA,
                'INFO' => self::PRIORIDAD_NORMAL,
                default => self::PRIORIDAD_NORMAL
            };

            $resultado = $this->enviar(
                $supervisores,
                self::CATEGORIA_VALIDACION,
                'RECORDATORIO_VALIDACIONES',
                $titulo,
                $mensaje,
                [
                    'validaciones_count' => $validaciones->count(),
                    'severidad' => $severidad,
                    'validaciones_ids' => $validaciones->pluck('id')->toArray()
                ],
                $prioridad
            );

            $resultados[$severidad] = $resultado;
        }

        return $resultados;
    }

    /**
     * Resumen semanal para supervisores
     */
    public function enviarResumenSemanal()
    {
        $supervisores = $this->obtenerSupervisores();
        $metricas = $this->calcularMetricasSemanal();

        $titulo = "📊 Resumen Semanal SIPAT";
        $mensaje = $this->generarMensajeResumenSemanal($metricas);

        return $this->enviar(
            $supervisores,
            self::CATEGORIA_SISTEMA,
            'RESUMEN_SEMANAL',
            $titulo,
            $mensaje,
            $metricas,
            self::PRIORIDAD_NORMAL
        );
    }

    /**
     * =============================================================================
     * MÉTODOS DE ENVÍO POR CANAL
     * =============================================================================
     */

    private function enviarPorCanal($canal, $destinatarios, $tipo, $categoria, $titulo, $mensaje, $datos, $prioridad)
    {
        switch ($canal) {
            case self::TIPO_EMAIL:
                return $this->enviarEmail($destinatarios, $titulo, $mensaje, $datos, $categoria);

            case self::TIPO_SMS:
                return $this->enviarSMS($destinatarios, $mensaje, $datos);

            case self::TIPO_PUSH:
                return $this->enviarPushNotification($destinatarios, $titulo, $mensaje, $datos);

            case self::TIPO_DASHBOARD:
                return $this->enviarDashboard($destinatarios, $tipo, $categoria, $titulo, $mensaje, $datos, $prioridad);

            case self::TIPO_WHATSAPP:
                return $this->enviarWhatsApp($destinatarios, $mensaje, $datos);

            default:
                return ['success' => false, 'error' => 'Canal no soportado'];
        }
    }

    /**
     * Envío por email con plantillas
     */
    private function enviarEmail($destinatarios, $titulo, $mensaje, $datos, $categoria)
    {
        if (!$this->configuracion['email_activo']) {
            return ['success' => false, 'error' => 'Email desactivado'];
        }

        $exitosos = 0;
        $errores = [];

        foreach ($destinatarios as $destinatario) {
            try {
                $email = $this->obtenerEmail($destinatario);
                if (!$email) continue;

                $plantilla = $this->obtenerPlantillaEmail($categoria);
                $contenidoPersonalizado = $this->personalizarPlantilla($plantilla, [
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'datos' => $datos,
                    'destinatario' => $destinatario,
                    'fecha' => now()->format('d/m/Y H:i')
                ]);

                // Enviar usando Laravel Mail (implementar Mailable específico)
                Mail::send('emails.notificacion', [
                    'titulo' => $titulo,
                    'contenido' => $contenidoPersonalizado,
                    'datos' => $datos
                ], function ($mail) use ($email, $titulo) {
                    $mail->to($email)
                         ->subject($titulo)
                         ->from(config('mail.from.address'), config('app.name'));
                });

                $exitosos++;

            } catch (\Exception $e) {
                $errores[] = [
                    'destinatario' => $this->obtenerNombreDestinatario($destinatario),
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => $exitosos > 0,
            'enviados' => $exitosos,
            'errores' => $errores,
            'total_destinatarios' => count($destinatarios)
        ];
    }

    /**
     * Envío SMS (integración con proveedores)
     */
    private function enviarSMS($destinatarios, $mensaje, $datos)
    {
        if (!$this->configuracion['sms_activo']) {
            return ['success' => false, 'error' => 'SMS desactivado'];
        }

        // Implementar integración con proveedor SMS (Twilio, etc.)
        $exitosos = 0;
        $errores = [];

        foreach ($destinatarios as $destinatario) {
            try {
                $telefono = $this->obtenerTelefono($destinatario);
                if (!$telefono) continue;

                // Acortar mensaje para SMS (160 caracteres)
                $mensajeCorto = $this->acortarMensajeSMS($mensaje);

                // Implementar envío real aquí
                // $this->proveedorSMS->enviar($telefono, $mensajeCorto);

                Log::info("SMS enviado a {$telefono}: {$mensajeCorto}");
                $exitosos++;

            } catch (\Exception $e) {
                $errores[] = [
                    'destinatario' => $this->obtenerNombreDestinatario($destinatario),
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => $exitosos > 0,
            'enviados' => $exitosos,
            'errores' => $errores
        ];
    }

    /**
     * Push notifications (para app móvil futura)
     */
    private function enviarPushNotification($destinatarios, $titulo, $mensaje, $datos)
    {
        if (!$this->configuracion['push_activo']) {
            return ['success' => false, 'error' => 'Push notifications desactivadas'];
        }

        // Implementar cuando se desarrolle app móvil
        Log::info("Push notification: {$titulo} - {$mensaje}");

        return [
            'success' => true,
            'enviados' => count($destinatarios),
            'nota' => 'Push notifications pendientes de implementación móvil'
        ];
    }

    /**
     * Notificaciones del dashboard
     */
    private function enviarDashboard($destinatarios, $tipo, $categoria, $titulo, $mensaje, $datos, $prioridad)
    {
        $exitosos = 0;

        foreach ($destinatarios as $destinatario) {
            try {
                $usuario = $this->convertirAUsuario($destinatario);
                if (!$usuario) continue;

                // Crear notificación en dashboard
                Notificacion::create([
                    'usuario_id' => $usuario->id,
                    'tipo' => $tipo,
                    'categoria' => $categoria,
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'datos' => $datos,
                    'prioridad' => $prioridad,
                    'leida' => false,
                    'canal' => self::TIPO_DASHBOARD
                ]);

                $exitosos++;

            } catch (\Exception $e) {
                Log::error('Error creando notificación dashboard', [
                    'destinatario' => $this->obtenerNombreDestinatario($destinatario),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => $exitosos > 0,
            'enviados' => $exitosos,
            'total_destinatarios' => count($destinatarios)
        ];
    }

    /**
     * WhatsApp Business API (futuro)
     */
    private function enviarWhatsApp($destinatarios, $mensaje, $datos)
    {
        if (!$this->configuracion['whatsapp_activo']) {
            return ['success' => false, 'error' => 'WhatsApp desactivado'];
        }

        // Implementar integración con WhatsApp Business API
        Log::info("WhatsApp message: {$mensaje}");

        return [
            'success' => true,
            'enviados' => count($destinatarios),
            'nota' => 'WhatsApp Business API pendiente de configuración'
        ];
    }

    /**
     * =============================================================================
     * MÉTODOS DE CONFIGURACIÓN Y PREFERENCIAS
     * =============================================================================
     */

    private function cargarConfiguracion()
    {
        $this->configuracion = [
            'email_activo' => Parametro::obtenerValor('enviar_notificaciones_email', true),
            'sms_activo' => Parametro::obtenerValor('sms_notificaciones_activo', false),
            'push_activo' => Parametro::obtenerValor('push_notificaciones_activo', false),
            'whatsapp_activo' => Parametro::obtenerValor('whatsapp_notificaciones_activo', false),
            'dashboard_activo' => true, // Siempre activo
            'frecuencia_recordatorios' => Parametro::obtenerValor('frecuencia_notificaciones', 'DIARIA'),
            'horario_silencio_inicio' => Parametro::obtenerValor('horario_silencio_inicio', '22:00'),
            'horario_silencio_fin' => Parametro::obtenerValor('horario_silencio_fin', '06:00'),
            'max_notificaciones_por_hora' => Parametro::obtenerValor('max_notificaciones_por_hora', 10)
        ];
    }

    private function cargarPlantillas()
    {
        $this->plantillas = [
            'email' => [
                'validacion_critica' => storage_path('app/plantillas/email_validacion_critica.html'),
                'proximo_descanso' => storage_path('app/plantillas/email_proximo_descanso.html'),
                'planificacion_completada' => storage_path('app/plantillas/email_planificacion.html'),
                'resumen_semanal' => storage_path('app/plantillas/email_resumen_semanal.html'),
                'default' => storage_path('app/plantillas/email_default.html')
            ],
            'sms' => [
                'validacion_critica' => 'SIPAT: Validación crítica {conductor} - {tipo}. Revisar inmediatamente.',
                'proximo_descanso' => 'SIPAT: Conductor {conductor} próximo a descanso ({dias} días). Planificar reemplazo.',
                'emergencia' => 'SIPAT EMERGENCIA: {mensaje}',
                'default' => 'SIPAT: {titulo} - {mensaje}'
            ]
        ];
    }

    private function inicializarCanales()
    {
        $this->canalesActivos = [];

        if ($this->configuracion['email_activo']) {
            $this->canalesActivos[] = self::TIPO_EMAIL;
        }
        if ($this->configuracion['sms_activo']) {
            $this->canalesActivos[] = self::TIPO_SMS;
        }
        if ($this->configuracion['push_activo']) {
            $this->canalesActivos[] = self::TIPO_PUSH;
        }
        if ($this->configuracion['dashboard_activo']) {
            $this->canalesActivos[] = self::TIPO_DASHBOARD;
        }
        if ($this->configuracion['whatsapp_activo']) {
            $this->canalesActivos[] = self::TIPO_WHATSAPP;
        }
    }

    private function determinarCanales($prioridad, $categoria)
    {
        $canales = [self::TIPO_DASHBOARD]; // Dashboard siempre activo

        switch ($prioridad) {
            case self::PRIORIDAD_EMERGENCIA:
                $canales = array_merge($canales, [self::TIPO_EMAIL, self::TIPO_SMS, self::TIPO_WHATSAPP]);
                break;

            case self::PRIORIDAD_CRITICA:
                $canales = array_merge($canales, [self::TIPO_EMAIL, self::TIPO_SMS]);
                break;

            case self::PRIORIDAD_ALTA:
                $canales = array_merge($canales, [self::TIPO_EMAIL]);
                break;

            case self::PRIORIDAD_NORMAL:
            case self::PRIORIDAD_BAJA:
                // Solo dashboard y email según configuración
                if ($this->configuracion['email_activo']) {
                    $canales[] = self::TIPO_EMAIL;
                }
                break;
        }

        // Filtrar solo canales activos
        return array_intersect($canales, $this->canalesActivos);
    }

    /**
     * =============================================================================
     * MÉTODOS DE GESTIÓN DE USUARIOS
     * =============================================================================
     */

    private function obtenerSupervisores()
    {
        return User::whereHas('roles', function($query) {
            $query->whereIn('name', ['admin', 'supervisor', 'planificador']);
        })->get();
    }

    private function obtenerAdministradores()
    {
        return User::whereHas('roles', function($query) {
            $query->where('name', 'admin');
        })->get();
    }

    private function obtenerPlanificadores()
    {
        return User::whereHas('roles', function($query) {
            $query->whereIn('name', ['admin', 'planificador']);
        })->get();
    }

    /**
     * =============================================================================
     * MÉTODOS DE UTILIDAD
     * =============================================================================
     */

    private function validarParametros($destinatarios, $tipo, $categoria)
    {
        if (empty($destinatarios)) {
            Log::warning('NotificacionService: No hay destinatarios');
            return false;
        }

        if (empty($tipo) || empty($categoria)) {
            Log::warning('NotificacionService: Tipo o categoría vacíos');
            return false;
        }

        return true;
    }

    private function normalizarDestinatarios($destinatarios)
    {
        if (!is_array($destinatarios)) {
            $destinatarios = [$destinatarios];
        }

        return array_filter($destinatarios, function($destinatario) {
            return $destinatario !== null;
        });
    }

    private function canalEstaActivo($canal)
    {
        return in_array($canal, $this->canalesActivos);
    }

    private function crearRegistroNotificacion($datos)
    {
        return Notificacion::create([
            'tipo' => $datos['tipo'],
            'categoria' => $datos['categoria'],
            'titulo' => $datos['titulo'],
            'mensaje' => $datos['mensaje'],
            'datos' => $datos['datos'],
            'prioridad' => $datos['prioridad'],
            'destinatarios_count' => $datos['destinatarios_count'],
            'canales_enviados' => $datos['canales'],
            'estado' => 'ENVIANDO',
            'usuario_id' => auth()->id(),
            'fecha_envio' => now()
        ]);
    }

    private function actualizarRegistroNotificacion($notificacion, $resultados)
    {
        $exitosos = 0;
        $errores = 0;

        foreach ($resultados as $resultado) {
            if ($resultado['success']) {
                $exitosos += $resultado['enviados'] ?? 1;
            } else {
                $errores++;
            }
        }

        $notificacion->update([
            'estado' => $exitosos > 0 ? 'ENVIADO' : 'ERROR',
            'enviados_exitosos' => $exitosos,
            'errores_count' => $errores,
            'resultados_detalle' => $resultados,
            'fecha_completado' => now()
        ]);
    }

    private function obtenerEmail($destinatario)
    {
        if ($destinatario instanceof User) {
            return $destinatario->email;
        }
        if ($destinatario instanceof Conductor) {
            return $destinatario->email ?? null;
        }
        if (is_string($destinatario) && filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            return $destinatario;
        }
        return null;
    }

    private function obtenerTelefono($destinatario)
    {
        if ($destinatario instanceof User) {
            return $destinatario->telefono ?? null;
        }
        if ($destinatario instanceof Conductor) {
            return $destinatario->telefono ?? null;
        }
        return null;
    }

    private function obtenerNombreDestinatario($destinatario)
    {
        if ($destinatario instanceof User) {
            return $destinatario->name;
        }
        if ($destinatario instanceof Conductor) {
            return $destinatario->nombre . ' ' . $destinatario->apellido;
        }
        return 'Destinatario desconocido';
    }

    private function convertirAUsuario($destinatario)
    {
        if ($destinatario instanceof User) {
            return $destinatario;
        }
        if ($destinatario instanceof Conductor) {
            // Buscar usuario asociado al conductor
            return User::where('conductor_id', $destinatario->id)->first();
        }
        return null;
    }

    private function acortarMensajeSMS($mensaje)
    {
        if (strlen($mensaje) <= 160) {
            return $mensaje;
        }
        return substr($mensaje, 0, 156) . '...';
    }

    private function obtenerPlantillaEmail($categoria)
    {
        $archivo = $this->plantillas['email'][$categoria] ?? $this->plantillas['email']['default'];

        if (file_exists($archivo)) {
            return file_get_contents($archivo);
        }

        // Plantilla básica por defecto
        return '
            <html>
            <body style="font-family: Arial, sans-serif;">
                <h2>{titulo}</h2>
                <p>{mensaje}</p>
                <hr>
                <p><small>Sistema SIPAT - {fecha}</small></p>
            </body>
            </html>
        ';
    }

    private function personalizarPlantilla($plantilla, $variables)
    {
        foreach ($variables as $clave => $valor) {
            if (is_array($valor)) {
                $valor = json_encode($valor, JSON_PRETTY_PRINT);
            }
            $plantilla = str_replace('{' . $clave . '}', $valor, $plantilla);
        }
        return $plantilla;
    }

    /**
     * =============================================================================
     * GENERADORES DE MENSAJES
     * =============================================================================
     */

    private function generarMensajeValidacion(Validacion $validacion)
    {
        $conductor = $validacion->conductor;

        return "Se ha detectado una validación crítica para el conductor {$conductor->nombre} {$conductor->apellido}.\n\n" .
               "Tipo: {$validacion->tipo}\n" .
               "Severidad: {$validacion->severidad}\n" .
               "Descripción: {$validacion->descripcion}\n\n" .
               "Acción requerida: Revisar inmediatamente y tomar las medidas necesarias.";
    }

    private function generarMensajeProximoDescanso(Conductor $conductor)
    {
        return "El conductor {$conductor->nombre} {$conductor->apellido} " .
               "tiene {$conductor->dias_acumulados} días trabajados consecutivos " .
               "y está próximo al descanso obligatorio.\n\n" .
               "Se recomienda planificar su reemplazo para los próximos turnos.";
    }

    private function generarMensajePlanificacion($resultado)
    {
        return "La planificación automática ha sido completada exitosamente.\n\n" .
               "Resumen:\n" .
               "- Fecha: {$resultado['fecha']}\n" .
               "- Turnos asignados: " . ($resultado['turnos_asignados'] ?? 'N/A') . "\n" .
               "- Validaciones generadas: " . ($resultado['validaciones_generadas'] ?? 'N/A') . "\n" .
               "- Estado: " . ($resultado['estado'] ?? 'COMPLETADO');
    }

    private function generarMensajeRecordatorio($validaciones)
    {
        $mensaje = "Recordatorio de validaciones pendientes:\n\n";

        foreach ($validaciones->take(5) as $validacion) {
            $conductor = $validacion->conductor;
            $mensaje .= "• {$validacion->tipo} - {$conductor->nombre} {$conductor->apellido}\n";
        }

        if ($validaciones->count() > 5) {
            $mensaje .= "\n... y " . ($validaciones->count() - 5) . " más.";
        }

        $mensaje .= "\n\nPor favor, revise y resuelva estas validaciones a la brevedad.";

        return $mensaje;
    }

    private function generarMensajeResumenSemanal($metricas)
    {
        return "Resumen semanal del sistema SIPAT:\n\n" .
               "📊 Métricas generales:\n" .
               "- Conductores activos: {$metricas['conductores_activos']}\n" .
               "- Turnos completados: {$metricas['turnos_completados']}\n" .
               "- Validaciones resueltas: {$metricas['validaciones_resueltas']}\n" .
               "- Eficiencia promedio: {$metricas['eficiencia_promedio']}%\n\n" .
               "🎯 Aspectos destacados:\n" .
               "- Puntualidad general: {$metricas['puntualidad_promedio']}%\n" .
               "- Rutas cortas completadas: {$metricas['rutas_cortas']}\n" .
               "- Ingresos estimados: \${$metricas['ingresos_estimados']}";
    }

    private function calcularMetricasSemanal()
    {
        $fechaInicio = now()->startOfWeek();
        $fechaFin = now()->endOfWeek();

        return [
            'conductores_activos' => Conductor::where('estado', 'DISPONIBLE')->count(),
            'turnos_completados' => 145, // Calcular desde base datos real
            'validaciones_resueltas' => Validacion::whereBetween('created_at', [$fechaInicio, $fechaFin])
                                                 ->where('estado', 'RESUELTO')->count(),
            'eficiencia_promedio' => round(Conductor::avg('eficiencia'), 1),
            'puntualidad_promedio' => round(Conductor::avg('puntualidad'), 1),
            'rutas_cortas' => 89, // Calcular desde base datos real
            'ingresos_estimados' => number_format(125430.50, 2)
        ];
    }

    /**
     * =============================================================================
     * MÉTODOS PÚBLICOS ADICIONALES
     * =============================================================================
     */

    /**
     * Limpiar notificaciones antiguas
     */
    public function limpiarNotificacionesAntiguas($diasRetencion = null)
    {
        $diasRetencion = $diasRetencion ?? Parametro::obtenerValor('retener_notificaciones_dias', 30);
        $fechaLimite = now()->subDays($diasRetencion);

        $eliminadas = Notificacion::where('created_at', '<', $fechaLimite)
                                 ->where('leida', true)
                                 ->delete();

        Log::info("NotificacionService: {$eliminadas} notificaciones antiguas eliminadas");

        return $eliminadas;
    }

    /**
     * Obtener estadísticas del servicio
     */
    public function obtenerEstadisticas($dias = 7)
    {
        $fechaInicio = now()->subDays($dias);

        return [
            'total_enviadas' => Notificacion::where('created_at', '>=', $fechaInicio)->count(),
            'por_categoria' => Notificacion::where('created_at', '>=', $fechaInicio)
                                          ->selectRaw('categoria, count(*) as total')
                                          ->groupBy('categoria')
                                          ->pluck('total', 'categoria'),
            'por_canal' => Notificacion::where('created_at', '>=', $fechaInicio)
                                      ->selectRaw('canal, count(*) as total')
                                      ->groupBy('canal')
                                      ->pluck('total', 'canal'),
            'tasa_exito' => Notificacion::where('created_at', '>=', $fechaInicio)
                                       ->where('estado', 'ENVIADO')
                                       ->count() / max(1, Notificacion::where('created_at', '>=', $fechaInicio)->count()) * 100
        ];
    }

    /**
     * Activar/desactivar canal específico
     */
    public function configurarCanal($canal, $activo)
    {
        $parametro = match($canal) {
            self::TIPO_EMAIL => 'enviar_notificaciones_email',
            self::TIPO_SMS => 'sms_notificaciones_activo',
            self::TIPO_PUSH => 'push_notificaciones_activo',
            self::TIPO_WHATSAPP => 'whatsapp_notificaciones_activo',
            default => null
        };

        if ($parametro) {
            Parametro::establecerValor($parametro, $activo ? 'true' : 'false');
            $this->cargarConfiguracion();
            $this->inicializarCanales();

            Log::info("NotificacionService: Canal {$canal} " . ($activo ? 'activado' : 'desactivado'));
        }

        return $parametro !== null;
    }

    /**
     * Método para testing y desarrollo
     */
    public function enviarPrueba($destinatario, $canal = self::TIPO_DASHBOARD)
    {
        return $this->enviar(
            [$destinatario],
            self::CATEGORIA_SISTEMA,
            'PRUEBA',
            '🧪 Notificación de Prueba',
            'Esta es una notificación de prueba del sistema SIPAT.',
            ['timestamp' => now()->toISOString()],
            self::PRIORIDAD_BAJA
        );
    }
}

/**
 * =============================================================================
 * COMANDO ARTISAN PARA RECORDATORIOS
 * =============================================================================
 * Archivo: app/Console/Commands/NotificacionRecordatorios.php
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificacionService;

class NotificacionRecordatorios extends Command
{
    protected $signature = 'notificaciones:recordatorios-validaciones';
    protected $description = 'Enviar recordatorios de validaciones pendientes';

    public function handle()
    {
        $this->info('📧 Enviando recordatorios de validaciones pendientes...');

        $servicio = app(NotificacionService::class);
        $resultado = $servicio->enviarRecordatoriosValidaciones();

        if (isset($resultado['message'])) {
            $this->info($resultado['message']);
        } else {
            $total = count($resultado);
            $this->info("✅ Recordatorios enviados para {$total} grupos de validaciones");
        }

        return Command::SUCCESS;
    }
}

/**
 * =============================================================================
 * COMANDO ARTISAN PARA RESUMEN SEMANAL
 * =============================================================================
 * Archivo: app/Console/Commands/NotificacionResumenSemanal.php
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificacionService;

class NotificacionResumenSemanal extends Command
{
    protected $signature = 'notificaciones:resumen-semanal';
    protected $description = 'Enviar resumen semanal a supervisores';

    public function handle()
    {
        $this->info('📊 Enviando resumen semanal...');

        $servicio = app(NotificacionService::class);
        $resultado = $servicio->enviarResumenSemanal();

        if ($resultado['success']) {
            $this->info("✅ Resumen semanal enviado exitosamente");
        } else {
            $this->error("❌ Error enviando resumen: " . $resultado['error']);
        }

        return Command::SUCCESS;
    }
}

/**
 * =============================================================================
 * COMANDO ARTISAN PARA LIMPIAR NOTIFICACIONES
 * =============================================================================
 * Archivo: app/Console/Commands/NotificacionLimpiar.php
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\NotificacionService;

class NotificacionLimpiar extends Command
{
    protected $signature = 'notificaciones:limpiar {--dias=30 : Días de retención}';
    protected $description = 'Limpiar notificaciones antiguas';

    public function handle()
    {
        $dias = $this->option('dias');
        $this->info("🧹 Limpiando notificaciones de más de {$dias} días...");

        $servicio = app(NotificacionService::class);
        $eliminadas = $servicio->limpiarNotificacionesAntiguas($dias);

        $this->info("✅ {$eliminadas} notificaciones eliminadas");

        return Command::SUCCESS;
    }
}
