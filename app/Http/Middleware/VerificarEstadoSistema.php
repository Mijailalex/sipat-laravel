<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\NotificacionService;
use App\Models\Validacion;
use Symfony\Component\HttpFoundation\Response;
use Exception;

class VerificarEstadoSistema
{
    /**
     * Estados del sistema
     */
    private const ESTADO_OPERATIVO = 'OPERATIVO';
    private const ESTADO_MANTENIMIENTO = 'MANTENIMIENTO';
    private const ESTADO_ALERTA = 'ALERTA';
    private const ESTADO_CRITICO = 'CRITICO';
    private const ESTADO_ERROR = 'ERROR';

    /**
     * Rutas que están exentas de verificación
     */
    private const RUTAS_EXENTAS = [
        'api/health',
        'api/status',
        'admin/sistema/estado',
        'admin/mantenimiento',
        '_debugbar',
        'telescope',
        'horizon'
    ];

    /**
     * Rutas administrativas que pueden acceder durante mantenimiento
     */
    private const RUTAS_ADMIN_MANTENIMIENTO = [
        'admin/sistema',
        'admin/mantenimiento',
        'admin/logs',
        'login',
        'logout'
    ];

    /**
     * Umbrales del sistema
     */
    private const UMBRALES = [
        'memoria_critica' => 90,        // % de memoria
        'memoria_advertencia' => 80,    // % de memoria
        'cpu_critica' => 90,           // % de CPU
        'cpu_advertencia' => 80,       // % de CPU
        'disco_critico' => 95,         // % de disco
        'disco_advertencia' => 85,     // % de disco
        'validaciones_criticas' => 5,  // Número de validaciones críticas
        'tiempo_respuesta_bd' => 2000, // ms
        'conexiones_bd_maximas' => 100
    ];

