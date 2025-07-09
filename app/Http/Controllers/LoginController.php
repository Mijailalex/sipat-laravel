<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | Este controlador maneja la autenticación de usuarios para la aplicación
    | y los redirige al dashboard después del login exitoso.
    |
    */

    use AuthenticatesUsers;

    /**
     * Dónde redirigir a los usuarios después del login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Crear una nueva instancia del controlador.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Mostrar el formulario de login.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Manejar el intento de login.
     */
    public function login(Request $request)
    {
        $this->validateLogin($request);

        // Si el usuario excedió los intentos máximos, bloquear temporalmente
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        // Incrementar intentos de login
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    /**
     * Validar la solicitud de login.
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string',
        ], [
            $this->username() . '.required' => 'El email es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);
    }

    /**
     * Intentar autenticar al usuario.
     */
    protected function attemptLogin(Request $request)
    {
        return $this->guard()->attempt(
            $this->credentials($request),
            $request->boolean('remember')
        );
    }

    /**
     * Obtener las credenciales de la solicitud.
     */
    protected function credentials(Request $request)
    {
        return $request->only($this->username(), 'password');
    }

    /**
     * Enviar la respuesta de login exitoso.
     */
    protected function sendLoginResponse(Request $request)
    {
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        return $request->wantsJson()
                    ? new \Illuminate\Http\JsonResponse([], 204)
                    : redirect()->intended($this->redirectPath());
    }

    /**
     * El usuario ha sido autenticado.
     */
    protected function authenticated(Request $request, $user)
    {
        // Actualizar última fecha de acceso
        try {
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            // Si las columnas no existen, continuar sin error
            Log::info('Información de login no actualizada (columnas no existen): ' . $e->getMessage());
        }

        // Log de acceso exitoso
        Log::info('Usuario autenticado exitosamente', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Registrar en historial si existe el modelo
        try {
            if (class_exists('\App\Models\HistorialCredenciales')) {
                \App\Models\HistorialCredenciales::registrarAccesoExitoso($user->id);
            }
        } catch (\Exception $e) {
            Log::info('Historial de credenciales no disponible: ' . $e->getMessage());
        }

        return null; // Continuar con el flujo normal
    }

    /**
     * Enviar la respuesta de login fallido.
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        // Log de intento fallido
        Log::warning('Intento de login fallido', [
            'email' => $request->input($this->username()),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Registrar acceso fallido si existe el modelo
        try {
            if (class_exists('\App\Models\HistorialCredenciales')) {
                $user = \App\Models\User::where($this->username(), $request->input($this->username()))->first();
                if ($user) {
                    \App\Models\HistorialCredenciales::registrarAccesoFallido($user->id, 'CREDENCIALES_INVALIDAS');
                }
            }
        } catch (\Exception $e) {
            Log::info('No se pudo registrar acceso fallido: ' . $e->getMessage());
        }

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    /**
     * Cerrar sesión del usuario.
     */
    public function logout(Request $request)
    {
        $user = $this->guard()->user();

        // Log de logout
        if ($user) {
            Log::info('Usuario cerró sesión', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            // Registrar logout si existe el modelo
            try {
                if (class_exists('\App\Models\HistorialCredenciales')) {
                    \App\Models\HistorialCredenciales::registrarLogout($user->id);
                }
            } catch (\Exception $e) {
                Log::info('No se pudo registrar logout: ' . $e->getMessage());
            }
        }

        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($response = $this->loggedOut($request)) {
            return $response;
        }

        return $request->wantsJson()
            ? new \Illuminate\Http\JsonResponse([], 204)
            : redirect('/login');
    }

    /**
     * El usuario ha cerrado sesión.
     */
    protected function loggedOut(Request $request)
    {
        return null;
    }

    /**
     * Obtener el nombre del campo de autenticación.
     */
    public function username()
    {
        return 'email';
    }

    /**
     * Obtener el guard de autenticación.
     */
    protected function guard()
    {
        return Auth::guard();
    }

    /**
     * Obtener la ruta de redirección.
     */
    public function redirectPath()
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/dashboard';
    }
}
