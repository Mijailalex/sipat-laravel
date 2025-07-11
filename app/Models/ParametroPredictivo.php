<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Conductor;
use App\Models\Validacion;

class ParametroPredictivo extends Model
{
    // Nombre de tabla personalizado
    protected $table = 'parametros_predictivos';

    // Campos permitidos para asignación masiva
    protected $fillable = [
        'clave',
        'configuracion',
        'tipo_prediccion',
        'descripcion',
        'activo',
        'prioridad',
        'umbral_confianza',
        'validaciones_asociadas',
        'historial_predicciones',
        'metricas_rendimiento'
    ];

    // Conversión de tipos de datos
    protected $casts = [
        'configuracion' => 'array',
        'validaciones_asociadas' => 'array',
        'historial_predicciones' => 'array',
        'metricas_rendimiento' => 'array',
        'activo' => 'boolean',
        'umbral_confianza' => 'decimal:2'
    ];

    // Constantes para tipos de predicción
    const TIPO_RANGO = 'RANGO';
    const TIPO_FORMULA = 'FORMULA';
    const TIPO_CONDICIONAL = 'CONDICIONAL';
    const TIPO_AUTOMATICO = 'AUTOMATICO';
    const TIPO_ML = 'ML';

    // Relaciones con otras tablas
    public function validaciones()
    {
        return $this->hasMany(Validacion::class, 'parametro_predictivo_id');
    }

    // Scopes (consultas predefinidas)
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorPrioridad($query)
    {
        return $query->orderBy('prioridad', 'desc');
    }

    // Método de evaluación principal IMPLEMENTADO COMPLETO
    public function evaluar($datos)
    {
        try {
            // Verificar si el parámetro está activo
            if (!$this->activo) {
                return [
                    'cumple' => false,
                    'confianza' => 0,
                    'mensaje' => 'Parámetro predictivo inactivo',
                    'tipo_evaluacion' => 'INACTIVO',
                    'timestamp' => now()->toISOString()
                ];
            }

            // Inicializar resultado base
            $resultado = [
                'cumple' => false,
                'confianza' => 0,
                'mensaje' => '',
                'tipo_evaluacion' => $this->tipo_prediccion,
                'datos_entrada' => $datos,
                'configuracion_usada' => $this->configuracion,
                'timestamp' => now()->toISOString(),
                'detalles_evaluacion' => []
            ];

            // Evaluar según el tipo de predicción
            switch ($this->tipo_prediccion) {
                case self::TIPO_RANGO:
                    $resultado = $this->evaluarTipoRango($datos, $resultado);
                    break;

                case self::TIPO_FORMULA:
                    $resultado = $this->evaluarTipoFormula($datos, $resultado);
                    break;

                case self::TIPO_CONDICIONAL:
                    $resultado = $this->evaluarTipoCondicional($datos, $resultado);
                    break;

                case self::TIPO_AUTOMATICO:
                    $resultado = $this->evaluarTipoAutomatico($datos, $resultado);
                    break;

                case self::TIPO_ML:
                    $resultado = $this->evaluarTipoML($datos, $resultado);
                    break;

                default:
                    $resultado['mensaje'] = "Tipo de predicción no reconocido: {$this->tipo_prediccion}";
                    return $resultado;
            }

            // Aplicar umbral de confianza
            if ($resultado['confianza'] < $this->umbral_confianza) {
                $resultado['cumple'] = false;
                $resultado['mensaje'] .= " (Confianza {$resultado['confianza']}% < umbral {$this->umbral_confianza}%)";
            }

            // Registrar en historial
            $this->registrarPrediccion($resultado);

            // Log para auditoría
            Log::info("ParametroPredictivo evaluado", [
                'clave' => $this->clave,
                'tipo' => $this->tipo_prediccion,
                'resultado' => $resultado['cumple'],
                'confianza' => $resultado['confianza']
            ]);

            return $resultado;

        } catch (\Exception $e) {
            Log::error("Error en evaluación de ParametroPredictivo", [
                'clave' => $this->clave,
                'error' => $e->getMessage(),
                'datos' => $datos
            ]);

            return [
                'cumple' => false,
                'confianza' => 0,
                'mensaje' => 'Error en evaluación: ' . $e->getMessage(),
                'tipo_evaluacion' => 'ERROR',
                'timestamp' => now()->toISOString(),
                'error_details' => [
                    'exception' => get_class($e),
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ]
            ];
        }
    }

