<?php

namespace App\Http\Controllers;

use App\Models\Plantilla;
use App\Models\PlantillaTurno;
use App\Models\Conductor;
use Illuminate\Http\Request;

class PlantillaController extends Controller
{
    public function index()
    {
        $plantillas = Plantilla::with('turnos')->latest()->paginate(10);

        $metricas = [
            'total' => Plantilla::count(),
            'activas' => Plantilla::where('activa', true)->count(),
            'turnos_total' => PlantillaTurno::count(),
            'ultima_generacion' => Plantilla::latest()->first()?->created_at?->diffForHumans() ?? 'Nunca'
        ];

        return view('plantillas.index', compact('plantillas', 'metricas'));
    }

    public function show(Plantilla $plantilla)
    {
        $plantilla->load('turnos.conductor');

        $turnos = $plantilla->turnos()
            ->orderBy('fecha_salida')
            ->orderBy('hora_salida')
            ->paginate(50);

        return view('plantillas.show', compact('plantilla', 'turnos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
            'archivo_excel' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            // Procesar archivo Excel/CSV
            $plantilla = $this->procesarArchivoPlantilla($request);

            return redirect()->route('plantillas.show', $plantilla)
                ->with('success', 'Plantilla creada exitosamente con ' . $plantilla->turnos()->count() . ' turnos.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al procesar archivo: ' . $e->getMessage())
                ->withInput();
        }
    }

    private function procesarArchivoPlantilla($request)
    {
        // Crear plantilla
        $plantilla = Plantilla::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'activa' => true,
            'total_turnos' => 0
        ]);

        // Procesar archivo
        $archivo = $request->file('archivo_excel');
        $contenido = $this->leerArchivoExcel($archivo);

        $turnosCreados = 0;
        foreach ($contenido as $fila) {
            if ($this->validarFilaTurno($fila)) {
                PlantillaTurno::create([
                    'plantilla_id' => $plantilla->id,
                    'fecha_salida' => $fila['fecha_salida'],
                    'numero_salida' => $fila['numero_salida'],
                    'hora_salida' => $fila['hora_salida'],
                    'hora_llegada' => $fila['hora_llegada'],
                    'codigo_bus' => $fila['codigo_bus'],
                    'codigo_conductor' => $fila['codigo_conductor'],
                    'nombre_conductor' => $fila['nombre_conductor'],
                    'tipo_servicio' => $fila['tipo_servicio'],
                    'origen_destino' => $fila['origen_destino'],
                    'origen_conductor' => $fila['origen_conductor']
                ]);
                $turnosCreados++;
            }
        }

        // Actualizar total
        $plantilla->update(['total_turnos' => $turnosCreados]);

        return $plantilla;
    }

    private function leerArchivoExcel($archivo)
    {
        // Implementar lectura de Excel/CSV
        // Por ahora retornamos datos de ejemplo
        return [
            [
                'fecha_salida' => '2025-07-02',
                'numero_salida' => 'S001',
                'hora_salida' => '06:00',
                'hora_llegada' => '10:30',
                'codigo_bus' => 'B101',
                'codigo_conductor' => 'C001',
                'nombre_conductor' => 'Juan Pérez',
                'tipo_servicio' => 'RUTERO',
                'origen_destino' => 'LIMA-ICA',
                'origen_conductor' => 'LIMA'
            ]
        ];
    }

    private function validarFilaTurno($fila)
    {
        return isset($fila['fecha_salida']) &&
               isset($fila['numero_salida']) &&
               isset($fila['hora_salida']);
    }
}
