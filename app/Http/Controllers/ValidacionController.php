<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Validacion;
use App\Models\Conductor;

class ValidacionController extends Controller
{
    public function index()
    {
        $validaciones = Validacion::with('conductor')->latest()->paginate(20);

        $metricas = [
            'total' => Validacion::count(),
            'pendientes' => Validacion::where('estado', 'PENDIENTE')->count(),
            'criticas' => Validacion::where('severidad', 'CRITICA')->count(),
            'resueltas_hoy' => Validacion::where('estado', 'RESUELTO')->whereDate('fecha_resolucion', today())->count()
        ];

        return view('validaciones.index', compact('validaciones', 'metricas'));
    }

    public function show($id)
    {
        $validacion = Validacion::findOrFail($id);
        return view('validaciones.show', compact('validacion'));
    }

    public function ejecutarValidaciones()
    {
        $conductoresCriticos = Conductor::where('dias_acumulados', '>=', 6)->get();
        $nuevasValidaciones = 0;

        foreach ($conductoresCriticos as $conductor) {
            $existe = Validacion::where('conductor_id', $conductor->id)
                ->where('tipo', 'DESCANSO_001')
                ->where('estado', 'PENDIENTE')
                ->exists();

            if (!$existe) {
                Validacion::create([
                    'tipo' => 'DESCANSO_001',
                    'conductor_id' => $conductor->id,
                    'mensaje' => 'Conductor requiere descanso obligatorio',
                    'severidad' => 'CRITICA',
                    'estado' => 'PENDIENTE'
                ]);
                $nuevasValidaciones++;
            }
        }

        return response()->json(['message' => "Se crearon {$nuevasValidaciones} validaciones"]);
    }
}
