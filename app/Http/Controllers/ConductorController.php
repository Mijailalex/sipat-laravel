<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use App\Models\Validacion;
use App\Models\ConductorBackup;
use App\Imports\ConductoresImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ConductorController extends Controller
{
    public function index(Request $request)
    {
        $query = Conductor::query();

        // Filtros
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_conductor', 'like', "%{$buscar}%")
                  ->orWhere('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%")
                  ->orWhere('licencia', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('subempresa')) {
            $query->where('subempresa', $request->subempresa);
        }

        if ($request->filled('filtro')) {
            switch ($request->filtro) {
                case 'criticos':
                    $query->criticos();
                    break;
                case 'disponibles':
                    $query->disponibles();
                    break;
                case 'descanso':
                    $query->enDescanso();
                    break;
            }
        }

        // Ordenamiento
        $ordenPor = $request->get('orden_por', 'created_at');
        $ordenDireccion = $request->get('orden_direccion', 'desc');
        $query->orderBy($ordenPor, $ordenDireccion);

        $conductores = $query->paginate(20);

        // Datos adicionales para la vista
        $estadisticas = [
            'total' => Conductor::count(),
            'disponibles' => Conductor::disponibles()->count(),
            'en_descanso' => Conductor::enDescanso()->count(),
            'criticos' => Conductor::criticos()->count(),
            'suspendidos' => Conductor::where('estado', 'SUSPENDIDO')->count()
        ];

        // Métricas requeridas para la vista
        $metricas = [
            'total' => Conductor::count(),
            'conductores_activos' => Conductor::where('estado', 'DISPONIBLE')->count(),
            'conductores_descanso' => Conductor::whereIn('estado', ['DESCANSO_FISICO', 'DESCANSO_SEMANAL'])->count(),
            'conductores_criticos' => Conductor::where('dias_acumulados', '>=', 6)->count(),
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
            'codigo_conductor' => 'nullable|string|max:20|unique:conductores',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => 'required|string|size:8|unique:conductores',
            'licencia' => 'required|string|max:20|unique:conductores',
            'categoria_licencia' => 'nullable|string|max:10',
            'fecha_vencimiento_licencia' => 'required|date|after:today',
            'telefono' => 'nullable|string|max:15',
            'email' => 'nullable|email|max:100',
            'direccion' => 'nullable|string|max:200',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'genero' => 'nullable|in:M,F,OTRO',
            'contacto_emergencia' => 'nullable|string|max:100',
            'telefono_emergencia' => 'nullable|string|max:15',
            'fecha_ingreso' => 'required|date',
            'años_experiencia' => 'nullable|integer|min:0',
            'salario_base' => 'nullable|numeric|min:0',
            'certificaciones' => 'nullable|array',
            'turno_preferido' => 'nullable|in:MAÑANA,TARDE,NOCHE,ROTATIVO',
            'estado' => 'required|in:DISPONIBLE,DESCANSO_FISICO,DESCANSO_SEMANAL,VACACIONES,SUSPENDIDO,FALTO_OPERATIVO,FALTO_NO_OPERATIVO',
            'origen_conductor' => 'nullable|string|max:100',
            'subempresa' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'puntualidad' => 'nullable|numeric|min:0|max:100',
            'eficiencia' => 'nullable|numeric|min:0|max:100'
        ]);

        try {
            DB::beginTransaction();

            // Valores por defecto
            $validated['puntualidad'] = $validated['puntualidad'] ?? 100;
            $validated['eficiencia'] = $validated['eficiencia'] ?? 100;
            $validated['score_general'] = ($validated['puntualidad'] + $validated['eficiencia']) / 2;
            $validated['dias_acumulados'] = 0;

            $conductor = Conductor::create($validated);

            // Crear backup inicial
            ConductorBackup::crearBackup(
                $conductor->id,
                'CREADO',
                null,
                $conductor->getAttributes(),
                'Conductor creado desde el sistema'
            );

            // Ejecutar validaciones automáticas
            $conductor->validarDatos();

            DB::commit();

            return redirect()
                ->route('conductores.show', $conductor)
                ->with('success', 'Conductor creado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
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
            'codigo_conductor' => 'nullable|string|max:20|unique:conductores,codigo_conductor,' . $id,
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => 'required|string|size:8|unique:conductores,dni,' . $id,
            'licencia' => 'required|string|max:20|unique:conductores,licencia,' . $id,
            'categoria_licencia' => 'nullable|string|max:10',
            'fecha_vencimiento_licencia' => 'required|date|after:today',
            'telefono' => 'nullable|string|max:15',
            'email' => 'nullable|email|max:100',
            'direccion' => 'nullable|string|max:200',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'genero' => 'nullable|in:M,F,OTRO',
            'contacto_emergencia' => 'nullable|string|max:100',
            'telefono_emergencia' => 'nullable|string|max:15',
            'fecha_ingreso' => 'required|date',
            'años_experiencia' => 'nullable|integer|min:0',
            'salario_base' => 'nullable|numeric|min:0',
            'certificaciones' => 'nullable|array',
            'turno_preferido' => 'nullable|in:MAÑANA,TARDE,NOCHE,ROTATIVO',
            'estado' => 'required|in:DISPONIBLE,DESCANSO_FISICO,DESCANSO_SEMANAL,VACACIONES,SUSPENDIDO,FALTO_OPERATIVO,FALTO_NO_OPERATIVO',
            'origen_conductor' => 'nullable|string|max:100',
            'subempresa' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'puntualidad' => 'nullable|numeric|min:0|max:100',
            'eficiencia' => 'nullable|numeric|min:0|max:100',
            'dias_acumulados' => 'nullable|integer|min:0'
        ]);

        try {
            DB::beginTransaction();

            $datosAnteriores = $conductor->getOriginal();

            // Recalcular score general
            if (isset($validated['puntualidad']) && isset($validated['eficiencia'])) {
                $validated['score_general'] = ($validated['puntualidad'] + $validated['eficiencia']) / 2;
            }

            // Reset días acumulados si cambia a descanso
            if (in_array($validated['estado'], ['DESCANSO_FISICO', 'DESCANSO_SEMANAL', 'VACACIONES'])) {
                $validated['dias_acumulados'] = 0;
            }

            $conductor->update($validated);

            // Crear backup
            ConductorBackup::create([
                'conductor_id' => $conductor->id,
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos' => $conductor->fresh()->toArray(),
                'accion' => 'ACTUALIZACION',
                'motivo' => $request->get('motivo_cambio', 'Actualización de datos')
            ]);

            // Ejecutar validaciones automáticas si cambió el estado a disponible
            if ($conductor->wasChanged('estado') && $conductor->estado === 'DISPONIBLE') {
                $conductor->validarDatos();
            }

            DB::commit();

            return redirect()
                ->route('conductores.show', $conductor)
                ->with('success', 'Conductor actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar conductor: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $conductor = Conductor::findOrFail($id);

        try {
            DB::beginTransaction();

            // Verificar si tiene registros relacionados activos
            if ($conductor->turnos()->whereIn('estado', ['PROGRAMADO', 'EN_CURSO'])->exists()) {
                throw new \Exception('No se puede eliminar un conductor con turnos activos');
            }

            if ($conductor->rutasCortas()->whereIn('estado', ['PROGRAMADA', 'EN_CURSO'])->exists()) {
                throw new \Exception('No se puede eliminar un conductor con rutas activas');
            }

            $datosAnteriores = $conductor->toArray();

            // Crear backup antes de eliminar
            ConductorBackup::create([
                'conductor_id' => $conductor->id,
                'datos_anteriores' => $datosAnteriores,
                'datos_nuevos' => null,
                'accion' => 'ELIMINACION',
                'motivo' => 'Conductor eliminado'
            ]);

            $conductor->delete();

            DB::commit();

            return redirect()
                ->route('conductores.index')
                ->with('success', 'Conductor eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error al eliminar conductor: ' . $e->getMessage());
        }
    }

    public function enviarADescanso(Request $request, $id)
    {
        $conductor = Conductor::findOrFail($id);

        $validated = $request->validate([
            'tipo_descanso' => 'required|in:FISICO,SEMANAL',
            'motivo' => 'nullable|string|max:500'
        ]);

        try {
            $estado = $validated['tipo_descanso'] == 'FISICO' ? 'DESCANSO_FISICO' : 'DESCANSO_SEMANAL';

            $conductor->update([
                'estado' => $estado,
                'dias_acumulados' => 0,
                'observaciones' => $validated['motivo'] ?? 'Descanso programado manualmente'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Conductor enviado a {$validated['tipo_descanso']} exitosamente"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar a descanso: ' . $e->getMessage()
            ], 500);
        }
    }

    public function activar($id)
    {
        $conductor = Conductor::findOrFail($id);

        try {
            $conductor->update([
                'estado' => 'DISPONIBLE',
                'dias_acumulados' => 0
            ]);

            // Ejecutar validaciones automáticas
            $conductor->validarDatos();

            return response()->json([
                'success' => true,
                'message' => 'Conductor activado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al activar conductor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarMetricas($id)
    {
        $conductor = Conductor::findOrFail($id);

        try {
            $conductor->actualizarMetricas();

            return response()->json([
                'success' => true,
                'data' => [
                    'eficiencia' => $conductor->eficiencia,
                    'puntualidad' => $conductor->puntualidad,
                    'score_general' => $conductor->score_general
                ],
                'message' => 'Métricas actualizadas exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar métricas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $query = Conductor::query();

        // Aplicar los mismos filtros que en index
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_conductor', 'like', "%{$buscar}%")
                  ->orWhere('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%")
                  ->orWhere('dni', 'like', "%{$buscar}%")
                  ->orWhere('licencia', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('subempresa')) {
            $query->where('subempresa', $request->subempresa);
        }

        $conductores = $query->get();

        $filename = 'conductores_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($conductores) {
            $file = fopen('php://output', 'w');

            // Encabezados CSV
            fputcsv($file, [
                'Código',
                'Nombre',
                'Apellido',
                'DNI',
                'Licencia',
                'Estado',
                'Subempresa',
                'Fecha Ingreso',
                'Días Acumulados',
                'Puntualidad',
                'Eficiencia',
                'Score General',
                'Teléfono'
            ]);

            // Datos
            foreach ($conductores as $conductor) {
                fputcsv($file, [
                    $conductor->codigo_conductor,
                    $conductor->nombre,
                    $conductor->apellido,
                    $conductor->dni,
                    $conductor->licencia,
                    $conductor->estado,
                    $conductor->subempresa,
                    $conductor->fecha_ingreso->format('d/m/Y'),
                    $conductor->dias_acumulados,
                    $conductor->puntualidad . '%',
                    $conductor->eficiencia . '%',
                    $conductor->score_general,
                    $conductor->telefono
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportar(Request $request)
    {
        // Alias para export() para compatibilidad
        return $this->export($request);
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $import = new ConductoresImport();
            Excel::import($import, $request->file('archivo'));

            return back()->with('success',
                "Importación completada. {$import->getRowCount()} conductores procesados."
            );

        } catch (\Exception $e) {
            return back()->with('error', 'Error en la importación: ' . $e->getMessage());
        }
    }

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
