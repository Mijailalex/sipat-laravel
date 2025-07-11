<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\ValidacionController;
use App\Http\Controllers\ReplanificacionController;
use App\Http\Controllers\CredencialesController;
use App\Http\Controllers\ParametroController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RutaCortaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubempresaController;
use App\Http\Controllers\BusController;

/*
|--------------------------------------------------------------------------
| Rutas Web - Sistema SIPAT
|--------------------------------------------------------------------------
|
| Sistema Integral de Planificación Automatizada de Transportes
| Todas las rutas están protegidas por autenticación y permisos específicos
|
*/

// =============================================================================
// RUTA RAÍZ - REDIRECCIÓN
// =============================================================================

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

// =============================================================================
// RUTAS DE AUTENTICACIÓN
// =============================================================================

// Login y Logout
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Registro (solo si está habilitado)
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register')->middleware('guest');
Route::post('/register', [RegisterController::class, 'register'])->middleware('guest');

// Recuperación de contraseña
Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request')->middleware('guest');
Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('guest');
Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset')->middleware('guest');
Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('guest');

// =============================================================================
// RUTAS PROTEGIDAS POR AUTENTICACIÓN
// =============================================================================

Route::middleware(['auth'])->group(function () {

    // =============================================================================
    // DASHBOARD PRINCIPAL - RUTAS PRINCIPALES
    // =============================================================================

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/planificacion', [DashboardController::class, 'planificacion'])->name('dashboard.planificacion');
    Route::get('/dashboard/backups', [DashboardController::class, 'backups'])->name('dashboard.backups');
    Route::get('/dashboard/usuarios', [DashboardController::class, 'usuarios'])->name('dashboard.usuarios');

    // =============================================================================
    // APIS DEL DASHBOARD - NUEVAS RUTAS COMPLETAS
    // =============================================================================

    Route::prefix('api/dashboard')->name('api.dashboard.')->middleware(['throttle:dashboard'])->group(function () {

        // Métricas y datos principales
        Route::get('/metricas', [DashboardController::class, 'metricas'])->name('metricas');
        Route::get('/chart-data', [DashboardController::class, 'chartData'])->name('chart_data');
        Route::get('/alertas', [DashboardController::class, 'alertas'])->name('alertas');

        // Dashboard específicos por rol
        Route::get('/planner-data', [DashboardController::class, 'plannerData'])->name('planner_data');
        Route::get('/programmer-data', [DashboardController::class, 'programmerData'])->name('programmer_data');
        Route::get('/operator-data', [DashboardController::class, 'operatorData'])->name('operator_data');

        // Planificación automática
        Route::post('/ejecutar-algoritmo', [DashboardController::class, 'ejecutarAlgoritmo'])->name('ejecutar_algoritmo');
        Route::post('/crear-planificacion', [DashboardController::class, 'crearPlanificacion'])->name('crear_planificacion');
        Route::post('/exportar-planificacion', [DashboardController::class, 'exportarPlanificacion'])->name('exportar_planificacion');

        // Notificaciones
        Route::get('/notificaciones', [DashboardController::class, 'notificaciones'])->name('notificaciones');
        Route::put('/notificaciones/{id}/leer', [DashboardController::class, 'leerNotificacion'])->name('leer_notificacion');
        Route::put('/notificaciones/leer-todas', [DashboardController::class, 'leerTodasNotificaciones'])->name('leer_todas_notificaciones');

        // Estado del sistema
        Route::get('/estado-sistema', [DashboardController::class, 'estadoSistema'])->name('estado_sistema');
        Route::post('/limpiar-cache', [DashboardController::class, 'limpiarCache'])->name('limpiar_cache');

        // APIs de actualización automática
        Route::get('/actualizar-metricas', [DashboardController::class, 'actualizarMetricas'])->name('actualizar_metricas');
        Route::get('/heartbeat', function() {
            return response()->json(['status' => 'OK', 'timestamp' => now()->toISOString()]);
        })->name('heartbeat');

        // APIs adicionales para funcionalidades específicas
        Route::get('/conductores-criticos', [DashboardController::class, 'conductoresCriticos'])->name('conductores_criticos');
        Route::get('/validaciones-pendientes', [DashboardController::class, 'validacionesPendientes'])->name('validaciones_pendientes');
        Route::get('/rutas-hoy', [DashboardController::class, 'rutasHoy'])->name('rutas_hoy');
        Route::post('/ejecutar-mantenimiento', [DashboardController::class, 'ejecutarMantenimiento'])->name('ejecutar_mantenimiento');
    });

    // =============================================================================
    // GESTIÓN DE CONDUCTORES
    // =============================================================================

    Route::prefix('conductores')->name('conductores.')->group(function () {
        Route::get('/', [ConductorController::class, 'index'])->name('index');
        Route::get('/create', [ConductorController::class, 'create'])->name('create');
        Route::post('/', [ConductorController::class, 'store'])->name('store');
        Route::get('/{conductor}', [ConductorController::class, 'show'])->name('show');
        Route::get('/{conductor}/edit', [ConductorController::class, 'edit'])->name('edit');
        Route::put('/{conductor}', [ConductorController::class, 'update'])->name('update');
        Route::delete('/{conductor}', [ConductorController::class, 'destroy'])->name('destroy');

        // Acciones especiales sobre conductores
        Route::post('/{conductor}/activar', [ConductorController::class, 'activar'])->name('activar');
        Route::post('/{conductor}/descanso', [ConductorController::class, 'enviarDescanso'])->name('descanso');
        Route::post('/{conductor}/suspension', [ConductorController::class, 'suspender'])->name('suspension');
        Route::get('/{conductor}/historial', [ConductorController::class, 'historial'])->name('historial');
        Route::get('/{conductor}/metricas', [ConductorController::class, 'metricas'])->name('metricas');

        // Importación y exportación
        Route::get('/importar/formulario', [ConductorController::class, 'mostrarImportar'])->name('importar.form');
        Route::post('/importar', [ConductorController::class, 'importar'])->name('importar');
        Route::get('/exportar', [ConductorController::class, 'exportar'])->name('exportar');

        // APIs para AJAX
        Route::get('/api/disponibles', [ConductorController::class, 'disponibles'])->name('api.disponibles');
        Route::get('/api/buscar', [ConductorController::class, 'buscar'])->name('api.buscar');
        Route::post('/api/cambiar-estado', [ConductorController::class, 'cambiarEstado'])->name('api.cambiar_estado');
    });

    // =============================================================================
    // GESTIÓN DE VALIDACIONES
    // =============================================================================

    Route::prefix('validaciones')->name('validaciones.')->group(function () {
        Route::get('/', [ValidacionController::class, 'index'])->name('index');
        Route::get('/create', [ValidacionController::class, 'create'])->name('create');
        Route::post('/', [ValidacionController::class, 'store'])->name('store');
        Route::get('/{validacion}', [ValidacionController::class, 'show'])->name('show');
        Route::get('/{validacion}/edit', [ValidacionController::class, 'edit'])->name('edit');
        Route::put('/{validacion}', [ValidacionController::class, 'update'])->name('update');
        Route::delete('/{validacion}', [ValidacionController::class, 'destroy'])->name('destroy');

        // Acciones sobre validaciones
        Route::post('/{validacion}/resolver', [ValidacionController::class, 'resolver'])->name('resolver');
        Route::post('/{validacion}/ignorar', [ValidacionController::class, 'ignorar'])->name('ignorar');
        Route::post('/ejecutar-automaticas', [ValidacionController::class, 'ejecutarAutomaticas'])->name('ejecutar_automaticas');
        Route::get('/reporte/resumen', [ValidacionController::class, 'reporteResumen'])->name('reporte.resumen');

        // APIs
        Route::get('/api/pendientes', [ValidacionController::class, 'pendientes'])->name('api.pendientes');
        Route::get('/api/criticas', [ValidacionController::class, 'criticas'])->name('api.criticas');
    });

    // =============================================================================
    // GESTIÓN DE PLANIFICACIÓN
    // =============================================================================

    Route::prefix('planificacion')->name('planificacion.')->group(function () {
        Route::get('/', [PlantillaController::class, 'index'])->name('index');
        Route::get('/create', [PlantillaController::class, 'create'])->name('create');
        Route::post('/', [PlantillaController::class, 'store'])->name('store');
        Route::get('/{plantilla}', [PlantillaController::class, 'show'])->name('show');
        Route::get('/{plantilla}/edit', [PlantillaController::class, 'edit'])->name('edit');
        Route::put('/{plantilla}', [PlantillaController::class, 'update'])->name('update');
        Route::delete('/{plantilla}', [PlantillaController::class, 'destroy'])->name('destroy');

        // Funcionalidades especiales de planificación
        Route::get('/algoritmo/configurar', [PlantillaController::class, 'configurarAlgoritmo'])->name('algoritmo.configurar');
        Route::post('/algoritmo/ejecutar', [PlantillaController::class, 'ejecutarAlgoritmo'])->name('algoritmo.ejecutar');
        Route::get('/{plantilla}/duplicar', [PlantillaController::class, 'duplicar'])->name('duplicar');
        Route::post('/{plantilla}/aprobar', [PlantillaController::class, 'aprobar'])->name('aprobar');

        // Exportación de planificaciones
        Route::get('/{plantilla}/exportar/{formato}', [PlantillaController::class, 'exportar'])->name('exportar');
    });

    // =============================================================================
    // REPLANIFICACIÓN
    // =============================================================================

    Route::prefix('replanificacion')->name('replanificacion.')->group(function () {
        Route::get('/', [ReplanificacionController::class, 'index'])->name('index');
        Route::get('/create', [ReplanificacionController::class, 'create'])->name('create');
        Route::post('/', [ReplanificacionController::class, 'store'])->name('store');
        Route::get('/{replanificacion}', [ReplanificacionController::class, 'show'])->name('show');
        Route::put('/{replanificacion}', [ReplanificacionController::class, 'update'])->name('update');
        Route::delete('/{replanificacion}', [ReplanificacionController::class, 'destroy'])->name('destroy');

        // Funcionalidades especiales
        Route::post('/cambio-turno', [ReplanificacionController::class, 'cambioTurno'])->name('cambio_turno');
        Route::post('/emergencia', [ReplanificacionController::class, 'emergencia'])->name('emergencia');
        Route::get('/historial-cambios', [ReplanificacionController::class, 'historialCambios'])->name('historial_cambios');
    });

    // =============================================================================
    // RUTAS CORTAS
    // =============================================================================

    Route::prefix('rutas-cortas')->name('rutas_cortas.')->group(function () {
        Route::get('/', [RutaCortaController::class, 'index'])->name('index');
        Route::get('/create', [RutaCortaController::class, 'create'])->name('create');
        Route::post('/', [RutaCortaController::class, 'store'])->name('store');
        Route::get('/{rutaCorta}', [RutaCortaController::class, 'show'])->name('show');
        Route::get('/{rutaCorta}/edit', [RutaCortaController::class, 'edit'])->name('edit');
        Route::put('/{rutaCorta}', [RutaCortaController::class, 'update'])->name('update');
        Route::delete('/{rutaCorta}', [RutaCortaController::class, 'destroy'])->name('destroy');

        // Gestión de asignaciones
        Route::post('/{rutaCorta}/asignar', [RutaCortaController::class, 'asignar'])->name('asignar');
        Route::post('/{rutaCorta}/completar', [RutaCortaController::class, 'completar'])->name('completar');
        Route::get('/balance/conductores', [RutaCortaController::class, 'balanceConductores'])->name('balance');
    });

    // =============================================================================
    // GESTIÓN DE BUSES
    // =============================================================================

    Route::prefix('buses')->name('buses.')->group(function () {
        Route::get('/', [BusController::class, 'index'])->name('index');
        Route::get('/create', [BusController::class, 'create'])->name('create');
        Route::post('/', [BusController::class, 'store'])->name('store');
        Route::get('/{bus}', [BusController::class, 'show'])->name('show');
        Route::get('/{bus}/edit', [BusController::class, 'edit'])->name('edit');
        Route::put('/{bus}', [BusController::class, 'update'])->name('update');
        Route::delete('/{bus}', [BusController::class, 'destroy'])->name('destroy');

        // Gestión de mantenimiento
        Route::get('/{bus}/mantenimiento', [BusController::class, 'mantenimiento'])->name('mantenimiento');
        Route::post('/{bus}/mantenimiento', [BusController::class, 'programarMantenimiento'])->name('programar_mantenimiento');
        Route::get('/disponibles', [BusController::class, 'disponibles'])->name('disponibles');
    });

    // =============================================================================
    // SUBEMPRESAS
    // =============================================================================

    Route::prefix('subempresas')->name('subempresas.')->group(function () {
        Route::get('/', [SubempresaController::class, 'index'])->name('index');
        Route::get('/create', [SubempresaController::class, 'create'])->name('create');
        Route::post('/', [SubempresaController::class, 'store'])->name('store');
        Route::get('/{subempresa}', [SubempresaController::class, 'show'])->name('show');
        Route::get('/{subempresa}/edit', [SubempresaController::class, 'edit'])->name('edit');
        Route::put('/{subempresa}', [SubempresaController::class, 'update'])->name('update');
        Route::delete('/{subempresa}', [SubempresaController::class, 'destroy'])->name('destroy');

        // Gestión de asignaciones
        Route::get('/{subempresa}/asignaciones', [SubempresaController::class, 'asignaciones'])->name('asignaciones');
        Route::post('/{subempresa}/asignar-conductor', [SubempresaController::class, 'asignarConductor'])->name('asignar_conductor');
    });

    // =============================================================================
    // PARÁMETROS DEL SISTEMA
    // =============================================================================

    Route::prefix('parametros')->name('parametros.')->middleware(['role:admin'])->group(function () {
        Route::get('/', [ParametroController::class, 'index'])->name('index');
        Route::get('/create', [ParametroController::class, 'create'])->name('create');
        Route::post('/', [ParametroController::class, 'store'])->name('store');
        Route::get('/{parametro}', [ParametroController::class, 'show'])->name('show');
        Route::get('/{parametro}/edit', [ParametroController::class, 'edit'])->name('edit');
        Route::put('/{parametro}', [ParametroController::class, 'update'])->name('update');
        Route::delete('/{parametro}', [ParametroController::class, 'destroy'])->name('destroy');

        // Funcionalidades especiales
        Route::post('/importar-csv', [ParametroController::class, 'importarCSV'])->name('importar_csv');
        Route::get('/exportar', [ParametroController::class, 'exportar'])->name('exportar');
        Route::post('/restaurar-defecto', [ParametroController::class, 'restaurarDefecto'])->name('restaurar_defecto');
        Route::get('/validar-sistema', [ParametroController::class, 'validarSistema'])->name('validar_sistema');
    });

    // =============================================================================
    // HISTORIAL Y AUDITORÍA
    // =============================================================================

    Route::prefix('historial')->name('historial.')->group(function () {
        Route::get('/', [HistorialController::class, 'index'])->name('index');
        Route::get('/planificaciones', [HistorialController::class, 'planificaciones'])->name('planificaciones');
        Route::get('/validaciones', [HistorialController::class, 'validaciones'])->name('validaciones');
        Route::get('/conductores', [HistorialController::class, 'conductores'])->name('conductores');
        Route::get('/sistema', [HistorialController::class, 'sistema'])->name('sistema');
        Route::get('/{historial}', [HistorialController::class, 'show'])->name('show');

        // Exportación de historiales
        Route::get('/exportar/{tipo}', [HistorialController::class, 'exportar'])->name('exportar');
        Route::post('/limpiar-antiguos', [HistorialController::class, 'limpiarAntiguos'])->name('limpiar_antiguos');
    });

    // =============================================================================
    // REPORTES AVANZADOS
    // =============================================================================

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');
        Route::get('/conductores', [ReporteController::class, 'conductores'])->name('conductores');
        Route::get('/validaciones', [ReporteController::class, 'validaciones'])->name('validaciones');
        Route::get('/eficiencia', [ReporteController::class, 'eficiencia'])->name('eficiencia');
        Route::get('/rutas-cortas', [ReporteController::class, 'rutasCortas'])->name('rutas_cortas');
        Route::get('/ingresos', [ReporteController::class, 'ingresos'])->name('ingresos');

        // Generación de reportes
        Route::post('/generar', [ReporteController::class, 'generar'])->name('generar');
        Route::get('/descargar/{reporte}', [ReporteController::class, 'descargar'])->name('descargar');
        Route::get('/programados', [ReporteController::class, 'programados'])->name('programados');
        Route::post('/programar', [ReporteController::class, 'programar'])->name('programar');
    });

    // =============================================================================
    // BACKUPS Y MANTENIMIENTO
    // =============================================================================

    Route::prefix('backups')->name('backups.')->middleware(['role:admin'])->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/crear', [BackupController::class, 'crear'])->name('crear');
        Route::get('/{backup}/descargar', [BackupController::class, 'descargar'])->name('descargar');
        Route::delete('/{backup}', [BackupController::class, 'eliminar'])->name('eliminar');
        Route::post('/{backup}/restaurar', [BackupController::class, 'restaurar'])->name('restaurar');

        // Configuración de backups automáticos
        Route::get('/configuracion', [BackupController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [BackupController::class, 'guardarConfiguracion'])->name('guardar_configuracion');
        Route::post('/ejecutar-mantenimiento', [BackupController::class, 'ejecutarMantenimiento'])->name('ejecutar_mantenimiento');
    });

    // =============================================================================
    // NOTIFICACIONES
    // =============================================================================

    Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/', [NotificacionController::class, 'index'])->name('index');
        Route::post('/{notificacion}/leer', [NotificacionController::class, 'marcarComoLeida'])->name('marcar_leida');
        Route::post('/leer-todas', [NotificacionController::class, 'marcarTodasComoLeidas'])->name('marcar_todas_leidas');
        Route::delete('/{notificacion}', [NotificacionController::class, 'eliminar'])->name('eliminar');
        Route::get('/configuracion', [NotificacionController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [NotificacionController::class, 'guardarConfiguracion'])->name('guardar_configuracion');

        // APIs para notificaciones en tiempo real
        Route::get('/api/recientes', [NotificacionController::class, 'recientes'])->name('api.recientes');
        Route::get('/api/count', [NotificacionController::class, 'count'])->name('api.count');
    });

    // =============================================================================
    // CREDENCIALES Y USUARIOS (Solo Administradores)
    // =============================================================================

    Route::prefix('usuarios')->name('usuarios.')->middleware(['role:admin'])->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

        // Gestión de roles y permisos
        Route::get('/{user}/roles', [UserController::class, 'roles'])->name('roles');
        Route::post('/{user}/roles', [UserController::class, 'asignarRoles'])->name('asignar_roles');
        Route::get('/{user}/permisos', [UserController::class, 'permisos'])->name('permisos');
        Route::post('/{user}/activar', [UserController::class, 'activar'])->name('activar');
        Route::post('/{user}/desactivar', [UserController::class, 'desactivar'])->name('desactivar');
    });

    Route::prefix('credenciales')->name('credenciales.')->middleware(['role:admin'])->group(function () {
        Route::get('/', [CredencialesController::class, 'index'])->name('index');
        Route::get('/create', [CredencialesController::class, 'create'])->name('create');
        Route::post('/', [CredencialesController::class, 'store'])->name('store');
        Route::get('/{credencial}/edit', [CredencialesController::class, 'edit'])->name('edit');
        Route::put('/{credencial}', [CredencialesController::class, 'update'])->name('update');
        Route::delete('/{credencial}', [CredencialesController::class, 'destroy'])->name('destroy');

        // Funcionalidades especiales de seguridad
        Route::post('/generar-api-key', [CredencialesController::class, 'generarApiKey'])->name('generar_api_key');
        Route::post('/rotar-claves', [CredencialesController::class, 'rotarClaves'])->name('rotar_claves');
        Route::get('/auditoria', [CredencialesController::class, 'auditoria'])->name('auditoria');
    });

    // =============================================================================
    // PERFIL DE USUARIO
    // =============================================================================

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');

        // Cambio de contraseña
        Route::get('/change-password', [ProfileController::class, 'changePassword'])->name('change_password');
        Route::post('/change-password', [ProfileController::class, 'updatePassword'])->name('update_password');

        // Configuraciones personales
        Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');
        Route::post('/settings', [ProfileController::class, 'saveSettings'])->name('save_settings');
    });

    // =============================================================================
    // CONFIGURACIÓN GENERAL DEL SISTEMA
    // =============================================================================

    Route::prefix('settings')->name('settings.')->middleware(['role:admin'])->group(function () {
        Route::get('/', function () {
            return view('settings.index');
        })->name('index');

        Route::get('/general', function () {
            return view('settings.general');
        })->name('general');

        Route::get('/notifications', function () {
            return view('settings.notifications');
        })->name('notifications');

        Route::get('/security', function () {
            return view('settings.security');
        })->name('security');

        Route::get('/integrations', function () {
            return view('settings.integrations');
        })->name('integrations');
    });

});

