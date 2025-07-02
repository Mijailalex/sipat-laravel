<?php

namespace App\Http\Controllers;

use CodePageTest;
use Illuminate\Http\Request;
use App\Models\Conductor;
use App\Models\Validacion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Imports\ConductoresImport;
use Maatwebsite\Excel\Facades\Excel;

class ConductorController extends Controller
{
    public function index(Request $request)
    {
        $query = Conductor::query();

        // Filtros
        if ($request->filled('busqueda')) {
            $busqueda = $request->busqueda;
            $query->where(function($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                  ->orWhere('codigo', 'like', "%{$busqueda}%")
                  ->orWhere('email', 'like', "%{$busqueda}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('origen')) {
            $query->where('origen', $request->origen);
        }

        if ($request->filled('dias_acumulados')) {
            if ($request->dias_acumulados === 'criticos') {
                $query->where('dias_acumulados', '>=', 6);
            } elseif ($request->dias_acumulados === 'normales') {
                $query->where('dias_acumulados', '<', 6);
            }
        }

        $conductores = $query->latest()->paginate(15);

        // Métricas para el header
        $metricas = [
            'total' => Conductor::count(),
            'disponibles' => Conductor::disponibles()->count(),
            'en_descanso' => Conductor::where('estado', 'DESCANSO')->count(),
            'promedio_puntualidad' => round(Conductor::avg('puntualidad'), 1),
            'promedio_eficiencia' => round(Conductor::avg('eficiencia'), 1),
            'criticos' => Conductor::criticos()->count()
        ];

        // Opciones para filtros
        $estados = ['DISPONIBLE', 'DESCANSO', 'VACACIONES', 'SUSPENDIDO'];
        $origenes = ['LIMA', 'ICA', 'CHINCHA', 'PISCO', 'CAÑETE', 'NAZCA'];

        return view('conductores.index', compact(
            'conductores',
            'metricas',
            'estados',
            'origenes'
        ));
    }

    public function create()
    {
        $estados = ['DISPONIBLE', 'DESCANSO', 'VACACIONES', 'SUSPENDIDO'];
        $origenes = ['LIMA', 'ICA', 'CHINCHA', 'PISCO', 'CAÑETE', 'NAZCA'];
        $licencias = ['A-I', 'A-IIa', 'A-IIb', 'A-IIIa', 'A-IIIb'];

        return view('conductores.create', compact('estados', 'origenes', 'licencias'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|max:10|unique:conductores',
            'nombre' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:conductores',
            'telefono' => 'required|string|max:20',
            'origen' => 'required|in:LIMA,ICA,CHINCHA,PISCO,CAÑETE,NAZCA',
            'estado' => 'required|in:DISPONIBLE,DESCANSO,VACACIONES,SUSPENDIDO',
            'licencia' => 'required|string|max:20',
            'fecha_ingreso' => 'required|date',
            'observaciones' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $conductor = Conductor::create($request->all());

            // Si el conductor tiene 6+ días acumulados, crear validación automática
            if ($conductor->dias_acumulados >= 6) {
                Validacion::create([
                    'tipo' => 'DESCANSO_001',
                    'conductor_id' => $conductor->id,
                    'mensaje' => 'Conductor requiere descanso obligatorio (' . $conductor->dias_acumulados . ' días trabajados)',
                    'severidad' => 'CRITICA',
                    'estado' => 'PENDIENTE'
                ]);
            }

            DB::commit();

            return redirect()->route('conductores.index')
                ->with('success', 'Conductor creado exitosamente.');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Error al crear conductor: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(Conductor $conductor)
    {
        $conductor->load('validaciones');

        // Calcular score general
        $conductor->score_general = ($conductor->puntualidad + $conductor->eficiencia) / 2;

        // Historial de validaciones
        $validaciones = $conductor->validaciones()
            ->latest()
            ->take(10)
            ->get();

        return view('conductores.show', compact('conductor', 'validaciones'));
    }

    public function edit(Conductor $conductor)
    {
        $estados = ['DISPONIBLE', 'DESCANSO', 'VACACIONES', 'SUSPENDIDO'];
        $origenes = ['LIMA', 'ICA', 'CHINCHA', 'PISCO', 'CAÑETE', 'NAZCA'];
        $licencias = ['A-I', 'A-IIa', 'A-IIb', 'A-IIIa', 'A-IIIb'];

        return view('conductores.edit', compact('conductor', 'estados', 'origenes', 'licencias'));
    }

    public function update(Request $request, Conductor $conductor)
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|max:10|unique:conductores,codigo,' . $conductor->id,
            'nombre' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:conductores,email,' . $conductor->id,
            'telefono' => 'required|string|max:20',
            'origen' => 'required|in:LIMA,ICA,CHINCHA,PISCO,CAÑETE,NAZCA',
            'estado' => 'required|in:DISPONIBLE,DESCANSO,VACACIONES,SUSPENDIDO',
            'licencia' => 'required|string|max:20',
            'fecha_ingreso' => 'required|date',
            'dias_acumulados' => 'required|integer|min:0|max:30',
            'puntualidad' => 'required|numeric|min:0|max:100',
            'eficiencia' => 'required|numeric|min:0|max:100',
            'observaciones' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $diasAcumuladosAnterior = $conductor->dias_acumulados;

            $conductor->update($request->all());

            // Si cambió a 6+ días acumulados, crear validación
            if ($diasAcumuladosAnterior < 6 && $request->dias_acumulados >= 6) {
                Validacion::create([
                    'tipo' => 'DESCANSO_001',
                    'conductor_id' => $conductor->id,
                    'mensaje' => 'Conductor requiere descanso obligatorio (' . $request->dias_acumulados . ' días trabajados)',
                    'severidad' => 'CRITICA',
                    'estado' => 'PENDIENTE'
                ]);
            }

            // Si se puso en descanso, resetear días acumulados
            if ($request->estado === 'DESCANSO' && $diasAcumuladosAnterior > 0) {
                $conductor->update(['dias_acumulados' => 0]);
            }

            DB::commit();

            return redirect()->route('conductores.index')
                ->with('success', 'Conductor actualizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Error al actualizar conductor: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Conductor $conductor)
    {
        try {
            $conductor->delete();

            return redirect()->route('conductores.index')
                ->with('success', 'Conductor eliminado exitosamente.');

        } catch (\Exception $e) {
            return redirect()->route('conductores.index')
                ->with('error', 'Error al eliminar conductor: ' . $e->getMessage());
        }
    }

    // ============ NUEVAS ACCIONES COMPLETADAS ============

    /**
     * Activar conductor (cambiar a disponible)
     */
    public function activar(Conductor $conductor)
    {
        try {
            if ($conductor->estado === 'DISPONIBLE') {
                return response()->json([
                    'success' => false,
                    'message' => 'El conductor ya está disponible.'
                ], 400);
            }

            $conductor->update([
                'estado' => 'DISPONIBLE',
                'dias_acumulados' => 0 // Resetear días al activar
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conductor activado exitosamente.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al activar conductor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Programar descanso
     */
    public function programarDescanso(Conductor $conductor)
    {
        try {
            if ($conductor->estado === 'DESCANSO') {
                return response()->json([
                    'success' => false,
                    'message' => 'El conductor ya está en descanso.'
                ], 400);
            }

            $conductor->update([
                'estado' => 'DESCANSO',
                'dias_acumulados' => 0
            ]);

            // Crear validación de seguimiento
            Validacion::create([
                'tipo' => 'DESCANSO_PROGRAMADO',
                'conductor_id' => $conductor->id,
                'mensaje' => 'Descanso programado manualmente por sistema',
                'severidad' => 'INFO',
                'estado' => 'RESUELTO',
                'fecha_resolucion' => now(),
                'resuelto_por' => 'Sistema'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Descanso programado exitosamente.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al programar descanso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspender conductor
     */
    public function suspender(Request $request, Conductor $conductor)
    {
        $request->validate([
            'motivo' => 'required|string|max:255'
        ]);

        try {
            $conductor->update([
                'estado' => 'SUSPENDIDO',
                'observaciones' => $conductor->observaciones . "\n[" . date('Y-m-d H:i') . "] SUSPENDIDO: " . $request->motivo
            ]);

            // Crear validación de suspensión
            Validacion::create([
                'tipo' => 'SUSPENSION',
                'conductor_id' => $conductor->id,
                'mensaje' => 'Conductor suspendido: ' . $request->motivo,
                'severidad' => 'CRITICA',
                'estado' => 'PENDIENTE'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Conductor suspendido exitosamente.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al suspender conductor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar a vacaciones
     */
    public function enviarVacaciones(Request $request, Conductor $conductor)
    {
        $request->validate([
            'fecha_inicio' => 'required|date|after_or_equal:today',
            'fecha_fin' => 'required|date|after:fecha_inicio'
        ]);

        try {
            $conductor->update([
                'estado' => 'VACACIONES',
                'observaciones' => $conductor->observaciones . "\n[" . date('Y-m-d H:i') . "] VACACIONES: Del " . $request->fecha_inicio . " al " . $request->fecha_fin
            ]);

            // Crear validación de vacaciones
            Validacion::create([
                'tipo' => 'VACACIONES',
                'conductor_id' => $conductor->id,
                'mensaje' => 'Conductor en vacaciones del ' . Carbon::parse($request->fecha_inicio)->format('d/m/Y') . ' al ' . Carbon::parse($request->fecha_fin)->format('d/m/Y'),
                'severidad' => 'INFO',
                'estado' => 'VERIFICADO'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vacaciones programadas exitosamente.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al programar vacaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar métricas del conductor
     */
    public function actualizarMetricas(Request $request, Conductor $conductor)
    {
        $request->validate([
            'puntualidad' => 'required|numeric|min:0|max:100',
            'eficiencia' => 'required|numeric|min:0|max:100',
            'rutas_completadas' => 'required|integer|min:0',
            'horas_trabajadas' => 'required|integer|min:0',
            'incidencias' => 'required|integer|min:0'
        ]);

        try {
            $conductor->update($request->only([
                'puntualidad',
                'eficiencia',
                'rutas_completadas',
                'horas_trabajadas',
                'incidencias'
            ]));

            // Crear validación si hay cambios significativos
            if ($request->puntualidad < 90 || $request->eficiencia < 90) {
                $existeValidacion = Validacion::where('conductor_id', $conductor->id)
                    ->where('tipo', 'RENDIMIENTO_BAJO')
                    ->where('estado', 'PENDIENTE')
                    ->exists();

                if (!$existeValidacion) {
                    Validacion::create([
                        'tipo' => 'RENDIMIENTO_BAJO',
                        'conductor_id' => $conductor->id,
                        'mensaje' => 'Conductor con métricas por debajo del promedio esperado',
                        'severidad' => 'ADVERTENCIA',
                        'estado' => 'PENDIENTE'
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Métricas actualizadas exitosamente.',
                'data' => [
                    'score_general' => round(($request->puntualidad + $request->eficiencia) / 2, 1)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar métricas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historial completo del conductor
     */
    public function historial(Conductor $conductor)
    {
        $validaciones = $conductor->validaciones()
            ->orderBy('fecha_deteccion', 'desc')
            ->paginate(20);

        return view('conductores.historial', compact('conductor', 'validaciones'));
    }

    /**
     * Exportar datos del conductor
     */
    public function exportarConductor(Conductor $conductor)
    {
        $filename = 'conductor_' . $conductor->codigo . '_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($conductor) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header del conductor
            fputcsv($file, ['INFORMACIÓN DEL CONDUCTOR']);
            fputcsv($file, ['Código', $conductor->codigo]);
            fputcsv($file, ['Nombre', $conductor->nombre]);
            fputcsv($file, ['Email', $conductor->email]);
            fputcsv($file, ['Teléfono', $conductor->telefono]);
            fputcsv($file, ['Origen', $conductor->origen]);
            fputcsv($file, ['Estado', $conductor->estado]);
            fputcsv($file, ['Días Acumulados', $conductor->dias_acumulados]);
            fputcsv($file, ['Puntualidad', $conductor->puntualidad . '%']);
            fputcsv($file, ['Eficiencia', $conductor->eficiencia . '%']);
            fputcsv($file, ['Rutas Completadas', $conductor->rutas_completadas]);
            fputcsv($file, ['Horas Trabajadas', $conductor->horas_trabajadas]);
            fputcsv($file, ['Incidencias', $conductor->incidencias]);
            fputcsv($file, ['Fecha Ingreso', $conductor->fecha_ingreso->format('d/m/Y')]);
            fputcsv($file, ['Licencia', $conductor->licencia]);
            fputcsv($file, []);

            // Historial de validaciones
            fputcsv($file, ['HISTORIAL DE VALIDACIONES']);
            fputcsv($file, ['Fecha', 'Tipo', 'Mensaje', 'Severidad', 'Estado']);

            foreach ($conductor->validaciones as $validacion) {
                fputcsv($file, [
                    $validacion->fecha_deteccion->format('d/m/Y H:i'),
                    $validacion->tipo,
                    $validacion->mensaje,
                    $validacion->severidad,
                    $validacion->estado
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Búsqueda AJAX para autocompletado
     */
    public function buscar(Request $request)
    {
        $query = $request->get('q');

        $conductores = Conductor::where('nombre', 'like', "%{$query}%")
            ->orWhere('codigo', 'like', "%{$query}%")
            ->select('id', 'codigo', 'nombre', 'estado')
            ->limit(10)
            ->get();

        return response()->json($conductores);
    }

    public function export(Request $request)
    {
        $conductores = Conductor::all();

        $filename = 'conductores_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($conductores) {
            $file = fopen('php://output', 'w');

            // Header CSV
            fputcsv($file, [
                'Código', 'Nombre', 'Email', 'Teléfono', 'Origen', 'Estado',
                'Días Acumulados', 'Puntualidad', 'Eficiencia', 'Rutas Completadas',
                'Horas Trabajadas', 'Incidencias', 'Fecha Ingreso', 'Licencia'
            ]);

            // Datos
            foreach ($conductores as $conductor) {
                fputcsv($file, [
                    $conductor->codigo,
                    $conductor->nombre,
                    $conductor->email,
                    $conductor->telefono,
                    $conductor->origen,
                    $conductor->estado,
                    $conductor->dias_acumulados,
                    $conductor->puntualidad,
                    $conductor->eficiencia,
                    $conductor->rutas_completadas,
                    $conductor->horas_trabajadas,
                    $conductor->incidencias,
                    $conductor->fecha_ingreso->format('Y-m-d'),
                    $conductor->licencia
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Añadir estos métodos al controlador:

    // public function importar(Request $request)
    // {
    //     $request->validate([
    //         'archivo' => 'required|mimes:xlsx,csv,xls'
    //     ]);

    //     try {
    //         Excel::import(new ConductoresImport, $request->file('archivo'));

    //         return redirect()->route('conductores.index')
    //             ->with('success', 'Conductores importados exitosamente.');
    //     } catch (\Exception $e) {
    //         return redirect()->back()
    //             ->with('error', 'Error al importar: ' . $e->getMessage());
    //     }
    // }


    public function importar(Request $request)
{
    $request->validate([
        'archivo_conductores' => 'required|file|mimes:xlsx,xls,csv'
    ]);

    try {
        $archivo = $request->file('archivo_conductores');
        $actualizarExistentes = $request->boolean('actualizar_existentes');

        $resultado = $this->procesarArchivoConductores($archivo, $actualizarExistentes);

        return redirect()->route('conductores.index')
            ->with('success', "Importación completada: {$resultado['creados']} creados, {$resultado['actualizados']} actualizados, {$resultado['errores']} errores.");

    } catch (\Exception $e) {
        return redirect()->back()
            ->with('error', 'Error al importar archivo: ' . $e->getMessage());
    }
}

    public function plantillaImportacion()
    {
        $filename = 'plantilla_conductores.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, [
                'conductor', 'estado', 'origen', 'disponibilidad_llegada',
                'ultima_hora_servicio', 'dias_acumulados', 'puntualidad',
                'eficiencia', 'incidencias', 'acciones'
            ]);

            // Ejemplo
            fputcsv($file, [
                'C001', 'DISPONIBLE', 'LIMA', '2025-07-02 10:00:00',
                '2025-07-01 18:00:00', '3', '95.5', '92.3', '1', 'Actualizar'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function procesarArchivoConductores($archivo, $actualizarExistentes)
    {
        // Simulación de procesamiento
        // En implementación real, usar una librería como PhpSpreadsheet

        return [
            'creados' => 0,
            'actualizados' => 0,
            'errores' => 0
        ];
    }
}
