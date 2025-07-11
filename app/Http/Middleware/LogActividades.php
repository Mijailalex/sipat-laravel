<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\AuditoriaService;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class LogActividades
{
    /**
     * Servicio de auditoría
     */
    protected AuditoriaService $auditoriaService;

    /**
     * Rutas que deben ser logueadas
     */
    private const RUTAS_CRITICAS = [
        // Gestión de conductores
        'conductores.store',
        'conductores.update',
        'conductores.destroy',
        'conductores.cambiar-estado',

        // Gestión de validaciones
        'validaciones.resolver',
        'validaciones.rechazar',
        'validaciones.update',
        'validaciones.destroy',

        // Gestión de turnos
        'turnos.store',
        'turnos.update',
        'turnos.destroy',
        'turnos.asignar-conductor',
        'turnos.completar',

        // Administración del sistema
        'admin.usuarios.store',
        'admin.usuarios.update',
        'admin.usuarios.destroy',
        'admin.configuracion.update',
        'admin.backups.create',
        'admin.backups.restore',

        // Planificación
        'planificacion.ejecutar',
        'planificacion.asignacion-masiva',

        // Reportes y exportaciones
        'reportes.export',
        'conductores.export',
        'validaciones.export'
    ];

    /**
     * Métodos HTTP que requieren logging
     */
    private const METODOS_CRITICOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Rutas que deben ser excluidas del logging
     */
    private const RUTAS_EXCLUIDAS = [
        'dashboard',
        'api/heartbeat',
        'api/health',
        '_debugbar',
        'telescope',
        'horizon'
    ];

    /**
     * Datos sensibles que no deben ser logueados
     */
    private const CAMPOS_SENSIBLES = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_key',
        'secret',
        'credit_card',
        'card_number',
        'cvv'
    ];

    public function __construct(AuditoriaService $auditoriaService)
    {
        $this->auditoriaService = $auditoriaService;
    }

    /**
     * Manejar una request entrante
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Capturar tiempo de inicio
        $tiempoInicio = microtime(true);

        // Procesar la request
        $response = $next($request);

        // Decidir si debe loguear esta actividad
        if ($this->debeLoguear($request, $response)) {
            $this->registrarActividad($request, $response, $tiempoInicio);
        }

        return $response;
    }

    /**
     * Determinar si debe loguear la actividad
     */
    private function debeLoguear(Request $request, Response $response): bool
    {
        // No loguear rutas excluidas
        if ($this->esRutaExcluida($request)) {
            return false;
        }

        // Siempre loguear rutas críticas
        if ($this->esRutaCritica($request)) {
            return true;
        }

        // Loguear métodos críticos (POST, PUT, PATCH, DELETE)
        if (in_array($request->method(), self::METODOS_CRITICOS)) {
            return true;
        }

        // Loguear errores HTTP (4xx, 5xx)
        if ($response->getStatusCode() >= 400) {
            return true;
        }

        // Loguear accesos administrativos
        if ($this->esAccesoAdministrativo($request)) {
            return true;
        }

        // Loguear actividad fuera de horario laboral para usuarios autenticados
        if (Auth::check() && $this->esFueraHorarioLaboral()) {
            return true;
        }

        return false;
    }

    /**
     * Verificar si es una ruta excluida
     */
    private function esRutaExcluida(Request $request): bool
    {
        $ruta = $request->path();

        foreach (self::RUTAS_EXCLUIDAS as $rutaExcluida) {
            if (str_contains($ruta, $rutaExcluida)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verificar si es una ruta crítica
     */
    private function esRutaCritica(Request $request): bool
    {
        $nombreRuta = $request->route()?->getName();

        if (!$nombreRuta) {
            return false;
        }

        return in_array($nombreRuta, self::RUTAS_CRITICAS);
    }

    /**
     * Verificar si es acceso administrativo
     */
    private function esAccesoAdministrativo(Request $request): bool
    {
        $ruta = $request->path();

        return str_starts_with($ruta, 'admin/') ||
               str_contains($ruta, '/admin') ||
               str_contains($ruta, 'configuracion') ||
               str_contains($ruta, 'usuarios');
    }

    /**
     * Verificar si es fuera de horario laboral
     */
    private function esFueraHorarioLaboral(): bool
    {
        $ahora = now();
        $hora = $ahora->hour;

        // Fuera de horario: antes de 7 AM, después de 7 PM, o fin de semana
        return $hora < 7 || $hora > 19 || $ahora->isWeekend();
    }

    /**
     * Registrar la actividad
     */
    private function registrarActividad(Request $request, Response $response, float $tiempoInicio): void
    {
        try {
            // Calcular tiempo de respuesta
            $tiempoRespuesta = round((microtime(true) - $tiempoInicio) * 1000, 2); // en millisegundos

            // Obtener datos de la request
            $datosRequest = $this->extraerDatosRequest($request);

            // Obtener datos de la response
            $datosResponse = $this->extraerDatosResponse($response);

            // Determinar el tipo de evento
            $tipoEvento = $this->determinarTipoEvento($request, $response);

            // Determinar la criticidad
            $criticidad = $this->determinarCriticidad($request, $response);

            // Preparar datos completos
            $datosActividad = [
                'request' => $datosRequest,
                'response' => $datosResponse,
                'tiempo_respuesta_ms' => $tiempoRespuesta,
                'memoria_pico_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'contexto' => $this->obtenerContexto($request)
            ];

            // Registrar en auditoría
            $this->auditoriaService->registrarEvento(
                $tipoEvento,
                $datosActividad,
                Auth::id(),
                $criticidad,
                $request->ip(),
                $request->userAgent()
            );

            // Log adicional para errores críticos
            if ($response->getStatusCode() >= 500) {
                Log::channel('sipat_errores')->error('Error crítico en aplicación', [
                    'url' => $request->fullUrl(),
                    'metodo' => $request->method(),
                    'codigo_estado' => $response->getStatusCode(),
                    'usuario_id' => Auth::id(),
                    'tiempo_respuesta' => $tiempoRespuesta
                ]);
            }

            // Log para actividades sospechosas
            if ($this->esActividadSospechosa($request, $response)) {
                Log::channel('sipat_auditoria')->warning('Actividad sospechosa detectada', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'metodo' => $request->method(),
                    'usuario_id' => Auth::id(),
                    'razon' => $this->obtenerRazonSospecha($request, $response)
                ]);
            }

        } catch (Exception $e) {
            // No interrumpir la aplicación por errores de logging
            Log::error('Error en middleware LogActividades: ' . $e->getMessage(), [
                'url' => $request->fullUrl(),
                'metodo' => $request->method(),
                'usuario_id' => Auth::id()
            ]);
        }
    }

    /**
     * Extraer datos relevantes de la request
     */
    private function extraerDatosRequest(Request $request): array
    {
        $datos = [
            'url' => $request->fullUrl(),
            'metodo' => $request->method(),
            'ruta' => $request->path(),
            'nombre_ruta' => $request->route()?->getName(),
            'parametros_ruta' => $request->route()?->parameters() ?? [],
            'headers_relevantes' => $this->filtrarHeaders($request->headers->all()),
            'query_parameters' => $request->query(),
            'body_size_bytes' => strlen($request->getContent())
        ];

        // Incluir datos del body para métodos que lo requieren (excluyendo archivos grandes)
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && $datos['body_size_bytes'] < 10240) { // < 10KB
            $bodyData = $request->except(self::CAMPOS_SENSIBLES);

            // Sanitizar datos de archivos
            $bodyData = $this->sanitizarDatosArchivos($bodyData);

            $datos['body_data'] = $bodyData;
        }

        return $datos;
    }

    /**
     * Filtrar headers relevantes para logging
     */
    private function filtrarHeaders(array $headers): array
    {
        $headersRelevantes = [
            'content-type',
            'accept',
            'origin',
            'referer',
            'x-requested-with',
            'x-forwarded-for',
            'x-real-ip'
        ];

        $headersFiltrados = [];
        foreach ($headersRelevantes as $header) {
            if (isset($headers[$header])) {
                $headersFiltrados[$header] = $headers[$header];
            }
        }

        return $headersFiltrados;
    }

    /**
     * Sanitizar datos de archivos para logging
     */
    private function sanitizarDatosArchivos(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            if ($valor instanceof \Illuminate\Http\UploadedFile) {
                $datos[$clave] = [
                    'original_name' => $valor->getClientOriginalName(),
                    'mime_type' => $valor->getMimeType(),
                    'size_bytes' => $valor->getSize(),
                    'extension' => $valor->getClientOriginalExtension()
                ];
            } elseif (is_array($valor)) {
                $datos[$clave] = $this->sanitizarDatosArchivos($valor);
            }
        }

        return $datos;
    }

    /**
     * Extraer datos relevantes de la response
     */
    private function extraerDatosResponse(Response $response): array
    {
        $datos = [
            'codigo_estado' => $response->getStatusCode(),
            'texto_estado' => Response::$statusTexts[$response->getStatusCode()] ?? 'Unknown',
            'content_type' => $response->headers->get('Content-Type'),
            'content_length' => $response->headers->get('Content-Length'),
            'headers_relevantes' => $this->filtrarHeadersResponse($response->headers->all())
        ];

        // Para errores, incluir parte del contenido (si es texto)
        if ($response->getStatusCode() >= 400) {
            $contenido = $response->getContent();
            if (is_string($contenido) && strlen($contenido) > 0) {
                // Solo los primeros 500 caracteres para errores
                $datos['error_content'] = substr($contenido, 0, 500);
            }
        }

        return $datos;
    }

    /**
     * Filtrar headers relevantes de la response
     */
    private function filtrarHeadersResponse(array $headers): array
    {
        $headersRelevantes = [
            'cache-control',
            'location',
            'set-cookie',
            'x-powered-by'
        ];

        $headersFiltrados = [];
        foreach ($headersRelevantes as $header) {
            if (isset($headers[$header])) {
                // Para cookies, solo indicar que existen
                if ($header === 'set-cookie') {
                    $headersFiltrados[$header] = ['cookies_set' => count($headers[$header])];
                } else {
                    $headersFiltrados[$header] = $headers[$header];
                }
            }
        }

        return $headersFiltrados;
    }

    /**
     * Determinar el tipo de evento para auditoría
     */
    private function determinarTipoEvento(Request $request, Response $response): string
    {
        // Errores HTTP
        if ($response->getStatusCode() >= 500) {
            return 'error_servidor';
        }

        if ($response->getStatusCode() >= 400) {
            return 'error_cliente';
        }

        // Basado en la ruta
        $nombreRuta = $request->route()?->getName();

        if ($nombreRuta) {
            // Mapeo de rutas a eventos
            $mapeoEventos = [
                'login' => 'usuario_login',
                'logout' => 'usuario_logout',
                'conductores.store' => 'conductor_creado',
                'conductores.update' => 'conductor_actualizado',
                'conductores.destroy' => 'conductor_eliminado',
                'validaciones.resolver' => 'validacion_resuelta',
                'turnos.store' => 'turno_creado',
                'turnos.update' => 'turno_actualizado',
                'admin.configuracion.update' => 'configuracion_actualizada',
                'admin.backups.create' => 'backup_iniciado'
            ];

            if (isset($mapeoEventos[$nombreRuta])) {
                return $mapeoEventos[$nombreRuta];
            }
        }

        // Basado en el método y ruta
        $metodo = $request->method();
        $ruta = $request->path();

        if ($metodo === 'POST' && str_contains($ruta, 'export')) {
            return 'exportacion_datos';
        }

        if ($metodo === 'POST' && str_contains($ruta, 'import')) {
            return 'importacion_datos';
        }

        if (str_contains($ruta, 'admin')) {
            return 'acceso_administrativo';
        }

        // Evento genérico
        return 'actividad_web';
    }

    /**
     * Determinar la criticidad del evento
     */
    private function determinarCriticidad(Request $request, Response $response): string
    {
        // Errores críticos
        if ($response->getStatusCode() >= 500) {
            return 'CRITICA';
        }

        // Errores de autorización
        if (in_array($response->getStatusCode(), [401, 403])) {
            return 'ALTA';
        }

        // Actividades administrativas críticas
        if ($this->esActividadAdministrativaCritica($request)) {
            return 'ALTA';
        }

        // Actividades fuera de horario
        if (Auth::check() && $this->esFueraHorarioLaboral()) {
            return 'MEDIA';
        }

        // Métodos críticos
        if (in_array($request->method(), ['DELETE', 'PATCH'])) {
            return 'MEDIA';
        }

        return 'BAJA';
    }

    /**
     * Verificar si es actividad administrativa crítica
     */
    private function esActividadAdministrativaCritica(Request $request): bool
    {
        $rutasCriticas = [
            'admin/usuarios',
            'admin/configuracion',
            'admin/backups',
            'conductores/destroy',
            'validaciones/destroy',
            'turnos/destroy'
        ];

        $ruta = $request->path();

        foreach ($rutasCriticas as $rutaCritica) {
            if (str_contains($ruta, $rutaCritica)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener contexto adicional
     */
    private function obtenerContexto(Request $request): array
    {
        $contexto = [
            'timestamp' => now()->toISOString(),
            'session_id' => session()->getId(),
            'csrf_token' => $request->session()->token(),
            'locale' => app()->getLocale(),
            'timezone' => config('app.timezone')
        ];

        // Información del usuario si está autenticado
        if (Auth::check()) {
            $usuario = Auth::user();
            $contexto['usuario'] = [
                'id' => $usuario->id,
                'email' => $usuario->email,
                'roles' => $usuario->getRoleNames()->toArray(),
                'last_login' => $usuario->last_login_at?->toISOString()
            ];
        }

        // Información de la sesión
        $contexto['sesion'] = [
            'duracion_minutos' => session()->has('login_time') ?
                now()->diffInMinutes(session('login_time')) : null,
            'requests_count' => session('requests_count', 0) + 1
        ];

        // Actualizar contador de requests en sesión
        session(['requests_count' => $contexto['sesion']['requests_count']]);

        return $contexto;
    }

    /**
     * Verificar si es actividad sospechosa
     */
    private function esActividadSospechosa(Request $request, Response $response): bool
    {
        // Múltiples errores 401/403
        if (in_array($response->getStatusCode(), [401, 403])) {
            return true;
        }

        // Acceso a rutas administrativas sin autenticación
        if (!Auth::check() && $this->esAccesoAdministrativo($request)) {
            return true;
        }

        // Métodos no permitidos en ciertas rutas
        if ($response->getStatusCode() === 405) {
            return true;
        }

        // Requests con User-Agent sospechoso
        $userAgent = $request->userAgent();
        $agentsSospechosos = ['bot', 'crawler', 'scanner', 'exploit'];

        foreach ($agentsSospechosos as $agentSospechoso) {
            if (str_contains(strtolower($userAgent), $agentSospechoso)) {
                return true;
            }
        }

        // Actividad inusual fuera de horario
        if ($this->esFueraHorarioLaboral() && $request->method() !== 'GET') {
            return true;
        }

        return false;
    }

    /**
     * Obtener razón de sospecha
     */
    private function obtenerRazonSospecha(Request $request, Response $response): string
    {
        $razones = [];

        if (in_array($response->getStatusCode(), [401, 403])) {
            $razones[] = 'Error de autorización';
        }

        if (!Auth::check() && $this->esAccesoAdministrativo($request)) {
            $razones[] = 'Acceso administrativo sin autenticación';
        }

        if ($response->getStatusCode() === 405) {
            $razones[] = 'Método HTTP no permitido';
        }

        $userAgent = $request->userAgent();
        $agentsSospechosos = ['bot', 'crawler', 'scanner', 'exploit'];

        foreach ($agentsSospechosos as $agentSospechoso) {
            if (str_contains(strtolower($userAgent), $agentSospechoso)) {
                $razones[] = 'User-Agent sospechoso: ' . $agentSospechoso;
                break;
            }
        }

        if ($this->esFueraHorarioLaboral() && $request->method() !== 'GET') {
            $razones[] = 'Actividad de escritura fuera de horario laboral';
        }

        return implode(', ', $razones);
    }
}
