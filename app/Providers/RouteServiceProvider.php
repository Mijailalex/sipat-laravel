<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // =============================================================================
        // RATE LIMITING PARA API GENERAL
        // =============================================================================

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // =============================================================================
        // RATE LIMITING ESPECÍFICO PARA LOGIN
        // =============================================================================

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            $ip = $request->ip();

            // Límite por email específico
            $emailLimit = Limit::perMinute(5)->by($email)->response(function () {
                return response()->json([
                    'message' => 'Demasiados intentos de login para este email. Intente en 1 minuto.',
                    'error' => 'EMAIL_RATE_LIMITED'
                ], 429);
            });

            // Límite por IP
            $ipLimit = Limit::perMinute(10)->by($ip)->response(function () {
                return response()->json([
                    'message' => 'Demasiados intentos de login desde esta IP. Intente en 1 minuto.',
                    'error' => 'IP_RATE_LIMITED'
                ], 429);
            });

            return [$emailLimit, $ipLimit];
        });

        // =============================================================================
        // RATE LIMITING PARA CREDENCIALES (ADMINISTRACIÓN)
        // =============================================================================

        RateLimiter::for('credenciales', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::none();
            }

            // Solo administradores pueden acceder a credenciales
            $user = $request->user();
            if (!$user->hasRole('admin')) {
                return Limit::perMinute(0); // Bloquear completamente
            }

            return Limit::perMinute(20)->by($userId)->response(function () {
                return response()->json([
                    'message' => 'Demasiadas operaciones en credenciales. Espere antes de continuar.',
                    'error' => 'CREDENTIALS_RATE_LIMITED'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA DASHBOARD APIs
        // =============================================================================

        RateLimiter::for('dashboard', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::perMinute(10)->by($request->ip());
            }

            // Obtener rol del usuario para límites personalizados
            $user = $request->user();
            $role = $user->roles->first()->name ?? 'user';

            $limits = [
                'admin' => 200,
                'supervisor' => 150,
                'planificador' => 120,
                'programador' => 100,
                'operador' => 80,
                'user' => 60
            ];

            $maxAttempts = $limits[$role] ?? 60;

            return Limit::perMinute($maxAttempts)->by($userId)->response(function () use ($maxAttempts, $role) {
                return response()->json([
                    'success' => false,
                    'message' => "Límite de {$maxAttempts} peticiones por minuto excedido para rol {$role}",
                    'error' => 'DASHBOARD_RATE_LIMITED',
                    'role' => $role,
                    'max_attempts' => $maxAttempts
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA PLANIFICACIÓN AUTOMÁTICA
        // =============================================================================

        RateLimiter::for('planificacion', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::none();
            }

            // Límite muy restrictivo para operaciones de planificación
            return Limit::perHour(10)->by($userId)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite de 10 ejecuciones de planificación por hora excedido',
                    'error' => 'PLANNING_RATE_LIMITED'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA EXPORTACIONES
        // =============================================================================

        RateLimiter::for('exportaciones', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($userId)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite de 5 exportaciones por minuto excedido',
                    'error' => 'EXPORT_RATE_LIMITED'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA BACKUPS
        // =============================================================================

        RateLimiter::for('backups', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::none();
            }

            // Solo administradores pueden hacer backups
            $user = $request->user();
            if (!$user->hasRole('admin')) {
                return Limit::perMinute(0);
            }

            return Limit::perHour(3)->by($userId)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite de 3 backups por hora excedido',
                    'error' => 'BACKUP_RATE_LIMITED'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA VALIDACIONES AUTOMÁTICAS
        // =============================================================================

        RateLimiter::for('validaciones', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::none();
            }

            return Limit::perMinute(30)->by($userId)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite de 30 operaciones de validación por minuto excedido',
                    'error' => 'VALIDATION_RATE_LIMITED'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA NOTIFICACIONES
        // =============================================================================

        RateLimiter::for('notificaciones', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::perMinute(10)->by($request->ip());
            }

            return Limit::perMinute(60)->by($userId)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite de notificaciones por minuto excedido',
                    'error' => 'NOTIFICATION_RATE_LIMITED'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA WEBHOOKS
        // =============================================================================

        RateLimiter::for('webhooks', function (Request $request) {
            $signature = $request->header('X-Signature');
            $source = $request->header('X-Source', 'unknown');

            return Limit::perMinute(100)->by($source)->response(function () {
                return response()->json([
                    'error' => 'WEBHOOK_RATE_LIMITED',
                    'message' => 'Demasiadas peticiones de webhook'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA APIs PÚBLICAS
        // =============================================================================

        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip())->response(function () {
                return response()->json([
                    'error' => 'PUBLIC_API_RATE_LIMITED',
                    'message' => 'Límite de API pública excedido'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA COMANDOS ARTISAN VÍA WEB
        // =============================================================================

        RateLimiter::for('artisan', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::none();
            }

            // Solo administradores pueden ejecutar comandos
            $user = $request->user();
            if (!$user->hasRole('admin')) {
                return Limit::perMinute(0);
            }

            return Limit::perMinute(5)->by($userId)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Límite de 5 comandos artisan por minuto excedido',
                    'error' => 'ARTISAN_RATE_LIMITED'
                ], 429);
            });
        });

        // =============================================================================
        // RATE LIMITING PARA DESARROLLO (SOLO EN LOCAL)
        // =============================================================================

        if (app()->environment('local')) {
            RateLimiter::for('dev', function (Request $request) {
                return Limit::perMinute(1000)->by($request->ip());
            });
        }

        // =============================================================================
        // RATE LIMITING PERSONALIZABLE VÍA CONFIGURACIÓN
        // =============================================================================

        RateLimiter::for('configurable', function (Request $request) {
            $maxAttempts = config('sipat.rate_limits.configurable.max_attempts', 60);
            $decayMinutes = config('sipat.rate_limits.configurable.decay_minutes', 1);

            $userId = $request->user()?->id;
            $key = $userId ?: $request->ip();

            if ($decayMinutes === 1) {
                return Limit::perMinute($maxAttempts)->by($key);
            } else {
                return Limit::perMinutes($decayMinutes, $maxAttempts)->by($key);
            }
        });

        // =============================================================================
        // RATE LIMITING INTELIGENTE BASADO EN CARGA DEL SISTEMA
        // =============================================================================

        RateLimiter::for('adaptive', function (Request $request) {
            $userId = $request->user()?->id;

            if (!$userId) {
                return Limit::perMinute(30)->by($request->ip());
            }

            // Obtener carga actual del sistema (implementar según métricas del sistema)
            $systemLoad = $this->getSystemLoad();

            // Ajustar límites según la carga
            if ($systemLoad > 80) {
                $maxAttempts = 30; // Reducir límites si hay alta carga
            } elseif ($systemLoad > 60) {
                $maxAttempts = 60;
            } else {
                $maxAttempts = 120; // Límites normales con baja carga
            }

            return Limit::perMinute($maxAttempts)->by($userId)->response(function () use ($maxAttempts, $systemLoad) {
                return response()->json([
                    'success' => false,
                    'message' => "Límite adaptativo: {$maxAttempts} peticiones/min (carga del sistema: {$systemLoad}%)",
                    'error' => 'ADAPTIVE_RATE_LIMITED',
                    'system_load' => $systemLoad,
                    'current_limit' => $maxAttempts
                ], 429);
            });
        });
    }

    /**
     * Obtener la carga actual del sistema
     * (Implementar según las métricas específicas del sistema)
     */
    private function getSystemLoad(): float
    {
        try {
            // Ejemplo básico - puede mejorarse con métricas más sofisticadas
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->convertToBytes(ini_get('memory_limit'));

            if ($memoryLimit > 0) {
                $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;
            } else {
                $memoryPercentage = 0;
            }

            // En un sistema real, aquí se combinarían múltiples métricas:
            // - CPU usage
            // - Memory usage
            // - Database connections
            // - Queue length
            // - Active users

            return min(100, max(0, $memoryPercentage));

        } catch (\Exception $e) {
            // En caso de error, asumir carga media
            return 50;
        }
    }

    /**
     * Convertir string de memoria a bytes
     */
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        switch ($last) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
        }

        return $number;
    }
}
