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

        $subempresas = Conductor::distinct('subempresa')
            ->whereNotNull('subempresa')
            ->pluck('subempresa')
            ->sort();

        return view('conductores.index', compact(
            'conductores',
            'estadisticas',
            'subempresas'
        ));
    }

    public function show($id)
    {
        $conductor = Conductor::with([
            'validaciones' => function ($query) {
                $query->orderBy('created_at', 'desc')->limit(10);
            },
            'rutasCortas' => function ($query) {
                $query->orderBy('fecha', 'desc')->limit(10);
            },
            'turnos' => function ($query) {
                $query->orderBy('fecha_turno', 'desc')->limit(10);
            }
        ])->findOrFail($id);

        // Estadísticas del conductor
        $estadisticas = [
            'validaciones_pendientes' => $conductor->validaciones()->where('estado', 'PENDIENTE')->count(),
            'rutas_mes_actual' => $conductor->rutasCortas()
                ->where('fecha', '>=', now()->startOfMonth())
                ->where('estado', 'COMPLETADA')
                ->count(),
            'ingresos_mes_actual' => $conductor->rutasCortas()
                ->where('fecha', '>=', now()->startOfMonth())
                ->where('estado', 'COMPLETADA')
                ->sum('ingreso_estimado'),
            'promedio_pasajeros' => $conductor->rutasCortas()
                ->where('estado', 'COMPLETADA')
                ->avg('pasajeros_transportados') ?: 0,
            'turnos_completados_mes' => $conductor->turnos()
                ->where('fecha_turno', '>=', now()->startOfMonth())
                ->where('estado', 'COMPLETADO')
                ->count()
        ];

        // Historial de cambios reciente
        $historialCambios = ConductorBackup::obtenerHistorialConductor($id, 5);

        return view('conductores.show', compact(
            'conductor',
            'estadisticas',
            'historialCambios'
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
            'origen_conductor' => 'nullable|string|max:100',
            'subempresa' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

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
            'observaciones' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $datosAnteriores = $conductor->getOriginal();
            $conductor->update($validated);

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

            // Crear backup antes de eliminar
            ConductorBackup::crearBackup(
                $conductor->id,
                'ELIMINADO',
                $conductor->getAttributes(),
                null,
                'Conductor eliminado desde el sistema'
            );

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
            $conductor->enviarADescanso(
                $validated['tipo_descanso'],
                $validated['motivo'] ?? 'Descanso programado manualmente'
            );

            return $this->successResponse(
                null,
                "Conductor enviado a {$validated['tipo_descanso']} exitosamente"
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error al enviar a descanso: ' . $e->getMessage());
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

            return $this->successResponse(null, 'Conductor activado exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al activar conductor: ' . $e->getMessage());
        }
    }

    public function actualizarMetricas($id)
    {
        $conductor = Conductor::findOrFail($id);

        try {
            $conductor->actualizarMetricas();

            return $this->successResponse([
                'eficiencia' => $conductor->eficiencia,
                'puntualidad' => $conductor->puntualidad,
                'score_general' => $conductor->score_general
            ], 'Métricas actualizadas exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar métricas: ' . $e->getMessage());
        }
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv'
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

    public function exportar(Request $request)
    {
        $query = Conductor::query();

        // Aplicar los mismos filtros que en el index
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('subempresa')) {
            $query->where('subempresa', $request->subempresa);
        }

        $conductores = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="conductores_' . now()->format('Y-m-d') . '.csv"'
        ];

        $callback = function() use ($conductores) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, [
                'Código', 'Nombre', 'Apellido', 'DNI', 'Licencia', 'Estado',
                'Eficiencia', 'Puntualidad', 'Días Acumulados', 'Subempresa',
                'Fecha Ingreso', 'Último Servicio'
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
                    $conductor->eficiencia,
                    $conductor->puntualidad,
                    $conductor->dias_acumulados,
                    $conductor->subempresa,
                    $conductor->fecha_ingreso->format('Y-m-d'),
                    $conductor->ultimo_servicio ? $conductor->ultimo_servicio->format('Y-m-d H:i') : ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function historial($id)
    {
        $conductor = Conductor::findOrFail($id);
        $historial = ConductorBackup::obtenerHistorialConductor($id, 50);

        return view('conductores.historial', compact('conductor', 'historial'));
    }

    public function restaurarVersion(Request $request, $id)
    {
        $conductor = Conductor::findOrFail($id);
        $backup = ConductorBackup::findOrFail($request->backup_id);

        if ($backup->conductor_id !== $conductor->id) {
            return $this->errorResponse('El backup no pertenece a este conductor');
        }

        try {
            $backup->restaurarVersion();

            return $this->successResponse(null, 'Versión restaurada exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al restaurar versión: ' . $e->getMessage());
        }
    }

    // API Methods
    public function apiIndex(Request $request)
    {
        $query = Conductor::query();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_conductor', 'like', "%{$buscar}%")
                  ->orWhere('nombre', 'like', "%{$buscar}%")
                  ->orWhere('apellido', 'like', "%{$buscar}%");
            });
        }

        $conductores = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($conductores);
    }

    public function apiShow($id)
    {
        $conductor = Conductor::with(['validaciones', 'rutasCortas', 'turnos'])
            ->findOrFail($id);

        return $this->successResponse($conductor);
    }

    public function apiDisponibles()
    {
        $conductores = Conductor::disponibles()
            ->select('id', 'codigo_conductor', 'nombre', 'apellido', 'eficiencia', 'puntualidad')
            ->orderBy('score_general', 'desc')
            ->get();

        return $this->successResponse($conductores);
    }
}