    /**
     * Manejar una request entrante
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Verificar si la ruta está exenta
            if ($this->esRutaExenta($request)) {
                return $next($request);
            }

            // Obtener estado actual del sistema
            $estadoSistema = $this->obtenerEstadoSistema();

            // Verificar acceso según el estado
            $verificacionAcceso = $this->verificarAcceso($request, $estadoSistema);

            if (!$verificacionAcceso['permitido']) {
                return $this->respuestaAccesoDenegado($verificacionAcceso['razon'], $estadoSistema);
            }

            // Agregar headers de estado a la response
            $response = $next($request);

            $this->agregarHeadersEstado($response, $estadoSistema);

            // Verificar estado después de procesar la request
            $this->verificarEstadoPostProcesamiento($request, $response);

            return $response;

        } catch (Exception $e) {
            Log::error('Error en middleware VerificarEstadoSistema: ' . $e->getMessage());

            // En caso de error, permitir el acceso pero registrar el problema
            $response = $next($request);
            $this->agregarHeadersEstado($response, ['estado' => self::ESTADO_ERROR, 'mensaje' => 'Error en verificación']);

            return $response;
        }
    }

    /**
     * Verificar si la ruta está exenta de verificación
     */
    private function esRutaExenta(Request $request): bool
    {
        $ruta = $request->path();

        foreach (self::RUTAS_EXENTAS as $rutaExenta) {
            if (str_contains($ruta, $rutaExenta)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener el estado actual del sistema
     */
    private function obtenerEstadoSistema(): array
    {
        return Cache::remember('estado_sistema_completo', 30, function () {
            try {
                $estado = [
                    'estado' => self::ESTADO_OPERATIVO,
                    'mensaje' => 'Sistema funcionando normalmente',
                    'metricas' => [],
                    'alertas' => [],
                    'timestamp' => now()->toISOString()
                ];

                // Verificar estado de mantenimiento manual
                $mantenimientoProgramado = Cache::get('sipat_sistema_estado');
                if ($mantenimientoProgramado === 'MANTENIMIENTO') {
                    $estado['estado'] = self::ESTADO_MANTENIMIENTO;
                    $estado['mensaje'] = 'Sistema en mantenimiento programado';
                    return $estado;
                }

                // Verificar métricas del sistema
                $metricas = $this->verificarMetricasSistema();
                $estado['metricas'] = $metricas;

                // Determinar estado basado en métricas
                $estadoCalculado = $this->calcularEstadoDesdeMetricas($metricas);
                $estado['estado'] = $estadoCalculado['estado'];
                $estado['mensaje'] = $estadoCalculado['mensaje'];
                $estado['alertas'] = $estadoCalculado['alertas'];

                return $estado;

            } catch (Exception $e) {
                Log::error('Error obteniendo estado del sistema: ' . $e->getMessage());

                return [
                    'estado' => self::ESTADO_ERROR,
                    'mensaje' => 'Error al verificar estado del sistema',
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ];
            }
        });
    }

    /**
     * Verificar métricas del sistema
     */
    private function verificarMetricasSistema(): array
    {
        $metricas = [
            'memoria' => $this->verificarMemoria(),
            'base_datos' => $this->verificarBaseDatos(),
            'validaciones' => $this->verificarValidacionesCriticas(),
            'cache' => $this->verificarCache(),
            'disco' => $this->verificarEspacioDisco(),
            'carga_sistema' => $this->verificarCargaSistema()
        ];

        return $metricas;
    }

    /**
     * Verificar uso de memoria
     */
    private function verificarMemoria(): array
    {
        try {
            $memoriaActual = memory_get_usage(true);
            $memoriaPico = memory_get_peak_usage(true);
            $memoriaLimite = $this->obtenerLimiteMemoria();

            $porcentajeUso = $memoriaLimite > 0 ? ($memoriaActual / $memoriaLimite) * 100 : 0;
            $porcentajePico = $memoriaLimite > 0 ? ($memoriaPico / $memoriaLimite) * 100 : 0;

            $estado = 'OK';
            $mensaje = 'Uso de memoria normal';

            if ($porcentajeUso >= self::UMBRALES['memoria_critica']) {
                $estado = 'CRITICO';
                $mensaje = 'Uso de memoria crítico';
            } elseif ($porcentajeUso >= self::UMBRALES['memoria_advertencia']) {
                $estado = 'ADVERTENCIA';
                $mensaje = 'Uso de memoria alto';
            }

            return [
                'estado' => $estado,
                'mensaje' => $mensaje,
                'porcentaje_uso' => round($porcentajeUso, 2),
                'porcentaje_pico' => round($porcentajePico, 2),
                'memoria_actual_mb' => round($memoriaActual / 1024 / 1024, 2),
                'memoria_pico_mb' => round($memoriaPico / 1024 / 1024, 2),
                'memoria_limite_mb' => round($memoriaLimite / 1024 / 1024, 2)
            ];

        } catch (Exception $e) {
            return [
                'estado' => 'ERROR',
                'mensaje' => 'Error verificando memoria: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener límite de memoria configurado
     */
    private function obtenerLimiteMemoria(): int
    {
        $limite = ini_get('memory_limit');

        if ($limite === '-1') {
            return 2 * 1024 * 1024 * 1024; // 2GB por defecto si es ilimitado
        }

        $valor = (int) $limite;
        $unidad = strtolower(substr($limite, -1));

        return match($unidad) {
            'g' => $valor * 1024 * 1024 * 1024,
            'm' => $valor * 1024 * 1024,
            'k' => $valor * 1024,
            default => $valor
        };
    }

    /**
     * Verificar estado de la base de datos
     */
    private function verificarBaseDatos(): array
    {
        try {
            $tiempoInicio = microtime(true);

            // Verificar conexión
            DB::connection()->getPdo();

            // Realizar query de prueba
            $resultado = DB::select('SELECT 1 as test');

            $tiempoRespuesta = (microtime(true) - $tiempoInicio) * 1000; // en ms

            $estado = 'OK';
            $mensaje = 'Base de datos funcionando correctamente';

            if ($tiempoRespuesta > self::UMBRALES['tiempo_respuesta_bd']) {
                $estado = 'ADVERTENCIA';
                $mensaje = 'Base de datos responde lentamente';
            }

            // Verificar número de conexiones (si es MySQL)
            $conexiones = $this->verificarConexionesBD();

            return [
                'estado' => $estado,
                'mensaje' => $mensaje,
                'tiempo_respuesta_ms' => round($tiempoRespuesta, 2),
                'conexiones_activas' => $conexiones['activas'] ?? 0,
                'conexiones_maximas' => $conexiones['maximas'] ?? 0,
                'test_query' => !empty($resultado)
            ];

        } catch (Exception $e) {
            return [
                'estado' => 'CRITICO',
                'mensaje' => 'Error de conexión a base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verificar conexiones de base de datos
     */
    private function verificarConexionesBD(): array
    {
        try {
            // Solo para MySQL
            if (config('database.default') === 'mysql') {
                $conexiones = DB::select("SHOW STATUS WHERE Variable_name IN ('Threads_connected', 'Max_connections')");

                $activas = 0;
                $maximas = 0;

                foreach ($conexiones as $conexion) {
                    if ($conexion->Variable_name === 'Threads_connected') {
                        $activas = (int) $conexion->Value;
                    } elseif ($conexion->Variable_name === 'Max_connections') {
                        $maximas = (int) $conexion->Value;
                    }
                }

                return ['activas' => $activas, 'maximas' => $maximas];
            }

            return ['activas' => 1, 'maximas' => 100]; // Valores por defecto

        } catch (Exception $e) {
            return ['activas' => 0, 'maximas' => 0];
        }
    }

    /**
     * Verificar validaciones críticas
     */
    private function verificarValidacionesCriticas(): array
    {
        try {
            $validacionesCriticas = Validacion::where('severidad', 'CRITICA')
                ->where('estado', 'PENDIENTE')
                ->count();

            $validacionesAntiguas = Validacion::where('severidad', 'CRITICA')
                ->where('estado', 'PENDIENTE')
                ->where('created_at', '<', now()->subHours(24))
                ->count();

            $estado = 'OK';
            $mensaje = 'No hay validaciones críticas pendientes';

            if ($validacionesCriticas >= self::UMBRALES['validaciones_criticas']) {
                $estado = 'CRITICO';
                $mensaje = "Hay {$validacionesCriticas} validaciones críticas pendientes";
            } elseif ($validacionesCriticas > 0) {
                $estado = 'ADVERTENCIA';
                $mensaje = "Hay {$validacionesCriticas} validación(es) crítica(s) pendiente(s)";
            }

            return [
                'estado' => $estado,
                'mensaje' => $mensaje,
                'criticas_pendientes' => $validacionesCriticas,
                'criticas_antiguas' => $validacionesAntiguas
            ];

        } catch (Exception $e) {
            return [
                'estado' => 'ERROR',
                'mensaje' => 'Error verificando validaciones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verificar estado del cache
     */
    private function verificarCache(): array
    {
        try {
            $clave = 'test_cache_' . uniqid();
            $valor = 'test_value';

            $tiempoInicio = microtime(true);

            // Probar escritura
            Cache::put($clave, $valor, 60);

            // Probar lectura
            $valorRecuperado = Cache::get($clave);

            // Limpiar
            Cache::forget($clave);

            $tiempoRespuesta = (microtime(true) - $tiempoInicio) * 1000;

            $estado = $valorRecuperado === $valor ? 'OK' : 'ADVERTENCIA';
            $mensaje = $valorRecuperado === $valor ?
                'Cache funcionando correctamente' :
                'Cache no está funcionando correctamente';

            return [
                'estado' => $estado,
                'mensaje' => $mensaje,
                'tiempo_respuesta_ms' => round($tiempoRespuesta, 2),
                'driver' => config('cache.default'),
                'funcional' => $valorRecuperado === $valor
            ];

        } catch (Exception $e) {
            return [
                'estado' => 'ERROR',
                'mensaje' => 'Error verificando cache: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verificar espacio en disco
     */
    private function verificarEspacioDisco(): array
    {
        try {
            $rutaStorage = storage_path();

            $espacioTotal = disk_total_space($rutaStorage);
            $espacioLibre = disk_free_space($rutaStorage);
            $espacioUsado = $espacioTotal - $espacioLibre;

            $porcentajeUso = $espacioTotal > 0 ? ($espacioUsado / $espacioTotal) * 100 : 0;

            $estado = 'OK';
            $mensaje = 'Espacio en disco suficiente';

            if ($porcentajeUso >= self::UMBRALES['disco_critico']) {
                $estado = 'CRITICO';
                $mensaje = 'Espacio en disco crítico';
            } elseif ($porcentajeUso >= self::UMBRALES['disco_advertencia']) {
                $estado = 'ADVERTENCIA';
                $mensaje = 'Espacio en disco bajo';
            }

            return [
                'estado' => $estado,
                'mensaje' => $mensaje,
                'porcentaje_uso' => round($porcentajeUso, 2),
                'espacio_total_gb' => round($espacioTotal / 1024 / 1024 / 1024, 2),
                'espacio_libre_gb' => round($espacioLibre / 1024 / 1024 / 1024, 2),
                'espacio_usado_gb' => round($espacioUsado / 1024 / 1024 / 1024, 2)
            ];

        } catch (Exception $e) {
            return [
                'estado' => 'ERROR',
                'mensaje' => 'Error verificando espacio en disco: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verificar carga del sistema
     */
    private function verificarCargaSistema(): array
    {
        try {
            // Obtener load average en sistemas Unix
            $loadAverage = null;
            if (function_exists('sys_getloadavg')) {
                $loadAverage = sys_getloadavg();
            }

            // Número de CPUs
            $numCpus = $this->obtenerNumeroCpus();

            $estado = 'OK';
            $mensaje = 'Carga del sistema normal';

            if ($loadAverage && $numCpus > 0) {
                $cargaPorcentaje = ($loadAverage[0] / $numCpus) * 100;

                if ($cargaPorcentaje >= self::UMBRALES['cpu_critica']) {
                    $estado = 'CRITICO';
                    $mensaje = 'Carga del sistema crítica';
                } elseif ($cargaPorcentaje >= self::UMBRALES['cpu_advertencia']) {
                    $estado = 'ADVERTENCIA';
                    $mensaje = 'Carga del sistema alta';
                }

                return [
                    'estado' => $estado,
                    'mensaje' => $mensaje,
                    'load_average' => $loadAverage,
                    'num_cpus' => $numCpus,
                    'carga_porcentaje' => round($cargaPorcentaje, 2)
                ];
            }

            return [
                'estado' => 'OK',
                'mensaje' => 'Información de carga no disponible',
                'disponible' => false
            ];

        } catch (Exception $e) {
            return [
                'estado' => 'ERROR',
                'mensaje' => 'Error verificando carga del sistema: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener número de CPUs
     */
    private function obtenerNumeroCpus(): int
    {
        try {
            if (PHP_OS_FAMILY === 'Linux') {
                $cpuInfo = file_get_contents('/proc/cpuinfo');
                return substr_count($cpuInfo, 'processor');
            }

            // Para otros sistemas, asumir 1 CPU
            return 1;

        } catch (Exception $e) {
            return 1;
        }
    }

    /**
     * Calcular estado del sistema desde métricas
     */
    private function calcularEstadoDesdeMetricas(array $metricas): array
    {
        $estadosCriticos = [];
        $estadosAdvertencia = [];
        $alertas = [];

        foreach ($metricas as $componente => $metrica) {
            switch ($metrica['estado'] ?? 'OK') {
                case 'CRITICO':
                    $estadosCriticos[] = $componente;
                    $alertas[] = [
                        'componente' => $componente,
                        'tipo' => 'CRITICO',
                        'mensaje' => $metrica['mensaje'] ?? 'Estado crítico'
                    ];
                    break;

                case 'ADVERTENCIA':
                    $estadosAdvertencia[] = $componente;
                    $alertas[] = [
                        'componente' => $componente,
                        'tipo' => 'ADVERTENCIA',
                        'mensaje' => $metrica['mensaje'] ?? 'Estado de advertencia'
                    ];
                    break;

                case 'ERROR':
                    $estadosAdvertencia[] = $componente;
                    $alertas[] = [
                        'componente' => $componente,
                        'tipo' => 'ERROR',
                        'mensaje' => $metrica['mensaje'] ?? 'Error en componente'
                    ];
                    break;
            }
        }

        // Determinar estado general
        if (!empty($estadosCriticos)) {
            return [
                'estado' => self::ESTADO_CRITICO,
                'mensaje' => 'Sistema en estado crítico: ' . implode(', ', $estadosCriticos),
                'alertas' => $alertas
            ];
        }

        if (!empty($estadosAdvertencia)) {
            return [
                'estado' => self::ESTADO_ALERTA,
                'mensaje' => 'Sistema con alertas: ' . implode(', ', $estadosAdvertencia),
                'alertas' => $alertas
            ];
        }

        return [
            'estado' => self::ESTADO_OPERATIVO,
            'mensaje' => 'Sistema funcionando normalmente',
            'alertas' => []
        ];
    }

    /**
     * Verificar acceso según el estado del sistema
     */
    private function verificarAcceso(Request $request, array $estadoSistema): array
    {
        $estado = $estadoSistema['estado'];

        // Durante mantenimiento, solo admins pueden acceder a rutas específicas
        if ($estado === self::ESTADO_MANTENIMIENTO) {
            if ($this->puedeAccederDuranteMantenimiento($request)) {
                return ['permitido' => true];
            }

            return [
                'permitido' => false,
                'razon' => 'Sistema en mantenimiento programado'
            ];
        }

        // Durante estado crítico, limitar acceso a operaciones de solo lectura
        if ($estado === self::ESTADO_CRITICO) {
            if ($this->puedeAccederEnEstadoCritico($request)) {
                return ['permitido' => true];
            }

            return [
                'permitido' => false,
                'razon' => 'Sistema en estado crítico - solo operaciones de lectura permitidas'
            ];
        }

        // Durante alertas, permitir acceso pero mostrar advertencias
        return ['permitido' => true];
    }

    /**
     * Verificar si puede acceder durante mantenimiento
     */
    private function puedeAccederDuranteMantenimiento(Request $request): bool
    {
        // Verificar si es una ruta administrativa permitida
        $ruta = $request->path();

        foreach (self::RUTAS_ADMIN_MANTENIMIENTO as $rutaPermitida) {
            if (str_contains($ruta, $rutaPermitida)) {
                return true;
            }
        }

        // Verificar si es administrador
        if (Auth::check() && Auth::user()->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Verificar si puede acceder en estado crítico
     */
    private function puedeAccederEnEstadoCritico(Request $request): bool
    {
        // Solo operaciones de lectura (GET) y rutas administrativas
        if ($request->method() === 'GET') {
            return true;
        }

        // Administradores pueden realizar operaciones críticas
        if (Auth::check() && Auth::user()->hasRole('admin')) {
            return true;
        }

        // Rutas de emergencia
        $rutasEmergencia = ['admin/sistema', 'admin/mantenimiento', 'logout'];
        $ruta = $request->path();

        foreach ($rutasEmergencia as $rutaEmergencia) {
            if (str_contains($ruta, $rutaEmergencia)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generar respuesta de acceso denegado
     */
    private function respuestaAccesoDenegado(string $razon, array $estadoSistema): Response
    {
        $datos = [
            'error' => 'Acceso no permitido',
            'razon' => $razon,
            'estado_sistema' => $estadoSistema['estado'],
            'mensaje' => $estadoSistema['mensaje'],
            'timestamp' => now()->toISOString()
        ];

        // Para requests AJAX/API, devolver JSON
        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json($datos, 503);
        }

        // Para requests web, mostrar página de mantenimiento
        return response()->view('errors.maintenance', $datos, 503);
    }

    /**
     * Agregar headers de estado a la response
     */
    private function agregarHeadersEstado(Response $response, array $estadoSistema): void
    {
        $response->headers->set('X-System-Status', $estadoSistema['estado']);
        $response->headers->set('X-System-Message', $estadoSistema['mensaje']);
        $response->headers->set('X-System-Check-Time', $estadoSistema['timestamp'] ?? now()->toISOString());

        // Agregar alertas si existen
        if (!empty($estadoSistema['alertas'])) {
            $response->headers->set('X-System-Alerts', count($estadoSistema['alertas']));
        }
    }

    /**
     * Verificar estado después del procesamiento
     */
    private function verificarEstadoPostProcesamiento(Request $request, Response $response): void
    {
        try {
            // Verificar tiempo de respuesta excesivo
            $tiempoInicio = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
            $tiempoRespuesta = (microtime(true) - $tiempoInicio) * 1000;

            if ($tiempoRespuesta > 5000) { // > 5 segundos
                Log::warning('Tiempo de respuesta excesivo detectado', [
                    'url' => $request->fullUrl(),
                    'metodo' => $request->method(),
                    'tiempo_ms' => $tiempoRespuesta,
                    'codigo_estado' => $response->getStatusCode()
                ]);

                // Notificar si es crítico (> 10 segundos)
                if ($tiempoRespuesta > 10000) {
                    app(NotificacionService::class)->enviarNotificacion(
                        'SISTEMA_ALERTA',
                        'Tiempo de Respuesta Crítico',
                        "Request con tiempo de respuesta crítico: {$tiempoRespuesta}ms en {$request->fullUrl()}",
                        [
                            'url' => $request->fullUrl(),
                            'tiempo_ms' => $tiempoRespuesta,
                            'metodo' => $request->method()
                        ],
                        null,
                        'CRITICA'
                    );
                }
            }

            // Verificar uso de memoria post-request
            $memoriaActual = memory_get_usage(true);
            $memoriaLimite = $this->obtenerLimiteMemoria();
            $porcentajeMemoria = ($memoriaActual / $memoriaLimite) * 100;

            if ($porcentajeMemoria > 90) {
                Log::warning('Uso crítico de memoria detectado post-request', [
                    'url' => $request->fullUrl(),
                    'memoria_mb' => round($memoriaActual / 1024 / 1024, 2),
                    'porcentaje' => round($porcentajeMemoria, 2)
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error en verificación post-procesamiento: ' . $e->getMessage());
        }
    }
}
