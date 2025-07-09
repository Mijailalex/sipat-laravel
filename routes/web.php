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
        Route::get('/chart-data', [DashboardController::class, 'getChartData'])->name('chart-data');
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
        Route::post('/ejecutar', [PlantillaController::class, 'ejecutar'])->name('ejecutar');
        Route::get('/historial', [PlantillaController::class, 'historial'])->name('historial');
        Route::post('/optimizar', [PlantillaController::class, 'optimizar'])->name('optimizar');

        // Gestión de plantillas
        Route::get('/plantillas', [PlantillaController::class, 'plantillas'])->name('plantillas');
        Route::post('/plantillas', [PlantillaController::class, 'guardarPlantilla'])->name('plantillas.guardar');
        Route::get('/plantillas/{plantilla}/editar', [PlantillaController::class, 'editarPlantilla'])->name('plantillas.editar');
        Route::put('/plantillas/{plantilla}', [PlantillaController::class, 'actualizarPlantilla'])->name('plantillas.actualizar');
        Route::delete('/plantillas/{plantilla}', [PlantillaController::class, 'eliminarPlantilla'])->name('plantillas.eliminar');

        // Validaciones de planificación
        Route::get('/validaciones', [PlantillaController::class, 'validaciones'])->name('validaciones');
        Route::post('/validaciones/ejecutar', [PlantillaController::class, 'ejecutarValidaciones'])->name('validaciones.ejecutar');

        // Replanificación
        Route::get('/replanificar', [ReplanificacionController::class, 'index'])->name('replanificar');
        Route::post('/replanificar/ejecutar', [ReplanificacionController::class, 'ejecutar'])->name('replanificar.ejecutar');
        Route::get('/replanificar/historial', [ReplanificacionController::class, 'historial'])->name('replanificar.historial');
    });

    // =============================================================================
    // SISTEMA DE VALIDACIONES
    // =============================================================================

    Route::prefix('validaciones')->name('validaciones.')->group(function () {
        Route::get('/', [ValidacionController::class, 'index'])->name('index');
        Route::get('/crear', [ValidacionController::class, 'crear'])->name('crear');
        Route::post('/', [ValidacionController::class, 'store'])->name('store');
        Route::get('/{validacion}', [ValidacionController::class, 'show'])->name('show');
        Route::put('/{validacion}', [ValidacionController::class, 'update'])->name('update');
        Route::delete('/{validacion}', [ValidacionController::class, 'destroy'])->name('destroy');

        // Acciones sobre validaciones
        Route::post('/{validacion}/resolver', [ValidacionController::class, 'resolver'])->name('resolver');
        Route::post('/{validacion}/ignorar', [ValidacionController::class, 'ignorar'])->name('ignorar');
        Route::post('/accion-masiva', [ValidacionController::class, 'accionMasiva'])->name('accion.masiva');

        // Filtros y búsquedas
        Route::get('/filtrar/{tipo}', [ValidacionController::class, 'filtrar'])->name('filtrar');
        Route::get('/buscar', [ValidacionController::class, 'buscar'])->name('buscar');
        Route::get('/exportar', [ValidacionController::class, 'exportar'])->name('exportar');

        // Configuración de validaciones
        Route::get('/configuracion', [ValidacionController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [ValidacionController::class, 'guardarConfiguracion'])->name('configuracion.guardar');
    });

    // =============================================================================
    // GESTIÓN DE RUTAS CORTAS
    // =============================================================================

    Route::prefix('rutas-cortas')->name('rutas-cortas.')->group(function () {
        Route::get('/', [RutaController::class, 'index'])->name('index');
        Route::get('/crear', [RutaController::class, 'crear'])->name('crear');
        Route::post('/', [RutaController::class, 'store'])->name('store');
        Route::get('/{ruta}', [RutaController::class, 'show'])->name('show');
        Route::get('/{ruta}/editar', [RutaController::class, 'edit'])->name('edit');
        Route::put('/{ruta}', [RutaController::class, 'update'])->name('update');
        Route::delete('/{ruta}', [RutaController::class, 'destroy'])->name('destroy');

        // Asignación de conductores
        Route::post('/{ruta}/asignar-conductor', [RutaController::class, 'asignarConductor'])->name('asignar.conductor');
        Route::post('/{ruta}/cambiar-conductor', [RutaController::class, 'cambiarConductor'])->name('cambiar.conductor');

        // Reportes y análisis
        Route::get('/reportes/conductor/{conductor}', [RutaController::class, 'reporteConductor'])->name('reporte.conductor');
        Route::get('/reportes/balance', [RutaController::class, 'reporteBalance'])->name('reporte.balance');
        Route::get('/analisis/rentabilidad', [RutaController::class, 'analisisRentabilidad'])->name('analisis.rentabilidad');

        // Configuración de tramos
        Route::get('/configuracion/tramos', [RutaController::class, 'configuracionTramos'])->name('configuracion.tramos');
        Route::post('/configuracion/tramos', [RutaController::class, 'guardarConfiguracionTramos'])->name('configuracion.tramos.guardar');
    });

    // =============================================================================
    // REPORTES AVANZADOS
    // =============================================================================

    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::get('/', [ReporteController::class, 'index'])->name('index');

        // Reportes de conductores
        Route::get('/conductores', [ReporteController::class, 'conductores'])->name('conductores');
        Route::get('/conductores/rendimiento', [ReporteController::class, 'rendimientoConductores'])->name('conductores.rendimiento');
        Route::get('/conductores/disponibilidad', [ReporteController::class, 'disponibilidadConductores'])->name('conductores.disponibilidad');
        Route::get('/conductores/descansos', [ReporteController::class, 'descansosConductores'])->name('conductores.descansos');

        // Reportes operativos
        Route::get('/operativos/cobertura', [ReporteController::class, 'coberturaOperativa'])->name('operativos.cobertura');
        Route::get('/operativos/eficiencia', [ReporteController::class, 'eficienciaOperativa'])->name('operativos.eficiencia');
        Route::get('/operativos/puntualidad', [ReporteController::class, 'puntualidadOperativa'])->name('operativos.puntualidad');

        // Reportes financieros
        Route::get('/financieros/ingresos', [ReporteController::class, 'reporteIngresos'])->name('financieros.ingresos');
        Route::get('/financieros/costos', [ReporteController::class, 'reporteCostos'])->name('financieros.costos');
        Route::get('/financieros/rentabilidad', [ReporteController::class, 'reporteRentabilidad'])->name('financieros.rentabilidad');

        // Reportes de validaciones
        Route::get('/validaciones/resumen', [ReporteController::class, 'resumenValidaciones'])->name('validaciones.resumen');
        Route::get('/validaciones/tendencias', [ReporteController::class, 'tendenciasValidaciones'])->name('validaciones.tendencias');

        // Exportación de reportes
        Route::post('/exportar/{tipo}', [ReporteController::class, 'exportar'])->name('exportar');
        Route::get('/programados', [ReporteController::class, 'reportesProgramados'])->name('programados');
        Route::post('/programar', [ReporteController::class, 'programarReporte'])->name('programar');
    });

    // =============================================================================
    // GESTIÓN DE NOTIFICACIONES
    // =============================================================================

    Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/', [NotificacionController::class, 'index'])->name('index');
        Route::get('/crear', [NotificacionController::class, 'crear'])->name('crear');
        Route::post('/', [NotificacionController::class, 'store'])->name('store');
        Route::get('/{notificacion}', [NotificacionController::class, 'show'])->name('show');
        Route::put('/{notificacion}', [NotificacionController::class, 'update'])->name('update');
        Route::delete('/{notificacion}', [NotificacionController::class, 'destroy'])->name('destroy');

        // Acciones sobre notificaciones
        Route::post('/{notificacion}/marcar-leida', [NotificacionController::class, 'marcarLeida'])->name('marcar.leida');
        Route::post('/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas'])->name('marcar.todas.leidas');
        Route::post('/limpiar-antiguas', [NotificacionController::class, 'limpiarAntiguas'])->name('limpiar.antiguas');

        // Configuración de notificaciones
        Route::get('/configuracion', [NotificacionController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [NotificacionController::class, 'guardarConfiguracion'])->name('configuracion.guardar');
        Route::get('/plantillas', [NotificacionController::class, 'plantillas'])->name('plantillas');
        Route::post('/plantillas', [NotificacionController::class, 'guardarPlantilla'])->name('plantillas.guardar');
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
        Route::post('/restaurar', [BackupController::class, 'restaurar'])->name('restaurar');
        Route::delete('/{backup}', [BackupController::class, 'eliminar'])->name('eliminar');
        Route::get('/{backup}/descargar', [BackupController::class, 'descargar'])->name('descargar');

        // Configuración de backups
        Route::get('/configuracion', [BackupController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [BackupController::class, 'guardarConfiguracion'])->name('configuracion.guardar');
        Route::post('/programar', [BackupController::class, 'programar'])->name('programar');
        Route::get('/historial', [BackupController::class, 'historial'])->name('historial');
        Route::post('/verificar-integridad', [BackupController::class, 'verificarIntegridad'])->name('verificar.integridad');
    });

    // =============================================================================
    // ADMINISTRACIÓN DE USUARIOS
    // =============================================================================

    Route::prefix('admin')->name('admin.')->group(function () {

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
            Route::get('/estado', [PlanificacionApiController::class, 'estado'])->name('estado');
            Route::get('/historial', [PlanificacionApiController::class, 'historial'])->name('historial');
        });

        // API de validaciones
        Route::apiResource('validaciones', ValidacionApiController::class);
        Route::post('/validaciones/{validacion}/resolver', [ValidacionApiController::class, 'resolver'])->name('validaciones.resolver');

        // API de rutas cortas
        Route::apiResource('rutas-cortas', RutaApiController::class);
        Route::post('/rutas-cortas/{ruta}/asignar', [RutaApiController::class, 'asignar'])->name('rutas-cortas.asignar');

        // API de reportes
        Route::prefix('reportes')->name('reportes.')->group(function () {
            Route::get('/conductores', [ReporteApiController::class, 'conductores'])->name('conductores');
            Route::get('/validaciones', [ReporteApiController::class, 'validaciones'])->name('validaciones');
            Route::get('/rutas-cortas', [ReporteApiController::class, 'rutasCortas'])->name('rutas-cortas');
        });

        // API de notificaciones
        Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
            Route::get('/', [NotificacionApiController::class, 'index'])->name('index');
            Route::post('/marcar-leida/{notificacion}', [NotificacionApiController::class, 'marcarLeida'])->name('marcar.leida');
        });
    });

    // =============================================================================
    // RUTAS DE ARCHIVOS Y DESCARGAS
    // =============================================================================

    Route::prefix('archivos')->name('archivos.')->group(function () {
        Route::get('/plantilla/{plantilla}/pdf', [ArchivoController::class, 'plantillaPDF'])->name('plantilla.pdf');
        Route::get('/plantilla/{plantilla}/excel', [ArchivoController::class, 'plantillaExcel'])->name('plantilla.excel');
        Route::get('/reporte/{reporte}/descargar', [ArchivoController::class, 'descargarReporte'])->name('reporte.descargar');
        Route::get('/backup/{backup}/descargar', [ArchivoController::class, 'descargarBackup'])->name('backup.descargar');
        Route::get('/logs/{fecha}/descargar', [ArchivoController::class, 'descargarLogs'])->name('logs.descargar');
    });

    // =============================================================================
    // RUTAS DE AYUDA Y DOCUMENTACIÓN
    // =============================================================================

    Route::prefix('ayuda')->name('ayuda.')->group(function () {
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
            Route::get('/test-db', [DevController::class, 'testDB'])->name('test.db');
            Route::get('/test-cache', [DevController::class, 'testCache'])->name('test.cache');
            Route::get('/test-queue', [DevController::class, 'testQueue'])->name('test.queue');
            Route::get('/generate-data', [DevController::class, 'generateData'])->name('generate.data');
            Route::get('/reset-system', [DevController::class, 'resetSystem'])->name('reset.system');
        });
    }
});

// =============================================================================
// RUTAS ADICIONALES PARA FUNCIONAMIENTO COMPLETO
// =============================================================================

// Ruta alternativa para chart-data (compatibilidad)
Route::middleware(['auth'])->get('/dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart-data');

// Rutas de fallback para desarrollo
Route::fallback(function () {
    return redirect()->route('dashboard');
});
