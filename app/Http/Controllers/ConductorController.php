<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\ConductorBackup;
use App\Models\Validacion;
use App\Imports\ConductorImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ConductorController extends Controller
{
    public function index(Request $request)
    {
        $query = Conductor::query();

        // Aplicar filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('subempresa')) {
            $query->where('subempresa', $request->subempresa);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function($q) use ($buscar) {
                $q->where('codigo_conductor', 'like', "%{$buscar}%")
                  ->orWhere('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%")
                  ->orWhere('licencia', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('eficiencia_min')) {
            $query->where('eficiencia', '>=', $request->eficiencia_min);
        }

        if ($request->filled('score_min')) {
            $query->where('score_general', '>=', $request->score_min);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'codigo_conductor');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $conductores = $query->paginate(15)->withQueryString();

        // Estadísticas generales
        $estadisticas = [
            'total' => Conductor::count(),
            'disponibles' => Conductor::where('estado', 'DISPONIBLE')->count(),
            'descanso_fisico' => Conductor::where('estado', 'DESCANSO_FISICO')->count(),
            'descanso_semanal' => Conductor::where('estado', 'DESCANSO_SEMANAL')->count(),
            'vacaciones' => Conductor::where('estado', 'VACACIONES')->count(),
            'suspendidos' => Conductor::where('estado', 'SUSPENDIDO')->count(),
            'falta_operativo' => Conductor::where('estado', 'FALTA_OPERATIVO')->count(),
            'falta_no_operativo' => Conductor::where('estado', 'FALTA_NO_OPERATIVO')->count()
        ];

        // Métricas de rendimiento
        $metricas = [
            'puntualidad_promedio' => round(Conductor::where('estado', '!=', 'SUSPENDIDO')->avg('puntualidad') ?? 0, 1),
            'eficiencia_promedio' => round(Conductor::where('estado', '!=', 'SUSPENDIDO')->avg('eficiencia') ?? 0, 1),
            'validaciones_pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'score_promedio' => round(Conductor::where('estado', '!=', 'SUSPENDIDO')->avg('score_general') ?? 0, 1)
        ];

        $subempresas = Conductor::distinct('subempresa')
            ->whereNotNull('subempresa')
            ->pluck('subempresa')
            ->sort();

        return view('conductores.index', compact(
            'conductores',
            'estadisticas',
            'metricas',
            'subempresas'
        ));
    }

    public function show($id)
    {
        $conductor = Conductor::findOrFail($id);

        // Estadísticas del conductor
        $estadisticas = [
            'turnos_mes' => $conductor->turnos()
                ->where('fecha_turno', '>=', now()->startOfMonth())
                ->count(),
            'rutas_cortas_semana' => $conductor->rutasCortas()
                ->where('fecha', '>=', now()->startOfWeek())
                ->count(),
            'validaciones_activas' => $conductor->validaciones()
                ->where('estado', 'PENDIENTE')
                ->count(),
            'dias_desde_ultimo_descanso' => $conductor->dias_acumulados ?? 0,
            'turnos_completados_mes' => $conductor->turnos()
                ->where('fecha_turno', '>=', now()->startOfMonth())
                ->where('estado', 'COMPLETADO')
                ->count()
        ];

        // Historial de cambios reciente
        $historialCambios = ConductorBackup::obtenerHistorialConductor($id, 5);

        // Obtener historial de cambios
        $historial = ConductorBackup::where('conductor_id', $id)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Obtener validaciones relacionadas
        $validaciones = Validacion::where('conductor_id', $id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('conductores.show', compact(
            'conductor',
            'estadisticas',
            'historialCambios',
            'historial',
            'validaciones'
        ));
    }

    public function create()
    {
        $subempresas = Conductor::distinct('subempresa')
            ->whereNotNull('subempresa')
            ->pluck('subempresa')
            ->sort();

        return view('conductores.create', compact('subempresas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_conductor' => 'required|string|max:20|unique:conductores',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => 'required|string|max:20|unique:conductores',
            'licencia' => 'required|string|max:30|unique:conductores',
            'fecha_ingreso' => 'required|date',
            'fecha_nacimiento' => 'required|date|before:today',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:200',
            'subempresa' => 'required|string|max:100',
            'estado' => 'required|in:DISPONIBLE,DESCANSO_FISICO,DESCANSO_SEMANAL,VACACIONES,SUSPENDIDO',
            'observaciones' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $conductor = Conductor::create($validated);

            // Crear backup inicial
            ConductorBackup::crearBackup($conductor, 'CREACION', 'Conductor creado');

            DB::commit();

            return redirect()
                ->route('conductores.show', $conductor)
                ->with('success', 'Conductor creado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creando conductor: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Error al crear conductor: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $conductor = Conductor::findOrFail($id);
        $subempresas = Conductor::distinct('subempresa')
            ->whereNotNull('subempresa')
            ->pluck('subempresa')
            ->sort();

        return view('conductores.edit', compact('conductor', 'subempresas'));
    }

    public function update(Request $request, $id)
    {
        $conductor = Conductor::findOrFail($id);

        $validated = $request->validate([
            'codigo_conductor' => [
                'required',
                'string',
                'max:20',
                Rule::unique('conductores')->ignore($conductor->id)
            ],
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => [
                'required',
                'string',
                'max:20',
                Rule::unique('conductores')->ignore($conductor->id)
            ],
            'licencia' => [
                'required',
                'string',
                'max:30',
                Rule::unique('conductores')->ignore($conductor->id)
            ],
            'fecha_ingreso' => 'required|date',
            'fecha_nacimiento' => 'required|date|before:today',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:200',
            'subempresa' => 'required|string|max:100',
            'estado' => 'required|in:DISPONIBLE,DESCANSO_FISICO,DESCANSO_SEMANAL,VACACIONES,SUSPENDIDO,FALTA_OPERATIVO,FALTA_NO_OPERATIVO',
            'observaciones' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Guardar valores originales para backup
            $valoresOriginales = $conductor->getOriginal();

            $conductor->update($validated);

            // Crear backup del cambio
            ConductorBackup::crearBackup(
                $conductor,
                'ACTUALIZACION',
                'Conductor actualizado',
                $valoresOriginales
            );

            DB::commit();

            return redirect()
                ->route('conductores.show', $conductor)
                ->with('success', 'Conductor actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando conductor: ' . $e->getMessage());
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar conductor: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $conductor = Conductor::findOrFail($id);

            // Verificar si puede ser eliminado
            if ($conductor->turnos()->exists() || $conductor->rutasCortas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el conductor porque tiene turnos o rutas cortas asignadas'
                ], 400);
            }

            DB::beginTransaction();

            // Crear backup antes de eliminar
            ConductorBackup::crearBackup($conductor, 'ELIMINACION', 'Conductor eliminado');

            $conductor->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Conductor eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error eliminando conductor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar conductor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cambiarEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:DISPONIBLE,DESCANSO_FISICO,DESCANSO_SEMANAL,VACACIONES,SUSPENDIDO,FALTA_OPERATIVO,FALTA_NO_OPERATIVO',
            'motivo' => 'nullable|string'
        ]);

        try {
            $conductor = Conductor::findOrFail($id);
            $estadoAnterior = $conductor->estado;

            DB::beginTransaction();

            $conductor->update([
                'estado' => $request->estado,
                'fecha_ultimo_cambio_estado' => now(),
                'motivo_estado' => $request->motivo
            ]);

            // Si cambia a descanso, resetear días acumulados
            if (in_array($request->estado, ['DESCANSO_FISICO', 'DESCANSO_SEMANAL', 'VACACIONES'])) {
                $conductor->update(['dias_acumulados' => 0]);
            }

            // Crear backup del cambio
            ConductorBackup::crearBackup(
                $conductor,
                'CAMBIO_ESTADO',
                "Estado cambiado de {$estadoAnterior} a {$request->estado}. Motivo: " . ($request->motivo ?? 'No especificado')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado exitosamente',
                'data' => [
                    'nuevo_estado' => $conductor->estado,
                    'estado_anterior' => $estadoAnterior
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error cambiando estado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function enviarDescanso($id)
    {
        try {
            $conductor = Conductor::findOrFail($id);

            if ($conductor->estado !== 'DISPONIBLE') {
                return response()->json([
                    'success' => false,
                    'message' => 'El conductor debe estar disponible para enviarlo a descanso'
                ], 400);
            }

            DB::beginTransaction();

            $tipoDescanso = $conductor->dias_acumulados >= 6 ? 'DESCANSO_SEMANAL' : 'DESCANSO_FISICO';

            $conductor->update([
                'estado' => $tipoDescanso,
                'fecha_ultimo_cambio_estado' => now(),
                'motivo_estado' => 'Enviado a descanso automáticamente',
                'dias_acumulados' => 0
            ]);

            // Crear backup del cambio
            ConductorBackup::crearBackup(
                $conductor,
                'ENVIO_DESCANSO',
                "Enviado a {$tipoDescanso} automáticamente"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Conductor enviado a {$tipoDescanso} exitosamente",
                'data' => ['nuevo_estado' => $conductor->estado]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error enviando a descanso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar a descanso: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarMetricas(Request $request, $id)
    {
        $request->validate([
            'eficiencia' => 'nullable|numeric|min:0|max:100',
            'puntualidad' => 'nullable|numeric|min:0|max:100',
            'dias_acumulados' => 'nullable|integer|min:0|max:30'
        ]);

        try {
            $conductor = Conductor::findOrFail($id);

            DB::beginTransaction();

            $datosActualizacion = array_filter($request->only(['eficiencia', 'puntualidad', 'dias_acumulados']));

            if (!empty($datosActualizacion)) {
                $conductor->update($datosActualizacion);

                // Recalcular score general
                $conductor->calcularScoreGeneral();

                // Crear backup del cambio
                ConductorBackup::crearBackup(
                    $conductor,
                    'ACTUALIZACION_METRICAS',
                    'Métricas actualizadas: ' . implode(', ', array_keys($datosActualizacion))
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Métricas actualizadas exitosamente',
                'data' => [
                    'eficiencia' => $conductor->eficiencia,
                    'puntualidad' => $conductor->puntualidad,
                    'score_general' => $conductor->score_general,
                    'dias_acumulados' => $conductor->dias_acumulados
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando métricas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar métricas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function accionMasiva(Request $request)
    {
        $request->validate([
            'accion' => 'required|in:cambiar_estado,enviar_descanso,actualizar_subempresa,eliminar',
            'conductores' => 'required|array|min:1',
            'conductores.*' => 'exists:conductores,id',
            'nuevo_estado' => 'nullable|required_if:accion,cambiar_estado|in:DISPONIBLE,DESCANSO_FISICO,DESCANSO_SEMANAL,VACACIONES,SUSPENDIDO',
            'nueva_subempresa' => 'nullable|required_if:accion,actualizar_subempresa|string|max:100',
            'motivo' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $procesados = 0;
            $errores = [];

            foreach ($request->conductores as $conductorId) {
                try {
                    $conductor = Conductor::findOrFail($conductorId);

                    switch ($request->accion) {
                        case 'cambiar_estado':
                            $conductor->update([
                                'estado' => $request->nuevo_estado,
                                'fecha_ultimo_cambio_estado' => now(),
                                'motivo_estado' => $request->motivo ?? 'Cambio masivo'
                            ]);
                            break;

                        case 'enviar_descanso':
                            if ($conductor->estado === 'DISPONIBLE') {
                                $tipoDescanso = $conductor->dias_acumulados >= 6 ? 'DESCANSO_SEMANAL' : 'DESCANSO_FISICO';
                                $conductor->update([
                                    'estado' => $tipoDescanso,
                                    'fecha_ultimo_cambio_estado' => now(),
                                    'motivo_estado' => 'Descanso masivo',
                                    'dias_acumulados' => 0
                                ]);
                            }
                            break;

                        case 'actualizar_subempresa':
                            $conductor->update(['subempresa' => $request->nueva_subempresa]);
                            break;

                        case 'eliminar':
                            if (!$conductor->turnos()->exists() && !$conductor->rutasCortas()->exists()) {
                                ConductorBackup::crearBackup($conductor, 'ELIMINACION', 'Eliminación masiva');
                                $conductor->delete();
                            } else {
                                $errores[] = "Conductor {$conductor->codigo_conductor}: tiene turnos/rutas asignadas";
                                continue 2;
                            }
                            break;
                    }

                    ConductorBackup::crearBackup($conductor, 'ACCION_MASIVA', "Acción masiva: {$request->accion}");
                    $procesados++;

                } catch (\Exception $e) {
                    $errores[] = "Conductor ID {$conductorId}: " . $e->getMessage();
                }
            }

            DB::commit();

            $mensaje = "Se procesaron {$procesados} conductores exitosamente.";
            if (!empty($errores)) {
                $mensaje .= " Errores: " . implode(', ', array_slice($errores, 0, 3));
                if (count($errores) > 3) {
                    $mensaje .= " y " . (count($errores) - 3) . " más.";
                }
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'data' => [
                    'procesados' => $procesados,
                    'errores' => count($errores)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en acción masiva: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en acción masiva: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'actualizar_existentes' => 'boolean'
        ]);

        try {
            $import = new ConductorImport($request->boolean('actualizar_existentes', false));
            Excel::import($import, $request->file('archivo'));

            return redirect()
                ->back()
                ->with(
                    'success',
                    "Importación completada. {$import->getRowCount()} conductores procesados."
                );

        } catch (\Exception $e) {
            return back()->with('error', 'Error en la importación: ' . $e->getMessage());
        }
    }

    public function exportar(Request $request)
    {
        try {
            $query = Conductor::query();

            // Aplicar filtros de exportación si se proporcionan
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('subempresa')) {
                $query->where('subempresa', $request->subempresa);
            }

            $conductores = $query->orderBy('codigo_conductor')->get();

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="conductores_' . now()->format('Y-m-d_H-i-s') . '.csv"',
            ];

            $callback = function() use ($conductores) {
                $file = fopen('php://output', 'w');

                // Encabezados
                fputcsv($file, [
                    'Código',
                    'Nombre',
                    'Apellido',
                    'DNI',
                    'Licencia',
                    'Fecha Ingreso',
                    'Estado',
                    'Subempresa',
                    'Teléfono',
                    'Eficiencia',
                    'Puntualidad',
                    'Score General',
                    'Días Acumulados',
                    'Fecha Nacimiento',
                    'Dirección',
                    'Observaciones'
                ]);

                // Datos
                foreach ($conductores as $conductor) {
                    fputcsv($file, [
                        $conductor->codigo_conductor,
                        $conductor->nombre,
                        $conductor->apellido,
                        $conductor->dni,
                        $conductor->licencia,
                        $conductor->fecha_ingreso?->format('Y-m-d'),
                        $conductor->estado,
                        $conductor->subempresa,
                        $conductor->telefono,
                        $conductor->eficiencia,
                        $conductor->puntualidad,
                        $conductor->score_general,
                        $conductor->dias_acumulados,
                        $conductor->fecha_nacimiento?->format('Y-m-d'),
                        $conductor->direccion,
                        $conductor->observaciones
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error exportando conductores: ' . $e->getMessage());
            return back()->with('error', 'Error al exportar conductores: ' . $e->getMessage());
        }
    }

    // SOLUCIÓN: Método plantillaImportacion que faltaba para la ruta conductores.plantilla
    public function plantillaImportacion()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="plantilla_conductores.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, [
                'codigo_conductor',
                'nombre',
                'apellido',
                'dni',
                'licencia',
                'fecha_ingreso',
                'estado',
                'subempresa',
                'telefono',
                'direccion',
                'fecha_nacimiento'
            ]);

            // Ejemplo
            fputcsv($file, [
                'C001',
                'Juan',
                'Pérez',
                '12345678',
                'LIC001',
                '2024-01-15',
                'DISPONIBLE',
                'SUBEMPRESA A',
                '987654321',
                'Av. Principal 123',
                '1990-05-20'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function historial($id)
    {
        $conductor = Conductor::findOrFail($id);
        $historial = ConductorBackup::where('conductor_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('conductores.historial', compact('conductor', 'historial'));
    }

    public function metricas($id)
    {
        $conductor = Conductor::findOrFail($id);

        $metricas = [
            'turnos_ultimo_mes' => $conductor->turnos()
                ->where('fecha_turno', '>=', now()->subMonth())
                ->count(),
            'rutas_cortas_ultimo_mes' => $conductor->rutasCortas()
                ->where('fecha', '>=', now()->subMonth())
                ->count(),
            'promedio_eficiencia_historico' => $conductor->historialMetricas()
                ->avg('eficiencia'),
            'promedio_puntualidad_historico' => $conductor->historialMetricas()
                ->avg('puntualidad'),
            'validaciones_resueltas' => $conductor->validaciones()
                ->where('estado', 'RESUELTO')
                ->count(),
            'validaciones_pendientes' => $conductor->validaciones()
                ->where('estado', 'PENDIENTE')
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $metricas
        ]);
    }

    public function reporteRendimiento(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->subMonth()->toDateString());
        $fechaFin = $request->input('fecha_fin', now()->toDateString());

        $conductores = Conductor::with(['turnos', 'rutasCortas'])
            ->where('estado', '!=', 'SUSPENDIDO')
            ->get()
            ->map(function ($conductor) use ($fechaInicio, $fechaFin) {
                return [
                    'id' => $conductor->id,
                    'codigo' => $conductor->codigo_conductor,
                    'nombre_completo' => $conductor->nombre_completo,
                    'subempresa' => $conductor->subempresa,
                    'eficiencia' => $conductor->eficiencia,
                    'puntualidad' => $conductor->puntualidad,
                    'score_general' => $conductor->score_general,
                    'turnos_periodo' => $conductor->turnos()
                        ->whereBetween('fecha_turno', [$fechaInicio, $fechaFin])
                        ->count(),
                    'rutas_cortas_periodo' => $conductor->rutasCortas()
                        ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                        ->count()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $conductores
        ]);
    }

    public function analisisDisponibilidad()
    {
        $analisis = [
            'disponibles_ahora' => Conductor::where('estado', 'DISPONIBLE')->count(),
            'en_descanso' => Conductor::whereIn('estado', ['DESCANSO_FISICO', 'DESCANSO_SEMANAL'])->count(),
            'en_vacaciones' => Conductor::where('estado', 'VACACIONES')->count(),
            'suspendidos' => Conductor::where('estado', 'SUSPENDIDO')->count(),
            'falta_operativo' => Conductor::where('estado', 'FALTA_OPERATIVO')->count(),
            'falta_no_operativo' => Conductor::where('estado', 'FALTA_NO_OPERATIVO')->count(),
            'por_subempresa' => Conductor::selectRaw('subempresa, estado, count(*) as total')
                ->groupBy('subempresa', 'estado')
                ->get()
                ->groupBy('subempresa'),
            'proximos_a_descanso' => Conductor::where('estado', 'DISPONIBLE')
                ->where('dias_acumulados', '>=', 5)
                ->orderBy('dias_acumulados', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $analisis
        ]);
    }

    // Helper methods para response JSON
    private function successResponse($data = null, $message = 'Operación exitosa')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    private function errorResponse($message = 'Error en la operación', $code = 500)
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }
}
