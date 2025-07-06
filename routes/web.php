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
use App\Http\Controllers\Auth\LoginController;

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
// RUTAS DE AUTENTICACIÓN
// =============================================================================

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// =============================================================================
// RUTAS PROTEGIDAS POR AUTENTICACIÓN
// =============================================================================

Route::middleware(['auth'])->group(function () {

    // =============================================================================
    // DASHBOARD PRINCIPAL
    // =============================================================================

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/planificacion', [DashboardController::class, 'planificacion'])->name('dashboard.planificacion');
    Route::get('/dashboard/backups', [DashboardController::class, 'backups'])->name('dashboard.backups');
    Route::get('/dashboard/usuarios', [DashboardController::class, 'usuarios'])->name('dashboard.usuarios');

    // APIs del dashboard
    Route::prefix('api/dashboard')->name('api.dashboard.')->group(function () {
        Route::post('/planificacion/ejecutar', [DashboardController::class, 'ejecutarPlanificacion'])->name('ejecutar.planificacion');
        Route::post('/backup/ejecutar', [DashboardController::class, 'ejecutarBackup'])->name('ejecutar.backup');
        Route::get('/estado-sistema', [DashboardController::class, 'estadoSistema'])->name('estado.sistema');
        Route::get('/metricas', [DashboardController::class, 'metricas'])->name('metricas');
        Route::post('/limpiar-cache', [DashboardController::class, 'limpiarCache'])->name('limpiar.cache');
    });

    // =============================================================================
    // GESTIÓN DE CONDUCTORES
    // =============================================================================

    Route::resource('conductores', ConductorController::class);
    Route::prefix('conductores')->name('conductores.')->group(function () {
        // Acciones masivas
        Route::post('/accion-masiva', [ConductorController::class, 'accionMasiva'])->name('accion.masiva');
        Route::post('/importar', [ConductorController::class, 'importar'])->name('importar');
        Route::get('/exportar', [ConductorController::class, 'exportar'])->name('exportar');

        // Gestión de estados
        Route::post('/{conductor}/cambiar-estado', [ConductorController::class, 'cambiarEstado'])->name('cambiar.estado');
        Route::post('/{conductor}/enviar-descanso', [ConductorController::class, 'enviarDescanso'])->name('enviar.descanso');
        Route::post('/{conductor}/actualizar-metricas', [ConductorController::class, 'actualizarMetricas'])->name('actualizar.metricas');

        // Reportes y análisis
        Route::get('/{conductor}/historial', [ConductorController::class, 'historial'])->name('historial');
        Route::get('/{conductor}/metricas', [ConductorController::class, 'metricas'])->name('metricas');
        Route::get('/reporte-rendimiento', [ConductorController::class, 'reporteRendimiento'])->name('reporte.rendimiento');
        Route::get('/analisis-disponibilidad', [ConductorController::class, 'analisisDisponibilidad'])->name('analisis.disponibilidad');
    });

    // =============================================================================
    // PLANIFICACIÓN AUTOMÁTICA
    // =============================================================================

    Route::prefix('planificacion')->name('planificacion.')->group(function () {
        Route::get('/', [PlantillaController::class, 'index'])->name('index');
        Route::get('/crear', [PlantillaController::class, 'crear'])->name('crear');
        Route::post('/generar-automatica', [PlantillaController::class, 'generarAutomatica'])->name('generar.automatica');
        Route::post('/generar-manual', [PlantillaController::class, 'generarManual'])->name('generar.manual');

        // Gestión de plantillas
        Route::get('/plantilla/{plantilla}', [PlantillaController::class, 'show'])->name('plantilla.show');
        Route::put('/plantilla/{plantilla}', [PlantillaController::class, 'update'])->name('plantilla.update');
        Route::delete('/plantilla/{plantilla}', [PlantillaController::class, 'destroy'])->name('plantilla.destroy');
        Route::post('/plantilla/{plantilla}/finalizar', [PlantillaController::class, 'finalizar'])->name('plantilla.finalizar');
        Route::post('/plantilla/{plantilla}/duplicar', [PlantillaController::class, 'duplicar'])->name('plantilla.duplicar');

        // Exportación de plantillas
        Route::get('/plantilla/{plantilla}/pdf', [PlantillaController::class, 'exportarPDF'])->name('plantilla.pdf');
        Route::get('/plantilla/{plantilla}/excel', [PlantillaController::class, 'exportarExcel'])->name('plantilla.excel');

        // Configuración de algoritmos
        Route::get('/configuracion', [PlantillaController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [PlantillaController::class, 'guardarConfiguracion'])->name('configuracion.guardar');

        // Simulaciones y pruebas
        Route::post('/simular', [PlantillaController::class, 'simular'])->name('simular');
        Route::get('/historial-algoritmo', [PlantillaController::class, 'historialAlgoritmo'])->name('historial.algoritmo');
    });

    // =============================================================================
    // REPLANIFICACIÓN Y CAMBIOS
    // =============================================================================

    Route::prefix('replanificacion')->name('replanificacion.')->group(function () {
        Route::get('/', [ReplanificacionController::class, 'index'])->name('index');
        Route::get('/crear', [ReplanificacionController::class, 'crear'])->name('crear');

        // Replanificación de turnos individuales
        Route::post('/turno/{turno}/reasignar', [ReplanificacionController::class, 'reasignarTurno'])->name('turno.reasignar');
        Route::post('/turno/{turno}/cancelar', [ReplanificacionController::class, 'cancelarTurno'])->name('turno.cancelar');
        Route::post('/ejecutar-turno', [ReplanificacionController::class, 'ejecutarReplanificacionTurno'])->name('ejecutar.turno');

        // Replanificación automática
        Route::post('/automatica', [ReplanificacionController::class, 'replanificarAutomatico'])->name('automatica');
        Route::get('/sugerencias/{plantilla}', [ReplanificacionController::class, 'obtenerSugerencias'])->name('sugerencias');

        // Gestión de emergencias
        Route::post('/emergencia', [ReplanificacionController::class, 'replanificacionEmergencia'])->name('emergencia');
        Route::get('/conductores-emergencia', [ReplanificacionController::class, 'conductoresEmergencia'])->name('conductores.emergencia');

        // Historial y backup
        Route::get('/historial/{plantilla}', [ReplanificacionController::class, 'historialCambios'])->name('historial');
        Route::get('/backup', [ReplanificacionController::class, 'gestionBackup'])->name('backup');
        Route::post('/backup/crear', [ReplanificacionController::class, 'crearBackup'])->name('backup.crear');
        Route::post('/backup/restaurar', [ReplanificacionController::class, 'restaurarBackup'])->name('backup.restaurar');
    });

    // =============================================================================
    // VALIDACIONES Y CONTROL DE CALIDAD
    // =============================================================================

    Route::prefix('validaciones')->name('validaciones.')->group(function () {
        Route::get('/', [ValidacionController::class, 'index'])->name('index');
        Route::get('/{validacion}', [ValidacionController::class, 'show'])->name('show');
        Route::post('/{validacion}/resolver', [ValidacionController::class, 'resolver'])->name('resolver');
        Route::post('/{validacion}/ignorar', [ValidacionController::class, 'ignorar'])->name('ignorar');

        // Gestión masiva de validaciones
        Route::post('/resolver-masivo', [ValidacionController::class, 'resolverMasivo'])->name('resolver.masivo');
        Route::post('/ignorar-masivo', [ValidacionController::class, 'ignorarMasivo'])->name('ignorar.masivo');
        Route::post('/ejecutar-todas', [ValidacionController::class, 'ejecutarTodasValidaciones'])->name('ejecutar.todas');

        // Configuración de validaciones
        Route::get('/configuracion/reglas', [ValidacionController::class, 'configuracionReglas'])->name('configuracion.reglas');
        Route::post('/configuracion/reglas', [ValidacionController::class, 'guardarReglas'])->name('configuracion.reglas.guardar');

        // Reportes de validaciones
        Route::get('/reporte/eficiencia', [ValidacionController::class, 'reporteEficiencia'])->name('reporte.eficiencia');
        Route::get('/reporte/tendencias', [ValidacionController::class, 'reporteTendencias'])->name('reporte.tendencias');
    });

    // =============================================================================
    // HISTORIAL Y AUDITORÍA
    // =============================================================================

    Route::prefix('historial')->name('historial.')->group(function () {
        // Historial de planificaciones
        Route::get('/planificaciones', [HistorialController::class, 'planificaciones'])->name('planificaciones');
        Route::get('/planificacion/{historial}', [HistorialController::class, 'detallePlanificacion'])->name('planificacion.detalle');
        Route::get('/planificacion/{historial}/metricas', [HistorialController::class, 'metricasPlanificacion'])->name('planificacion.metricas');

        // Historial de cambios en conductores
        Route::get('/conductores', [HistorialController::class, 'conductores'])->name('conductores');
        Route::get('/conductor/{conductor}/cambios', [HistorialController::class, 'cambiosConductor'])->name('conductor.cambios');

        // Historial de credenciales y seguridad
        Route::get('/seguridad', [HistorialController::class, 'seguridad'])->name('seguridad');
        Route::get('/accesos', [HistorialController::class, 'accesos'])->name('accesos');

        // Análisis y reportes de historial
        Route::get('/analisis/tendencias', [HistorialController::class, 'analisisTendencias'])->name('analisis.tendencias');
        Route::get('/analisis/rendimiento', [HistorialController::class, 'analisisRendimiento'])->name('analisis.rendimiento');
        Route::get('/exportar/{tipo}', [HistorialController::class, 'exportar'])->name('exportar');
    });

    // =============================================================================
    // CONFIGURACIÓN DEL SISTEMA
    // =============================================================================

    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        // Parámetros del sistema
        Route::get('/parametros', [ParametroController::class, 'index'])->name('parametros');
        Route::post('/parametros', [ParametroController::class, 'actualizar'])->name('parametros.actualizar');
        Route::post('/parametros/importar', [ParametroController::class, 'importar'])->name('parametros.importar');
        Route::get('/parametros/exportar', [ParametroController::class, 'exportar'])->name('parametros.exportar');
        Route::post('/parametros/resetear', [ParametroController::class, 'resetear'])->name('parametros.resetear');

        // Configuración de algoritmos
        Route::get('/algoritmos', [ParametroController::class, 'algoritmos'])->name('algoritmos');
        Route::post('/algoritmos', [ParametroController::class, 'guardarAlgoritmos'])->name('algoritmos.guardar');

        // Configuración de notificaciones
        Route::get('/notificaciones', [ParametroController::class, 'notificaciones'])->name('notificaciones');
        Route::post('/notificaciones', [ParametroController::class, 'guardarNotificaciones'])->name('notificaciones.guardar');

        // Mantenimiento del sistema
        Route::get('/mantenimiento', [ParametroController::class, 'mantenimiento'])->name('mantenimiento');
        Route::post('/mantenimiento/ejecutar', [ParametroController::class, 'ejecutarMantenimiento'])->name('mantenimiento.ejecutar');
        Route::post('/cache/limpiar', [ParametroController::class, 'limpiarCache'])->name('cache.limpiar');
    });

    // =============================================================================
    // GESTIÓN DE BACKUPS
    // =============================================================================

    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/crear', [BackupController::class, 'crear'])->name('crear');
        Route::get('/{backup}/descargar', [BackupController::class, 'descargar'])->name('descargar');
        Route::delete('/{backup}', [BackupController::class, 'eliminar'])->name('eliminar');

        // Restauración de backups
        Route::get('/restaurar', [BackupController::class, 'mostrarRestaurar'])->name('restaurar');
        Route::post('/restaurar', [BackupController::class, 'ejecutarRestaurar'])->name('restaurar.ejecutar');
        Route::get('/restaurar/{backup}/preview', [BackupController::class, 'previewRestaurar'])->name('restaurar.preview');

        // Configuración de backups automáticos
        Route::get('/configuracion', [BackupController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [BackupController::class, 'guardarConfiguracion'])->name('configuracion.guardar');

        // Monitoreo y estadísticas
        Route::get('/estadisticas', [BackupController::class, 'estadisticas'])->name('estadisticas');
        Route::get('/logs', [BackupController::class, 'logs'])->name('logs');
    });

    // =============================================================================
    // REPORTES AVANZADOS
    // =============================================================================

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');

        // Reportes de conductores
        Route::get('/conductores/rendimiento', [ReporteController::class, 'rendimientoConductores'])->name('conductores.rendimiento');
        Route::get('/conductores/disponibilidad', [ReporteController::class, 'disponibilidadConductores'])->name('conductores.disponibilidad');
        Route::get('/conductores/eficiencia', [ReporteController::class, 'eficienciaConductores'])->name('conductores.eficiencia');

        // Reportes de planificación
        Route::get('/planificacion/cobertura', [ReporteController::class, 'coberturaPlanificacion'])->name('planificacion.cobertura');
        Route::get('/planificacion/optimizacion', [ReporteController::class, 'optimizacionPlanificacion'])->name('planificacion.optimizacion');
        Route::get('/planificacion/tendencias', [ReporteController::class, 'tendenciasPlanificacion'])->name('planificacion.tendencias');

        // Reportes ejecutivos
        Route::get('/ejecutivo/mensual', [ReporteController::class, 'ejecutivoMensual'])->name('ejecutivo.mensual');
        Route::get('/ejecutivo/trimestral', [ReporteController::class, 'ejecutivoTrimestral'])->name('ejecutivo.trimestral');
        Route::get('/ejecutivo/anual', [ReporteController::class, 'ejecutivoAnual'])->name('ejecutivo.anual');

        // Exportación de reportes
        Route::post('/exportar', [ReporteController::class, 'exportar'])->name('exportar');
        Route::get('/programados', [ReporteController::class, 'programados'])->name('programados');
        Route::post('/programar', [ReporteController::class, 'programar'])->name('programar');
    });

});