// =============================================================================
// RUTAS DE API PÚBLICAS (Sin autenticación)
// =============================================================================

Route::prefix('api/public')->name('api.public.')->group(function () {
    // Estado del sistema (para monitoreo externo)
    Route::get('/health', function () {
        return response()->json([
            'status' => 'OK',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '2.0.0')
        ]);
    })->name('health');

    // Información básica del sistema
    Route::get('/info', function () {
        return response()->json([
            'name' => config('app.name'),
            'version' => config('app.version', '2.0.0'),
            'environment' => app()->environment(),
            'maintenance' => app()->isDownForMaintenance()
        ]);
    })->name('info');
});

// =============================================================================
// RUTAS DE DESARROLLO (Solo en ambiente local)
// =============================================================================

if (app()->environment('local')) {
    Route::prefix('dev')->name('dev.')->group(function () {

        // Herramientas de desarrollo
        Route::get('/phpinfo', function () {
            return phpinfo();
        })->name('phpinfo');

        // Test de notificaciones
        Route::get('/test-notification', function () {
            $notificationService = app(\App\Services\NotificacionService::class);
            return $notificationService->enviarPrueba(auth()->user());
        })->name('test_notification');

        // Limpiar cache completo
        Route::get('/clear-cache', function () {
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            \Artisan::call('view:clear');
            \Artisan::call('route:clear');

            return response()->json([
                'message' => 'Cache limpiado completamente',
                'commands' => ['cache:clear', 'config:clear', 'view:clear', 'route:clear']
            ]);
        })->name('clear_cache');

        // Ejecutar migraciones
        Route::get('/migrate', function () {
            \Artisan::call('migrate', ['--force' => true]);
            return response()->json(['message' => 'Migraciones ejecutadas']);
        })->name('migrate');

        // Ejecutar seeders
        Route::get('/seed', function () {
            \Artisan::call('db:seed');
            return response()->json(['message' => 'Seeders ejecutados']);
        })->name('seed');

        // Estado detallado del sistema
        Route::get('/system-status', function () {
            return response()->json([
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_usage' => memory_get_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'database' => \DB::select('SELECT VERSION() as version')[0]->version ?? 'N/A',
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'queue_driver' => config('queue.default')
            ]);
        })->name('system_status');
    });
}

