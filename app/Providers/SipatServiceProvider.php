<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Scheduling\Schedule;
use App\Services\ServicioPlanificacionAutomatizada;
use App\Services\ServicioBackupAutomatizado;
use App\Services\CacheMetricasService;
use App\Services\NotificacionService;
use App\Services\AuditoriaService;
use App\Models\Parametro;
use App\Models\User;
use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\Turno;
use App\Models\HistorialPlanificacion;
use App\View\Composers\DashboardComposer;
use App\View\Composers\MenuComposer;
use App\Observers\ConductorObserver;
use App\Observers\ValidacionObserver;
use App\Observers\TurnoObserver;
use App\Http\Middleware\LogActividades;
use App\Http\Middleware\VerificarEstadoSistema;

class SipatServiceProvider extends ServiceProvider
{
    /**
     * Todos los singletons del contenedor de servicios
     */
    public array $singletons = [
        ServicioPlanificacionAutomatizada::class => ServicioPlanificacionAutomatizada::class,
        ServicioBackupAutomatizado::class => ServicioBackupAutomatizado::class,
        CacheMetricasService::class => CacheMetricasService::class,
        NotificacionService::class => NotificacionService::class,
        AuditoriaService::class => AuditoriaService::class,
    ];

    /**
     * Registrar cualquier servicio de aplicación
     */
    public function register(): void
    {
        // Registrar servicios principales como singletons
        $this->app->singleton(ServicioPlanificacionAutomatizada::class, function ($app) {
            return new ServicioPlanificacionAutomatizada();
        });

        $this->app->singleton(ServicioBackupAutomatizado::class, function ($app) {
            return new ServicioBackupAutomatizado();
        });

        $this->app->singleton(CacheMetricasService::class, function ($app) {
            return new CacheMetricasService();
        });

        $this->app->singleton(NotificacionService::class, function ($app) {
            return new NotificacionService();
        });

        $this->app->singleton(AuditoriaService::class, function ($app) {
            return new AuditoriaService();
        });

        // Registrar aliases para facilitar el acceso
        $this->app->alias(ServicioPlanificacionAutomatizada::class, 'sipat.planificacion');
        $this->app->alias(ServicioBackupAutomatizado::class, 'sipat.backup');
        $this->app->alias(CacheMetricasService::class, 'sipat.cache');
        $this->app->alias(NotificacionService::class, 'sipat.notificaciones');
        $this->app->alias(AuditoriaService::class, 'sipat.auditoria');

        // Registrar configuraciones personalizadas
        $this->registerCustomConfigurations();

        // Registrar helpers personalizados
        $this->registerHelpers();

        // Registrar macros personalizados
        $this->registerMacros();
    }

    /**
     * Inicializar cualquier servicio de aplicación
     */
    public function boot(): void
    {
        // Inicializar configuración del sistema
        $this->initializeSystemConfiguration();

        // Registrar view composers
        $this->registerViewComposers();

        // Registrar directivas de Blade personalizadas
        $this->registerBladeDirectives();

        // Registrar gates de autorización
        $this->registerAuthorizationGates();

        // Configurar validadores personalizados
        $this->registerCustomValidators();

        // Configurar observadores de modelos
        $this->registerModelObservers();

        // Configurar eventos del sistema
        $this->registerSystemEvents();

        // Inicializar monitoreo del sistema
        $this->initializeSystemMonitoring();

        // Configurar logs personalizados
        $this->configureCustomLogs();

        // Registrar middleware personalizado
        $this->registerCustomMiddleware();

        // Registrar comandos de consola personalizados
        $this->registerConsoleCommands();

        // Configurar tareas programadas
        $this->configureScheduledTasks();

        // Registrar macros después de que Laravel esté completamente cargado
        $this->app->booted(function () {
            $this->registerPaginationMacros();
        });
    }