// =============================================================================
// RUTAS EXCLUSIVAS PARA ADMINISTRADORES
// =============================================================================

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    // Gestión de credenciales y usuarios
    Route::prefix('credenciales')->name('credenciales.')->group(function () {
        Route::get('/', [CredencialesController::class, 'index'])->name('index');
        Route::get('/crear', [CredencialesController::class, 'crear'])->name('crear');
        Route::post('/', [CredencialesController::class, 'almacenar'])->name('almacenar');
        Route::get('/{usuario}/editar', [CredencialesController::class, 'editar'])->name('editar');
        Route::put('/{usuario}', [CredencialesController::class, 'actualizar'])->name('actualizar');
        Route::delete('/{usuario}', [CredencialesController::class, 'eliminar'])->name('eliminar');

        // Gestión de contraseñas
        Route::post('/{usuario}/password', [CredencialesController::class, 'cambiarPassword'])->name('cambiar.password');
        Route::post('/{usuario}/reset-password', [CredencialesController::class, 'resetPassword'])->name('reset.password');

        // Gestión de estados de usuario
        Route::post('/{usuario}/toggle-estado', [CredencialesController::class, 'toggleEstado'])->name('toggle.estado');
        Route::post('/{usuario}/bloquear', [CredencialesController::class, 'bloquear'])->name('bloquear');
        Route::post('/{usuario}/desbloquear', [CredencialesController::class, 'desbloquear'])->name('desbloquear');

        // Historial y auditoría
        Route::get('/{usuario}/historial', [CredencialesController::class, 'historial'])->name('historial');
        Route::get('/reporte-seguridad', [CredencialesController::class, 'reporteSeguridad'])->name('reporte.seguridad');
        Route::get('/exportar-auditoria', [CredencialesController::class, 'exportarAuditoria'])->name('exportar.auditoria');
    });

    // Gestión de roles y permisos
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RolController::class, 'index'])->name('index');
        Route::post('/', [RolController::class, 'store'])->name('store');
        Route::get('/{rol}/editar', [RolController::class, 'edit'])->name('edit');
        Route::put('/{rol}', [RolController::class, 'update'])->name('update');
        Route::delete('/{rol}', [RolController::class, 'destroy'])->name('destroy');

        Route::get('/permisos', [RolController::class, 'permisos'])->name('permisos');
        Route::post('/permisos', [RolController::class, 'guardarPermisos'])->name('permisos.guardar');
    });

    // Configuración avanzada del sistema
    Route::prefix('sistema')->name('sistema.')->group(function () {
        Route::get('/configuracion-avanzada', [SistemaController::class, 'configuracionAvanzada'])->name('configuracion.avanzada');
        Route::post('/configuracion-avanzada', [SistemaController::class, 'guardarConfiguracionAvanzada'])->name('configuracion.avanzada.guardar');

        Route::get('/logs-sistema', [SistemaController::class, 'logsSistema'])->name('logs');
        Route::get('/monitoreo', [SistemaController::class, 'monitoreo'])->name('monitoreo');
        Route::get('/diagnosticos', [SistemaController::class, 'diagnosticos'])->name('diagnosticos');

        Route::post('/reiniciar-servicios', [SistemaController::class, 'reiniciarServicios'])->name('reiniciar.servicios');
        Route::post('/limpiar-sistema', [SistemaController::class, 'limpiarSistema'])->name('limpiar.sistema');
    });

    // Gestión de base de datos
    Route::prefix('database')->name('database.')->group(function () {
        Route::get('/estado', [DatabaseController::class, 'estado'])->name('estado');
        Route::get('/optimizar', [DatabaseController::class, 'optimizar'])->name('optimizar');
        Route::post('/optimizar/ejecutar', [DatabaseController::class, 'ejecutarOptimizacion'])->name('optimizar.ejecutar');

        Route::get('/migraciones', [DatabaseController::class, 'migraciones'])->name('migraciones');
        Route::post('/migraciones/ejecutar', [DatabaseController::class, 'ejecutarMigraciones'])->name('migraciones.ejecutar');

        Route::get('/semillas', [DatabaseController::class, 'semillas'])->name('semillas');
        Route::post('/semillas/ejecutar', [DatabaseController::class, 'ejecutarSemillas'])->name('semillas.ejecutar');
    });
});

