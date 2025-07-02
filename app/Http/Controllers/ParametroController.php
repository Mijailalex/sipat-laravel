<?php

namespace App\Http\Controllers;

use App\Models\Parametro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ParametroController extends Controller
{
    public function index()
    {
        $categorias = Parametro::select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        $parametrosPorCategoria = [];
        foreach ($categorias as $categoria) {
            $parametrosPorCategoria[$categoria] = Parametro::obtenerPorCategoria($categoria);
        }

        return view('parametros.index', compact('parametrosPorCategoria', 'categorias'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clave' => 'required|string|unique:parametros,clave',
            'valor' => 'required',
            'tipo' => 'required|in:STRING,INTEGER,DECIMAL,BOOLEAN,EMAIL,URL,JSON',
            'descripcion' => 'required|string',
            'categoria' => 'required|string',
            'editable' => 'boolean',
            'requerido' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $parametro = Parametro::create([
                'clave' => $request->clave,
                'valor' => $request->valor,
                'tipo' => $request->tipo,
                'descripcion' => $request->descripcion,
                'categoria' => $request->categoria,
                'editable' => $request->boolean('editable', true),
                'requerido' => $request->boolean('requerido', false),
                'orden' => Parametro::where('categoria', $request->categoria)->max('orden') + 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Parámetro creado exitosamente',
                'parametro' => $parametro
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear parámetro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Parametro $parametro)
    {
        if (!$parametro->editable) {
            return response()->json([
                'success' => false,
                'message' => 'Este parámetro no es editable'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'valor' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$parametro->validarValor($request->valor)) {
            return response()->json([
                'success' => false,
                'message' => "El valor no es válido para el tipo {$parametro->tipo}"
            ], 422);
        }

        try {
            $parametro->update([
                'valor' => $request->valor,
                'descripcion' => $request->descripcion ?? $parametro->descripcion
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Parámetro actualizado exitosamente',
                'parametro' => $parametro
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar parámetro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Parametro $parametro)
    {
        if ($parametro->requerido) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un parámetro requerido'
            ], 403);
        }

        try {
            $parametro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Parámetro eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar parámetro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarMultiples(Request $request)
    {
        $parametros = $request->input('parametros', []);
        $actualizados = 0;
        $errores = [];

        foreach ($parametros as $clave => $valor) {
            if (Parametro::actualizarValor($clave, $valor)) {
                $actualizados++;
            } else {
                $errores[] = "Error al actualizar {$clave}";
            }
        }

        return response()->json([
            'success' => empty($errores),
            'message' => "Se actualizaron {$actualizados} parámetros",
            'errores' => $errores
        ]);
    }
}