    /**
     * Registrar configuraciones personalizadas del sistema
     */
    private function registerCustomConfigurations(): void
    {
        // Configuración de SIPAT desde la base de datos
        $this->app->singleton('sipat.config', function ($app) {
            try {
                if (\Schema::hasTable('parametros')) {
                    return \Cache::remember('sipat_config', 3600, function () {
                        return Parametro::all()->pluck('valor', 'clave')->toArray();
                    });
                }
            } catch (\Exception $e) {
                Log::warning('No se pudo cargar configuración de SIPAT desde BD: ' . $e->getMessage());
            }

            return [];
        });

        // Configuración de servicios SIPAT
        config([
            'sipat.version' => '1.0.0',
            'sipat.name' => 'Sistema Integral de Planificación Automatizada de Transportes',
            'sipat.short_name' => 'SIPAT',
            'sipat.timezone' => 'America/Lima',
            'sipat.locale' => 'es',
            'sipat.pagination' => 20,
            'sipat.max_upload_size' => '10MB',
            'sipat.backup_retention_days' => 90,
            'sipat.log_retention_days' => 30,
            'sipat.session_timeout' => 720, // 12 horas
            'sipat.password_min_length' => 8,
            'sipat.max_login_attempts' => 5,
            'sipat.lockout_duration' => 15, // minutos

            // Configuraciones específicas de planificación
            'sipat.planificacion.dias_maximos_sin_descanso' => 6,
            'sipat.planificacion.horas_minimas_descanso' => 12,
            'sipat.planificacion.eficiencia_minima' => 80,
            'sipat.planificacion.puntualidad_minima' => 85,
            'sipat.planificacion.rutas_cortas_maximas_semanales' => 4,
            'sipat.planificacion.rutas_largas_minimas_semanales' => 8,

            // Configuraciones de monitoreo
            'sipat.monitoreo.intervalo_validaciones' => 60, // minutos
            'sipat.monitoreo.tiempo_limite_respuesta_critica' => 30, // minutos
            'sipat.monitoreo.umbral_cpu' => 80, // porcentaje
            'sipat.monitoreo.umbral_memoria' => 85, // porcentaje

            // Configuraciones de notificaciones
            'sipat.notificaciones.email_admin' => env('SIPAT_ADMIN_EMAIL', 'admin@sipat.com'),
            'sipat.notificaciones.canales_habilitados' => ['mail', 'database'],
            'sipat.notificaciones.retencion_dias' => 30,
        ]);
    }

