<?php
namespace App\Http\Controllers;

use App\Models\ParametroPredictivo;
use Illuminate\Http\Request;

class ParametroPredictivoController extends Controller
{
    public function index()
    {
        $parametros = ParametroPredictivo::all();
        return view('parametros.index', compact('parametros'));
    }

    public function create()
    {
        return view('parametros.create');
    }

    public function store(Request $request)
    {
        $validado = $request->validate([
            'clave' => 'required|unique:parametros_predictivos',
            'tipo_prediccion' => 'required',
            'configuracion' => 'required|array'
        ]);

        ParametroPredictivo::create($validado);

        return redirect()->route('parametros_predictivos.index')
            ->with('success', 'Parámetro creado exitosamente');
    }
}
