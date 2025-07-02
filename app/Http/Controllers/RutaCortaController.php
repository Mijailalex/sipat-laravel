<?php

namespace App\Http\Controllers;

use App\Models\RutaCorta;
use App\Models\Conductor;
use App\Models\Bus;
use App\Models\ConfiguracionTramo;
use App\Models\BalanceRutasCortas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RutaCortaController extends Controller
{
    public function index(Request $request)
    {
        $query = RutaCorta::with(['conductor:id,codigo_conductor,nombre,apellido', 'bus:id,numero_bus,placa']);

        // Filtros
        if ($request->filled('fecha')) {
            $query->where('fecha', $request->fecha);
        } else {
            // Por defecto mostrar hoy
            $query->where('fecha', now()->toDateString());
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('tramo')) {
            $query->where('tramo', $request->tramo);
        }

        if ($request->filled('conductor_id')) {
            $query->where('conductor_id', $request->conductor_id);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('origen', 'like', "%{$buscar}%")
                  ->orWhere('destino', 'like', "%{$buscar}%")
                  ->orWhere('tramo', 'like', "%{$buscar}%")
                  ->orWhereHas('conductor', function ($conductorQuery) use ($buscar) {
                      $conductorQuery->where('codigo_conductor', 'like', "%{$buscar}%");
                  });
            });
        }

        $rutasCortas = $query->orderBy('hora_inicio')->paginate(20);

        // Estadísticas para la fecha seleccionada
        $fechaSeleccionada = $request->get('fecha', now()->toDateString());
        $estadisticas = RutaCorta::where('fecha', $fechaSeleccionada)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN estado = "COMPLETADA" THEN 1 ELSE 0 END) as completadas,
                SUM(CASE WHEN estado = "EN_CURSO" THEN 1 ELSE 0 END) as en_curso,
                SUM(CASE WHEN estado = "PROGRAMADA" THEN 1 ELSE 0 END) as programadas,
                SUM(CASE WHEN estado = "CANCELADA" THEN 1 ELSE 0 END) as canceladas,
                SUM(CASE WHEN estado = "COMPLETADA" THEN pasajeros_transportados ELSE 0 END) as total_pasajeros,
                SUM(CASE WHEN estado = "COMPLETADA" THEN ingreso_estimado ELSE 0 END) as total_ingresos
            ')
            ->first();

        // Datos para filtros
        $tramos = ConfiguracionTramo::activos()->orderBy('nombre')->get();
        $conductores = Conductor::disponibles()->orderBy('codigo_conductor')->get();

        return view('rutas-cortas.index', compact(
            'rutasCortas',
            'estadisticas',
            'tramos',
            'conductores',
            'fechaSeleccionada'
        ));
    }

    public function show($id)
    {
        $rutaCorta = RutaCorta::with([
            'conductor',
            'bus',
            'configuracionTramo'
        ])->findOrFail($id);

        return view('rutas-cortas.show', compact('rutaCorta'));
    }

    public function create()
    {
        $tramos = ConfiguracionTramo::activos()->orderBy('nombre')->get();
        $conductores = Conductor::disponibles()->orderBy('codigo_conductor')->get();
        $buses = Bus::operativos()->orderBy('numero_bus')->get();

        return view('rutas-cortas.create', compact('tramos', 'conductores', 'buses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'conductor_id' => 'required|exists:conductores,id',
            'bus_id' => 'nullable|exists:buses,id',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'origen' => 'required|string|max:200',
            'destino' => 'required|string|max:200',
            'tramo' => 'required|string|max:100',
            'distancia_km' => 'nullable|numeric|min:0',
            'tarifa_cobrada' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Verificar disponibilidad del conductor
            $conductor = Conductor::findOrFail($validated['conductor_id']);
            if (!$conductor->estaDisponiblePara($validated['hora_inicio'])) {
                throw new \Exception('El conductor no está disponible para este horario');
            }

            // Verificar disponibilidad del bus si se asignó
            if ($validated['bus_id']) {
                $bus = Bus::findOrFail($validated['bus_id']);
                if (!$bus->estaDisponiblePara($validated['fecha'], $validated['hora_inicio'])) {
                    throw new \Exception('El bus no está disponible para este horario');
                }
            }

            $rutaCorta = RutaCorta::create($validated);

            DB::commit();

            return redirect()
                ->route('rutas-cortas.show', $rutaCorta)
                ->with('success', 'Ruta corta creada exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->withInput()
                ->with('error', 'Error al crear ruta corta: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $rutaCorta = RutaCorta::findOrFail($id);

        if ($rutaCorta->estado === 'COMPLETADA') {
            return back()->with('error', 'No se puede editar una ruta completada');
        }

        $tramos = ConfiguracionTramo::activos()->orderBy('nombre')->get();
        $conductores = Conductor::disponibles()->orderBy('codigo_conductor')->get();
        $buses = Bus::operativos()->orderBy('numero_bus')->get();

        return view('rutas-cortas.edit', compact('rutaCorta', 'tramos', 'conductores', 'buses'));
    }

    public function update(Request $request, $id)
    {
        $rutaCorta = RutaCorta::findOrFail($id);

        if ($rutaCorta->estado === 'COMPLETADA') {
            return back()->with('error', 'No se puede modificar una ruta completada');
        }

        $validated = $request->validate([
            'conductor_id' => 'required|exists:conductores,id',
            'bus_id' => 'nullable|exists:buses,id',
            'fecha' => 'required|date',
            'hora_inicio' => 'required|date_format:H:i',
            'origen' => 'required|string|max:200',
            'destino' => 'required|string|max:200',
            'tramo' => 'required|string|max:100',
            'distancia_km' => 'nullable|numeric|min:0',
            'tarifa_cobrada' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $rutaCorta->update($validated);

            DB::commit();

            return redirect()
                ->route('rutas-cortas.show', $rutaCorta)
                ->with('success', 'Ruta corta actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar ruta corta: ' . $e->getMessage());
        }
    }

    public function iniciar($id)
    {
        $rutaCorta = RutaCorta::findOrFail($id);

        try {
            $rutaCorta->iniciar();

            return $this->successResponse(null, 'Ruta iniciada exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al iniciar ruta: ' . $e->getMessage());
        }
    }

    public function completar(Request $request, $id)
    {
        $rutaCorta = RutaCorta::findOrFail($id);

        $validated = $request->validate([
            'pasajeros_transportados' => 'required|integer|min:0',
            'ingreso_estimado' => 'nullable|numeric|min:0',
            'calificacion_servicio' => 'nullable|numeric|between:1,5',
            'observaciones' => 'nullable|string'
        ]);

        try {
            $rutaCorta->completar($validated);

            return $this->successResponse(null, 'Ruta completada exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al completar ruta: ' . $e->getMessage());
        }
    }

    public function cancelar(Request $request, $id)
    {
        $rutaCorta = RutaCorta::findOrFail($id);

        $validated = $request->validate([
            'motivo' => 'nullable|string|max:500'
        ]);

        try {
            $rutaCorta->cancelar($validated['motivo'] ?? null);

            return $this->successResponse(null, 'Ruta cancelada exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al cancelar ruta: ' . $e->getMessage());
        }
    }

    public function reporteConductor(Request $request)
    {
        $conductorId = $request->get('conductor_id');
        $fechaInicio = $request->get('fecha_inicio', now()->subDays(30)->toDateString());
        $fechaFin = $request->get('fecha_fin', now()->toDateString());

        if (!$conductorId) {
            return back()->with('error', 'Debe seleccionar un conductor');
        }

        $conductor = Conductor::findOrFail($conductorId);

        $rutas = RutaCorta::where('conductor_id', $conductorId)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->with(['bus:id,numero_bus', 'configuracionTramo'])
            ->orderBy('fecha', 'desc')
            ->get();

        $estadisticas = [
            'total_rutas' => $rutas->count(),
            'completadas' => $rutas->where('estado', 'COMPLETADA')->count(),
            'canceladas' => $rutas->where('estado', 'CANCELADA')->count(),
            'total_pasajeros' => $rutas->where('estado', 'COMPLETADA')->sum('pasajeros_transportados'),
            'total_ingresos' => $rutas->where('estado', 'COMPLETADA')->sum('ingreso_estimado'),
            'promedio_pasajeros' => $rutas->where('estado', 'COMPLETADA')->avg('pasajeros_transportados') ?: 0,
            'promedio_calificacion' => $rutas->where('estado', 'COMPLETADA')->whereNotNull('calificacion_servicio')->avg('calificacion_servicio') ?: 0,
            'eficiencia' => $rutas->count() > 0 ?
                round(($rutas->where('estado', 'COMPLETADA')->count() / $rutas->count()) * 100, 2) : 0
        ];

        return view('rutas-cortas.reporte-conductor', compact(
            'conductor',
            'rutas',
            'estadisticas',
            'fechaInicio',
            'fechaFin'
        ));
    }

    public function balanceTramos(Request $request)
    {
        $fecha = $request->get('fecha', now()->toDateString());

        $balance = BalanceRutasCortas::where('fecha', $fecha)
            ->with('configuracionTramo:codigo_tramo,nombre,origen,destino')
            ->orderBy('total_rutas', 'desc')
            ->get();

        // Si no hay balance para la fecha, generarlo
        if ($balance->isEmpty()) {
            $tramos = RutaCorta::where('fecha', $fecha)
                ->distinct('tramo')
                ->pluck('tramo');

            foreach ($tramos as $tramo) {
                BalanceRutasCortas::actualizarBalance($fecha, $tramo);
            }

            $balance = BalanceRutasCortas::where('fecha', $fecha)
                ->with('configuracionTramo')
                ->orderBy('total_rutas', 'desc')
                ->get();
        }

        return view('rutas-cortas.balance-tramos', compact('balance', 'fecha'));
    }

    public function tendenciasTramo(Request $request, $tramo)
    {
        $dias = $request->get('dias', 30);

        $tendencias = RutaCorta::obtenerTendenciasTramo($tramo, $dias);
        $configuracionTramo = ConfiguracionTramo::where('codigo_tramo', $tramo)->first();

        return $this->successResponse([
            'tramo' => $configuracionTramo,
            'tendencias' => $tendencias
        ], 'Tendencias obtenidas exitosamente');
    }

    public function rankingConductores(Request $request)
    {
        $dias = $request->get('dias', 30);

        $ranking = RutaCorta::obtenerRankingConductores($dias);

        return $this->successResponse($ranking, 'Ranking obtenido exitosamente');
    }

    public function exportar(Request $request)
    {
        $query = RutaCorta::with(['conductor:id,codigo_conductor,nombre,apellido', 'bus:id,numero_bus']);

        // Aplicar filtros
        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('tramo')) {
            $query->where('tramo', $request->tramo);
        }

        $rutasCortas = $query->orderBy('fecha', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="rutas_cortas_' . now()->format('Y-m-d') . '.csv"'
        ];

        $callback = function() use ($rutasCortas) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Fecha', 'Conductor', 'Bus', 'Tramo', 'Origen', 'Destino',
                'Hora Inicio', 'Hora Fin', 'Estado', 'Pasajeros', 'Ingresos',
                'Calificación', 'Observaciones'
            ]);

            foreach ($rutasCortas as $ruta) {
                fputcsv($file, [
                    $ruta->fecha->format('Y-m-d'),
                    $ruta->conductor ? $ruta->conductor->codigo_conductor : '',
                    $ruta->bus ? $ruta->bus->numero_bus : '',
                    $ruta->tramo,
                    $ruta->origen,
                    $ruta->destino,
                    $ruta->hora_inicio ? $ruta->hora_inicio->format('H:i') : '',
                    $ruta->hora_fin ? $ruta->hora_fin->format('H:i') : '',
                    $ruta->estado,
                    $ruta->pasajeros_transportados ?: 0,
                    $ruta->ingreso_estimado ?: 0,
                    $ruta->calificacion_servicio ?: '',
                    $ruta->observaciones ?: ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // API Methods
    public function apiIndex(Request $request)
    {
        $query = RutaCorta::with(['conductor:id,codigo_conductor,nombre', 'bus:id,numero_bus']);

        if ($request->filled('fecha')) {
            $query->where('fecha', $request->fecha);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        $rutasCortas = $query->orderBy('hora_inicio')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($rutasCortas);
    }

    public function apiEstadisticasHoy()
    {
        $estadisticas = RutaCorta::obtenerEstadisticasHoy();
        return $this->successResponse($estadisticas);
    }
}