    /**
     * Registrar helpers personalizados
     */
    private function registerHelpers(): void
    {
        // Helper para obtener configuración de SIPAT
        if (!function_exists('sipat_config')) {
            function sipat_config($key, $default = null) {
                try {
                    $config = app('sipat.config');
                    return data_get($config, $key, $default);
                } catch (\Exception $e) {
                    return $default;
                }
            }
        }

        // Helper para formatear fechas en español
        if (!function_exists('fecha_es')) {
            function fecha_es($fecha, $formato = 'd/m/Y H:i') {
                if (!$fecha) return '';

                try {
                    $carbon = $fecha instanceof \Carbon\Carbon ?
                        $fecha : \Carbon\Carbon::parse($fecha);

                    return $carbon->locale('es')->isoFormat($this->convertirFormatoFecha($formato));
                } catch (\Exception $e) {
                    return $fecha;
                }
            }
        }

        // Helper para estados con iconos
        if (!function_exists('estado_icono')) {
            function estado_icono($estado) {
                return match(strtoupper($estado)) {
                    'DISPONIBLE' => '<i class="fas fa-check-circle text-success"></i>',
                    'OCUPADO' => '<i class="fas fa-clock text-warning"></i>',
                    'DESCANSO' => '<i class="fas fa-bed text-info"></i>',
                    'INACTIVO' => '<i class="fas fa-times-circle text-danger"></i>',
                    'MANTENIMIENTO' => '<i class="fas fa-tools text-secondary"></i>',
                    'PENDIENTE' => '<i class="fas fa-hourglass-half text-warning"></i>',
                    'APROBADO' => '<i class="fas fa-check text-success"></i>',
                    'RECHAZADO' => '<i class="fas fa-times text-danger"></i>',
                    'CRITICA' => '<i class="fas fa-exclamation-triangle text-danger"></i>',
                    'ADVERTENCIA' => '<i class="fas fa-exclamation-circle text-warning"></i>',
                    'INFO' => '<i class="fas fa-info-circle text-info"></i>',
                    default => '<i class="fas fa-question-circle text-muted"></i>'
                };
            }
        }

        // Helper para formatear tamaño de archivos
        if (!function_exists('formatear_bytes')) {
            function formatear_bytes($bytes, $precision = 2) {
                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $bytes = max($bytes, 0);
                $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
                $pow = min($pow, count($units) - 1);

                $bytes /= pow(1024, $pow);

                return round($bytes, $precision) . ' ' . $units[$pow];
            }
        }

        // Helper para verificar permisos de usuario
        if (!function_exists('usuario_puede')) {
            function usuario_puede($permiso, $usuario = null) {
                $usuario = $usuario ?: auth()->user();
                if (!$usuario) return false;

                return $usuario->can($permiso);
            }
        }

        // Helper para obtener métricas en tiempo real
        if (!function_exists('metrica_tiempo_real')) {
            function metrica_tiempo_real($metrica, $parametros = []) {
                try {
                    $cacheService = app(CacheMetricasService::class);
                    return $cacheService->obtenerMetrica($metrica, $parametros);
                } catch (\Exception $e) {
                    Log::warning("Error obteniendo métrica {$metrica}: " . $e->getMessage());
                    return null;
                }
            }
        }

        // Helper para validar horarios de trabajo
        if (!function_exists('validar_horario_trabajo')) {
            function validar_horario_trabajo($horaInicio, $horaFin, $fechaTrabajo = null) {
                try {
                    $inicio = \Carbon\Carbon::parse($horaInicio);
                    $fin = \Carbon\Carbon::parse($horaFin);
                    $fecha = $fechaTrabajo ? \Carbon\Carbon::parse($fechaTrabajo) : now();

                    // Validar que sea día laborable (lunes a domingo permitido)
                    $esFinDeSemana = $fecha->isWeekend();

                    // Validar duración mínima y máxima del turno
                    $duracionHoras = $fin->diffInHours($inicio);
                    $duracionValida = $duracionHoras >= 4 && $duracionHoras <= 12;

                    return [
                        'valido' => $duracionValida && $fin->gt($inicio),
                        'duracion_horas' => $duracionHoras,
                        'es_fin_semana' => $esFinDeSemana,
                        'warnings' => []
                    ];
                } catch (\Exception $e) {
                    return [
                        'valido' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
    }

    /**
     * Convertir formato de fecha de PHP a formato ICU
     */
    private function convertirFormatoFecha($formato)
    {
        $conversiones = [
            'd/m/Y' => 'DD/MM/YYYY',
            'd/m/Y H:i' => 'DD/MM/YYYY HH:mm',
            'Y-m-d' => 'YYYY-MM-DD',
            'Y-m-d H:i:s' => 'YYYY-MM-DD HH:mm:ss',
        ];

        return $conversiones[$formato] ?? $formato;
    }

    /**
     * Registrar macros personalizados
     */
    private function registerMacros(): void
    {
        // Macro para respuesta JSON estándar de SIPAT
        \Response::macro('sipatJson', function ($data = null, $message = null, $status = 200) {
            return response()->json([
                'success' => $status >= 200 && $status < 300,
                'data' => $data,
                'message' => $message,
                'timestamp' => now()->toISOString(),
                'version' => config('sipat.version'),
                'sistema' => config('sipat.short_name')
            ], $status);
        });

        // Macro para respuesta de error estandarizada
        \Response::macro('sipatError', function ($message, $errors = null, $status = 400) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
                'timestamp' => now()->toISOString(),
                'version' => config('sipat.version')
            ], $status);
        });

        // Macro para colección con metadatos
        \Illuminate\Database\Eloquent\Collection::macro('conMetadatos', function ($metadatos = []) {
            return [
                'data' => $this->toArray(),
                'meta' => array_merge([
                    'total' => $this->count(),
                    'timestamp' => now()->toISOString()
                ], $metadatos)
            ];
        });
    }

    /**
     * Registrar macros de paginación
     */
    private function registerPaginationMacros(): void
    {
        try {
            if (class_exists('\Illuminate\Pagination\LengthAwarePaginator') &&
                method_exists('\Illuminate\Pagination\LengthAwarePaginator', 'macro')) {

                \Illuminate\Pagination\LengthAwarePaginator::macro('toSipatArray', function () {
                    return [
                        'data' => $this->items(),
                        'pagination' => [
                            'current_page' => $this->currentPage(),
                            'from' => $this->firstItem(),
                            'last_page' => $this->lastPage(),
                            'per_page' => $this->perPage(),
                            'to' => $this->lastItem(),
                            'total' => $this->total(),
                            'has_more_pages' => $this->hasMorePages(),
                        ],
                        'links' => [
                            'first' => $this->url(1),
                            'last' => $this->url($this->lastPage()),
                            'prev' => $this->previousPageUrl(),
                            'next' => $this->nextPageUrl(),
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'sistema' => config('sipat.short_name')
                        ]
                    ];
                });
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo registrar macro de paginación: ' . $e->getMessage());
        }
    }

    /**
     * Inicializar configuración del sistema desde base de datos
     */
    private function initializeSystemConfiguration(): void
    {
        try {
            if (\Schema::hasTable('parametros')) {
                // Cargar configuraciones críticas
                $configuraciones = Parametro::whereIn('clave', [
                    'zona_horaria',
                    'items_por_pagina',
                    'nombre_empresa',
                    'dias_maximos_sin_descanso',
                    'eficiencia_minima_conductor',
                    'puntualidad_minima_conductor'
                ])->get()->pluck('valor', 'clave');

                // Aplicar configuraciones dinámicamente
                if ($configuraciones->has('zona_horaria')) {
                    config(['app.timezone' => $configuraciones->get('zona_horaria')]);
                    date_default_timezone_set($configuraciones->get('zona_horaria'));
                }

                if ($configuraciones->has('items_por_pagina')) {
                    config(['sipat.pagination' => (int) $configuraciones->get('items_por_pagina')]);
                }

                // Almacenar configuraciones en cache para acceso rápido
                Cache::put('sipat_runtime_config', $configuraciones->toArray(), 3600);
            }
        } catch (\Exception $e) {
            Log::warning('No se pudo inicializar configuración del sistema: ' . $e->getMessage());
        }
    }

    /**
     * Registrar view composers
     */
    private function registerViewComposers(): void
    {
        // Composer para el dashboard con métricas
        View::composer('dashboard.*', function ($view) {
            try {
                $metricas = app(CacheMetricasService::class)->obtenerMetricasDashboard();
                $view->with('metricas_dashboard', $metricas);
            } catch (\Exception $e) {
                Log::warning('Error cargando métricas para dashboard: ' . $e->getMessage());
                $view->with('metricas_dashboard', []);
            }
        });

        // Composer para el menú de navegación
        View::composer('layouts.app', function ($view) {
            try {
                $notificacionesPendientes = auth()->check() ?
                    app(NotificacionService::class)->contarPendientes(auth()->id()) : 0;

                $view->with([
                    'notificaciones_pendientes' => $notificacionesPendientes,
                    'menu_items' => $this->obtenerItemsMenu()
                ]);
            } catch (\Exception $e) {
                Log::warning('Error cargando datos del menú: ' . $e->getMessage());
            }
        });

        // Composer global con variables del sistema
        View::composer('*', function ($view) {
            $view->with([
                'sipat_version' => config('sipat.version'),
                'sipat_name' => config('sipat.name'),
                'sipat_short_name' => config('sipat.short_name'),
                'usuario_actual' => auth()->user(),
                'es_admin' => auth()->check() && auth()->user()->hasRole('admin'),
                'configuracion_sistema' => app('sipat.config'),
                'zona_horaria_sistema' => config('sipat.timezone')
            ]);
        });

        // Composer para notificaciones en tiempo real
        View::composer(['layouts.app', 'dashboard.*'], function ($view) {
            if (auth()->check()) {
                try {
                    $notificaciones = app(NotificacionService::class)
                        ->obtenerRecientes(auth()->id(), 5);

                    $view->with('notificaciones_recientes', $notificaciones);
                } catch (\Exception $e) {
                    Log::warning('Error cargando notificaciones: ' . $e->getMessage());
                    $view->with('notificaciones_recientes', collect([]));
                }
            }
        });
    }

    /**
     * Obtener items del menú según permisos del usuario
     */
    private function obtenerItemsMenu(): array
    {
        if (!auth()->check()) {
            return [];
        }

        $usuario = auth()->user();
        $items = [];

        // Dashboard siempre visible para usuarios autenticados
        $items[] = [
            'title' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'fas fa-tachometer-alt'
        ];

        // Módulo de conductores
        if ($usuario->can('ver_conductores')) {
            $items[] = [
                'title' => 'Conductores',
                'route' => 'conductores.index',
                'icon' => 'fas fa-users',
                'badge' => metrica_tiempo_real('conductores_disponibles')
            ];
        }

        // Módulo de planificación
        if ($usuario->can('gestionar_planificacion')) {
            $items[] = [
                'title' => 'Planificación',
                'icon' => 'fas fa-calendar-alt',
                'children' => [
                    ['title' => 'Turnos', 'route' => 'turnos.index'],
                    ['title' => 'Rutas Cortas', 'route' => 'rutas-cortas.index'],
                    ['title' => 'Asignaciones', 'route' => 'asignaciones.index']
                ]
            ];
        }

        // Módulo de validaciones
        if ($usuario->can('ver_validaciones')) {
            $validacionesPendientes = metrica_tiempo_real('validaciones_pendientes');
            $items[] = [
                'title' => 'Validaciones',
                'route' => 'validaciones.index',
                'icon' => 'fas fa-clipboard-check',
                'badge' => $validacionesPendientes > 0 ? $validacionesPendientes : null,
                'badge_class' => $validacionesPendientes > 0 ? 'bg-warning' : ''
            ];
        }

        // Módulo de reportes
        if ($usuario->can('ver_reportes')) {
            $items[] = [
                'title' => 'Reportes',
                'icon' => 'fas fa-chart-bar',
                'children' => [
                    ['title' => 'Eficiencia', 'route' => 'reportes.eficiencia'],
                    ['title' => 'Turnos', 'route' => 'reportes.turnos'],
                    ['title' => 'Validaciones', 'route' => 'reportes.validaciones']
                ]
            ];
        }

        // Módulo de administración
        if ($usuario->can('administrar_sistema')) {
            $items[] = [
                'title' => 'Administración',
                'icon' => 'fas fa-cogs',
                'children' => [
                    ['title' => 'Usuarios', 'route' => 'admin.usuarios.index'],
                    ['title' => 'Configuración', 'route' => 'admin.configuracion.index'],
                    ['title' => 'Logs', 'route' => 'admin.logs.index'],
                    ['title' => 'Backups', 'route' => 'admin.backups.index']
                ]
            ];
        }

        return $items;
    }

    /**
     * Registrar directivas de Blade personalizadas
     */
    private function registerBladeDirectives(): void
    {
        // Directiva para mostrar estados con iconos
        Blade::directive('estado', function ($expression) {
            return "<?php echo estado_icono($expression); ?>";
        });

        // Directiva para formatear fechas en español
        Blade::directive('fechaEs', function ($expression) {
            return "<?php echo fecha_es($expression); ?>";
        });

        // Directiva para verificar permisos
        Blade::directive('permiso', function ($expression) {
            return "<?php if(usuario_puede($expression)): ?>";
        });

        Blade::directive('endpermiso', function () {
            return "<?php endif; ?>";
        });

        // Directiva para mostrar métricas en tiempo real
        Blade::directive('metrica', function ($expression) {
            return "<?php echo metrica_tiempo_real($expression) ?? 'N/A'; ?>";
        });

        // Directiva para formatear tamaños de archivo
        Blade::directive('bytes', function ($expression) {
            return "<?php echo formatear_bytes($expression); ?>";
        });

        // Directiva condicional para roles
        Blade::directive('rol', function ($expression) {
            return "<?php if(auth()->check() && auth()->user()->hasRole($expression)): ?>";
        });

        Blade::directive('endrol', function () {
            return "<?php endif; ?>";
        });

        // Directiva para alertas de validaciones críticas
        Blade::directive('alertaCritica', function ($expression) {
            return "<?php if(metrica_tiempo_real('validaciones_criticas') > 0): ?>
                        <div class='alert alert-danger'>
                            <i class='fas fa-exclamation-triangle'></i>
                            Hay validaciones críticas pendientes
                        </div>
                    <?php endif; ?>";
        });
    }

    /**
     * Registrar gates de autorización
     */
    private function registerAuthorizationGates(): void
    {
        // Gate para verificar si puede gestionar conductores
        Gate::define('gestionar_conductores', function (User $user) {
            return $user->hasRole(['admin', 'supervisor', 'planificador']);
        });

        // Gate para verificar si puede ver métricas avanzadas
        Gate::define('ver_metricas_avanzadas', function (User $user) {
            return $user->hasRole(['admin', 'supervisor']);
        });

        // Gate para verificar si puede gestionar planificación
        Gate::define('gestionar_planificacion', function (User $user) {
            return $user->hasRole(['admin', 'planificador']);
        });

        // Gate para validaciones críticas
        Gate::define('resolver_validaciones_criticas', function (User $user) {
            return $user->hasRole(['admin', 'supervisor']);
        });

        // Gate para administración del sistema
        Gate::define('administrar_sistema', function (User $user) {
            return $user->hasRole('admin');
        });

        // Gate para acceso a backups
        Gate::define('gestionar_backups', function (User $user) {
            return $user->hasRole('admin');
        });

        // Gate para ver logs del sistema
        Gate::define('ver_logs_sistema', function (User $user) {
            return $user->hasRole(['admin', 'supervisor']);
        });

        // Gate dinámico para verificar límites operacionales
        Gate::define('exceder_limites_planificacion', function (User $user, $tipo_limite) {
            if (!$user->hasRole(['admin', 'supervisor'])) {
                return false;
            }

            // Verificar límites específicos según el tipo
            return match($tipo_limite) {
                'horas_extras' => $user->hasRole('admin'),
                'descansos_minimos' => $user->hasRole(['admin', 'supervisor']),
                'rutas_adicionales' => true,
                default => false
            };
        });

        // Gate para operaciones de emergencia
        Gate::define('operaciones_emergencia', function (User $user) {
            return $user->hasRole('admin') &&
                   sipat_config('permitir_operaciones_emergencia', false);
        });
    }

    /**
     * Registrar validadores personalizados
     */
    private function registerCustomValidators(): void
    {
        // Validador para horarios de trabajo
        Validator::extend('horario_trabajo_valido', function ($attribute, $value, $parameters, $validator) {
            $data = $validator->getData();

            if (!isset($data['hora_fin'])) {
                return false;
            }

            $resultado = validar_horario_trabajo($value, $data['hora_fin']);
            return $resultado['valido'];
        });

        // Validador para disponibilidad de conductor
        Validator::extend('conductor_disponible', function ($attribute, $value, $parameters, $validator) {
            try {
                $conductor = Conductor::find($value);
                if (!$conductor) {
                    return false;
                }

                $data = $validator->getData();
                $fechaInicio = $data['fecha_inicio'] ?? now();
                $fechaFin = $data['fecha_fin'] ?? now();

                return $conductor->estaDisponibleEntre($fechaInicio, $fechaFin);
            } catch (\Exception $e) {
                Log::warning("Error validando disponibilidad de conductor: " . $e->getMessage());
                return false;
            }
        });

        // Validador para límites de rutas por conductor
        Validator::extend('limite_rutas_conductor', function ($attribute, $value, $parameters, $validator) {
            try {
                $conductor = Conductor::find($value);
                if (!$conductor) {
                    return false;
                }

                $limite = sipat_config('rutas_cortas_maximas_semanales', 4);
                $rutasEstaSemanana = $conductor->rutasEstaSemanana()->count();

                return $rutasEstaSemanana < $limite;
            } catch (\Exception $e) {
                return false;
            }
        });

        // Validador para eficiencia mínima
        Validator::extend('eficiencia_minima', function ($attribute, $value, $parameters, $validator) {
            $minima = sipat_config('eficiencia_minima_conductor', 80);
            return $value >= $minima;
        });

        // Validador para código de conductor único
        Validator::extend('codigo_conductor_unico', function ($attribute, $value, $parameters, $validator) {
            $conductorId = $parameters[0] ?? null;

            $query = Conductor::where('codigo', $value);

            if ($conductorId) {
                $query->where('id', '!=', $conductorId);
            }

            return !$query->exists();
        });

        // Mensajes de validación personalizados
        Validator::replacer('horario_trabajo_valido', function ($message, $attribute, $rule, $parameters) {
            return 'El horario de trabajo no es válido. Debe tener entre 4 y 12 horas de duración.';
        });

        Validator::replacer('conductor_disponible', function ($message, $attribute, $rule, $parameters) {
            return 'El conductor seleccionado no está disponible en el horario indicado.';
        });

        Validator::replacer('limite_rutas_conductor', function ($message, $attribute, $rule, $parameters) {
            $limite = sipat_config('rutas_cortas_maximas_semanales', 4);
            return "El conductor ya ha alcanzado el límite de {$limite} rutas cortas esta semana.";
        });

        Validator::replacer('eficiencia_minima', function ($message, $attribute, $rule, $parameters) {
            $minima = sipat_config('eficiencia_minima_conductor', 80);
            return "La eficiencia debe ser al menos {$minima}%.";
        });

        Validator::replacer('codigo_conductor_unico', function ($message, $attribute, $rule, $parameters) {
            return 'El código de conductor ya está en uso.';
        });
    }

    /**
     * Registrar observadores de modelos
     */
    private function registerModelObservers(): void
    {
        try {
            // Observador para el modelo Conductor
            Conductor::observe(ConductorObserver::class);

            // Observador para el modelo Validacion
            Validacion::observe(ValidacionObserver::class);

            // Observador para el modelo Turno
            Turno::observe(TurnoObserver::class);

        } catch (\Exception $e) {
            Log::warning('Error registrando observadores de modelos: ' . $e->getMessage());
        }
    }

    /**
     * Registrar eventos del sistema
     */
    private function registerSystemEvents(): void
    {
        // Evento cuando se crea una validación crítica
        Event::listen('validacion.critica.creada', function ($validacion) {
            try {
                app(NotificacionService::class)->enviarNotificacionCritica($validacion);
                app(AuditoriaService::class)->registrarEvento('validacion_critica_creada', [
                    'validacion_id' => $validacion->id,
                    'tipo' => $validacion->tipo,
                    'conductor_id' => $validacion->conductor_id
                ]);
            } catch (\Exception $e) {
                Log::error('Error procesando validación crítica: ' . $e->getMessage());
            }
        });

        // Evento cuando un conductor cambia de estado
        Event::listen('conductor.estado.cambio', function ($conductor, $estadoAnterior, $estadoNuevo) {
            try {
                app(CacheMetricasService::class)->invalidarMetricasConductor($conductor->id);

                app(AuditoriaService::class)->registrarEvento('conductor_cambio_estado', [
                    'conductor_id' => $conductor->id,
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $estadoNuevo,
                    'usuario_id' => auth()->id()
                ]);

                // Si el conductor pasa a inactivo, verificar impacto en planificación
                if ($estadoNuevo === 'INACTIVO') {
                    app(ServicioPlanificacionAutomatizada::class)
                        ->verificarImpactoConductorInactivo($conductor);
                }
            } catch (\Exception $e) {
                Log::error('Error procesando cambio de estado de conductor: ' . $e->getMessage());
            }
        });

        // Evento cuando se completa un turno
        Event::listen('turno.completado', function ($turno) {
            try {
                // Actualizar métricas del conductor
                $conductor = $turno->conductor;
                if ($conductor) {
                    $conductor->actualizarMetricasRendimiento();
                    app(CacheMetricasService::class)->invalidarMetricasConductor($conductor->id);
                }

                app(AuditoriaService::class)->registrarEvento('turno_completado', [
                    'turno_id' => $turno->id,
                    'conductor_id' => $turno->conductor_id,
                    'duracion_horas' => $turno->duracion_horas,
                    'eficiencia' => $turno->eficiencia_calculada
                ]);
            } catch (\Exception $e) {
                Log::error('Error procesando turno completado: ' . $e->getMessage());
            }
        });

        // Evento para backup automático
        Event::listen('backup.automatico.completado', function ($backup) {
            try {
                app(NotificacionService::class)->notificarBackupCompletado($backup);

                // Limpiar backups antiguos
                app(ServicioBackupAutomatizado::class)->limpiarBackupsAntiguos();
            } catch (\Exception $e) {
                Log::error('Error procesando backup completado: ' . $e->getMessage());
            }
        });

        // Evento cuando el sistema entra en modo mantenimiento
        Event::listen('sistema.mantenimiento.iniciado', function () {
            try {
                Cache::put('sipat_sistema_estado', 'MANTENIMIENTO', 3600);
                app(NotificacionService::class)->notificarMantenimientoSistema();
            } catch (\Exception $e) {
                Log::error('Error durante inicio de mantenimiento: ' . $e->getMessage());
            }
        });

        // Evento de login de usuario
        Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            try {
                app(AuditoriaService::class)->registrarEvento('usuario_login', [
                    'usuario_id' => $event->user->id,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);

                // Limpiar cache de métricas para el usuario
                app(CacheMetricasService::class)->invalidarCacheUsuario($event->user->id);
            } catch (\Exception $e) {
                Log::error('Error registrando login: ' . $e->getMessage());
            }
        });
    }

    /**
     * Inicializar monitoreo del sistema
     */
    private function initializeSystemMonitoring(): void
    {
        try {
            // Configurar monitoreo de memoria
            if (function_exists('memory_get_usage')) {
                $memoriaInicial = memory_get_usage(true);
                Cache::put('sipat_memoria_inicial', $memoriaInicial, 3600);
            }

            // Configurar monitoreo de tiempo de respuesta
            $tiempoInicio = microtime(true);
            Cache::put('sipat_tiempo_inicio', $tiempoInicio, 3600);

            // Programar verificaciones periódicas
            $this->programarVerificacionesSistema();

        } catch (\Exception $e) {
            Log::warning('Error inicializando monitoreo del sistema: ' . $e->getMessage());
        }
    }

    /**
     * Programar verificaciones del sistema
     */
    private function programarVerificacionesSistema(): void
    {
        // Solo programar si estamos en un entorno que soporta scheduling
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            // Verificar cada 5 minutos el estado del sistema
            Cache::remember('sipat_ultimo_health_check', 300, function () {
                $this->ejecutarHealthCheck();
                return now()->timestamp;
            });

        } catch (\Exception $e) {
            Log::warning('Error programando verificaciones: ' . $e->getMessage());
        }
    }

