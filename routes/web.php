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

        // SOLUCIÓN: Agregar ruta faltante que esperan las vistas
        Route::get('/export', [ConductorController::class, 'exportar'])->name('export');
        Route::get('/plantilla', [ConductorController::class, 'plantillaImportacion'])->name('plantilla');

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
    // GESTIÓN DE PARÁMETROS - RUTAS DIRECTAS QUE ESPERAN LAS VISTAS
    // =============================================================================

    Route::prefix('parametros')->name('parametros.')->group(function () {
        Route::get('/', [ParametroController::class, 'index'])->name('index');
        Route::get('/create', [ParametroController::class, 'create'])->name('create');
        Route::post('/', [ParametroController::class, 'store'])->name('store');
        Route::get('/{parametro}', [ParametroController::class, 'show'])->name('show');
        Route::get('/{parametro}/edit', [ParametroController::class, 'edit'])->name('edit');
        Route::put('/{parametro}', [ParametroController::class, 'update'])->name('update');
        Route::delete('/{parametro}', [ParametroController::class, 'destroy'])->name('destroy');

        Route::post('/actualizar', [ParametroController::class, 'actualizar'])->name('actualizar');
        Route::post('/importar', [ParametroController::class, 'importar'])->name('importar');
        Route::get('/exportar', [ParametroController::class, 'exportar'])->name('exportar');
        Route::post('/resetear', [ParametroController::class, 'resetear'])->name('resetear');

        // SOLUCIÓN: Agregar rutas faltantes que esperan las vistas
        Route::get('/plantilla', [ParametroController::class, 'plantilla'])->name('plantilla');
        Route::post('/validar', [ParametroController::class, 'validarConfiguracion'])->name('validar');
        Route::post('/{parametro}/restaurar-defecto', [ParametroController::class, 'restaurarDefecto'])->name('restaurar.defecto');

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
    // GESTIÓN DE PLANTILLAS
    // =============================================================================

    Route::resource('plantillas', PlantillaController::class);
    Route::prefix('plantillas')->name('plantillas.')->group(function () {
        // Gestión de plantillas
        Route::post('/guardar', [PlantillaController::class, 'guardarPlantilla'])->name('guardar');
        Route::get('/{plantilla}/editar', [PlantillaController::class, 'editarPlantilla'])->name('editar');
        Route::put('/{plantilla}/actualizar', [PlantillaController::class, 'actualizarPlantilla'])->name('actualizar');
        Route::delete('/{plantilla}/eliminar', [PlantillaController::class, 'eliminarPlantilla'])->name('eliminar');

        // Operaciones de planificación
        Route::post('/ejecutar', [PlantillaController::class, 'ejecutar'])->name('ejecutar');
        Route::get('/historial', [PlantillaController::class, 'historial'])->name('historial');
        Route::post('/optimizar', [PlantillaController::class, 'optimizar'])->name('optimizar');

        // APIs de plantillas
        Route::get('/api/index', [PlantillaController::class, 'apiIndex'])->name('api.index');
        Route::get('/api/{id}', [PlantillaController::class, 'apiShow'])->name('api.show');
        Route::post('/api/preview', [PlantillaController::class, 'generarPreview'])->name('api.preview');
        Route::get('/api/mas-utilizadas', [PlantillaController::class, 'obtenerMasUtilizadas'])->name('api.mas-utilizadas');
    });

    // =============================================================================
    // SISTEMA DE VALIDACIONES
    // =============================================================================

    Route::resource('validaciones', ValidacionController::class);
    Route::prefix('validaciones')->name('validaciones.')->group(function () {
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

        // Ejecución de validaciones
        Route::post('/ejecutar', [ValidacionController::class, 'ejecutarValidaciones'])->name('ejecutar');
    });

    // =============================================================================
    // PLANIFICACIÓN AUTOMÁTICA (GRUPO EXISTENTE MANTENIDO)
    // =============================================================================

    Route::prefix('planificacion')->name('planificacion.')->group(function () {
        Route::get('/', [PlantillaController::class, 'index'])->name('index');
        Route::get('/crear', [PlantillaController::class, 'crear'])->name('crear');
        Route::post('/ejecutar', [PlantillaController::class, 'ejecutar'])->name('ejecutar');
        Route::get('/historial', [PlantillaController::class, 'historial'])->name('historial');
        Route::post('/optimizar', [PlantillaController::class, 'optimizar'])->name('optimizar');

        // Gestión de plantillas dentro del grupo planificación
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
    // GESTIÓN DE RUTAS CORTAS
    // =============================================================================

    Route::prefix('rutas-cortas')->name('rutas-cortas.')->group(function () {
        Route::get('/', [RutaCortaController::class, 'index'])->name('index');
        Route::get('/crear', [RutaCortaController::class, 'crear'])->name('crear');
        Route::post('/', [RutaCortaController::class, 'store'])->name('store');
        Route::get('/{ruta}', [RutaCortaController::class, 'show'])->name('show');
        Route::get('/{ruta}/editar', [RutaCortaController::class, 'edit'])->name('edit');
        Route::put('/{ruta}', [RutaCortaController::class, 'update'])->name('update');
        Route::delete('/{ruta}', [RutaCortaController::class, 'destroy'])->name('destroy');

        // Asignación de conductores
        Route::post('/{ruta}/asignar-conductor', [RutaCortaController::class, 'asignarConductor'])->name('asignar.conductor');
        Route::post('/{ruta}/cambiar-conductor', [RutaCortaController::class, 'cambiarConductor'])->name('cambiar.conductor');

        // Reportes y análisis
        Route::get('/reportes/conductor/{conductor}', [RutaCortaController::class, 'reporteConductor'])->name('reporte.conductor');
        Route::get('/reportes/balance', [RutaCortaController::class, 'reporteBalance'])->name('reporte.balance');
        Route::get('/analisis/rentabilidad', [RutaCortaController::class, 'analisisRentabilidad'])->name('analisis.rentabilidad');

        // Configuración de tramos
        Route::get('/configuracion/tramos', [RutaCortaController::class, 'configuracionTramos'])->name('configuracion.tramos');
        Route::post('/configuracion/tramos', [RutaCortaController::class, 'guardarConfiguracionTramos'])->name('configuracion.tramos.guardar');
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
    // GESTIÓN DE BACKUPS
    // =============================================================================

    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/crear', [BackupController::class, 'crear'])->name('crear');
        Route::post('/restaurar/{backup}', [BackupController::class, 'restaurar'])->name('restaurar');
        Route::delete('/{backup}', [BackupController::class, 'eliminar'])->name('eliminar');
        Route::get('/descargar/{backup}', [BackupController::class, 'descargar'])->name('descargar');
        Route::post('/programar', [BackupController::class, 'programar'])->name('programar');
        Route::get('/configuracion', [BackupController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [BackupController::class, 'guardarConfiguracion'])->name('configuracion.guardar');
    });

    // =============================================================================
    // GESTIÓN DE CREDENCIALES
    // =============================================================================

    Route::prefix('credenciales')->name('credenciales.')->group(function () {
        Route::get('/', [CredencialesController::class, 'index'])->name('index');
        Route::get('/crear', [CredencialesController::class, 'crear'])->name('crear');
        Route::post('/', [CredencialesController::class, 'store'])->name('store');
        Route::get('/{credencial}/editar', [CredencialesController::class, 'edit'])->name('edit');
        Route::put('/{credencial}', [CredencialesController::class, 'update'])->name('update');
        Route::delete('/{credencial}', [CredencialesController::class, 'destroy'])->name('destroy');
        Route::post('/{credencial}/test', [CredencialesController::class, 'testConexion'])->name('test');
    });

    // =============================================================================
    // HISTORIAL Y AUDITORÍA
    // =============================================================================

    Route::prefix('historial')->name('historial.')->group(function () {
        Route::get('/', [HistorialController::class, 'index'])->name('index');
        Route::get('/conductores', [HistorialController::class, 'conductores'])->name('conductores');
        Route::get('/planificacion', [HistorialController::class, 'planificacion'])->name('planificacion');
        Route::get('/validaciones', [HistorialController::class, 'validaciones'])->name('validaciones');
        Route::get('/sistema', [HistorialController::class, 'sistema'])->name('sistema');
        Route::post('/limpiar', [HistorialController::class, 'limpiar'])->name('limpiar');
        Route::get('/exportar', [HistorialController::class, 'exportar'])->name('exportar');
    });

    // =============================================================================
    // CONFIGURACIÓN DEL SISTEMA (GRUPO EXISTENTE MANTENIDO)
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
    // GESTIÓN DE NOTIFICACIONES
    // =============================================================================

    Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/', [NotificacionController::class, 'index'])->name('index');
        Route::post('/marcar-leidas', [NotificacionController::class, 'marcarLeidas'])->name('marcar.leidas');
        Route::post('/eliminar-leidas', [NotificacionController::class, 'eliminarLeidas'])->name('eliminar.leidas');
        Route::get('/configuracion', [NotificacionController::class, 'configuracion'])->name('configuracion');
        Route::post('/configuracion', [NotificacionController::class, 'guardarConfiguracion'])->name('configuracion.guardar');
    });

    // =============================================================================
    // RUTAS PARA DESARROLLO (EN AMBIENTE NO PRODUCCIÓN)
    // =============================================================================

    if (config('app.env') !== 'production') {
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
