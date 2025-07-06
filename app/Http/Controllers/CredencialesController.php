<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\HistorialCredenciales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

class CredencialesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin'); // Solo administradores
        $this->middleware('throttle:credenciales,10,1'); // Límite de 10 acciones por minuto
    }

    /**
     * Dashboard principal de gestión de credenciales
     */
    public function index(Request $request)
    {
        $filtros = $request->only(['estado', 'rol', 'buscar']);

        // Obtener usuarios con filtros
        $usuarios = User::with(['roles', 'historialCredenciales' => function($query) {
                $query->latest()->limit(3);
            }])
            ->when($filtros['estado'] ?? null, function($query, $estado) {
                if ($estado === 'activos') {
                    $query->where('activo', true);
                } elseif ($estado === 'inactivos') {
                    $query->where('activo', false);
                } elseif ($estado === 'bloqueados') {
                    $query->where('bloqueado', true);
                }
            })
            ->when($filtros['rol'] ?? null, function($query, $rol) {
                $query->whereHas('roles', function($q) use ($rol) {
                    $q->where('name', $rol);
                });
            })
            ->when($filtros['buscar'] ?? null, function($query, $buscar) {
                $query->where(function($q) use ($buscar) {
                    $q->where('name', 'LIKE', "%{$buscar}%")
                      ->orWhere('email', 'LIKE', "%{$buscar}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Estadísticas de seguridad
        $estadisticas = $this->obtenerEstadisticasSeguridad();

        // Intentos de acceso recientes
        $intentosRecientes = $this->obtenerIntentosAccesoRecientes();

        // Alertas de seguridad
        $alertasSeguridad = $this->obtenerAlertasSeguridad();

        return view('admin.credenciales.index', compact(
            'usuarios',
            'estadisticas',
            'intentosRecientes',
            'alertasSeguridad',
            'filtros'
        ));
    }

    /**
     * Crear nuevo usuario del sistema
     */
    public function crear()
    {
        $roles = \Spatie\Permission\Models\Role::all();
        $permisos = \Spatie\Permission\Models\Permission::all()->groupBy('categoria');

        return view('admin.credenciales.crear', compact('roles', 'permisos'));
    }

    /**
     * Almacenar nuevo usuario
     */
    public function almacenar(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
            'rol' => 'required|exists:roles,name',
            'permisos_adicionales' => 'array',
            'permisos_adicionales.*' => 'exists:permissions,name',
            'activo' => 'boolean',
            'forzar_cambio_password' => 'boolean',
            'notas_admin' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            // Crear usuario
            $usuario = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'activo' => $validated['activo'] ?? true,
                'debe_cambiar_password' => $validated['forzar_cambio_password'] ?? false,
                'fecha_ultimo_acceso' => null,
                'intentos_fallidos' => 0,
                'bloqueado' => false,
                'notas_admin' => $validated['notas_admin']
            ]);

            // Asignar rol
            $usuario->assignRole($validated['rol']);

            // Asignar permisos adicionales si los hay
            if (!empty($validated['permisos_adicionales'])) {
                $usuario->givePermissionTo($validated['permisos_adicionales']);
            }

            // Registrar en historial
            HistorialCredenciales::registrarAccion($usuario->id, 'USUARIO_CREADO', [
                'creado_por' => Auth::id(),
                'rol_asignado' => $validated['rol'],
                'permisos_adicionales' => $validated['permisos_adicionales'] ?? [],
                'ip_creacion' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            DB::commit();

            Log::info('Nuevo usuario creado por administrador', [
                'usuario_creado' => $usuario->email,
                'admin_creador' => Auth::user()->email,
                'rol' => $validated['rol'],
                'ip' => $request->ip()
            ]);

            return redirect()->route('admin.credenciales.index')
                           ->with('success', "Usuario {$usuario->name} creado exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al crear usuario', [
                'error' => $e->getMessage(),
                'admin' => Auth::user()->email,
                'datos' => $validated
            ]);

            return back()->withInput()
                        ->with('error', 'Error al crear usuario: ' . $e->getMessage());
        }
    }

    /**
     * Editar credenciales de usuario
     */
    public function editar(User $usuario)
    {
        $roles = \Spatie\Permission\Models\Role::all();
        $permisos = \Spatie\Permission\Models\Permission::all()->groupBy('categoria');
        $historialReciente = $usuario->historialCredenciales()
                                   ->latest()
                                   ->limit(10)
                                   ->get();

        return view('admin.credenciales.editar', compact(
            'usuario',
            'roles',
            'permisos',
            'historialReciente'
        ));
    }

    /**
     * Actualizar credenciales de usuario
     */
    public function actualizar(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'rol' => 'required|exists:roles,name',
            'permisos_adicionales' => 'array',
            'permisos_adicionales.*' => 'exists:permissions,name',
            'activo' => 'boolean',
            'bloqueado' => 'boolean',
            'debe_cambiar_password' => 'boolean',
            'resetear_intentos_fallidos' => 'boolean',
            'notas_admin' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $cambiosRealizados = [];
            $datosOriginales = $usuario->toArray();

            // Actualizar datos básicos
            $cambiosBasicos = [];
            foreach (['name', 'email', 'activo', 'bloqueado', 'debe_cambiar_password', 'notas_admin'] as $campo) {
                if (isset($validated[$campo]) && $usuario->{$campo} != $validated[$campo]) {
                    $cambiosBasicos[$campo] = [
                        'anterior' => $usuario->{$campo},
                        'nuevo' => $validated[$campo]
                    ];
                    $usuario->{$campo} = $validated[$campo];
                }
            }

            if (!empty($cambiosBasicos)) {
                $cambiosRealizados['datos_basicos'] = $cambiosBasicos;
            }

            // Resetear intentos fallidos si se solicitó
            if ($validated['resetear_intentos_fallidos'] ?? false) {
                $usuario->intentos_fallidos = 0;
                $cambiosRealizados['intentos_resetados'] = true;
            }

            $usuario->save();

            // Actualizar rol si cambió
            $rolActual = $usuario->roles->first()?->name;
            if ($rolActual !== $validated['rol']) {
                $usuario->syncRoles([$validated['rol']]);
                $cambiosRealizados['rol'] = [
                    'anterior' => $rolActual,
                    'nuevo' => $validated['rol']
                ];
            }

            // Actualizar permisos adicionales
            $permisosActuales = $usuario->getDirectPermissions()->pluck('name')->toArray();
            $permisosNuevos = $validated['permisos_adicionales'] ?? [];

            if ($permisosActuales !== $permisosNuevos) {
                $usuario->syncPermissions($permisosNuevos);
                $cambiosRealizados['permisos'] = [
                    'anteriores' => $permisosActuales,
                    'nuevos' => $permisosNuevos
                ];
            }

            // Registrar cambios en historial
            if (!empty($cambiosRealizados)) {
                HistorialCredenciales::registrarAccion($usuario->id, 'USUARIO_ACTUALIZADO', [
                    'actualizado_por' => Auth::id(),
                    'cambios' => $cambiosRealizados,
                    'ip_actualizacion' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
            }

            DB::commit();

            Log::info('Usuario actualizado por administrador', [
                'usuario_actualizado' => $usuario->email,
                'admin_actualizador' => Auth::user()->email,
                'cambios' => $cambiosRealizados,
                'ip' => $request->ip()
            ]);

            return redirect()->route('admin.credenciales.editar', $usuario)
                           ->with('success', 'Usuario actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al actualizar usuario', [
                'error' => $e->getMessage(),
                'usuario_id' => $usuario->id,
                'admin' => Auth::user()->email
            ]);

            return back()->with('error', 'Error al actualizar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Cambiar contraseña de usuario
     */
    public function cambiarPassword(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'nueva_password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()],
            'forzar_cambio_siguiente_acceso' => 'boolean',
            'notificar_usuario' => 'boolean'
        ]);

        DB::beginTransaction();

        try {
            $passwordAnterior = $usuario->password;

            $usuario->update([
                'password' => Hash::make($validated['nueva_password']),
                'debe_cambiar_password' => $validated['forzar_cambio_siguiente_acceso'] ?? false,
                'fecha_cambio_password' => now()
            ]);

            // Registrar cambio de contraseña
            HistorialCredenciales::registrarAccion($usuario->id, 'PASSWORD_CAMBIADA', [
                'cambiada_por_admin' => Auth::id(),
                'forzar_cambio_siguiente' => $validated['forzar_cambio_siguiente_acceso'] ?? false,
                'notificacion_enviada' => $validated['notificar_usuario'] ?? false,
                'ip_cambio' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Invalidar todas las sesiones del usuario
            $this->invalidarSesionesUsuario($usuario);

            DB::commit();

            // Notificar al usuario si se solicitó
            if ($validated['notificar_usuario'] ?? false) {
                $this->notificarCambioPassword($usuario);
            }

            Log::warning('Contraseña cambiada por administrador', [
                'usuario_afectado' => $usuario->email,
                'admin_ejecutor' => Auth::user()->email,
                'forzar_cambio' => $validated['forzar_cambio_siguiente_acceso'] ?? false,
                'ip' => $request->ip()
            ]);

            return back()->with('success', 'Contraseña actualizada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al cambiar contraseña', [
                'error' => $e->getMessage(),
                'usuario_id' => $usuario->id,
                'admin' => Auth::user()->email
            ]);

            return back()->with('error', 'Error al cambiar contraseña: ' . $e->getMessage());
        }
    }

    /**
     * Suspender o activar usuario
     */
    public function toggleEstado(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'accion' => 'required|in:activar,suspender,bloquear,desbloquear',
            'motivo' => 'required_if:accion,suspender,bloquear|string|max:500',
            'duracion_suspension' => 'nullable|integer|min:1|max:365', // días
            'notificar_usuario' => 'boolean'
        ]);

        DB::beginTransaction();

        try {
            $estadoAnterior = [
                'activo' => $usuario->activo,
                'bloqueado' => $usuario->bloqueado
            ];

            switch ($validated['accion']) {
                case 'activar':
                    $usuario->update([
                        'activo' => true,
                        'bloqueado' => false,
                        'intentos_fallidos' => 0,
                        'fecha_suspension' => null
                    ]);
                    $mensaje = 'Usuario activado exitosamente';
                    break;

                case 'suspender':
                    $fechaSuspension = $validated['duracion_suspension'] ?? null
                        ? now()->addDays($validated['duracion_suspension'])
                        : null;

                    $usuario->update([
                        'activo' => false,
                        'fecha_suspension' => $fechaSuspension,
                        'motivo_suspension' => $validated['motivo']
                    ]);
                    $mensaje = 'Usuario suspendido exitosamente';
                    break;

                case 'bloquear':
                    $usuario->update([
                        'bloqueado' => true,
                        'activo' => false,
                        'fecha_bloqueo' => now(),
                        'motivo_bloqueo' => $validated['motivo']
                    ]);
                    $mensaje = 'Usuario bloqueado exitosamente';
                    break;

                case 'desbloquear':
                    $usuario->update([
                        'bloqueado' => false,
                        'activo' => true,
                        'intentos_fallidos' => 0,
                        'fecha_bloqueo' => null,
                        'motivo_bloqueo' => null
                    ]);
                    $mensaje = 'Usuario desbloqueado exitosamente';
                    break;
            }

            // Registrar acción en historial
            HistorialCredenciales::registrarAccion($usuario->id, 'ESTADO_CAMBIADO', [
                'accion_ejecutada' => $validated['accion'],
                'ejecutada_por' => Auth::id(),
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => [
                    'activo' => $usuario->activo,
                    'bloqueado' => $usuario->bloqueado
                ],
                'motivo' => $validated['motivo'] ?? null,
                'duracion_suspension' => $validated['duracion_suspension'] ?? null,
                'ip_accion' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Invalidar sesiones si se suspende o bloquea
            if (in_array($validated['accion'], ['suspender', 'bloquear'])) {
                $this->invalidarSesionesUsuario($usuario);
            }

            DB::commit();

            // Notificar al usuario si se solicitó
            if ($validated['notificar_usuario'] ?? false) {
                $this->notificarCambioEstado($usuario, $validated['accion'], $validated['motivo'] ?? null);
            }

            Log::warning("Usuario {$validated['accion']} por administrador", [
                'usuario_afectado' => $usuario->email,
                'admin_ejecutor' => Auth::user()->email,
                'motivo' => $validated['motivo'] ?? null,
                'ip' => $request->ip()
            ]);

            return back()->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al cambiar estado de usuario', [
                'error' => $e->getMessage(),
                'usuario_id' => $usuario->id,
                'accion' => $validated['accion'],
                'admin' => Auth::user()->email
            ]);

            return back()->with('error', 'Error al cambiar estado: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function eliminar(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'confirmacion' => 'required|in:CONFIRMAR_ELIMINACION',
            'motivo_eliminacion' => 'required|string|max:500',
            'transferir_datos_a' => 'nullable|exists:users,id'
        ]);

        // Verificar que no se elimine el último administrador
        if ($usuario->hasRole('admin') && User::role('admin')->count() <= 1) {
            return back()->with('error', 'No se puede eliminar el último administrador del sistema.');
        }

        DB::beginTransaction();

        try {
            // Registrar eliminación antes de ejecutarla
            HistorialCredenciales::registrarAccion($usuario->id, 'USUARIO_ELIMINADO', [
                'eliminado_por' => Auth::id(),
                'motivo' => $validated['motivo_eliminacion'],
                'datos_transferidos_a' => $validated['transferir_datos_a'] ?? null,
                'fecha_eliminacion' => now(),
                'ip_eliminacion' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'datos_usuario_backup' => $usuario->toArray()
            ]);

            // Transferir datos si se especificó
            if ($validated['transferir_datos_a']) {
                $this->transferirDatosUsuario($usuario->id, $validated['transferir_datos_a']);
            }

            // Invalidar todas las sesiones
            $this->invalidarSesionesUsuario($usuario);

            // Eliminar (soft delete)
            $usuario->delete();

            DB::commit();

            Log::critical('Usuario eliminado por administrador', [
                'usuario_eliminado' => $usuario->email,
                'admin_ejecutor' => Auth::user()->email,
                'motivo' => $validated['motivo_eliminacion'],
                'ip' => $request->ip()
            ]);

            return redirect()->route('admin.credenciales.index')
                           ->with('success', 'Usuario eliminado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al eliminar usuario', [
                'error' => $e->getMessage(),
                'usuario_id' => $usuario->id,
                'admin' => Auth::user()->email
            ]);

            return back()->with('error', 'Error al eliminar usuario: ' . $e->getMessage());
        }
    }

    /**
     * Ver historial completo de un usuario
     */
    public function historial(User $usuario)
    {
        $historial = $usuario->historialCredenciales()
                            ->with('administrador')
                            ->orderBy('created_at', 'desc')
                            ->paginate(20);

        $estadisticasUsuario = $this->obtenerEstadisticasUsuario($usuario);

        return view('admin.credenciales.historial', compact(
            'usuario',
            'historial',
            'estadisticasUsuario'
        ));
    }

    /**
     * Reporte de seguridad del sistema
     */
    public function reporteSeguridad(Request $request)
    {
        $periodo = $request->input('periodo', 30); // días
        $fechaInicio = now()->subDays($periodo);

        $reporte = [
            'periodo' => $periodo,
            'usuarios_activos' => User::where('activo', true)->count(),
            'usuarios_bloqueados' => User::where('bloqueado', true)->count(),
            'intentos_fallidos_recientes' => $this->contarIntentosFallidos($fechaInicio),
            'cambios_credenciales' => $this->contarCambiosCredenciales($fechaInicio),
            'accesos_administrativos' => $this->contarAccesosAdmin($fechaInicio),
            'alertas_seguridad' => $this->obtenerAlertasSeguridad(),
            'usuarios_inactivos' => $this->obtenerUsuariosInactivos(60), // 60 días sin acceso
            'passwords_debiles' => $this->identificarPasswordsDebiles(),
            'recomendaciones' => $this->generarRecomendacionesSeguridad()
        ];

        if ($request->wantsJson()) {
            return response()->json($reporte);
        }

        return view('admin.credenciales.reporte-seguridad', compact('reporte'));
    }

    // =============================================================================
    // MÉTODOS PRIVADOS DE APOYO
    // =============================================================================

    private function obtenerEstadisticasSeguridad()
    {
        return [
            'total_usuarios' => User::count(),
            'usuarios_activos' => User::where('activo', true)->count(),
            'usuarios_bloqueados' => User::where('bloqueado', true)->count(),
            'administradores' => User::role('admin')->count(),
            'intentos_fallidos_hoy' => User::where('intentos_fallidos', '>', 0)
                                         ->whereDate('updated_at', today())
                                         ->count(),
            'cambios_recientes' => HistorialCredenciales::where('created_at', '>=', now()->subHours(24))
                                                       ->count(),
            'usuarios_deben_cambiar_password' => User::where('debe_cambiar_password', true)->count()
        ];
    }

    private function obtenerIntentosAccesoRecientes()
    {
        return HistorialCredenciales::where('accion', 'LIKE', '%ACCESO%')
                                   ->where('created_at', '>=', now()->subHours(6))
                                   ->with('usuario')
                                   ->orderBy('created_at', 'desc')
                                   ->limit(10)
                                   ->get();
    }

    private function obtenerAlertasSeguridad()
    {
        $alertas = [];

        // Usuarios con muchos intentos fallidos
        $usuariosRiesgo = User::where('intentos_fallidos', '>=', 3)->count();
        if ($usuariosRiesgo > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'mensaje' => "{$usuariosRiesgo} usuarios con múltiples intentos de acceso fallidos",
                'accion' => 'Revisar y considerar bloqueo temporal'
            ];
        }

        // Administradores sin actividad reciente
        $adminsInactivos = User::role('admin')
                              ->where('fecha_ultimo_acceso', '<', now()->subDays(7))
                              ->count();
        if ($adminsInactivos > 0) {
            $alertas[] = [
                'tipo' => 'info',
                'mensaje' => "{$adminsInactivos} administradores sin actividad en 7 días",
                'accion' => 'Verificar necesidad de acceso administrativo'
            ];
        }

        // Usuarios que deben cambiar contraseña hace tiempo
        $passwordsVencidas = User::where('debe_cambiar_password', true)
                                ->where('updated_at', '<', now()->subDays(7))
                                ->count();
        if ($passwordsVencidas > 0) {
            $alertas[] = [
                'tipo' => 'danger',
                'mensaje' => "{$passwordsVencidas} usuarios no han cambiado contraseñas vencidas",
                'accion' => 'Forzar cambio de contraseña'
            ];
        }

        return $alertas;
    }

    private function invalidarSesionesUsuario(User $usuario)
    {
        // Invalidar todas las sesiones activas del usuario
        DB::table('sessions')
          ->where('user_id', $usuario->id)
          ->delete();
    }

    private function notificarCambioPassword(User $usuario)
    {
        // Implementar notificación de cambio de contraseña
        Log::info("Notificación de cambio de contraseña enviada", [
            'usuario' => $usuario->email
        ]);
    }

    private function notificarCambioEstado(User $usuario, $accion, $motivo)
    {
        // Implementar notificación de cambio de estado
        Log::info("Notificación de cambio de estado enviada", [
            'usuario' => $usuario->email,
            'accion' => $accion,
            'motivo' => $motivo
        ]);
    }

    private function transferirDatosUsuario($usuarioOrigenId, $usuarioDestinoId)
    {
        // Implementar transferencia de datos críticos
        Log::info("Transferencia de datos iniciada", [
            'origen' => $usuarioOrigenId,
            'destino' => $usuarioDestinoId
        ]);
    }

    private function obtenerEstadisticasUsuario(User $usuario)
    {
        return [
            'total_acciones' => $usuario->historialCredenciales()->count(),
            'ultimo_acceso' => $usuario->fecha_ultimo_acceso,
            'intentos_fallidos' => $usuario->intentos_fallidos,
            'dias_desde_creacion' => $usuario->created_at->diffInDays(now()),
            'cambios_password' => $usuario->historialCredenciales()
                                        ->where('accion', 'PASSWORD_CAMBIADA')
                                        ->count(),
            'roles_historicos' => $usuario->historialCredenciales()
                                        ->where('accion', 'USUARIO_ACTUALIZADO')
                                        ->get()
                                        ->pluck('datos.cambios.rol')
                                        ->filter()
                                        ->unique()
        ];
    }

    private function contarIntentosFallidos($fechaInicio)
    {
        return HistorialCredenciales::where('accion', 'ACCESO_FALLIDO')
                                   ->where('created_at', '>=', $fechaInicio)
                                   ->count();
    }

    private function contarCambiosCredenciales($fechaInicio)
    {
        return HistorialCredenciales::whereIn('accion', [
                                       'PASSWORD_CAMBIADA',
                                       'USUARIO_ACTUALIZADO',
                                       'ESTADO_CAMBIADO'
                                   ])
                                   ->where('created_at', '>=', $fechaInicio)
                                   ->count();
    }

    private function contarAccesosAdmin($fechaInicio)
    {
        return HistorialCredenciales::where('accion', 'ACCESO_EXITOSO')
                                   ->whereHas('usuario', function($query) {
                                       $query->role('admin');
                                   })
                                   ->where('created_at', '>=', $fechaInicio)
                                   ->count();
    }

    private function obtenerUsuariosInactivos($dias)
    {
        return User::where('fecha_ultimo_acceso', '<', now()->subDays($dias))
                   ->orWhereNull('fecha_ultimo_acceso')
                   ->where('activo', true)
                   ->get()
                   ->map(function($usuario) {
                       return [
                           'id' => $usuario->id,
                           'nombre' => $usuario->name,
                           'email' => $usuario->email,
                           'ultimo_acceso' => $usuario->fecha_ultimo_acceso,
                           'dias_inactivo' => $usuario->fecha_ultimo_acceso
                               ? $usuario->fecha_ultimo_acceso->diffInDays(now())
                               : 'Nunca'
                       ];
                   });
    }

    private function identificarPasswordsDebiles()
    {
        // Esta sería una implementación más compleja en producción
        return User::where('debe_cambiar_password', true)
                   ->orWhere('password_actualizada_en', '<', now()->subMonths(6))
                   ->count();
    }

    private function generarRecomendacionesSeguridad()
    {
        $recomendaciones = [];

        $usuariosInactivos = User::where('fecha_ultimo_acceso', '<', now()->subDays(30))->count();
        if ($usuariosInactivos > 0) {
            $recomendaciones[] = "Revisar {$usuariosInactivos} usuarios sin actividad en 30+ días";
        }

        $intentosAltos = User::where('intentos_fallidos', '>=', 5)->count();
        if ($intentosAltos > 0) {
            $recomendaciones[] = "Bloquear temporalmente {$intentosAltos} usuarios con intentos fallidos elevados";
        }

        $adminsExcesivos = User::role('admin')->count();
        if ($adminsExcesivos > 3) {
            $recomendaciones[] = "Evaluar necesidad de {$adminsExcesivos} cuentas administrativas";
        }

        return $recomendaciones ?: ['Sistema de credenciales operando dentro de parámetros normales'];
    }
}
