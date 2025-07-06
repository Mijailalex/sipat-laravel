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
        $query->orderByRaw('
            CASE severidad
                WHEN "CRITICA" THEN 1
                WHEN "ALTA" THEN 2
                WHEN "MEDIA" THEN 3
                WHEN "BAJA" THEN 4
                ELSE 5
            END
        ')->orderBy('created_at', 'desc');

        $validaciones = $query->paginate(20);

        // MÉTRICAS REQUERIDAS PARA LA VISTA
        $metricas = [
            'total' => Validacion::count(),
            'pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'criticas' => Validacion::where('estado', 'PENDIENTE')->where('severidad', 'CRITICA')->count(),
            'resueltas_hoy' => Validacion::where('fecha_resolucion', '>=', now()->startOfDay())->count(),
            'porcentaje_criticas' => $this->calcularPorcentaje(
                Validacion::where('estado', 'PENDIENTE')->where('severidad', 'CRITICA')->count(),
                Validacion::where('estado', 'PENDIENTE')->count()
            ),
            'tiempo_promedio_resolucion' => $this->calcularTiempoPromedioResolucion(),
            'conductores_con_validaciones' => Validacion::where('estado', 'PENDIENTE')->distinct('conductor_id')->count()
        ];

        // Estadísticas adicionales
        $estadisticas = [
            'por_severidad' => Validacion::selectRaw('severidad, count(*) as total')
                ->where('estado', 'PENDIENTE')
                ->groupBy('severidad')
                ->pluck('total', 'severidad'),
            'por_tipo' => Validacion::selectRaw('tipo, count(*) as total')
                ->where('estado', 'PENDIENTE')
                ->groupBy('tipo')
                ->pluck('total', 'tipo'),
            'tendencia_semanal' => $this->obtenerTendenciaSemanal()
        ];

        // Filtros para la vista
        $conductores = Conductor::select('id', 'codigo_conductor', 'nombre', 'apellido')
            ->orderBy('codigo_conductor')
            ->get();

        $tipos = Validacion::distinct('tipo')->whereNotNull('tipo')->pluck('tipo')->sort();

        $estados = [
            'PENDIENTE' => 'Pendiente',
            'EN_PROCESO' => 'En Proceso',
            'RESUELTO' => 'Resuelto',
            'CERRADO' => 'Cerrado'
        ];

        $severidades = [
            'CRITICA' => 'Crítica',
            'ALTA' => 'Alta',
            'MEDIA' => 'Media',
            'BAJA' => 'Baja'
        ];

        return view('validaciones.index', compact(
            'validaciones',
            'metricas',
            'estadisticas',
            'conductores',
            'tipos',
            'estados',
            'severidades'
        ));
    }

    public function show($id)
    {
        $validacion = Validacion::with(['conductor', 'resueltoBy'])->findOrFail($id);

        // Historial de validaciones del mismo conductor
        $historial = Validacion::where('conductor_id', $validacion->conductor_id)
            ->where('id', '!=', $id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('validaciones.show', compact('validacion', 'historial'));
    }

    public function resolver(Request $request, $id)
    {
        $validacion = Validacion::findOrFail($id);

        $validated = $request->validate([
            'accion_realizada' => 'required|string|max:1000',
            'observaciones_resolucion' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $validacion->update([
                'estado' => 'RESUELTO',
                'fecha_resolucion' => now(),
                'resuelto_por' => auth()->id(),
                'accion_realizada' => $validated['accion_realizada'],
                'observaciones_resolucion' => $validated['observaciones_resolucion'] ?? null
            ]);

            DB::commit();

            return redirect()
                ->route('validaciones.show', $validacion)
                ->with('success', 'Validación resuelta exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->withInput()
                ->with('error', 'Error al resolver validación: ' . $e->getMessage());
        }
    }

    public function resolverMasivo(Request $request)
    {
        $validated = $request->validate([
            'validacion_ids' => 'required|array|min:1',
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

                    if ($validacion->estado === 'PENDIENTE') {
                        $validacion->update([
                            'estado' => 'RESUELTO',
                            'fecha_resolucion' => now(),
                            'resuelto_por' => auth()->id(),
                            'accion_realizada' => $validated['accion_realizada']
                        ]);
                        $resueltas++;
                    }
                } catch (\Exception $e) {
                    $errores[] = "Validación ID {$id}: " . $e->getMessage();
                }
            }

            DB::commit();

            $mensaje = "Se resolvieron {$resueltas} validaciones exitosamente.";
            if (!empty($errores)) {
                $mensaje .= " Errores: " . implode(', ', $errores);
            }

            return back()->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error en resolución masiva: ' . $e->getMessage());
        }
    }

    public function ejecutarValidaciones()
    {
        try {
            $conductores = Conductor::where('estado', 'DISPONIBLE')->get();
            $validacionesCreadas = 0;

            foreach ($conductores as $conductor) {
                $nuevasValidaciones = $conductor->validarDatos();
                $validacionesCreadas += count($nuevasValidaciones);
            }

            return back()->with('success',
                "Validaciones ejecutadas. Se crearon {$validacionesCreadas} nuevas validaciones."
            );

        } catch (\Exception $e) {
            return back()->with('error', 'Error al ejecutar validaciones: ' . $e->getMessage());
        }
    }

    public function obtenerTendencias(Request $request)
    {
        $dias = $request->get('dias', 30);

        $tendencias = Validacion::selectRaw('
                DATE(created_at) as fecha,
                COUNT(*) as total,
                SUM(CASE WHEN severidad = "CRITICA" THEN 1 ELSE 0 END) as criticas,
                SUM(CASE WHEN estado = "RESUELTO" THEN 1 ELSE 0 END) as resueltas
            ')
            ->where('created_at', '>=', now()->subDays($dias))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tendencias
        ]);
    }

    public function obtenerEstadisticas()
    {
        $estadisticas = [
            'resumen' => [
                'total' => Validacion::count(),
                'pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
                'resueltas' => Validacion::where('estado', 'RESUELTO')->count(),
                'criticas' => Validacion::where('severidad', 'CRITICA')->count()
            ],
            'por_tipo' => Validacion::selectRaw('tipo, count(*) as total')
                ->groupBy('tipo')
                ->orderBy('total', 'desc')
                ->get(),
            'por_severidad' => Validacion::selectRaw('severidad, count(*) as total')
                ->groupBy('severidad')
                ->get(),
            'tiempo_promedio_resolucion' => $this->calcularTiempoPromedioResolucion()
        ];

        return response()->json([
            'success' => true,
            'data' => $estadisticas
        ]);
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
                AVG(CASE WHEN fecha_resolucion IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, created_at, fecha_resolucion)
                    ELSE NULL END) as tiempo_promedio_resolucion
            ')
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->groupBy('tipo', 'severidad')
            ->orderBy('total', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reporte,
            'periodo' => ['inicio' => $fechaInicio, 'fin' => $fechaFin]
        ]);
    }

    // MÉTODOS AUXILIARES PRIVADOS

    private function calcularPorcentaje($numerador, $denominador)
    {
        return $denominador > 0 ? round(($numerador / $denominador) * 100, 2) : 0;
    }

    private function calcularTiempoPromedioResolucion()
    {
        try {
            $tiempoPromedio = Validacion::whereNotNull('fecha_resolucion')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, fecha_resolucion)) as promedio')
                ->value('promedio');

            return round($tiempoPromedio ?: 0, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function obtenerTendenciaSemanal()
    {
        try {
            $datos = [];
            for ($i = 6; $i >= 0; $i--) {
                $fecha = now()->subDays($i);
                $total = Validacion::whereDate('created_at', $fecha->toDateString())->count();
                $datos[] = [
                    'fecha' => $fecha->format('d/m'),
                    'total' => $total
                ];
            }
            return $datos;
        } catch (\Exception $e) {
            return [];
        }
    }
}
