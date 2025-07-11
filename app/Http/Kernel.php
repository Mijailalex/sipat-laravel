<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // =============================================================================
        // MIDDLEWARE PERSONALIZADO SIPAT
        // =============================================================================

        // Rate limiting específico para dashboard
        'dashboard' => \App\Http\Middleware\DashboardRateLimit::class,

        // Middleware de roles y permisos (si usas Spatie Laravel Permission)
        'role' => \Spatie\Permission\Middlewares\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middlewares\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,

        // Rate limiting para credenciales (administración)
        'credenciales' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':credenciales',

        // Rate limiting para login
        'login' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':login',

        // Middleware personalizado para auditoría (si se implementa)
        'audit' => \App\Http\Middleware\AuditMiddleware::class,

        // Middleware para verificar mantenimiento del sistema
        'maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class,

        // Middleware para verificar permisos específicos de SIPAT
        'sipat.admin' => \App\Http\Middleware\SipatAdminMiddleware::class,
        'sipat.planner' => \App\Http\Middleware\SipatPlannerMiddleware::class,
        'sipat.operator' => \App\Http\Middleware\SipatOperatorMiddleware::class,

        // Middleware para APIs externas
        'api.key' => \App\Http\Middleware\ApiKeyMiddleware::class,
        'webhook.signature' => \App\Http\Middleware\WebhookSignatureMiddleware::class,

        // Middleware para validación de JSON
        'json.validate' => \App\Http\Middleware\ValidateJsonMiddleware::class,

        // Middleware para CORS personalizado
        'cors.sipat' => \App\Http\Middleware\SipatCorsMiddleware::class,

        // Middleware para logging de actividad
        'activity.log' => \App\Http\Middleware\ActivityLogMiddleware::class,

        // Middleware para validación de timezone
        'timezone' => \App\Http\Middleware\TimezoneMiddleware::class,

        // Middleware para verificar licencia del sistema
        'license.check' => \App\Http\Middleware\LicenseCheckMiddleware::class,

        // Middleware para limitar acceso por IP
        'ip.whitelist' => \App\Http\Middleware\IpWhitelistMiddleware::class,

        // Middleware para verificar estado del sistema
        'system.health' => \App\Http\Middleware\SystemHealthMiddleware::class,

        // Middleware para cache inteligente de respuestas
        'smart.cache' => \App\Http\Middleware\SmartCacheMiddleware::class,

        // Middleware para compresión de respuestas
        'response.compress' => \App\Http\Middleware\ResponseCompressionMiddleware::class,

        // Middleware para validación de dispositivos móviles
        'mobile.detect' => \App\Http\Middleware\MobileDetectionMiddleware::class,

        // Middleware para sesiones de tiempo limitado
        'session.timeout' => \App\Http\Middleware\SessionTimeoutMiddleware::class,

        // Middleware para validación de datos sensibles
        'sensitive.data' => \App\Http\Middleware\SensitiveDataMiddleware::class,

        // Middleware para backup automático en operaciones críticas
        'auto.backup' => \App\Http\Middleware\AutoBackupMiddleware::class,

        // Middleware para notificaciones en tiempo real
        'realtime.notify' => \App\Http\Middleware\RealtimeNotificationMiddleware::class,

        // Middleware para validación de integridad de datos
        'data.integrity' => \App\Http\Middleware\DataIntegrityMiddleware::class,

        // Middleware para prevención de ataques
        'security.shield' => \App\Http\Middleware\SecurityShieldMiddleware::class,

        // Middleware para configuración dinámica
        'dynamic.config' => \App\Http\Middleware\DynamicConfigMiddleware::class,

        // Middleware para métricas de rendimiento
        'performance.metrics' => \App\Http\Middleware\PerformanceMetricsMiddleware::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * This forces non-global middleware to always be in the given order.
     *
     * @var string[]
     */
    protected $middlewarePriority = [
        \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,

        // Prioridad personalizada para SIPAT
        \App\Http\Middleware\SystemHealthMiddleware::class,
        \App\Http\Middleware\LicenseCheckMiddleware::class,
        \App\Http\Middleware\SecurityShieldMiddleware::class,
        \App\Http\Middleware\DashboardRateLimit::class,
        \App\Http\Middleware\ActivityLogMiddleware::class,
    ];
}