    /**
     * Ejecutar verificación de salud del sistema
     */
    private function ejecutarHealthCheck(): array
    {
        $salud = [
            'estado' => 'OPERATIVO',
            'timestamp' => now()->toISOString(),
            'checks' => []
        ];

        try {
            // Verificar base de datos
            $salud['checks']['database'] = $this->verificarBaseDatos();

            // Verificar cache
            $salud['checks']['cache'] = $this->verificarCache();

            // Verificar memoria
            $salud['checks']['memoria'] = $this->verificarMemoria();

            // Verificar validaciones pendientes
            $salud['checks']['validaciones'] = $this->verificarValidacionesPendientes();

            // Determinar estado general
            $estadosChecks = array_column($salud['checks'], 'estado');
            if (in_array('CRITICO', $estadosChecks)) {
                $salud['estado'] = 'CRITICO';
            } elseif (in_array('ADVERTENCIA', $estadosChecks)) {
                $salud['estado'] = 'ADVERTENCIA';
            }

            Cache::put('sipat_health_status', $salud, 300);

        } catch (\Exception $e) {
            $salud['estado'] = 'ERROR';
            $salud['error'] = $e->getMessage();
            Log::error('Error en health check: ' . $e->getMessage());
        }

        return $salud;
    }

    /**
     * Verificar estado de la base de datos
     */
    private function verificarBaseDatos(): array
    {
        try {
            \DB::connection()->getPdo();
            $tiempoInicio = microtime(true);
            \DB::select('SELECT 1');
            $tiempoRespuesta = (microtime(true) - $tiempoInicio) * 1000;

            return [
                'estado' => $tiempoRespuesta > 1000 ? 'ADVERTENCIA' : 'OK',
                'tiempo_respuesta_ms' => round($tiempoRespuesta, 2),
                'mensaje' => $tiempoRespuesta > 1000 ? 'Respuesta lenta' : 'Conexión estable'
            ];
        } catch (\Exception $e) {
            return [
                'estado' => 'CRITICO',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar estado del cache
     */
    private function verificarCache(): array
    {
        try {
            $key = 'sipat_cache_test_' . uniqid();
            $valor = 'test_value';

            Cache::put($key, $valor, 60);
            $valorRecuperado = Cache::get($key);
            Cache::forget($key);

            return [
                'estado' => $valorRecuperado === $valor ? 'OK' : 'ADVERTENCIA',
                'funcional' => $valorRecuperado === $valor,
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            return [
                'estado' => 'CRITICO',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar uso de memoria
     */
    private function verificarMemoria(): array
    {
        try {
            $memoriaActual = memory_get_usage(true);
            $memoriaMaxima = ini_get('memory_limit');
            $memoriaMaximaBytes = $this->convertirABytes($memoriaMaxima);

            $porcentajeUso = ($memoriaActual / $memoriaMaximaBytes) * 100;

            return [
                'estado' => $porcentajeUso > 85 ? 'CRITICO' : ($porcentajeUso > 70 ? 'ADVERTENCIA' : 'OK'),
                'uso_actual' => formatear_bytes($memoriaActual),
                'limite' => $memoriaMaxima,
                'porcentaje_uso' => round($porcentajeUso, 2)
            ];
        } catch (\Exception $e) {
            return [
                'estado' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar validaciones pendientes críticas
     */
    private function verificarValidacionesPendientes(): array
    {
        try {
            if (!\Schema::hasTable('validaciones')) {
                return ['estado' => 'OK', 'mensaje' => 'Tabla validaciones no existe aún'];
            }

            $validacionesCriticas = Validacion::where('severidad', 'CRITICA')
                ->where('estado', 'PENDIENTE')
                ->where('created_at', '<=', now()->subMinutes(30))
                ->count();

            return [
                'estado' => $validacionesCriticas > 0 ? 'CRITICO' : 'OK',
                'validaciones_criticas_pendientes' => $validacionesCriticas,
                'mensaje' => $validacionesCriticas > 0 ?
                    "Hay {$validacionesCriticas} validaciones críticas pendientes" :
                    'No hay validaciones críticas pendientes'
            ];
        } catch (\Exception $e) {
            return [
                'estado' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convertir string de memoria a bytes
     */
    private function convertirABytes($memoria): int
    {
        $memoria = trim($memoria);
        $ultimo = strtolower($memoria[strlen($memoria) - 1]);
        $numero = (int) $memoria;

        return match($ultimo) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero
        };
    }

    /**
     * Configurar logs personalizados
     */
    private function configureCustomLogs(): void
    {
        try {
            // Configurar canal de log específico para SIPAT
            config([
                'logging.channels.sipat' => [
                    'driver' => 'daily',
                    'path' => storage_path('logs/sipat/sipat.log'),
                    'level' => env('SIPAT_LOG_LEVEL', 'info'),
                    'days' => sipat_config('log_retention_days', 30),
                    'permission' => 0664,
                ],
                'logging.channels.sipat_auditoria' => [
                    'driver' => 'daily',
                    'path' => storage_path('logs/sipat/auditoria.log'),
                    'level' => 'info',
                    'days' => 90,
                    'permission' => 0664,
                ],
                'logging.channels.sipat_errores' => [
                    'driver' => 'daily',
                    'path' => storage_path('logs/sipat/errores.log'),
                    'level' => 'error',
                    'days' => 90,
                    'permission' => 0664,
                ]
            ]);

            // Crear directorios si no existen
            $directorioLogs = storage_path('logs/sipat');
            if (!file_exists($directorioLogs)) {
                mkdir($directorioLogs, 0755, true);
            }

        } catch (\Exception $e) {
            Log::warning('Error configurando logs personalizados: ' . $e->getMessage());
        }
    }

    /**
     * Registrar middleware personalizado
     */
    private function registerCustomMiddleware(): void
    {
        try {
            $router = $this->app['router'];

            // Registrar middleware personalizado
            $router->aliasMiddleware('log.actividades', LogActividades::class);
            $router->aliasMiddleware('verificar.estado.sistema', VerificarEstadoSistema::class);

            // Aplicar middleware a grupos específicos
            $router->middlewareGroup('sipat', [
                'log.actividades',
                'verificar.estado.sistema'
            ]);

        } catch (\Exception $e) {
            Log::warning('Error registrando middleware personalizado: ' . $e->getMessage());
        }
    }

    /**
     * Registrar comandos de consola personalizados
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\ValidarSistema::class,
                \App\Console\Commands\LimpiarCache::class,
                \App\Console\Commands\GenerarReporteEstado::class,
                \App\Console\Commands\OptimizarBaseDatos::class,
                \App\Console\Commands\BackupAutomatico::class,
                \App\Console\Commands\RepararSistema::class,
            ]);
        }
    }

    /**
     * Configurar tareas programadas
     */
    private function configureScheduledTasks(): void
    {
        // Solo configurar en consola y si el scheduling está habilitado
        if (!$this->app->runningInConsole() || !sipat_config('habilitar_tareas_programadas', true)) {
            return;
        }

        try {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);

                // Ejecutar validaciones automáticas cada hora
                $schedule->command('sipat:validar --automatico')
                    ->hourly()
                    ->withoutOverlapping()
                    ->runInBackground();

                // Limpiar cache cada 6 horas
                $schedule->command('sipat:limpiar-cache')
                    ->everySixHours()
                    ->withoutOverlapping();

                // Backup automático diario a las 2:00 AM
                $schedule->command('sipat:backup --tipo=completo')
                    ->dailyAt('02:00')
                    ->withoutOverlapping()
                    ->runInBackground();

                // Optimización de BD semanal (domingos a las 3:00 AM)
                $schedule->command('sipat:optimizar-bd')
                    ->weekly()
                    ->sundays()
                    ->at('03:00')
                    ->withoutOverlapping();

                // Generar reporte de estado diario
                $schedule->command('sipat:generar-reporte-estado')
                    ->dailyAt('23:00');

                // Limpiar logs antiguos semanalmente
                $schedule->command('sipat:limpiar-logs')
                    ->weekly()
                    ->saturdays()
                    ->at('01:00');
            });

        } catch (\Exception $e) {
            Log::warning('Error configurando tareas programadas: ' . $e->getMessage());
        }
    }
}