// =============================================================================
// RUTAS DE API PÚBLICAS (CON AUTENTICACIÓN)
// =============================================================================

Route::middleware(['auth:sanctum'])->prefix('api/v1')->name('api.v1.')->group(function () {

    // API de conductores
    Route::apiResource('conductores', ConductorApiController::class);
    Route::get('/conductores/{conductor}/metricas', [ConductorApiController::class, 'metricas'])->name('conductores.metricas');
    Route::get('/conductores-disponibles', [ConductorApiController::class, 'disponibles'])->name('conductores.disponibles');

    // API de planificación
    Route::prefix('planificacion')->name('planificacion.')->group(function () {
        Route::post('/ejecutar', [PlanificacionApiController::class, 'ejecutar'])->name('ejecutar');
        Route::get('/estado/{plantilla}', [PlanificacionApiController::class, 'estado'])->name('estado');
        Route::post('/simular', [PlanificacionApiController::class, 'simular'])->name('simular');
    });

    // API de validaciones
    Route::apiResource('validaciones', ValidacionApiController::class)->only(['index', 'show', 'update']);
    Route::post('/validaciones/ejecutar', [ValidacionApiController::class, 'ejecutar'])->name('validaciones.ejecutar');

    // API de métricas y reportes
    Route::prefix('metricas')->name('metricas.')->group(function () {
        Route::get('/dashboard', [MetricasApiController::class, 'dashboard'])->name('dashboard');
        Route::get('/conductores', [MetricasApiController::class, 'conductores'])->name('conductores');
        Route::get('/planificacion', [MetricasApiController::class, 'planificacion'])->name('planificacion');
        Route::get('/sistema', [MetricasApiController::class, 'sistema'])->name('sistema');
    });
});