// =============================================================================
// RUTAS DE FALLBACK
// =============================================================================

// Página 404 personalizada
Route::fallback(function () {
    if (request()->expectsJson()) {
        return response()->json([
            'message' => 'Endpoint no encontrado',
            'error' => 'NOT_FOUND'
        ], 404);
    }

    return response()->view('errors.404', [], 404);
});

// =============================================================================
// RUTAS PARA MANTENIMIENTO Y COMANDOS ARTISAN
// =============================================================================

Route::prefix('artisan')->name('artisan.')->middleware(['auth', 'role:admin'])->group(function () {

    // Ejecutar comandos SIPAT
    Route::post('/planificacion', function () {
        \Artisan::call('sipat:planificacion-automatica');
        return response()->json(['message' => 'Planificación ejecutada', 'output' => \Artisan::output()]);
    })->name('planificacion');

    Route::post('/validaciones', function () {
        \Artisan::call('sipat:validaciones');
        return response()->json(['message' => 'Validaciones ejecutadas', 'output' => \Artisan::output()]);
    })->name('validaciones');

    Route::post('/backup', function () {
        \Artisan::call('sipat:backup');
        return response()->json(['message' => 'Backup ejecutado', 'output' => \Artisan::output()]);
    })->name('backup');

    Route::post('/mantenimiento', function () {
        \Artisan::call('sipat:mantenimiento');
        return response()->json(['message' => 'Mantenimiento ejecutado', 'output' => \Artisan::output()]);
    })->name('mantenimiento');

    Route::post('/setup', function () {
        \Artisan::call('sipat:setup', ['--force' => true]);
        return response()->json(['message' => 'Setup ejecutado', 'output' => \Artisan::output()]);
    })->name('setup');
});