    /**
     * Evaluación por RANGO - Verifica si valores están dentro de rangos especificados
     */
    private function evaluarTipoRango($datos, $resultado)
    {
        $config = $this->configuracion;
        $cumpleRangos = true;
        $totalRangos = 0;
        $rangosAprobados = 0;

        if (!isset($config['rangos']) || !is_array($config['rangos'])) {
            $resultado['mensaje'] = 'Configuración de rangos inválida';
            return $resultado;
        }

        foreach ($config['rangos'] as $campo => $rango) {
            $totalRangos++;
            $valor = $datos[$campo] ?? null;

            if ($valor === null) {
                $resultado['detalles_evaluacion'][$campo] = 'Valor no proporcionado';
                $cumpleRangos = false;
                continue;
            }

            $min = $rango['min'] ?? null;
            $max = $rango['max'] ?? null;
            $cumpleRango = true;

            if ($min !== null && $valor < $min) {
                $cumpleRango = false;
            }
            if ($max !== null && $valor > $max) {
                $cumpleRango = false;
            }

            $resultado['detalles_evaluacion'][$campo] = [
                'valor' => $valor,
                'rango' => $rango,
                'cumple' => $cumpleRango
            ];

            if ($cumpleRango) {
                $rangosAprobados++;
            } else {
                $cumpleRangos = false;
            }
        }

        $confianza = $totalRangos > 0 ? ($rangosAprobados / $totalRangos) * 100 : 0;

        $resultado['cumple'] = $cumpleRangos;
        $resultado['confianza'] = round($confianza, 2);
        $resultado['mensaje'] = $cumpleRangos ?
            "Todos los rangos cumplidos ({$rangosAprobados}/{$totalRangos})" :
            "Rangos no cumplidos ({$rangosAprobados}/{$totalRangos})";

        return $resultado;
    }

    /**
     * Evaluación por FORMULA - Aplica fórmulas matemáticas complejas
     */
    private function evaluarTipoFormula($datos, $resultado)
    {
        $config = $this->configuracion;

        if (!isset($config['formula'])) {
            $resultado['mensaje'] = 'Fórmula no configurada';
            return $resultado;
        }

        $formula = $config['formula'];
        $variables = $config['variables'] ?? [];

        // Reemplazar variables en la fórmula
        foreach ($variables as $variable => $campo) {
            $valor = $datos[$campo] ?? 0;
            $formula = str_replace($variable, $valor, $formula);
        }

        try {
            // Evaluar fórmula de forma segura (solo operaciones básicas)
            $formulaSegura = preg_replace('/[^0-9+\-*\/(). ]/', '', $formula);
            $resultadoFormula = eval("return {$formulaSegura};");

            $umbralEsperado = $config['umbral_esperado'] ?? 1;
            $cumple = $resultadoFormula >= $umbralEsperado;

            $confianza = min(100, max(0, ($resultadoFormula / $umbralEsperado) * 100));

            $resultado['cumple'] = $cumple;
            $resultado['confianza'] = round($confianza, 2);
            $resultado['mensaje'] = "Fórmula evaluada: {$resultadoFormula} (esperado: >= {$umbralEsperado})";
            $resultado['detalles_evaluacion'] = [
                'formula_original' => $config['formula'],
                'formula_evaluada' => $formulaSegura,
                'resultado_formula' => $resultadoFormula,
                'umbral_esperado' => $umbralEsperado
            ];

        } catch (\Exception $e) {
            $resultado['mensaje'] = 'Error al evaluar fórmula: ' . $e->getMessage();
            $resultado['confianza'] = 0;
        }

        return $resultado;
    }

    /**
     * Evaluación CONDICIONAL - Verifica condiciones if/then complejas
     */
    private function evaluarTipoCondicional($datos, $resultado)
    {
        $config = $this->configuracion;
        $condiciones = $config['condiciones'] ?? [];

        if (empty($condiciones)) {
            $resultado['mensaje'] = 'No hay condiciones configuradas';
            return $resultado;
        }

        $condicionesCumplidas = 0;
        $totalCondiciones = count($condiciones);

        foreach ($condiciones as $index => $condicion) {
            $cumpleCondicion = $this->evaluarCondicionIndividual($datos, $condicion);

            $resultado['detalles_evaluacion']["condicion_{$index}"] = [
                'condicion' => $condicion,
                'cumple' => $cumpleCondicion
            ];

            if ($cumpleCondicion) {
                $condicionesCumplidas++;
            }
        }

        $operador = $config['operador'] ?? 'AND'; // AND, OR

        if ($operador === 'AND') {
            $cumple = $condicionesCumplidas === $totalCondiciones;
        } else { // OR
            $cumple = $condicionesCumplidas > 0;
        }

        $confianza = $totalCondiciones > 0 ? ($condicionesCumplidas / $totalCondiciones) * 100 : 0;

        $resultado['cumple'] = $cumple;
        $resultado['confianza'] = round($confianza, 2);
        $resultado['mensaje'] = "Condiciones ({$operador}): {$condicionesCumplidas}/{$totalCondiciones} cumplidas";

        return $resultado;
    }