// =============================================================================
// RUTAS DE WEBHOOK Y NOTIFICACIONES
// =============================================================================

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/planificacion-completada', [WebhookController::class, 'planificacionCompletada'])->name('planificacion.completada');
    Route::post('/backup-realizado', [WebhookController::class, 'backupRealizado'])->name('backup.realizado');
    Route::post('/alerta-seguridad', [WebhookController::class, 'alertaSeguridad'])->name('alerta.seguridad');
});

// =============================================================================
// RUTAS DE ARCHIVOS Y DESCARGAS
// =============================================================================

Route::middleware(['auth'])->prefix('archivos')->name('archivos.')->group(function () {
    Route::get('/plantilla/{plantilla}/pdf', [ArchivoController::class, 'plantillaPDF'])->name('plantilla.pdf');
    Route::get('/plantilla/{plantilla}/excel', [ArchivoController::class, 'plantillaExcel'])->name('plantilla.excel');
    Route::get('/reporte/{reporte}/descargar', [ArchivoController::class, 'descargarReporte'])->name('reporte.descargar');
    Route::get('/backup/{backup}/descargar', [ArchivoController::class, 'descargarBackup'])->name('backup.descargar');
    Route::get('/logs/{fecha}/descargar', [ArchivoController::class, 'descargarLogs'])->name('logs.descargar');
});

