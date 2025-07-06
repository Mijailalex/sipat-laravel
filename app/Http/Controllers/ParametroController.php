<?php

namespace App\Http\Controllers;

use App\Models\Parametro;
use App\Http\Requests\ParametroRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ParametroController extends Controller
{
    public function index(Request $request)
    {
        $query = Parametro::query();

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->filled('buscar')) {
            $texto = $request->buscar;
            $query->where(function($q) use ($texto) {
                $q->where('nombre', 'like', "%{$texto}%")
                  ->orWhere('clave', 'like', "%{$texto}%")
                  ->orWhere('descripcion', 'like', "%{$texto}%");
            });
        }

        if ($request->has('modificable')) {
            $query->where('modificable', $request->boolean('modificable'));
        }

        $parametros = $query->where('visible_interfaz', true)
                          ->orderBy('categoria')
                          ->orderBy('orden_visualizacion')
                          ->orderBy('nombre')
                          ->paginate(20);

        $categorias = Parametro::select('categoria')->distinct()->orderBy('categoria')->pluck('categoria');

        $metricas = [
            'total' => Parametro::count(),
            'por_categoria' => Parametro::selectRaw('categoria, count(*) as total')
                                      ->groupBy('categoria')
                                      ->pluck('total', 'categoria'),
            'modificables' => Parametro::where('modificable', true)->count(),
            'no_modificables' => Parametro::where('modificable', false)->count()
        ];

        return view('parametros.index', compact('parametros', 'categorias', 'metricas'));
    }

    public function create()
    {
        $categorias = Parametro::select('categoria')->distinct()->orderBy('categoria')->pluck('categoria');
        return view('parametros.create', compact('categorias'));
    }

    public function store(ParametroRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->getValidatedData();
            Parametro::create($data);

            DB::commit();
            return redirect()->route('parametros.index')->with('success', 'Parámetro creado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creando parámetro: ' . $e->getMessage());
            return back()->with('error', 'Error al crear parámetro: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        $parametro = Parametro::findOrFail($id);
        return view('parametros.show', compact('parametro'));
    }

    public function edit($id)
    {
        $parametro = Parametro::findOrFail($id);
        $categorias = Parametro::select('categoria')->distinct()->orderBy('categoria')->pluck('categoria');
        return view('parametros.edit', compact('parametro', 'categorias'));
    }

    public function update(ParametroRequest $request, $id)
    {
        $parametro = Parametro::findOrFail($id);

        try {
            DB::beginTransaction();

            $data = $request->getValidatedData();
            $parametro->update($data);

            DB::commit();
            return redirect()->route('parametros.index')->with('success', 'Parámetro actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error actualizando parámetro: ' . $e->getMessage());
            return back()->with('error', 'Error al actualizar parámetro: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $parametro = Parametro::findOrFail($id);

            if (!$parametro->modificable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este parámetro no puede ser eliminado'
                ], 403);
            }

            $parametro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Parámetro eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error eliminando parámetro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar parámetro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportarConfiguracion()
    {
        try {
            $configuracion = Parametro::exportarConfiguracion();

            $headers = [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="parametros_configuracion_' . now()->format('Y-m-d_H-i-s') . '.json"'
            ];

            return response()->json($configuracion, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error exportando configuración: ' . $e->getMessage());
            return back()->with('error', 'Error al exportar configuración: ' . $e->getMessage());
        }
    }

    public function importarConfiguracion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|mimes:json|max:2048'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $contenido = file_get_contents($request->file('archivo')->path());
            $configuracion = json_decode($contenido, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Archivo JSON inválido: ' . json_last_error_msg());
            }

            $actualizados = 0;
            $creados = 0;
            $errores = [];

            DB::beginTransaction();

            foreach ($configuracion as $categoria => $parametros) {
                foreach ($parametros as $clave => $datos) {
                    try {
                        $parametro = Parametro::where('clave', $clave)->first();

                        if ($parametro) {
                            if ($parametro->modificable) {
                                $parametro->update([
                                    'valor' => $datos['valor_actual'] ?? $datos['valor_por_defecto'],
                                    'modificado_por' => auth()->id()
                                ]);
                                $actualizados++;
                            } else {
                                $errores[] = "Parámetro {$clave} no es modificable";
                            }
                        } else {
                            Parametro::create([
                                'categoria' => $categoria,
                                'clave' => $clave,
                                'nombre' => $datos['nombre'],
                                'descripcion' => $datos['descripcion'] ?? null,
                                'tipo' => $datos['tipo'],
                                'valor' => $datos['valor_actual'] ?? $datos['valor_por_defecto'],
                                'valor_por_defecto' => $datos['valor_por_defecto'],
                                'opciones' => isset($datos['opciones']) ? json_encode($datos['opciones']) : null,
                                'modificable' => $datos['modificable'] ?? true,
                                'visible_interfaz' => true,
                                'orden_visualizacion' => $datos['orden_visualizacion'] ?? 0,
                                'modificado_por' => auth()->id()
                            ]);
                            $creados++;
                        }
                    } catch (\Exception $e) {
                        $errores[] = "Parámetro {$clave}: " . $e->getMessage();
                    }
                }
            }

            DB::commit();

            $mensaje = "Importación completada. {$actualizados} actualizados, {$creados} creados.";
            if (!empty($errores)) {
                $mensaje .= " Errores: " . implode(', ', array_slice($errores, 0, 3));
            }

            return back()->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en importación: ' . $e->getMessage());
            return back()->with('error', 'Error en la importación: ' . $e->getMessage());
        }
    }

    public function descargarPlantilla()
    {
        try {
            $plantilla = [
                "EJEMPLO_CATEGORIA" => [
                    "ejemplo_parametro_string" => [
                        "nombre" => "Ejemplo Parámetro Texto",
                        "descripcion" => "Este es un ejemplo de parámetro de tipo texto",
                        "tipo" => "STRING",
                        "valor_actual" => "Valor ejemplo",
                        "valor_por_defecto" => "Valor por defecto",
                        "opciones" => ["Opción 1", "Opción 2", "Opción 3"],
                        "modificable" => true,
                        "orden_visualizacion" => 1
                    ],
                    "ejemplo_parametro_numero" => [
                        "nombre" => "Ejemplo Parámetro Número",
                        "descripcion" => "Este es un ejemplo de parámetro numérico",
                        "tipo" => "INTEGER",
                        "valor_actual" => "100",
                        "valor_por_defecto" => "50",
                        "opciones" => null,
                        "modificable" => true,
                        "orden_visualizacion" => 2
                    ],
                    "ejemplo_parametro_boolean" => [
                        "nombre" => "Ejemplo Parámetro Booleano",
                        "descripcion" => "Este es un ejemplo de parámetro verdadero/falso",
                        "tipo" => "BOOLEAN",
                        "valor_actual" => "true",
                        "valor_por_defecto" => "false",
                        "opciones" => ["true", "false"],
                        "modificable" => true,
                        "orden_visualizacion" => 3
                    ]
                ]
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="plantilla_parametros.json"'
            ];

            return response()->json($plantilla, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error generando plantilla: ' . $e->getMessage());
            return back()->with('error', 'Error al generar plantilla: ' . $e->getMessage());
        }
    }

    public function validarConfiguracion()
    {
        try {
            $parametros = Parametro::all();
            $problemas = [];

            foreach ($parametros as $parametro) {
                try {
                    if (!$parametro->validarValorActual()) {
                        $problemas[] = [
                            'parametro' => $parametro->clave,
                            'problema' => 'Valor no válido según el tipo',
                            'valor_actual' => $parametro->valor
                        ];
                    }
                } catch (\Exception $e) {
                    $problemas[] = [
                        'parametro' => $parametro->clave,
                        'problema' => $e->getMessage(),
                        'valor_actual' => $parametro->valor
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'configuracion_valida' => empty($problemas),
                    'total_parametros' => $parametros->count(),
                    'problemas_encontrados' => count($problemas),
                    'problemas' => $problemas
                ],
                'message' => 'Validación completada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error validando configuración: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al validar configuración: ' . $e->getMessage()
            ], 500);
        }
    }

    public function restaurarDefecto($id)
    {
        try {
            $parametro = Parametro::findOrFail($id);

            if (!$parametro->modificable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este parámetro no puede ser modificado'
                ], 403);
            }

            $parametro->update([
                'valor' => $parametro->valor_por_defecto,
                'modificado_por' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'data' => ['nuevo_valor' => $parametro->fresh()->valor],
                'message' => 'Parámetro restaurado al valor por defecto'
            ]);

        } catch (\Exception $e) {
            Log::error('Error restaurando parámetro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar parámetro: ' . $e->getMessage()
            ], 500);
        }
    }
}