// =============================================================================
// WEBHOOKS Y INTEGRACIONES EXTERNAS
// =============================================================================

Route::prefix('webhooks')->name('webhooks.')->group(function () {

    // Webhook para notificaciones externas
    Route::post('/notification', function (\Illuminate\Http\Request $request) {
        // Validar firma de seguridad
        $signature = $request->header('X-Signature');
        if (!hash_equals($signature, hash_hmac('sha256', $request->getContent(), config('app.webhook_secret')))) {
            abort(401);
        }

        // Procesar webhook
        $data = $request->json()->all();
        \Log::info('Webhook recibido', $data);

        return response()->json(['status' => 'processed']);
    })->name('notification');

    // Webhook para integraciones de GPS/Tracking
    Route::post('/tracking', function (\Illuminate\Http\Request $request) {
        // Implementar lógica de tracking de buses
        return response()->json(['status' => 'received']);
    })->name('tracking');

    // Webhook para sistemas de pago
    Route::post('/payment', function (\Illuminate\Http\Request $request) {
        // Implementar integración con sistemas de pago
        return response()->json(['status' => 'received']);
    })->name('payment');
});

// =============================================================================
// COMENTARIO FINAL
// =============================================================================

/*
 * NOTAS IMPORTANTES:
 *
 * 1. MIDDLEWARES NECESARIOS:
 *    - 'auth': Verificar autenticación
 *    - 'role:admin': Solo administradores
 *    - 'throttle:dashboard': Rate limiting para APIs del dashboard
 *
 * 2. CONTROLADORES A CREAR/COMPLETAR:
 *    - ProfileController
 *    - UserController
 *    - SubempresaController
 *    - BusController
 *    - NotificacionController
 *
 * 3. MIDDLEWARE A CREAR:
 *    - Role-based access control
 *    - Rate limiting personalizado
 *
 * 4. CONFIGURAR EN .ENV:
 *    - APP_VERSION=2.0.0
 *    - WEBHOOK_SECRET=tu_clave_secreta
 *
 * 5. COMANDOS ARTISAN REQUERIDOS:
 *    - php artisan route:list (verificar rutas)
 *    - php artisan route:cache (cachear en producción)
 */