// =============================================================================
// RUTAS DE AYUDA Y DOCUMENTACIÓN
// =============================================================================

Route::middleware(['auth'])->prefix('ayuda')->name('ayuda.')->group(function () {
    Route::get('/', [AyudaController::class, 'index'])->name('index');
    Route::get('/manual-usuario', [AyudaController::class, 'manualUsuario'])->name('manual.usuario');
    Route::get('/manual-administrador', [AyudaController::class, 'manualAdministrador'])->name('manual.administrador');
    Route::get('/faq', [AyudaController::class, 'faq'])->name('faq');
    Route::get('/soporte', [AyudaController::class, 'soporte'])->name('soporte');
    Route::post('/ticket-soporte', [AyudaController::class, 'crearTicketSoporte'])->name('ticket.soporte');
});

// =============================================================================
// RUTAS DE DESARROLLO (SOLO EN ENTORNO LOCAL)
// =============================================================================

if (app()->environment('local')) {
    Route::prefix('dev')->name('dev.')->group(function () {
        Route::get('/test-planificacion', [DevController::class, 'testPlanificacion'])->name('test.planificacion');
        Route::get('/test-backup', [DevController::class, 'testBackup'])->name('test.backup');
        Route::get('/test-notificaciones', [DevController::class, 'testNotificaciones'])->name('test.notificaciones');
        Route::get('/generar-datos-prueba', [DevController::class, 'generarDatosPrueba'])->name('generar.datos.prueba');
        Route::get('/limpiar-datos-prueba', [DevController::class, 'limpiarDatosPrueba'])->name('limpiar.datos.prueba');
    });
}

// =============================================================================
// REDIRECCIÓN DE RUTAS LEGACY
// =============================================================================

// Redirecciones para compatibilidad con versiones anteriores
Route::redirect('/home', '/dashboard', 301);
Route::redirect('/admin', '/admin/credenciales', 301);
Route::redirect('/planificar', '/planificacion', 301);
Route::redirect('/configuracion', '/configuracion/parametros', 301);

// =============================================================================
// FALLBACK PARA RUTAS NO ENCONTRADAS
// =============================================================================

Route::fallback(function () {
    return view('errors.404')->with('message', 'La página solicitada no existe en el sistema SIPAT.');
});
