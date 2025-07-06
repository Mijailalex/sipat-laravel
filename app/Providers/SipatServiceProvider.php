<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Scheduling\Schedule;
use App\Services\ServicioPlanificacionAutomatizada;
use App\Services\ServicioBackupAutomatizado;
use App\Models\Parametro;
use App\Models\User;
use App\View\Composers\DashboardComposer;
use App\View\Composers\MenuComposer;

class SipatServiceProvider extends ServiceProvider
{
    /**
     * Todos los singletons del contenedor de servicios
     */
    public array $singletons = [
        ServicioPlanificacionAutomatizada::class => ServicioPlanificacionAutomatizada::class,
        ServicioBackupAutomatizado::class => ServicioBackupAutomatizado::class,
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

        // Registrar aliases para facilitar el acceso
        $this->app->alias(ServicioPlanificacionAutomatizada::class, 'sipat.planificacion');
        $this->app->alias(ServicioBackupAutomatizado::class, 'sipat.backup');

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
                    $carbon = $fecha instanceof \Carbon\Carbon ? $fecha : \Carbon\Carbon::parse($fecha);
                    return $carbon->locale('es')->format($formato);
                } catch (\Exception $e) {
                    return $fecha;
                }
            }
        }

        // Helper para obtener estado del conductor con icono
        if (!function_exists('estado_conductor_badge')) {
            function estado_conductor_badge($estado) {
                $badges = [
                    'DISPONIBLE' => '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Disponible</span>',
                    'DESCANSO_FISICO' => '<span class="badge bg-warning"><i class="fas fa-bed me-1"></i>Descanso Físico</span>',
                    'DESCANSO_SEMANAL' => '<span class="badge bg-info"><i class="fas fa-calendar me-1"></i>Descanso Semanal</span>',
                    'VACACIONES' => '<span class="badge bg-primary"><i class="fas fa-umbrella-beach me-1"></i>Vacaciones</span>',
                    'SUSPENDIDO' => '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Suspendido</span>',
                    'FALTO_OPERATIVO' => '<span class="badge bg-secondary"><i class="fas fa-exclamation-triangle me-1"></i>Falta Operativa</span>',
                    'FALTO_NO_OPERATIVO' => '<span class="badge bg-dark"><i class="fas fa-times me-1"></i>Falta No Operativa</span>',
                ];

                return $badges[$estado] ?? '<span class="badge bg-secondary">' . $estado . '</span>';
            }
        }

        // Helper para calcular progreso de eficiencia
        if (!function_exists('progreso_eficiencia')) {
            function progreso_eficiencia($eficiencia) {
                $color = $eficiencia >= 90 ? 'success' :
                        ($eficiencia >= 80 ? 'warning' : 'danger');

                return [
                    'porcentaje' => $eficiencia,
                    'color' => $color,
                    'clase_css' => "progress-bar bg-{$color}"
                ];
            }
        }

        // Helper para obtener icono de severidad
        if (!function_exists('icono_severidad')) {
            function icono_severidad($severidad) {
                $iconos = [
                    'INFO' => '<i class="fas fa-info-circle text-info"></i>',
                    'ADVERTENCIA' => '<i class="fas fa-exclamation-triangle text-warning"></i>',
                    'CRITICA' => '<i class="fas fa-times-circle text-danger"></i>',
                    'EMERGENCIA' => '<i class="fas fa-exclamation-circle text-danger"></i>',
                ];

                return $iconos[$severidad] ?? '<i class="fas fa-question-circle text-muted"></i>';
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
                'version' => config('sipat.version')
            ], $status);
        });
    }

    /**
     * Registrar macros de paginación (movido al boot con corrección)
     */
    private function registerPaginationMacros(): void
    {
        // Macro para paginación con información adicional
        try {
            if (class_exists('\Illuminate\Pagination\LengthAwarePaginator') && method_exists('\Illuminate\Pagination\LengthAwarePaginator', 'macro')) {
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
                    'nombre_empresa'
                ])->get()->pluck('valor', 'clave');

                // Aplicar configuraciones
                if ($configuraciones->has('zona_horaria')) {
                    config(['app.timezone' => $configuraciones->get('zona_horaria')]);
                    date_default_timezone_set($configuraciones->get('zona_horaria'));
                }

                if ($configuraciones->has('items_por_pagina')) {
                    config(['sipat.pagination' => (int) $configuraciones->get('items_por_pagina')]);
                }
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
        // Composer para el dashboard
        View::composer('dashboard.*', function ($view) {
            // Composer básico sin clase externa por ahora
        });

        // Composer para el menú de navegación
        View::composer('layouts.app', function ($view) {
            // Composer básico sin clase externa por ahora
        });

        // Composer global con variables del sistema
        View::composer('*', function ($view) {
            $view->with([
                'sipat_version' => config('sipat.version'),
                'sipat_name' => config('sipat.name'),
                'usuario_actual' => auth()->user(),
                'es_admin' => auth()->check() && auth()->user()->hasRole('admin'),
                'configuracion_sistema' => app('sipat.config')
            ]);
        });

        // Composer para notificaciones
        View::composer(['layouts.app', 'dashboard.*'], function ($view) {
            if (auth()->check()) {
                $notificaciones = $this->obtenerNotificacionesUsuario();
                $view->with('notificaciones_usuario', $notificaciones);
            }
        });
    }

    /**
     * Registrar directivas de Blade personalizadas
     */
    private function registerBladeDirectives(): void
    {
        // Directiva para verificar permisos
        Blade::directive('sipat_can', function ($permission) {
            return "<?php if(auth()->check() && auth()->user()->can({$permission})): ?>";
        });

        Blade::directive('endsipat_can', function () {
            return "<?php endif; ?>";
        });

        // Directiva para mostrar estado del conductor
        Blade::directive('estado_conductor', function ($estado) {
            return "<?php echo estado_conductor_badge({$estado}); ?>";
        });

        // Directiva para formatear fechas
        Blade::directive('fecha', function ($fecha) {
            return "<?php echo fecha_es({$fecha}); ?>";
        });

        // Directiva para mostrar progreso
        Blade::directive('progreso', function ($valor) {
            return "<?php
                \$prog = progreso_eficiencia({$valor});
                echo '<div class=\"progress\"><div class=\"' . \$prog['clase_css'] . '\" style=\"width: ' . \$prog['porcentaje'] . '%\">' . \$prog['porcentaje'] . '%</div></div>';
            ?>";
        });

        // Directiva para incluir scripts de SIPAT
        Blade::directive('sipat_scripts', function () {
            return "<?php echo view('layouts.partials.sipat-scripts')->render(); ?>";
        });

        // Directiva para mostrar icono de severidad
        Blade::directive('severidad', function ($severidad) {
            return "<?php echo icono_severidad({$severidad}); ?>";
        });
    }

    /**
     * Registrar gates de autorización
     */
    private function registerAuthorizationGates(): void
    {
        // Gate para administración completa
        Gate::define('administrar_sistema', function (User $user) {
            return $user->hasRole('admin');
        });

        // Gate para gestión de planificación
        Gate::define('gestionar_planificacion', function (User $user) {
            return $user->hasAnyRole(['admin', 'supervisor']);
        });

        // Gate para solo lectura
        Gate::define('solo_lectura', function (User $user) {
            return $user->hasRole('auditor');
        });

        // Gate dinámico para conductores
        Gate::define('gestionar_conductor', function (User $user, $conductor) {
            if ($user->hasRole('admin')) return true;
            if ($user->hasRole('supervisor')) return true;
            if ($user->hasRole('operador')) {
                // Los operadores solo pueden gestionar conductores de su subempresa
                return $user->subempresa === $conductor->subempresa;
            }
            return false;
        });

        // Gate para backups
        Gate::define('gestionar_backups', function (User $user) {
            return $user->hasRole('admin');
        });

        // Gate para validaciones críticas
        Gate::define('resolver_validaciones_criticas', function (User $user) {
            return $user->hasAnyRole(['admin', 'supervisor']);
        });
    }

    /**
     * Registrar validadores personalizados
     */
    private function registerCustomValidators(): void
    {
        // Validador para código de conductor único
        \Validator::extend('codigo_conductor_unico', function ($attribute, $value, $parameters, $validator) {
            $conductorId = $parameters[0] ?? null;
            $query = \App\Models\Conductor::where('codigo_conductor', $value);

            if ($conductorId) {
                $query->where('id', '!=', $conductorId);
            }

            return $query->count() === 0;
        });

        // Validador para DNI peruano
        \Validator::extend('dni_peruano', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\d{8}$/', $value);
        });

        // Validador para licencia de conducir
        \Validator::extend('licencia_conducir', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[A-Z]{1,2}\d{7,9}$/', $value);
        });

        // Validador para horario válido
        \Validator::extend('horario_valido', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value);
        });

        // Mensajes personalizados
        \Validator::replacer('codigo_conductor_unico', function ($message, $attribute, $rule, $parameters) {
            return 'El código de conductor ya está en uso.';
        });

        \Validator::replacer('dni_peruano', function ($message, $attribute, $rule, $parameters) {
            return 'El DNI debe tener exactamente 8 dígitos.';
        });

        \Validator::replacer('licencia_conducir', function ($message, $attribute, $rule, $parameters) {
            return 'El formato de la licencia de conducir no es válido.';
        });

        \Validator::replacer('horario_valido', function ($message, $attribute, $rule, $parameters) {
            return 'El horario debe tener el formato HH:MM válido.';
        });
    }

    /**
     * Registrar observadores de modelos
     */
    private function registerModelObservers(): void
    {
        // Los observadores se registrarán cuando se creen las clases correspondientes
    }

    /**
     * Registrar eventos del sistema
     */
    private function registerSystemEvents(): void
    {
        // Evento de inicio de sesión
        \Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            if (class_exists('\App\Models\HistorialCredenciales')) {
                try {
                    \App\Models\HistorialCredenciales::registrarAccesoExitoso($event->user->id);
                } catch (\Exception $e) {
                    Log::warning('Error registrando acceso exitoso: ' . $e->getMessage());
                }
            }
        });

        // Evento de cierre de sesión
        \Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if (class_exists('\App\Models\HistorialCredenciales')) {
                try {
                    \App\Models\HistorialCredenciales::registrarLogout($event->user->id);
                } catch (\Exception $e) {
                    Log::warning('Error registrando logout: ' . $e->getMessage());
                }
            }
        });

        // Evento de intento de acceso fallido
        \Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            if ($event->user && class_exists('\App\Models\HistorialCredenciales')) {
                try {
                    \App\Models\HistorialCredenciales::registrarAccesoFallido(
                        $event->user->id,
                        'PASSWORD_INCORRECTO'
                    );
                } catch (\Exception $e) {
                    Log::warning('Error registrando acceso fallido: ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * Inicializar monitoreo del sistema
     */
    private function initializeSystemMonitoring(): void
    {
        // Monitorear memoria del sistema
        if (app()->environment('production')) {
            register_shutdown_function(function () {
                $memoryUsage = memory_get_peak_usage(true);
                $memoryLimit = ini_get('memory_limit');

                if ($memoryLimit !== '-1') {
                    $limitBytes = $this->convertToBytes($memoryLimit);
                    $percentage = ($memoryUsage / $limitBytes) * 100;

                    if ($percentage > 90) {
                        Log::warning("Uso de memoria alto: {$percentage}%", [
                            'memory_usage' => formatear_bytes($memoryUsage),
                            'memory_limit' => $memoryLimit
                        ]);
                    }
                }
            });
        }
    }

    /**
     * Configurar logs personalizados
     */
    private function configureCustomLogs(): void
    {
        // Canal de log para planificación
        config([
            'logging.channels.planificacion' => [
                'driver' => 'daily',
                'path' => storage_path('logs/planificacion.log'),
                'level' => 'info',
                'days' => 30,
            ],
            'logging.channels.backups' => [
                'driver' => 'daily',
                'path' => storage_path('logs/backups.log'),
                'level' => 'info',
                'days' => 90,
            ],
            'logging.channels.seguridad' => [
                'driver' => 'daily',
                'path' => storage_path('logs/seguridad.log'),
                'level' => 'warning',
                'days' => 180,
            ]
        ]);
    }

    /**
     * Registrar middleware personalizado
     */
    private function registerCustomMiddleware(): void
    {
        // Los middleware se registrarán cuando se creen las clases correspondientes
    }

    /**
     * Obtener notificaciones del usuario actual
     */
    private function obtenerNotificacionesUsuario(): array
    {
        if (!auth()->check()) {
            return [];
        }

        try {
            // Obtener validaciones asignadas al usuario
            $validacionesPendientes = 0;
            if (class_exists('\App\Models\Validacion')) {
                $validacionesPendientes = \App\Models\Validacion::where('asignado_a', auth()->id())
                    ->where('estado', 'PENDIENTE')
                    ->count();
            }

            // Obtener conductores críticos si es supervisor o admin
            $conductoresCriticos = 0;
            if (auth()->user()->hasAnyRole(['admin', 'supervisor']) && class_exists('\App\Models\Conductor')) {
                $conductoresCriticos = \App\Models\Conductor::where('dias_acumulados', '>=', 6)
                    ->where('estado', 'DISPONIBLE')
                    ->count();
            }

            return [
                'validaciones_pendientes' => $validacionesPendientes,
                'conductores_criticos' => $conductoresCriticos,
                'total' => $validacionesPendientes + $conductoresCriticos
            ];
        } catch (\Exception $e) {
            Log::warning('Error obteniendo notificaciones de usuario: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Convertir string de memoria a bytes
     */
    private function convertToBytes(string $memory): int
    {
        $unit = strtolower(substr($memory, -1));
        $number = (int) substr($memory, 0, -1);

        switch ($unit) {
            case 'g': return $number * 1024 * 1024 * 1024;
            case 'm': return $number * 1024 * 1024;
            case 'k': return $number * 1024;
            default: return $number;
        }
    }

    /**
     * Obtener los servicios proporcionados por este provider
     */
    public function provides(): array
    {
        return [
            ServicioPlanificacionAutomatizada::class,
            ServicioBackupAutomatizado::class,
            'sipat.planificacion',
            'sipat.backup',
            'sipat.config'
        ];
    }
}
