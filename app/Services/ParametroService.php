<?php

namespace App\Services;

use App\Models\Parametro;
use Illuminate\Support\Facades\Cache;

class ParametroService
{
    /**
     * Obtener valor de parámetro con cache
     */
    public function get($clave, $default = null)
    {
        return Parametro::obtenerValor($clave, $default);
    }

    /**
     * Establecer valor de parámetro
     */
    public function set($clave, $valor, $userId = null)
    {
        return Parametro::establecerValor($clave, $valor, $userId);
    }

    /**
     * Obtener configuración completa por categorías
     */
    public function getConfiguracion()
    {
        return Cache::remember('configuracion_completa', 3600, function () {
            return Parametro::visibles()
                           ->ordenados()
                           ->get()
                           ->groupBy('categoria')
                           ->map(function ($parametros) {
                               return $parametros->pluck('valor', 'clave');
                           });
        });
    }

    /**
     * Obtener todas las categorías disponibles
     */
    public function getCategorias()
    {
        return Parametro::obtenerCategorias();
    }

    /**
     * Validar que un parámetro existe y es modificable
     */
    public function puedeModificar($clave)
    {
        $parametro = Parametro::where('clave', $clave)->first();
        return $parametro && $parametro->modificable;
    }

    /**
     * Obtener parámetros por categoría
     */
    public function porCategoria($categoria)
    {
        return Cache::remember("parametros_categoria_{$categoria}", 3600, function () use ($categoria) {
            return Parametro::categoria($categoria)
                           ->visibles()
                           ->ordenados()
                           ->get();
        });
    }

    /**
     * Limpiar cache de parámetros
     */
    public function limpiarCache($clave = null)
    {
        if ($clave) {
            Cache::forget("parametro_{$clave}");
        } else {
            // Limpiar todo el cache de parámetros
            $categorias = $this->getCategorias();
            foreach ($categorias as $categoria) {
                Cache::forget("parametros_categoria_{$categoria}");
            }
            Cache::forget('configuracion_completa');
        }
    }

    /**
     * Exportar configuración a array
     */
    public function exportar()
    {
        $parametros = Parametro::visibles()->ordenados()->get();

        $configuracion = [];
        foreach ($parametros as $parametro) {
            if (!isset($configuracion[$parametro->categoria])) {
                $configuracion[$parametro->categoria] = [];
            }

            $configuracion[$parametro->categoria][$parametro->clave] = [
                'nombre' => $parametro->nombre,
                'valor_actual' => $parametro->valor,
                'valor_por_defecto' => $parametro->valor_por_defecto,
                'tipo' => $parametro->tipo,
                'descripcion' => $parametro->descripcion,
                'modificable' => $parametro->modificable,
                'ultima_modificacion' => $parametro->updated_at?->format('Y-m-d H:i:s')
            ];
        }

        return $configuracion;
    }

    /**
     * Importar configuración desde array
     */
    public function importar(array $configuracion, $userId = null)
    {
        $actualizados = 0;
        $errores = [];

        foreach ($configuracion as $categoria => $parametros) {
            foreach ($parametros as $clave => $datos) {
                try {
                    if (isset($datos['valor_actual']) && $this->puedeModificar($clave)) {
                        $this->set($clave, $datos['valor_actual'], $userId);
                        $actualizados++;
                    }
                } catch (\Exception $e) {
                    $errores[] = "Error en {$clave}: " . $e->getMessage();
                }
            }
        }

        return [
            'actualizados' => $actualizados,
            'errores' => $errores
        ];
    }

    /**
     * Restaurar parámetro a su valor por defecto
     */
    public function restaurarDefecto($clave, $userId = null)
    {
        $parametro = Parametro::where('clave', $clave)->first();

        if (!$parametro) {
            throw new \Exception("Parámetro '{$clave}' no encontrado");
        }

        if (!$parametro->modificable) {
            throw new \Exception("Parámetro '{$clave}' no es modificable");
        }

        return $this->set($clave, $parametro->valor_por_defecto, $userId);
    }

    /**
     * Métodos de conveniencia para tipos específicos
     */
    public function getBool($clave, $default = false)
    {
        $valor = $this->get($clave, $default);
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public function getInt($clave, $default = 0)
    {
        $valor = $this->get($clave, $default);
        return (int) $valor;
    }

    public function getFloat($clave, $default = 0.0)
    {
        $valor = $this->get($clave, $default);
        return (float) $valor;
    }

    public function getArray($clave, $default = [])
    {
        $valor = $this->get($clave, $default);
        return is_array($valor) ? $valor : json_decode($valor, true) ?: $default;
    }

    public function getString($clave, $default = '')
    {
        $valor = $this->get($clave, $default);
        return (string) $valor;
    }
}
