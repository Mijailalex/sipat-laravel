<?php

namespace App\Http\Controllers;

use App\Models\Plantilla;
use App\Models\Conductor;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantillaController extends Controller
{
    public function index(Request $request)
    {
        $query = Plantilla::with('creadoPor:id,name');

        // Filtros
        if ($request->filled('tipo')) {
            $query->tipo($request->tipo);
        }

        if ($request->filled('activa')) {
            if ($request->activa === '1') {
                $query->activas();
            } else {
                $query->where('activa', false);
            }
        }

        if ($request->filled('buscar')) {
            $query->buscarTexto($request->buscar);
        }

        $plantillas = $query->orderBy('created_at', 'desc')->paginate(15);

        // Estadísticas de uso para cada plantilla
        foreach ($plantillas as $plantilla) {
            $plantilla->estadisticas_uso = $plantilla->obtenerEstadisticasUso(30);
        }

        return view('plantillas.index', compact('plantillas'));
    }

    public function show($id)
    {
        $plantilla = Plantilla::with(['creadoPor', 'plantillaTurnos' => function ($query) {
            $query->activos()->orderBy('hora_inicio');
        }])->findOrFail($id);

        $estadisticasUso = $plantilla->obtenerEstadisticasUso(30);

        return view('plantillas.show', compact('plantilla', 'estadisticasUso'));
    }

    public function create()
    {
        return view('plantillas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'codigo' => 'nullable|string|max:20|unique:plantillas',
            'descripcion' => 'nullable|string',
            'tipo' => 'required|in:DIARIA,SEMANAL,MENSUAL,ESPECIAL',
            'configuracion_turnos' => 'required|array|min:1',
            'configuracion_turnos.*.nombre' => 'required|string|max:100',
            'configuracion_turnos.*.hora_inicio' => 'required|date_format:H:i',
            'configuracion_turnos.*.hora_fin' => 'required|date_format:H:i|after:configuracion_turnos.*.hora_inicio',
            'configuracion_turnos.*.tipo' => 'required|in:REGULAR,NOCTURNO,ESPECIAL,REFUERZO',
            'configuracion_turnos.*.conductores_requeridos' => 'required|integer|min:1',
            'configuracion_turnos.*.dias_semana' => 'required|array',
            'configuracion_turnos.*.dias_semana.*' => 'integer|between:0,6',
            'parametros_especiales' => 'nullable|array',
            'fecha_vigencia_desde' => 'nullable|date',
            'fecha_vigencia_hasta' => 'nullable|date|after:fecha_vigencia_desde'
        ]);

        try {
            DB::beginTransaction();

            $validated['creado_por'] = auth()->id();
            $plantilla = Plantilla::create($validated);

            DB::commit();

            return redirect()
                ->route('plantillas.show', $plantilla)
                ->with('success', 'Plantilla creada exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->withInput()
                ->with('error', 'Error al crear plantilla: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $plantilla = Plantilla::findOrFail($id);

        return view('plantillas.edit', compact('plantilla'));
    }

    public function update(Request $request, $id)
    {
        $plantilla = Plantilla::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'tipo' => 'required|in:DIARIA,SEMANAL,MENSUAL,ESPECIAL',
            'configuracion_turnos' => 'required|array|min:1',
            'configuracion_turnos.*.nombre' => 'required|string|max:100',
            'configuracion_turnos.*.hora_inicio' => 'required|date_format:H:i',
            'configuracion_turnos.*.hora_fin' => 'required|date_format:H:i|after:configuracion_turnos.*.hora_inicio',
            'configuracion_turnos.*.tipo' => 'required|in:REGULAR,NOCTURNO,ESPECIAL,REFUERZO',
            'configuracion_turnos.*.conductores_requeridos' => 'required|integer|min:1',
            'configuracion_turnos.*.dias_semana' => 'required|array',
            'configuracion_turnos.*.dias_semana.*' => 'integer|between:0,6',
            'parametros_especiales' => 'nullable|array',
            'fecha_vigencia_desde' => 'nullable|date',
            'fecha_vigencia_hasta' => 'nullable|date|after:fecha_vigencia_desde'
        ]);

        try {
            DB::beginTransaction();

            $plantilla->update($validated);

            DB::commit();

            return redirect()
                ->route('plantillas.show', $plantilla)
                ->with('success', 'Plantilla actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return back()
                ->withInput()
                ->with('error', 'Error al actualizar plantilla: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $plantilla = Plantilla::findOrFail($id);

        try {
            // Verificar si tiene turnos activos
            if ($plantilla->turnos()->whereIn('estado', ['PROGRAMADO', 'EN_CURSO'])->exists()) {
                return back()->with('error', 'No se puede eliminar una plantilla con turnos activos');
            }

            $plantilla->delete();

            return redirect()
                ->route('plantillas.index')
                ->with('success', 'Plantilla eliminada exitosamente');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar plantilla: ' . $e->getMessage());
        }
    }

    public function activar($id)
    {
        $plantilla = Plantilla::findOrFail($id);

        try {
            $plantilla->activar();

            return $this->successResponse(null, 'Plantilla activada exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al activar plantilla: ' . $e->getMessage());
        }
    }

    public function desactivar($id)
    {
        $plantilla = Plantilla::findOrFail($id);

        try {
            $plantilla->desactivar();

            return $this->successResponse(null, 'Plantilla desactivada exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al desactivar plantilla: ' . $e->getMessage());
        }
    }

    public function clonar($id)
    {
        $plantilla = Plantilla::findOrFail($id);

        try {
            $nuevaPlantilla = $plantilla->clonar(
                $plantilla->nombre . ' (Copia)',
                $plantilla->codigo . '_COPY_' . time()
            );

            return redirect()
                ->route('plantillas.edit', $nuevaPlantilla)
                ->with('success', 'Plantilla clonada exitosamente');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al clonar plantilla: ' . $e->getMessage());
        }
    }

    public function generarTurnos(Request $request, $id)
    {
        $plantilla = Plantilla::findOrFail($id);

        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'conductores' => 'nullable|array',
            'conductores.*' => 'exists:conductores,id'
        ]);

        try {
            DB::beginTransaction();

            $conductores = [];
            if ($validated['conductores']) {
                $conductores = Conductor::whereIn('id', $validated['conductores'])
                    ->disponibles()
                    ->get();
            } else {
                $conductores = Conductor::disponibles()->get();
            }

            $turnosCreados = $plantilla->generarTurnos(
                $validated['fecha_inicio'],
                $validated['fecha_fin'],
                $conductores
            );

            DB::commit();

            return $this->successResponse([
                'turnos_creados' => count($turnosCreados),
                'turnos' => $turnosCreados
            ], count($turnosCreados) . ' turnos generados exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Error al generar turnos: ' . $e->getMessage());
        }
    }

    public function previewTurnos(Request $request, $id)
    {
        $plantilla = Plantilla::findOrFail($id);

        $validated = $request->validate([
            'fecha' => 'required|date'
        ]);

        try {
            $conductoresDisponibles = Conductor::disponibles()->get();

            // Simular generación sin crear registros
            $preview = [];
            foreach ($plantilla->configuracion_turnos as $index => $configuracion) {
                $preview[] = [
                    'indice' => $index,
                    'nombre' => $configuracion['nombre'] ?? "Turno " . ($index + 1),
                    'hora_inicio' => $configuracion['hora_inicio'],
                    'hora_fin' => $configuracion['hora_fin'],
                    'tipo' => $configuracion['tipo'] ?? 'REGULAR',
                    'conductores_requeridos' => $configuracion['conductores_requeridos'] ?? 1,
                    'conductores_sugeridos' => $conductoresDisponibles->take($configuracion['conductores_requeridos'] ?? 1)
                        ->map(function ($conductor) {
                            return [
                                'id' => $conductor->id,
                                'codigo' => $conductor->codigo_conductor,
                                'nombre' => $conductor->nombre_completo,
                                'score' => $conductor->score_general
                            ];
                        })
                ];
            }

            return $this->successResponse($preview, 'Preview generado exitosamente');

        } catch (\Exception $e) {
            return $this->errorResponse('Error al generar preview: ' . $e->getMessage());
        }
    }

    public function obtenerMasUtilizadas()
    {
        $plantillas = Plantilla::obtenerMasUtilizadas(10, 30);

        return $this->successResponse($plantillas, 'Plantillas más utilizadas obtenidas');
    }

    // API Methods
    public function apiIndex(Request $request)
    {
        $query = Plantilla::query();

        if ($request->filled('activas_solo')) {
            $query->activas()->vigentes();
        }

        $plantillas = $query->orderBy('nombre')->get();

        return $this->successResponse($plantillas);
    }

    public function apiShow($id)
    {
        $plantilla = Plantilla::with('plantillaTurnos')->findOrFail($id);

        return $this->successResponse($plantilla);
    }
}
