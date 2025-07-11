<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DashboardRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar si el usuario está autenticado
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado',
                'error' => 'UNAUTHORIZED'
            ], 401);
        }

        // Obtener ID del usuario para crear clave única
        $userId = Auth::id();
        $userRole = Auth::user()->roles->first()->name ?? 'user';

        // Crear clave de rate limiting específica por usuario
        $key = 'dashboard-api:' . $userId;

        // Configurar límites según el rol del usuario
        $limits = $this->getRoleLimits($userRole);
        $maxAttempts = $limits['max_attempts'];
        $decayMinutes = $limits['decay_minutes'];

        // Verificar si el usuario ha excedido el límite
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            // Log del rate limiting para auditoría
            Log::warning('Rate limit excedido en Dashboard API', [
                'user_id' => $userId,
                'user_role' => $userRole,
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'retry_after' => $retryAfter
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Demasiadas peticiones. Intente más tarde.',
                'error' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter,
                'max_attempts' => $maxAttempts,
                'reset_time' => now()->addSeconds($retryAfter)->toISOString()
            ], 429);
        }

        // Incrementar contador de intentos
        RateLimiter::hit($key, $decayMinutes * 60);

        // Agregar headers informativos sobre rate limiting
        $response = $next($request);

        $attemptsLeft = $maxAttempts - RateLimiter::attempts($key);
        $resetsAt = now()->addSeconds(RateLimiter::availableIn($key));

        // Agregar headers de rate limiting a la respuesta
        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', $maxAttempts);
            $response->header('X-RateLimit-Remaining', max(0, $attemptsLeft));
            $response->header('X-RateLimit-Reset', $resetsAt->timestamp);
            $response->header('X-RateLimit-User', $userId);
            $response->header('X-RateLimit-Role', $userRole);
        }

        return $response;
    }

    /**
     * Obtener límites de rate limiting según el rol del usuario
     *
     * @param string $role
     * @return array
     */
    private function getRoleLimits($role)
    {
        $limits = [
            'admin' => [
                'max_attempts' => 200,      // 200 requests
                'decay_minutes' => 1        // por minuto
            ],
            'supervisor' => [
                'max_attempts' => 150,      // 150 requests
                'decay_minutes' => 1        // por minuto
            ],
            'planificador' => [
                'max_attempts' => 120,      // 120 requests
                'decay_minutes' => 1        // por minuto
            ],
            'programador' => [
                'max_attempts' => 100,      // 100 requests
                'decay_minutes' => 1        // por minuto
            ],
            'operador' => [
                'max_attempts' => 80,       // 80 requests
                'decay_minutes' => 1        // por minuto
            ],
            'user' => [
                'max_attempts' => 60,       // 60 requests
                'decay_minutes' => 1        // por minuto
            ]
        ];

        return $limits[$role] ?? $limits['user'];
    }

    /**
     * Limpiar rate limiting para un usuario específico
     * (Método de utilidad para administradores)
     *
     * @param int $userId
     * @return bool
     */
    public static function clearUserRateLimit($userId)
    {
        $key = 'dashboard-api:' . $userId;
        RateLimiter::clear($key);

        Log::info('Rate limit limpiado para usuario', [
            'user_id' => $userId,
            'cleared_by' => Auth::id() ?? 'system'
        ]);

        return true;
    }

    /**
     * Obtener estadísticas de rate limiting para un usuario
     *
     * @param int $userId
     * @return array
     */
    public static function getUserRateLimitStats($userId)
    {
        $key = 'dashboard-api:' . $userId;
        $attempts = RateLimiter::attempts($key);
        $availableIn = RateLimiter::availableIn($key);

        return [
            'user_id' => $userId,
            'attempts' => $attempts,
            'available_in' => $availableIn,
            'is_blocked' => $attempts > 0 && $availableIn > 0,
            'reset_at' => now()->addSeconds($availableIn)->toISOString()
        ];
    }

    /**
     * Verificar si un usuario está bloqueado por rate limiting
     *
     * @param int $userId
     * @param string $role
     * @return bool
     */
    public static function isUserBlocked($userId, $role = 'user')
    {
        $key = 'dashboard-api:' . $userId;
        $limits = (new self())->getRoleLimits($role);

        return RateLimiter::tooManyAttempts($key, $limits['max_attempts']);
    }

    /**
     * Obtener límites configurados para un rol específico
     *
     * @param string $role
     * @return array
     */
    public static function getRoleLimitsStatic($role)
    {
        return (new self())->getRoleLimits($role);
    }
}
