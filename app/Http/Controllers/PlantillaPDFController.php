<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plantilla;
use App\Models\PlantillaTurno;
use Barryvdh\DomPDF\Facade\Pdf;

class PlantillaPDFController extends Controller
{
    public function generarPDF($plantillaId)
    {
        $plantilla = Plantilla::findOrFail($plantillaId);
        $turnos = PlantillaTurno::where('plantilla_id', $plantillaId)
            ->orderBy('fecha_salida')
            ->orderBy('hora_salida')
            ->get();

        $pdf = PDF::loadView('plantillas.pdf', compact('plantilla', 'turnos'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download("plantilla_{$plantilla->nombre}_" . date('Y-m-d') . ".pdf");
    }

    public function generarExcel($plantillaId)
    {
        $plantilla = Plantilla::findOrFail($plantillaId);
        $turnos = PlantillaTurno::where('plantilla_id', $plantillaId)
            ->orderBy('fecha_salida')
            ->orderBy('hora_salida')
            ->get();

        $filename = "plantilla_{$plantilla->nombre}_" . date('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($turnos) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers con las 10 columnas requeridas
            fputcsv($file, [
                'Fecha de Salida',
                'Número de Salida',
                'Hora Salida',
                'Hora Llegada',
                'Código de Bus',
                'Código de Conductor',
                'Nombre de Conductor',
                'Tipo de Servicio',
                'Origen-Destino',
                'Origen de Conductor'
            ]);

            // Datos
            foreach ($turnos as $turno) {
                fputcsv($file, [
                    $turno->fecha_salida,
                    $turno->numero_salida,
                    $turno->hora_salida,
                    $turno->hora_llegada,
                    $turno->codigo_bus,
                    $turno->codigo_conductor,
                    $turno->nombre_conductor,
                    $turno->tipo_servicio,
                    $turno->origen_destino,
                    $turno->origen_conductor
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
