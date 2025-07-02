<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ConductorController;
use App\Http\Controllers\ValidacionController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\ParametroController;
use App\Http\Controllers\ReporteController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal redirige al dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart-data');

// Conductores - CRUD completo
Route::resource('conductores', ConductorController::class);
Route::get('/conductores-export', [ConductorController::class, 'export'])->name('conductores.export');

// Validaciones
Route::get('/validaciones', [ValidacionController::class, 'index'])->name('validaciones.index');
Route::get('/validaciones/{id}', [ValidacionController::class, 'show'])->name('validaciones.show');
Route::post('/validaciones/ejecutar', [ValidacionController::class, 'ejecutarValidaciones'])->name('validaciones.ejecutar');

// Plantillas
Route::resource('plantillas', PlantillaController::class);

// Parámetros
Route::resource('parametros', ParametroController::class);

// Reportes
Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
Route::get('/reportes/conductores', [ReporteController::class, 'conductores'])->name('reportes.conductores');
Route::get('/reportes/operativo', [ReporteController::class, 'operativo'])->name('reportes.operativo');

// Rutas API para datos dinámicos
Route::prefix('api')->group(function () {
    Route::get('/metricas', [DashboardController::class, 'getChartData']);
    Route::get('/conductores/estados', function() {
        return response()->json(
            \App\Models\Conductor::select('estado', \DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->pluck('total', 'estado')
        );
    });
});

// Importaciones de conductores
Route::post('/conductores/importar', [ConductorController::class, 'importar'])->name('conductores.importar');
Route::get('/conductores/plantilla-importacion', [ConductorController::class, 'plantillaImportacion'])->name('conductores.plantilla');

// PDFs de plantillas
Route::get('/plantillas/{id}/pdf', [PlantillaPDFController::class, 'generarPDF'])->name('plantillas.pdf');
Route::get('/plantillas/{id}/excel', [PlantillaPDFController::class, 'generarExcel'])->name('plantillas.excel');

// Subempresas
Route::resource('subempresas', SubempresaController::class);
Route::get('/subempresas/{id}/asignacion-semanal', [SubempresaController::class, 'asignacionSemanal'])->name('subempresas.asignacion');
Route::post('/subempresas/{id}/asignacion-automatica', [SubempresaController::class, 'procesarAsignacionAutomatica'])->name('subempresas.asignacion.automatica');

// Descansos
Route::resource('descansos', DescansoController::class);

// Replanificación
Route::get('/replanificacion', [ReplanificacionController::class, 'index'])->name('replanificacion.index');
Route::post('/replanificacion/automatica', [ReplanificacionController::class, 'replanificarAutomatico'])->name('replanificacion.automatica');
Route::get('/replanificacion/backup', [ReplanificacionController::class, 'gestionBackup'])->name('replanificacion.backup');

// Rutas para parámetros predictivos
Route::resource('parametros-predictivos', ParametroPredictivoController::class)
    ->names('parametros_predictivos');

// Rutas de Notificaciones
Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
// Listar notificaciones de un conductor
   Route::get('/conductor/{conductorId}', [NotificacionController::class, 'index'])
        ->name('index');

// Marcar notificación individual como leída
Route::put('/{id}/marcar-leida', [NotificacionController::class, 'marcarLeida'])
       ->name('marcar-leida');

// Marcar todas las notificaciones de un conductor como leídas
Route::put('/conductor/{conductorId}/marcar-todas', [NotificacionController::class, 'marcarTodasLeidas'])
     ->name('marcar-todas');

// Generar notificaciones de prueba
Route::post('/generar-prueba', [NotificacionController::class, 'generarNotificacionesPrueba'])
     ->name('generar-prueba');
});
