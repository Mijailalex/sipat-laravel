<?php

namespace App\Http\Controllers;

use App\Models\Validacion;
use App\Models\Conductor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidacionController extends Controller
{
    public function index(Request $request)
    {
        $query = Validacion::with(['conductor:id,codigo_conductor,nombre,apellido', 'resueltoBy:id,name']);

        // Filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('severidad')) {
            $query->where('severidad', $request->severidad);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('conductor_id')) {
            $query->where('conductor_id', $request->conductor_id);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('titulo', 'like', "%{$buscar}%")
                  ->orWhere('descripcion', 'like', "%{$buscar}%")
                  ->orWhereHas('conductor', function ($conductorQuery) use ($buscar) {
                      $conductorQuery->where('codigo_conductor', 'like', "%{$buscar}%")
                                   ->orWhere('nombre', 'like', "%{$buscar}%")
                                   ->orWhere('apellido', 'like', "%{$buscar}%");
                  });
            });
        }

        // Ordenamiento por prioridad y fecha
        $query->porPrioridad();

        $validaciones = $query->paginate(20);

        // Estadísticas
        $estadisticas = Validacion::obtenerEstadisticas();

        // Filtros para la vista
        $conductores = Conductor::select('id', 'codigo_conductor', 'nombre', 'apellido')
            ->orderBy('codigo_conductor')
            ->get();

        $tipos = Validacion::distinct('tipo')->pluck('tipo')->sort();

        return view('validaciones.index', compact(
            'validaciones',
            'estadisticas',
            'conductores',
            'tipos'
        ));
    }

    public function show($id)
    {
        $validacion = Validacion::with([
            'conductor',
            'resueltoBy:id,name'
        ])->findOrFail($id);

        return view('validaciones.show', compact('validacion'));
    }

    public function resolver(Request $request, $id)
    {
        $validacion = Validacion::findOrFail($id);

        $validated = $request->validate([
            'accion_realizada' => 'required|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $validacion->resolver($validated['accion_realizada'], auth()->id());

            DB::commit();

            return $this->successResponse(
                null,
                'Validación resuelta exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Error al resolver validación: ' . $e->getMessage());
        }
    }

    public function omitir(Request $request, $id)
    {
        $validacion = Validacion::findOrFail($id);

        $validated = $request->validate([
            'motivo' => 'nullable|string|max:500'
        ]);

        try {
            $validacion->omitir($validated['motivo'] ?? 'Validación omitida');

            return $this->successResponse(
                null,
                'Validación omitida exitosamente'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error al omitir validación: ' . $e->getMessage());
        }
    }

    public function ejecutarValidacionesAutomaticas()
    {
        try {
            $validacionesCreadas = Validacion::ejecutarValidacionesAutomaticas();

            return $this->successResponse([
                'validaciones_creadas' => $validacionesCreadas
            ], "Se ejecutaron las validaciones automáticas. {$validacionesCreadas} nuevas validaciones creadas.");

        } catch (\Exception $e) {
            return $this->errorResponse('Error al ejecutar validaciones: ' . $e->getMessage());
        }
    }

    public function resolverMasivo(Request $request)
    {
        $validated = $request->validate([
            'validacion_ids' => 'required|array',
            'validacion_ids.*' => 'exists:validaciones,id',
            'accion_realizada' => 'required|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $resueltas = 0;
            $errores = [];

            foreach ($validated['validacion_ids'] as $id) {
                try {
                    $validacion = Validacion::findOrFail($id);
                    $validacion->resolver($validated['accion_realizada'], auth()->id());
                    $resueltas++;
                } catch (\Exception $e) {
                    $errores[] = "Validación ID {$id}: " . $e->getMessage();
                }
            }

            DB::commit();

            $mensaje = "Se resolvieron {$resueltas} validaciones exitosamente.";
            if (!empty($errores)) {
                $mensaje .= " Errores: " . implode(', ', $errores);
            }

            return $this->successResponse([
                'resueltas' => $resueltas,
                'errores' => $errores
            ], $mensaje);

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Error en resolución masiva: ' . $e->getMessage());
        }
    }

    public function obtenerTendencias(Request $request)
    {
        $dias = $request->get('dias', 30);

        $tendencias = Validacion::obtenerTendencias($dias);

        return $this->successResponse($tendencias, 'Tendencias obtenidas exitosamente');
    }

    public function obtenerEstadisticas()
    {
        $estadisticas = Validacion::obtenerEstadisticas();

        return $this->successResponse($estadisticas, 'Estadísticas obtenidas exitosamente');
    }

    public function reportePorTipo(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio', now()->subDays(30)->toDateString());
        $fechaFin = $request->get('fecha_fin', now()->toDateString());

        $reporte = Validacion::selectRaw('
                tipo,
                severidad,
                COUNT(*) as total,
                SUM(CASE WHEN estado = "RESUELTO" THEN 1 ELSE 0 END) as resueltas,
                SUM(CASE WHEN estado = "PENDIENTE" THEN 1 ELSE 0 END) as pendientes,
                AVG(prioridad_calculada) as prioridad_promedio,
                AVG(CASE WHEN estado = "RESUELTO" THEN DATEDIFF(fecha_resolucion, created_at) ELSE NULL END) as dias_promedio_resolucion
            ')
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->groupBy('tipo', 'severidad')
            ->orderBy('total', 'desc')
            ->get();

        return $this->successResponse($reporte, 'Reporte por tipo generado exitosamente');
    }

    public function reportePorConductor(Request $request)
    {
        $fechaInicio = $request->get('fecha_inicio', now()->subDays(30)->toDateString());
        $fechaFin = $request->get('fecha_fin', now()->toDateString());

        $reporte = Validacion::selectRaw('
                conductor_id,
                COUNT(*) as total_validaciones,
                SUM(CASE WHEN severidad = "CRITICA" THEN 1 ELSE 0 END) as criticas,
                SUM(CASE WHEN severidad = "ADVERTENCIA" THEN 1 ELSE 0 END) as advertencias,
                SUM(CASE WHEN estado = "RESUELTO" THEN 1 ELSE 0 END) as resueltas,
                AVG(prioridad_calculada) as prioridad_promedio
            ')
            ->with('conductor:id,codigo_conductor,nombre,apellido,estado')
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->groupBy('conductor_id')
            ->having('total_validaciones', '>', 0)
            ->orderBy('total_validaciones', 'desc')
            ->limit(20)
            ->get();

        return $this->successResponse($reporte, 'Reporte por conductor generado exitosamente');
    }

    public function exportar(Request $request)
    {
        $query = Validacion::with(['conductor:id,codigo_conductor,nombre,apellido', 'resueltoBy:id,name']);

        // Aplicar filtros similares al index
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('severidad')) {
            $query->where('severidad', $request->severidad);
        }

        if ($request->filled('fecha_inicio')) {
            $query->where('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('created_at', '<=', $request->fecha_fin . ' 23:59:59');
        }

        $validaciones = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="validaciones_' . now()->format('Y-m-d') . '.csv"'
        ];

        $callback = function() use ($validaciones) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, [
                'ID', 'Conductor', 'Tipo', 'Severidad', 'Título', 'Estado',
                'Prioridad', 'Fecha Creación', 'Fecha Resolución', 'Resuelto Por',
                'Acción Realizada'
            ]);

            // Datos
            foreach ($validaciones as $validacion) {
                fputcsv($file, [
                    $validacion->id,
                    $validacion->conductor ?
                        $validacion->conductor->codigo_conductor . ' - ' . $validacion->conductor->nombre :
                        'Sin conductor',
                    $validacion->tipo,
                    $validacion->severidad,
                    $validacion->titulo,
                    $validacion->estado,
                    $validacion->prioridad_calculada,
                    $validacion->created_at->format('Y-m-d H:i:s'),
                    $validacion->fecha_resolucion ? $validacion->fecha_resolucion->format('Y-m-d H:i:s') : '',
                    $validacion->resueltoBy ? $validacion->resueltoBy->name : '',
                    $validacion->accion_realizada ?: ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // API Methods
    public function apiIndex(Request $request)
    {
        $query = Validacion::with(['conductor:id,codigo_conductor,nombre,apellido']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('severidad')) {
            $query->where('severidad', $request->severidad);
        }

        $validaciones = $query->porPrioridad()
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($validaciones);
    }

    public function apiPendientes()
    {
        $validaciones = Validacion::pendientes()
            ->with(['conductor:id,codigo_conductor,nombre,apellido'])
            ->porPrioridad()
            ->limit(10)
            ->get();

        return $this->successResponse($validaciones);
    }

    public function apiCriticas()
    {
        $validaciones = Validacion::criticas()
            ->pendientes()
            ->with(['conductor:id,codigo_conductor,nombre,apellido'])
            ->porPrioridad()
            ->get();

        return $this->successResponse($validaciones);
    }
}
