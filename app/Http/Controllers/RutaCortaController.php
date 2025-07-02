<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RutaCorta;
use App\Models\ConfiguracionTramo;
use App\Models\BalanceRutasCortas;
use App\Models\Conductor;
use App\Models\Parametro;
use App\Models\Validacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RutaCortaController extends Controller
{
    public function index(Request $request)
    {
        $query = RutaCorta::with('conductor');

        // Filtros
        if ($request->filled('conductor_id')) {
            $query->where('conductor_id', $request->conductor_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha_asignacion', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_asignacion', '<=', $request->fecha_hasta);
        }

        if ($request->filled('tramo')) {
            $query->where('tramo', 'like', '%' . $request->tramo . '%');
        }

        if ($request->filled('semana')) {
            $query->where('semana_numero', $request->semana);
        }

        // Ordenamiento por defecto
        $rutasCortas = $query->orderBy('fecha_asignacion', 'desc')
            ->orderBy('hora_inicio')
            ->paginate(20);

        // Métricas principales
        $metricas = $this->calcularMetricas();

        // Datos para filtros
        $conductores = Conductor::select('id', 'codigo', 'nombre')
            ->orderBy('nombre')
            ->get();

        $tramos = ConfiguracionTramo::where('es_ruta_corta', true)
            ->where('activo', true)
            ->pluck('tramo')
            ->unique()
            ->sort();

        return view('rutas-cortas.index', compact(
            'rutasCortas',
            'metricas',
            'conductores',
            'tramos'
        ));
    }

    public function create()
    {
        $conductores = Conductor::disponibles()
            ->select('id', 'codigo', 'nombre', 'origen')
            ->orderBy('nombre')
            ->get();

        $tramos = ConfiguracionTramo::where('es_ruta_corta', true)
            ->where('activo', true)
            ->orderBy('tramo')
            ->get();

        return view('rutas-cortas.create', compact('conductores', 'tramos'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conductor_id' => 'required|exists:conductores,id',
            'tramo' => 'required|exists:configuracion_tramos,tramo',
            'fecha_asignacion' => 'required|date|after_or_equal:today',
            'hora_inicio' => 'required',
            'observaciones' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validar si se puede asignar la ruta corta
        $validacion = RutaCorta::puedeAsignarRutaCorta(
            $request->conductor_id,
            $request->fecha_asignacion
        );

        if (!$validacion['puede']) {
            return redirect()->back()
                ->with('error', $validacion['razon'])
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // Obtener configuración del tramo
            $configTramo = ConfiguracionTramo::where('tramo', $request->tramo)->first();

            $fechaAsignacion = Carbon::parse($request->fecha_asignacion);
            $horaInicio = Carbon::parse($request->hora_inicio);
            $horaFin = $horaInicio->copy()->addHours($configTramo->duracion_horas);

            // Verificar si es consecutiva
            $esConsecutiva = RutaCorta::where('conductor_id', $request->conductor_id)
                ->where('fecha_asignacion', $fechaAsignacion->copy()->subDay())
                ->where('estado', '!=', 'CANCELADA')
                ->exists();

            $rutaCorta = RutaCorta::create([
                'conductor_id' => $request->conductor_id,
                'tramo' => $request->tramo,
                'rumbo' => $configTramo->rumbo,
                'fecha_asignacion' => $fechaAsignacion,
                'hora_inicio' => $horaInicio->format('H:i:s'),
                'hora_fin' => $horaFin->format('H:i:s'),
                'duracion_horas' => $configTramo->duracion_horas,
                'semana_numero' => $fechaAsignacion->week,
                'dia_semana' => $fechaAsignacion->dayOfWeek,
                'es_consecutiva' => $esConsecutiva,
                'ingreso_estimado' => $configTramo->ingreso_base,
                'observaciones' => $request->observaciones
            ]);

            // Actualizar última ruta corta del conductor
            $conductor = Conductor::find($request->conductor_id);
            $conductor->update(['ultima_ruta_corta' => $fechaAsignacion]);

            // Crear validación si es consecutiva (violación de regla)
            if ($esConsecutiva) {
                Validacion::create([
                    'tipo' => 'RUTAS_CORTAS_CONSECUTIVAS',
                    'conductor_id' => $request->conductor_id,
                    'mensaje' => 'Se asignó ruta corta en días consecutivos para el conductor ' . $conductor->nombre,
                    'severidad' => 'ADVERTENCIA',
                    'estado' => 'PENDIENTE'
                ]);
            }

            // Actualizar balance semanal
            $this->actualizarBalanceSemanal($request->conductor_id, $fechaAsignacion->week, $fechaAsignacion->year);

            DB::commit();

            return redirect()->route('rutas-cortas.index')
                ->with('success', 'Ruta corta asignada exitosamente.');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Error al asignar ruta corta: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(RutaCorta $rutaCorta)
    {
        $rutaCorta->load('conductor');

        // Balance semanal del conductor
        $balanceSemanal = RutaCorta::obtenerBalanceSemanal(
            $rutaCorta->conductor_id,
            $rutaCorta->semana_numero,
            $rutaCorta->fecha_asignacion->year
        );

        // Rutas de la misma semana
        $rutasSemana = RutaCorta::where('conductor_id', $rutaCorta->conductor_id)
            ->where('semana_numero', $rutaCorta->semana_numero)
            ->whereYear('fecha_asignacion', $rutaCorta->fecha_asignacion->year)
            ->orderBy('fecha_asignacion')
            ->get();

        return view('rutas-cortas.show', compact(
            'rutaCorta',
            'balanceSemanal',
            'rutasSemana'
        ));
    }

    public function edit(RutaCorta $rutaCorta)
    {
        if (in_array($rutaCorta->estado, ['COMPLETADA', 'CANCELADA'])) {
            return redirect()->route('rutas-cortas.index')
                ->with('error', 'No se puede editar una ruta ' . strtolower($rutaCorta->estado) . '.');
        }

        $conductores = Conductor::disponibles()
            ->select('id', 'codigo', 'nombre', 'origen')
            ->orderBy('nombre')
            ->get();

        $tramos = ConfiguracionTramo::where('es_ruta_corta', true)
            ->where('activo', true)
            ->orderBy('tramo')
            ->get();

        return view('rutas-cortas.edit', compact('rutaCorta', 'conductores', 'tramos'));
    }

    public function update(Request $request, RutaCorta $rutaCorta)
    {
        if (in_array($rutaCorta->estado, ['COMPLETADA', 'CANCELADA'])) {
            return redirect()->route('rutas-cortas.index')
                ->with('error', 'No se puede editar una ruta ' . strtolower($rutaCorta->estado) . '.');
        }

        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:PROGRAMADA,EN_CURSO,COMPLETADA,CANCELADA',
            'hora_inicio' => 'required',
            'observaciones' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $estadoAnterior = $rutaCorta->estado;

            // Recalcular hora fin si cambió la hora inicio
            if ($request->hora_inicio != $rutaCorta->hora_inicio->format('H:i')) {
                $horaInicio = Carbon::parse($request->hora_inicio);
                $horaFin = $horaInicio->copy()->addHours($rutaCorta->duracion_horas);

                $rutaCorta->update([
                    'estado' => $request->estado,
                    'hora_inicio' => $horaInicio->format('H:i:s'),
                    'hora_fin' => $horaFin->format('H:i:s'),
                    'observaciones' => $request->observaciones
                ]);
            } else {
                $rutaCorta->update([
                    'estado' => $request->estado,
                    'observaciones' => $request->observaciones
                ]);
            }

            // Si cambió a completada, actualizar métricas del conductor
            if ($request->estado === 'COMPLETADA' && $estadoAnterior !== 'COMPLETADA') {
                $conductor = $rutaCorta->conductor;
                $conductor->increment('rutas_completadas');
                $conductor->increment('horas_trabajadas', $rutaCorta->duracion_horas);
            }

            // Actualizar balance semanal
            $this->actualizarBalanceSemanal(
                $rutaCorta->conductor_id,
                $rutaCorta->semana_numero,
                $rutaCorta->fecha_asignacion->year
            );

            DB::commit();

            return redirect()->route('rutas-cortas.index')
                ->with('success', 'Ruta corta actualizada exitosamente.');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Error al actualizar ruta corta: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(RutaCorta $rutaCorta)
    {
        if ($rutaCorta->estado === 'COMPLETADA') {
            return redirect()->route('rutas-cortas.index')
                ->with('error', 'No se puede eliminar una ruta completada.');
        }

        try {
            DB::beginTransaction();

            $conductorId = $rutaCorta->conductor_id;
            $semana = $rutaCorta->semana_numero;
            $año = $rutaCorta->fecha_asignacion->year;

            $rutaCorta->delete();

            // Actualizar balance semanal
            $this->actualizarBalanceSemanal($conductorId, $semana, $año);

            DB::commit();

            return redirect()->route('rutas-cortas.index')
                ->with('success', 'Ruta corta eliminada exitosamente.');

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->route('rutas-cortas.index')
                ->with('error', 'Error al eliminar ruta corta: ' . $e->getMessage());
        }
    }

    // Configuración de tramos
    public function configuracionTramos()
    {
        $tramos = ConfiguracionTramo::orderBy('tramo')->paginate(20);

        // Parámetros relacionados con rutas cortas
        $parametros = Parametro::where('categoria', 'RUTAS')
            ->orderBy('clave')
            ->get();

        return view('rutas-cortas.configuracion.tramos', compact('tramos', 'parametros'));
    }

    // Validar asignación
    public function validarAsignacion(Request $request)
    {
        $request->validate([
            'conductor_id' => 'required|exists:conductores,id',
            'fecha' => 'required|date'
        ]);

        $validacion = RutaCorta::puedeAsignarRutaCorta(
            $request->conductor_id,
            $request->fecha
        );

        return response()->json($validacion);
    }

    // Reporte de conductor
    public function reporteConductor(Conductor $conductor, Request $request)
    {
        $semana = $request->get('semana', Carbon::now()->week);
        $año = $request->get('año', Carbon::now()->year);

        // Balance semanal del conductor
        $balance = RutaCorta::obtenerBalanceSemanal($conductor->id, $semana, $año);

        // Rutas detalladas
        $rutas = RutaCorta::where('conductor_id', $conductor->id)
            ->where('semana_numero', $semana)
            ->whereYear('fecha_asignacion', $año)
            ->orderBy('fecha_asignacion')
            ->get();

        // Histórico de las últimas 4 semanas
        $historico = collect();
        for ($i = 3; $i >= 0; $i--) {
            $semanaHistorica = $semana - $i;
            if ($semanaHistorica <= 0) {
                $semanaHistorica += 52;
                $añoHistorico = $año - 1;
            } else {
                $añoHistorico = $año;
            }

            $balanceHistorico = RutaCorta::obtenerBalanceSemanal($conductor->id, $semanaHistorica, $añoHistorico);
            $historico->push($balanceHistorico);
        }

        return view('rutas-cortas.reporte-conductor', compact(
            'conductor',
            'balance',
            'rutas',
            'historico',
            'semana',
            'año'
        ));
    }

    // ============ MÉTODOS HELPER ============

    private function calcularMetricas()
    {
        $hoy = Carbon::now();
        $semanaActual = $hoy->week;

        return [
            'total_rutas' => RutaCorta::count(),
            'programadas_hoy' => RutaCorta::where('fecha_asignacion', $hoy->toDateString())
                ->where('estado', 'PROGRAMADA')
                ->count(),
            'completadas_semana' => RutaCorta::where('semana_numero', $semanaActual)
                ->where('estado', 'COMPLETADA')
                ->count(),
            'promedio_duracion' => round(RutaCorta::avg('duracion_horas') ?? 0, 1),
            'total_ingresos_semana' => RutaCorta::where('semana_numero', $semanaActual)
                ->where('estado', 'COMPLETADA')
                ->sum('ingreso_estimado'),
            'conductores_con_rutas' => RutaCorta::where('semana_numero', $semanaActual)
                ->distinct('conductor_id')
                ->count(),
            'violaciones_consecutivas' => RutaCorta::where('es_consecutiva', true)
                ->where('semana_numero', $semanaActual)
                ->count()
        ];
    }

    private function actualizarBalanceSemanal($conductorId, $semana, $año)
    {
        $rutas = RutaCorta::where('conductor_id', $conductorId)
            ->where('semana_numero', $semana)
            ->whereYear('fecha_asignacion', $año)
            ->get();

        $programadas = $rutas->where('estado', 'PROGRAMADA')->count();
        $completadas = $rutas->where('estado', 'COMPLETADA')->count();
        $totalIngresos = $rutas->where('estado', 'COMPLETADA')->sum('ingreso_estimado');

        $total = $programadas + $completadas;
        $objetivoCumplido = $total >= 3 && $total <= 4;

        BalanceRutasCortas::updateOrCreate(
            [
                'conductor_id' => $conductorId,
                'semana_numero' => $semana,
                'año' => $año
            ],
            [
                'rutas_programadas' => $programadas,
                'rutas_completadas' => $completadas,
                'objetivo_cumplido' => $objetivoCumplido,
                'total_ingresos' => $totalIngresos
            ]
        );
    }
}
