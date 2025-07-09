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
    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }

        $this->incrementLoginAttempts($request);
        return $this->sendFailedLoginResponse($request);
    }

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

    protected function attemptLogin(Request $request)
    {
        return $this->guard()->attempt(
            $this->credentials($request),
            $request->boolean('remember')
        );
    }

    protected function credentials(Request $request)
    {
        return $request->only($this->username(), 'password');
    }

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

    protected function authenticated(Request $request, $user)
    {
        try {
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            // Si las columnas no existen, continuar sin error
        }

        Log::info('Usuario autenticado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        return null;
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        Log::warning('Intento de login fallido', [
            'email' => $request->input($this->username()),
            'ip' => $request->ip()
        ]);

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $this->guard()->user();

        if ($user) {
            Log::info('Usuario cerró sesión', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);
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

    protected function loggedOut(Request $request)
    {
        return null;
    }

    public function username()
    {
        return 'email';
    }

    protected function guard()
    {
        return Auth::guard();
    }

    public function redirectPath()
    {
        if (method_exists($this, 'redirectTo')) {
            return $this->redirectTo();
        }

        return property_exists($this, 'redirectTo') ? $this->redirectTo : '/dashboard';
    }
}
