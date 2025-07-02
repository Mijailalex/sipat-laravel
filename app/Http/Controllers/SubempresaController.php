<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subempresa;
use App\Models\Conductor;

class SubempresaController extends Controller
{
    public function index()
    {
        $subempresas = Subempresa::with('conductores')->paginate(15);

        $metricas = [
            'total_subempresas' => Subempresa::count(),
            'activas' => Subempresa::where('activa', true)->count(),
            'conductores_asignados' => Subempresa::sum('conductores_asignados'),
            'turnos_vacios' => $this->calcularTurnosVacios()
        ];

        return view('subempresas.index', compact('subempresas', 'metricas'));
    }

    public function asignacionSemanal($id)
    {
        $subempresa = Subempresa::findOrFail($id);
        $conductoresDisponibles = Conductor::disponibles()
            ->where('estado_operativo', 'DISPONIBLE')
            ->get();

        $turnosVacios = $this->obtenerTurnosVacios($id);

        return view('subempresas.asignacion-semanal', compact(
            'subempresa', 'conductoresDisponibles', 'turnosVacios'
        ));
    }

    public function procesarAsignacionAutomatica($id)
    {
        $subempresa = Subempresa::findOrFail($id);
        $turnosVacios = $this->obtenerTurnosVacios($id);
        $conductoresDisponibles = Conductor::disponibles()->get();

        $asignaciones = [];

        foreach ($turnosVacios as $turno) {
            $conductorOptimo = $this->seleccionarConductorOptimo(
                $conductoresDisponibles, $turno
            );

            if ($conductorOptimo) {
                $asignaciones[] = [
                    'turno_id' => $turno->id,
                    'conductor_id' => $conductorOptimo->id,
                    'score_compatibilidad' => $this->calcularCompatibilidad($conductorOptimo, $turno)
                ];
            }
        }

        return response()->json([
            'success' => true,
            'asignaciones' => $asignaciones,
            'message' => count($asignaciones) . ' turnos asignados automáticamente'
        ]);
    }

    private function calcularTurnosVacios()
    {
        // Lógica para calcular turnos sin asignar
        return rand(5, 25); // Temporal
    }

    private function obtenerTurnosVacios($subempresaId)
    {
        // Lógica para obtener turnos vacíos de la subempresa
        return collect(); // Temporal
    }

    private function seleccionarConductorOptimo($conductores, $turno)
    {
        return $conductores->first(); // Temporal - aquí iría el algoritmo de optimización
    }

    private function calcularCompatibilidad($conductor, $turno)
    {
        // Algoritmo de compatibilidad basado en:
        // - Proximidad geográfica
        // - Experiencia en tipo de servicio
        // - Puntualidad histórica
        // - Disponibilidad horaria

        $score = 0;

        // Factor proximidad (30%)
        if ($conductor->origen === $turno->origen_conductor) {
            $score += 30;
        }

        // Factor puntualidad (25%)
        $score += ($conductor->puntualidad / 100) * 25;

        // Factor eficiencia (25%)
        $score += ($conductor->eficiencia / 100) * 25;

        // Factor disponibilidad (20%)
        if ($conductor->estaDisponiblePara($turno->hora_salida)) {
            $score += 20;
        }

        return round($score, 2);
    }
}