    /**
     * Evaluación AUTOMATICA - Usa datos históricos del sistema
     */
    private function evaluarTipoAutomatico($datos, $resultado)
    {
        $config = $this->configuracion;
        $conductor_id = $datos['conductor_id'] ?? null;

        if (!$conductor_id) {
            $resultado['mensaje'] = 'ID de conductor requerido para evaluación automática';
            return $resultado;
        }

        $conductor = Conductor::find($conductor_id);
        if (!$conductor) {
            $resultado['mensaje'] = 'Conductor no encontrado';
            return $resultado;
        }

        // Análisis automático basado en métricas del conductor
        $metricas = [
            'eficiencia' => $conductor->eficiencia ?? 0,
            'puntualidad' => $conductor->puntualidad ?? 0,
            'dias_acumulados' => $conductor->dias_acumulados ?? 0,
            'score_general' => $conductor->score_general ?? 0
        ];

        // Lógica predictiva automática
        $predicciones = [];

        // Predicción de necesidad de descanso
        if ($metricas['dias_acumulados'] >= 5) {
            $predicciones['necesita_descanso'] = [
                'probabilidad' => min(100, ($metricas['dias_acumulados'] / 6) * 100),
                'mensaje' => 'Conductor próximo a descanso obligatorio'
            ];
        }

        // Predicción de rendimiento
        $scorePromedio = ($metricas['eficiencia'] + $metricas['puntualidad']) / 2;
        $predicciones['rendimiento_bajo'] = [
            'probabilidad' => $scorePromedio < 80 ? (100 - $scorePromedio) : 0,
            'mensaje' => $scorePromedio < 80 ? 'Rendimiento por debajo del umbral' : 'Rendimiento adecuado'
        ];

        // Calcular confianza general
        $confianzaTotal = 0;
        $prediccionesSignificativas = 0;

        foreach ($predicciones as $prediccion) {
            if ($prediccion['probabilidad'] > 20) { // Solo predicciones significativas
                $confianzaTotal += $prediccion['probabilidad'];
                $prediccionesSignificativas++;
            }
        }

        $confianzaPromedio = $prediccionesSignificativas > 0 ? $confianzaTotal / $prediccionesSignificativas : 0;
        $umbralRiesgo = $config['umbral_riesgo'] ?? 60;

        $resultado['cumple'] = $confianzaPromedio < $umbralRiesgo;
        $resultado['confianza'] = round($confianzaPromedio, 2);
        $resultado['mensaje'] = "Análisis automático: riesgo {$confianzaPromedio}% (umbral: {$umbralRiesgo}%)";
        $resultado['detalles_evaluacion'] = [
            'metricas_conductor' => $metricas,
            'predicciones' => $predicciones,
            'umbral_riesgo' => $umbralRiesgo
        ];

        return $resultado;
    }

    /**
     * Evaluación ML - Machine Learning básico (futuro: integrar con librerías ML)
     */
    private function evaluarTipoML($datos, $resultado)
    {
        $config = $this->configuracion;

        // Por ahora, ML básico usando promedios ponderados
        $pesos = $config['pesos'] ?? [
            'eficiencia' => 0.3,
            'puntualidad' => 0.3,
            'dias_acumulados' => 0.2,
            'experiencia' => 0.2
        ];

        $score = 0;
        $totalPesos = 0;

        foreach ($pesos as $campo => $peso) {
            if (isset($datos[$campo])) {
                $valor = $datos[$campo];

                // Normalizar valores según el campo
                $valorNormalizado = $this->normalizarValor($campo, $valor);

                $score += $valorNormalizado * $peso;
                $totalPesos += $peso;
            }
        }

        $scorePromedio = $totalPesos > 0 ? ($score / $totalPesos) * 100 : 0;
        $umbralML = $config['umbral_ml'] ?? 70;

        $resultado['cumple'] = $scorePromedio >= $umbralML;
        $resultado['confianza'] = round($scorePromedio, 2);
        $resultado['mensaje'] = "Predicción ML: score {$scorePromedio}% (umbral: {$umbralML}%)";
        $resultado['detalles_evaluacion'] = [
            'pesos_utilizados' => $pesos,
            'score_calculado' => $scorePromedio,
            'umbral_ml' => $umbralML,
            'datos_normalizados' => $this->obtenerDatosNormalizados($datos, $pesos)
        ];

        return $resultado;
    }

