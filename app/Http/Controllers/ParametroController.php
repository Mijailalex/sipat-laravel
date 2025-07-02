<?php

namespace App\Http\Controllers;

use App\Models\Parametro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParametroController extends Controller
{
    public function index(Request $request)
    {
        $query = Parametro::with('modificadoPor:id,name');

        // Filtro por categoría
        if ($request->filled('categoria')) {
            $query->categoria($request->categoria);
        }

        // Filtro por búsqueda
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('clave', 'like', "%{$buscar}%")
                  ->orWhere('descripcion', 'like', "%{$buscar}%");
            });
        }

        // Solo mostrar parámetros visibles por defecto
        if (!$request->filled('mostrar_ocultos')) {
            $query->visibles();
        }

        $parametros = $query->ordenados()->paginate(20);

        // Obtener categorías para el filtro
        $categorias = Parametro::distinct('categoria')->pluck('categoria')->sort();

        // Agrupar parámetros por categoría para mejor visualización
        $parametrosAgrupados = $parametros->getCollection()->groupBy('categoria');

        return view('parametros.index', compact(
            'parametros',
            'parametrosAgrupados',
            'categorias'
        ));
    }

    public function show($id)
    {
        $parametro = Parametro::with('modificadoPor')->findOrFail($id);

        return view('parametros.show', compact('parametro'));
    }

    public function update(Request $request, $id)
    {
        $parametro = Parametro::findOrFail($id);

        if (!$parametro->modificable) {
            return $this->errorResponse('Este parámetro no puede ser modificado');
        }

        $validated = $request->validate([
            'valor' => 'required'
        ]);

        try {
            Parametro::establecerValor(
                $parametro->clave,
                $validated['valor'],
                auth()->id()
            );

            return $this->successResponse(
                ['nuevo_valor' => $parametro->fresh()->valor_formateado],
                'Parámetro actualizado exitosamente'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar parámetro: ' . $e->getMessage());
        }
    }

    public function restaurarDefecto($id)
    {
        $parametro = Parametro::findOrFail($id);

        try {
            $parametro->restaurarValorDefecto();

            return $this->successResponse(
                ['nuevo_valor' => $parametro->fresh()->valor_formateado],
                'Parámetro restaurado al valor por defecto'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error al restaurar parámetro: ' . $e->getMessage());
        }
    }

    public function obtenerPorCategoria($categoria)
    {
        $parametros = Parametro::obtenerPorCategoria($categoria);

        return $this->successResponse($parametros, 'Parámetros obtenidos exitosamente');
    }

    public function actualizarMasivo(Request $request)
    {
        $validated = $request->validate([
            'parametros' => 'required|array',
            'parametros.*.clave' => 'required|string',
            'parametros.*.valor' => 'required'
        ]);

        $actualizados = 0;
        $errores = [];

        DB::beginTransaction();

        try {
            foreach ($validated['parametros'] as $param) {
                try {
                    Parametro::establecerValor(
                        $param['clave'],
                        $param['valor'],
                        auth()->id()
                    );
                    $actualizados++;
                } catch (\Exception $e) {
                    $errores[] = "Parámetro {$param['clave']}: " . $e->getMessage();
                }
            }

            if (empty($errores)) {
                DB::commit();
                return $this->successResponse(
                    ['actualizados' => $actualizados],
                    "Se actualizaron {$actualizados} parámetros exitosamente"
                );
            } else {
                DB::rollback();
                return $this->errorResponse(
                    'Errores en la actualización masiva',
                    $errores
                );
            }

        } catch (\Exception $e) {
            DB::rollback();
            return $this->errorResponse('Error en actualización masiva: ' . $e->getMessage());
        }
    }

    public function limpiarCache(Request $request)
    {
        try {
            if ($request->filled('categoria')) {
                Parametro::limpiarCache($request->categoria);
                $mensaje = "Cache limpiado para la categoría {$request->categoria}";
            } else {
                Parametro::limpiarCache();
                $mensaje = "Cache de parámetros limpiado completamente";
            }

            return $this->successResponse(null, $mensaje);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al limpiar cache: ' . $e->getMessage());
        }
    }

    public function exportarConfiguracion()
    {
        $parametros = Parametro::visibles()->ordenados()->get();

        $configuracion = [];
        foreach ($parametros as $parametro) {
            if (!isset($configuracion[$parametro->categoria])) {
                $configuracion[$parametro->categoria] = [];
            }

            $configuracion[$parametro->categoria][$parametro->clave] = [
                'nombre' => $parametro->nombre,
                'valor_actual' => $parametro->valor_formateado,
                'valor_por_defecto' => $parametro->valor_por_defecto,
                'tipo' => $parametro->tipo,
                'descripcion' => $parametro->descripcion,
                'modificable' => $parametro->modificable,
                'ultima_modificacion' => $parametro->updated_at?->format('Y-m-d H:i:s')
            ];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="configuracion_sistema_' . now()->format('Y-m-d') . '.json"'
        ];

        return response()->json($configuracion, 200, $headers);
    }

    public function importarConfiguracion(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:json'
        ]);

        try {
            $contenido = file_get_contents($request->file('archivo')->path());
            $configuracion = json_decode($contenido, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Archivo JSON inválido');
            }

            $actualizados = 0;
            $errores = [];

            DB::beginTransaction();

            foreach ($configuracion as $categoria => $parametros) {
                foreach ($parametros as $clave => $datos) {
                    try {
                        $parametro = Parametro::where('clave', $clave)->first();

                        if ($parametro && $parametro->modificable) {
                            Parametro::establecerValor($clave, $datos['valor_actual'], auth()->id());
                            $actualizados++;
                        } elseif (!$parametro) {
                            $errores[] = "Parámetro {$clave} no encontrado";
                        } else {
                            $errores[] = "Parámetro {$clave} no es modificable";
                        }
                    } catch (\Exception $e) {
                        $errores[] = "Parámetro {$clave}: " . $e->getMessage();
                    }
                }
            }

            DB::commit();

            $mensaje = "Importación completada. {$actualizados} parámetros actualizados.";
            if (!empty($errores)) {
                $mensaje .= " Errores: " . implode(', ', array_slice($errores, 0, 5));
            }

            return back()->with('success', $mensaje);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Error en la importación: ' . $e->getMessage());
        }
    }

    public function validarConfiguracion()
    {
        $parametros = Parametro::all();
        $problemas = [];

        foreach ($parametros as $parametro) {
            try {
                // Validar que el valor actual es válido según el tipo
                $valorConvertido = Parametro::convertirValor($parametro->valor, $parametro->tipo);

                // Validar opciones si existen
                if ($parametro->opciones && !in_array($parametro->valor, $parametro->opciones)) {
                    $problemas[] = [
                        'parametro' => $parametro->clave,
                        'problema' => 'Valor no está en las opciones válidas',
                        'valor_actual' => $parametro->valor,
                        'opciones_validas' => $parametro->opciones
                    ];
                }

                // Validaciones adicionales según el tipo
                if ($parametro->tipo === 'INTEGER' && !is_int($valorConvertido)) {
                    $problemas[] = [
                        'parametro' => $parametro->clave,
                        'problema' => 'Valor no es un entero válido',
                        'valor_actual' => $parametro->valor
                    ];
                }

            } catch (\Exception $e) {
                $problemas[] = [
                    'parametro' => $parametro->clave,
                    'problema' => 'Error de validación: ' . $e->getMessage(),
                    'valor_actual' => $parametro->valor
                ];
            }
        }

        return $this->successResponse([
            'configuracion_valida' => empty($problemas),
            'total_parametros' => $parametros->count(),
            'problemas_encontrados' => count($problemas),
            'problemas' => $problemas
        ], 'Validación de configuración completada');
    }

    public function obtenerHistorialCambios(Request $request)
    {
        $dias = $request->get('dias', 30);

        $historial = Parametro::with('modificadoPor:id,name')
            ->where('updated_at', '>=', now()->subDays($dias))
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($parametro) {
                return [
                    'clave' => $parametro->clave,
                    'nombre' => $parametro->nombre,
                    'categoria' => $parametro->categoria,
                    'valor_actual' => $parametro->valor_formateado,
                    'modificado_por' => $parametro->modificadoPor?->name ?? 'Sistema',
                    'fecha_modificacion' => $parametro->updated_at->format('Y-m-d H:i:s'),
                    'hace' => $parametro->updated_at->diffForHumans()
                ];
            });

        return $this->successResponse($historial, 'Historial obtenido exitosamente');
    }

    // API Methods
    public function apiIndex(Request $request)
    {
        $query = Parametro::visibles();

        if ($request->filled('categoria')) {
            $query->categoria($request->categoria);
        }

        $parametros = $query->ordenados()->get()->groupBy('categoria');

        return $this->successResponse($parametros);
    }

    public function apiObtenerValor($clave)
    {
        try {
            $valor = Parametro::obtenerValor($clave);

            if ($valor === null) {
                return $this->errorResponse('Parámetro no encontrado', null, 404);
            }

            return $this->successResponse(['valor' => $valor]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener parámetro: ' . $e->getMessage());
        }
    }

    public function apiEstablecerValor(Request $request, $clave)
    {
        $validated = $request->validate([
            'valor' => 'required'
        ]);

        try {
            Parametro::establecerValor($clave, $validated['valor'], auth()->id());

            return $this->successResponse(
                ['nuevo_valor' => Parametro::obtenerValor($clave)],
                'Parámetro actualizado exitosamente'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Error al establecer parámetro: ' . $e->getMessage());
        }
    }
}