    /**
     * Evalúa una condición individual
     */
    private function evaluarCondicionIndividual($datos, $condicion)
    {
        $campo = $condicion['campo'] ?? null;
        $operador = $condicion['operador'] ?? '=';
        $valorEsperado = $condicion['valor'] ?? null;

        if (!$campo || !isset($datos[$campo])) {
            return false;
        }

        $valorActual = $datos[$campo];

        switch ($operador) {
            case '=':
            case '==':
                return $valorActual == $valorEsperado;
            case '!=':
                return $valorActual != $valorEsperado;
            case '>':
                return $valorActual > $valorEsperado;
            case '>=':
                return $valorActual >= $valorEsperado;
            case '<':
                return $valorActual < $valorEsperado;
            case '<=':
                return $valorActual <= $valorEsperado;
            case 'contains':
                return str_contains($valorActual, $valorEsperado);
            case 'in':
                return in_array($valorActual, (array)$valorEsperado);
            default:
                return false;
        }
    }

    /**
     * Normaliza valores para evaluación ML
     */
    private function normalizarValor($campo, $valor)
    {
        switch ($campo) {
            case 'eficiencia':
            case 'puntualidad':
                return min(1, max(0, $valor / 100)); // 0-100 -> 0-1

            case 'dias_acumulados':
                return min(1, max(0, (6 - $valor) / 6)); // Invertido: menos días = mejor

            case 'experiencia':
                return min(1, $valor / 10); // Años -> 0-1 (max 10 años)

            default:
                return min(1, max(0, $valor / 100)); // Default: porcentaje
        }
    }

    /**
     * Obtiene datos normalizados para debugging
     */
    private function obtenerDatosNormalizados($datos, $pesos)
    {
        $datosNormalizados = [];

        foreach ($pesos as $campo => $peso) {
            if (isset($datos[$campo])) {
                $datosNormalizados[$campo] = [
                    'valor_original' => $datos[$campo],
                    'valor_normalizado' => $this->normalizarValor($campo, $datos[$campo]),
                    'peso' => $peso
                ];
            }
        }

        return $datosNormalizados;
    }

    /**
     * Registra la predicción en el historial
     */
    private function registrarPrediccion($resultado)
    {
        $historial = $this->historial_predicciones ?? [];

        // Mantener solo los últimos 100 registros
        if (count($historial) >= 100) {
            $historial = array_slice($historial, -99);
        }

        $historial[] = [
            'timestamp' => $resultado['timestamp'],
            'cumple' => $resultado['cumple'],
            'confianza' => $resultado['confianza'],
            'tipo_evaluacion' => $resultado['tipo_evaluacion']
        ];

        $this->update(['historial_predicciones' => $historial]);
    }

    /**
     * Obtiene estadísticas del historial de predicciones
     */
    public function obtenerEstadisticasHistorial()
    {
        $historial = $this->historial_predicciones ?? [];

        if (empty($historial)) {
            return [
                'total_predicciones' => 0,
                'precision' => 0,
                'confianza_promedio' => 0
            ];
        }

        $total = count($historial);
        $exitosas = collect($historial)->where('cumple', true)->count();
        $confianzaPromedio = collect($historial)->avg('confianza');

        return [
            'total_predicciones' => $total,
            'precision' => round(($exitosas / $total) * 100, 2),
            'confianza_promedio' => round($confianzaPromedio, 2),
            'ultima_prediccion' => end($historial)
        ];
    }

    /**
     * Limpia historial antiguo
     */
    public function limpiarHistorial($diasAntiguedad = 30)
    {
        $fechaLimite = now()->subDays($diasAntiguedad);
        $historial = $this->historial_predicciones ?? [];

        $historialFiltrado = collect($historial)->filter(function ($item) use ($fechaLimite) {
            return Carbon::parse($item['timestamp'])->gt($fechaLimite);
        })->values()->toArray();

        $this->update(['historial_predicciones' => $historialFiltrado]);

        return count($historial) - count($historialFiltrado); // Cantidad de registros eliminados
    }
}
