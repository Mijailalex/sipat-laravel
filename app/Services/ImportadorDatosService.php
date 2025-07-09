<?php

namespace App\Services;

use App\Models\Planificacion;
use App\Models\Conductor;
use App\Models\Bus;
use App\Models\HistorialPlanificacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Exception;

class ImportadorDatosService
{
    private $errores = [];
    private $exitosos = 0;
    private $omitidos = 0;
    private $archivo;
    private $fechaPlanificacion;

    /**
     * IMPORTAR PLANIFICACIONES DESDE EXCEL
     */
    public function importarPlanificacionesDesdeExcel(UploadedFile $archivo, $fechaPlanificacion)
    {
        $this->archivo = $archivo;
        $this->fechaPlanificacion = $fechaPlanificacion;
        $this->reiniciarContadores();

        Log::info('📁 Iniciando importación de planificaciones desde Excel', [
            'archivo' => $archivo->getClientOriginalName(),
            'fecha_planificacion' => $fechaPlanificacion
        ]);

        try {
            DB::beginTransaction();

            // Leer y validar archivo Excel
            $datosExcel = $this->leerArchivoExcel();

            // Validar estructura del archivo
            $this->validarEstructuraArchivo($datosExcel);

            // Procesar cada fila de datos
            foreach ($datosExcel as $indice => $fila) {
                $this->procesarFilaPlanificacion($fila, $indice + 2); // +2 porque la fila 1 son headers
            }

            // Crear historial de importación
            $this->crearHistorialImportacion();

            DB::commit();

            Log::info('✅ Importación completada exitosamente', [
                'exitosos' => $this->exitosos,
                'errores' => count($this->errores),
                'omitidos' => $this->omitidos
            ]);

            return $this->obtenerResultadoImportacion();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('❌ Error en importación de Excel: ' . $e->getMessage());

            $this->errores[] = "Error crítico en importación: " . $e->getMessage();

            return $this->obtenerResultadoImportacion();
        }
    }

    /**
     * IMPORTAR CONDUCTORES DESDE EXCEL
     */
    public function importarConductoresDesdeExcel(UploadedFile $archivo)
    {
        $this->archivo = $archivo;
        $this->reiniciarContadores();

        Log::info('👥 Iniciando importación de conductores desde Excel');

        try {
            DB::beginTransaction();

            $datosExcel = $this->leerArchivoExcel();
            $this->validarEstructuraConductores($datosExcel);

            foreach ($datosExcel as $indice => $fila) {
                $this->procesarFilaConductor($fila, $indice + 2);
            }

            $this->crearHistorialImportacion('CONDUCTORES');

            DB::commit();

            return $this->obtenerResultadoImportacion();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('❌ Error en importación de conductores: ' . $e->getMessage());

            $this->errores[] = "Error crítico: " . $e->getMessage();
            return $this->obtenerResultadoImportacion();
        }
    }

    /**
     * IMPORTAR DATOS MASIVOS CON VALIDACIÓN AVANZADA
     */
    public function importarDatosMasivos(UploadedFile $archivo, $tipoDatos = 'PLANIFICACIONES')
    {
        $this->archivo = $archivo;
        $this->reiniciarContadores();

        Log::info('📊 Iniciando importación masiva', [
            'tipo' => $tipoDatos,
            'archivo' => $archivo->getClientOriginalName()
        ]);

        try {
            // Validar tamaño del archivo
            if ($archivo->getSize() > 10 * 1024 * 1024) { // 10MB
                throw new Exception('El archivo excede el tamaño máximo permitido (10MB)');
            }

            DB::beginTransaction();

            $datosExcel = $this->leerArchivoExcel();

            // Validación previa masiva
            $erroresValidacion = $this->validarDatosMasivos($datosExcel, $tipoDatos);

            if (count($erroresValidacion) > 0) {
                $this->errores = array_merge($this->errores, $erroresValidacion);
                throw new Exception('Errores de validación encontrados antes del procesamiento');
            }

            // Procesar en lotes para mejor rendimiento
            $lotes = array_chunk($datosExcel, 50); // Procesar de 50 en 50

            foreach ($lotes as $indiceLote => $lote) {
                $this->procesarLoteDatos($lote, $tipoDatos, $indiceLote);
            }

            $this->crearHistorialImportacion($tipoDatos . '_MASIVO');

            DB::commit();

            return $this->obtenerResultadoImportacion();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('❌ Error en importación masiva: ' . $e->getMessage());

            $this->errores[] = "Error crítico en importación masiva: " . $e->getMessage();
            return $this->obtenerResultadoImportacion();
        }
    }

    /**
     * LEER ARCHIVO EXCEL CON MANEJO DE ERRORES
     */
    private function leerArchivoExcel()
    {
        try {
            $datos = Excel::toArray(null, $this->archivo);

            if (empty($datos) || empty($datos[0])) {
                throw new Exception('El archivo Excel está vacío o no se pudo leer correctamente');
            }

            // Tomar la primera hoja del Excel
            $datosHoja = $datos[0];

            // Remover la primera fila (headers)
            array_shift($datosHoja);

            // Filtrar filas vacías
            $datosLimpios = array_filter($datosHoja, function($fila) {
                return !empty(array_filter($fila, function($celda) {
                    return !is_null($celda) && trim($celda) !== '';
                }));
            });

            return $datosLimpios;

        } catch (Exception $e) {
            throw new Exception('Error al leer archivo Excel: ' . $e->getMessage());
        }
    }

    /**
     * VALIDAR ESTRUCTURA DEL ARCHIVO PARA PLANIFICACIONES
     */
    private function validarEstructuraArchivo($datos)
    {
        if (empty($datos)) {
            throw new Exception('El archivo no contiene datos válidos');
        }

        $primeraFila = reset($datos);
        $columnasRequeridas = 10; // Número mínimo de columnas esperadas

        if (count($primeraFila) < $columnasRequeridas) {
            throw new Exception("El archivo debe tener al menos {$columnasRequeridas} columnas");
        }

        // Validar que hay datos útiles
        $filasValidas = 0;
        foreach ($datos as $fila) {
            if (!empty($fila[0]) || !empty($fila[1])) { // Al menos fecha o número de salida
                $filasValidas++;
            }
        }

        if ($filasValidas === 0) {
            throw new Exception('No se encontraron filas válidas para procesar');
        }

        Log::info("✅ Estructura validada: {$filasValidas} filas válidas encontradas");
    }

    /**
     * VALIDAR ESTRUCTURA PARA CONDUCTORES
     */
    private function validarEstructuraConductores($datos)
    {
        if (empty($datos)) {
            throw new Exception('El archivo no contiene datos de conductores');
        }

        $primeraFila = reset($datos);
        $columnasMinimas = 6; // código, nombre, documento, teléfono, etc.

        if (count($primeraFila) < $columnasMinimas) {
            throw new Exception("El archivo de conductores debe tener al menos {$columnasMinimas} columnas");
        }
    }

    /**
     * PROCESAR FILA INDIVIDUAL DE PLANIFICACIÓN
     */
    private function procesarFilaPlanificacion($fila, $numeroFila)
    {
        try {
            // Mapear datos de la fila
            $datos = $this->mapearDatosPlanificacion($fila);

            // Validar datos específicos
            $erroresFila = $this->validarDatosFila($datos, $numeroFila);

            if (!empty($erroresFila)) {
                $this->errores = array_merge($this->errores, $erroresFila);
                $this->omitidos++;
                return;
            }

            // Verificar si ya existe
            $planificacionExistente = Planificacion::where('numero_salida', $datos['numero_salida'])
                ->where('fecha_salida', $datos['fecha_salida'])
                ->first();

            if ($planificacionExistente) {
                $this->omitidos++;
                Log::debug("Planificación ya existe (fila {$numeroFila}): {$datos['numero_salida']}");
                return;
            }

            // Crear nueva planificación
            $planificacion = Planificacion::create([
                'fecha_salida' => $datos['fecha_salida'],
                'numero_salida' => $datos['numero_salida'],
                'hora_salida' => $datos['hora_salida'],
                'hora_llegada' => $datos['hora_llegada'],
                'codigo_bus' => $datos['codigo_bus'],
                'codigo_conductor' => $datos['codigo_conductor'],
                'nombre_conductor' => $datos['nombre_conductor'],
                'tipo_servicio' => $datos['tipo_servicio'],
                'origen_destino' => $datos['origen_destino'],
                'origen_conductor' => $datos['origen_conductor'],
                'estado_planificacion' => 'PROGRAMADO',
                'created_by' => auth()->id() ?? 1,
                'observaciones' => 'Importado desde Excel fila ' . $numeroFila
            ]);

            $this->exitosos++;

            Log::debug("✅ Planificación creada (fila {$numeroFila}): {$planificacion->numero_salida}");

        } catch (Exception $e) {
            $this->errores[] = "Fila {$numeroFila}: Error al procesar - " . $e->getMessage();
            Log::error("Error procesando fila {$numeroFila}: " . $e->getMessage());
        }
    }

    /**
     * PROCESAR FILA DE CONDUCTOR
     */
    private function procesarFilaConductor($fila, $numeroFila)
    {
        try {
            $datos = $this->mapearDatosConductor($fila);

            $erroresFila = $this->validarDatosConductor($datos, $numeroFila);

            if (!empty($erroresFila)) {
                $this->errores = array_merge($this->errores, $erroresFila);
                $this->omitidos++;
                return;
            }

            // Verificar si el conductor ya existe
            $conductorExistente = Conductor::where('codigo', $datos['codigo'])
                ->orWhere('documento', $datos['documento'])
                ->first();

            if ($conductorExistente) {
                $this->omitidos++;
                return;
            }

            // Crear nuevo conductor
            $conductor = Conductor::create([
                'codigo' => $datos['codigo'],
                'nombre' => $datos['nombre'],
                'apellidos' => $datos['apellidos'],
                'documento' => $datos['documento'],
                'telefono' => $datos['telefono'],
                'email' => $datos['email'] ?? null,
                'direccion' => $datos['direccion'] ?? null,
                'estado' => 'DISPONIBLE',
                'eficiencia' => 85.0,
                'puntualidad' => 90.0,
                'dias_acumulados' => 0,
                'created_by' => auth()->id() ?? 1
            ]);

            $this->exitosos++;

        } catch (Exception $e) {
            $this->errores[] = "Fila {$numeroFila}: Error al crear conductor - " . $e->getMessage();
        }
    }

    /**
     * MAPEAR DATOS DE PLANIFICACIÓN DESDE FILA EXCEL
     */
    private function mapearDatosPlanificacion($fila)
    {
        return [
            'fecha_salida' => $this->procesarFecha($fila[0] ?? null),
            'numero_salida' => $this->limpiarTexto($fila[1] ?? null),
            'hora_salida' => $this->procesarHora($fila[2] ?? null),
            'hora_llegada' => $this->procesarHora($fila[3] ?? null),
            'codigo_bus' => $this->limpiarTexto($fila[4] ?? null),
            'codigo_conductor' => $this->limpiarTexto($fila[5] ?? null),
            'nombre_conductor' => $this->limpiarTexto($fila[6] ?? null),
            'tipo_servicio' => $this->limpiarTexto($fila[7] ?? 'REGULAR'),
            'origen_destino' => $this->limpiarTexto($fila[8] ?? null),
            'origen_conductor' => $this->limpiarTexto($fila[9] ?? null)
        ];
    }

    /**
     * MAPEAR DATOS DE CONDUCTOR DESDE FILA EXCEL
     */
    private function mapearDatosConductor($fila)
    {
        return [
            'codigo' => $this->limpiarTexto($fila[0] ?? null),
            'nombre' => $this->limpiarTexto($fila[1] ?? null),
            'apellidos' => $this->limpiarTexto($fila[2] ?? null),
            'documento' => $this->limpiarTexto($fila[3] ?? null),
            'telefono' => $this->limpiarTexto($fila[4] ?? null),
            'email' => $this->limpiarTexto($fila[5] ?? null),
            'direccion' => $this->limpiarTexto($fila[6] ?? null)
        ];
    }

    /**
     * VALIDAR DATOS DE FILA INDIVIDUAL
     */
    private function validarDatosFila($datos, $numeroFila)
    {
        $errores = [];

        // Validaciones obligatorias
        if (empty($datos['fecha_salida'])) {
            $errores[] = "Fila {$numeroFila}: Fecha de salida requerida";
        }

        if (empty($datos['numero_salida'])) {
            $errores[] = "Fila {$numeroFila}: Número de salida requerido";
        }

        if (empty($datos['codigo_conductor'])) {
            $errores[] = "Fila {$numeroFila}: Código de conductor requerido";
        }

        if (empty($datos['codigo_bus'])) {
            $errores[] = "Fila {$numeroFila}: Código de bus requerido";
        }

        // Validar formato de hora
        if (!empty($datos['hora_salida']) && !$this->validarFormatoHora($datos['hora_salida'])) {
            $errores[] = "Fila {$numeroFila}: Formato de hora de salida inválido";
        }

        if (!empty($datos['hora_llegada']) && !$this->validarFormatoHora($datos['hora_llegada'])) {
            $errores[] = "Fila {$numeroFila}: Formato de hora de llegada inválido";
        }

        // Validar que hora de llegada sea posterior a hora de salida
        if (!empty($datos['hora_salida']) && !empty($datos['hora_llegada'])) {
            if (Carbon::parse($datos['hora_salida'])->gte(Carbon::parse($datos['hora_llegada']))) {
                $errores[] = "Fila {$numeroFila}: Hora de llegada debe ser posterior a hora de salida";
            }
        }

        return $errores;
    }

    /**
     * VALIDAR DATOS DE CONDUCTOR
     */
    private function validarDatosConductor($datos, $numeroFila)
    {
        $errores = [];

        if (empty($datos['codigo'])) {
            $errores[] = "Fila {$numeroFila}: Código de conductor requerido";
        }

        if (empty($datos['nombre'])) {
            $errores[] = "Fila {$numeroFila}: Nombre requerido";
        }

        if (empty($datos['documento'])) {
            $errores[] = "Fila {$numeroFila}: Documento requerido";
        }

        // Validar formato de documento (DNI peruano: 8 dígitos)
        if (!empty($datos['documento']) && !preg_match('/^\d{8}$/', $datos['documento'])) {
            $errores[] = "Fila {$numeroFila}: Documento debe tener 8 dígitos";
        }

        // Validar email si está presente
        if (!empty($datos['email']) && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "Fila {$numeroFila}: Email inválido";
        }

        return $errores;
    }

    /**
     * VALIDAR DATOS MASIVOS ANTES DEL PROCESAMIENTO
     */
    private function validarDatosMasivos($datos, $tipoDatos)
    {
        $errores = [];
        $codigos = [];
        $documentos = [];

        foreach ($datos as $indice => $fila) {
            $numeroFila = $indice + 2;

            if ($tipoDatos === 'CONDUCTORES') {
                $datosFila = $this->mapearDatosConductor($fila);

                // Verificar duplicados en el mismo archivo
                if (!empty($datosFila['codigo'])) {
                    if (in_array($datosFila['codigo'], $codigos)) {
                        $errores[] = "Fila {$numeroFila}: Código {$datosFila['codigo']} duplicado en el archivo";
                    }
                    $codigos[] = $datosFila['codigo'];
                }

                if (!empty($datosFila['documento'])) {
                    if (in_array($datosFila['documento'], $documentos)) {
                        $errores[] = "Fila {$numeroFila}: Documento {$datosFila['documento']} duplicado en el archivo";
                    }
                    $documentos[] = $datosFila['documento'];
                }
            }
        }

        return $errores;
    }

    /**
     * PROCESAR LOTE DE DATOS
     */
    private function procesarLoteDatos($lote, $tipoDatos, $indiceLote)
    {
        Log::info("Procesando lote {$indiceLote} de {$tipoDatos}");

        foreach ($lote as $indice => $fila) {
            $numeroFilaGlobal = ($indiceLote * 50) + $indice + 2;

            if ($tipoDatos === 'CONDUCTORES') {
                $this->procesarFilaConductor($fila, $numeroFilaGlobal);
            } else {
                $this->procesarFilaPlanificacion($fila, $numeroFilaGlobal);
            }
        }
    }

    /**
     * CREAR HISTORIAL DE IMPORTACIÓN
     */
    private function crearHistorialImportacion($tipo = 'PLANIFICACIONES')
    {
        HistorialPlanificacion::create([
            'fecha_servicio' => $this->fechaPlanificacion ?? Carbon::today()->format('Y-m-d'),
            'tipo_proceso' => 'IMPORTACION_' . $tipo,
            'estado' => count($this->errores) > 0 ? 'COMPLETADO_CON_ERRORES' : 'COMPLETADO',
            'conductores_procesados' => 0,
            'asignaciones_realizadas' => $this->exitosos,
            'tiempo_procesamiento' => 0, // Se podría calcular
            'metricas' => [
                'archivo_importado' => $this->archivo->getClientOriginalName(),
                'registros_exitosos' => $this->exitosos,
                'registros_omitidos' => $this->omitidos,
                'total_errores' => count($this->errores)
            ],
            'observaciones' => "Importación desde archivo Excel. Éxitos: {$this->exitosos}, Errores: " . count($this->errores) . ", Omitidos: {$this->omitidos}",
            'created_by' => auth()->id() ?? 1
        ]);
    }

    /**
     * UTILIDADES DE PROCESAMIENTO
     */
    private function procesarFecha($fecha)
    {
        if (empty($fecha)) return null;

        try {
            // Intentar varios formatos de fecha
            $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];

            foreach ($formatos as $formato) {
                $fechaParsed = Carbon::createFromFormat($formato, $fecha);
                if ($fechaParsed && $fechaParsed->format($formato) === $fecha) {
                    return $fechaParsed->format('Y-m-d');
                }
            }

            // Si es un número (Excel timestamp)
            if (is_numeric($fecha)) {
                $fechaExcel = Carbon::createFromTimestamp(($fecha - 25569) * 86400);
                return $fechaExcel->format('Y-m-d');
            }

            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function procesarHora($hora)
    {
        if (empty($hora)) return null;

        try {
            // Si ya está en formato HH:MM
            if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
                return $hora;
            }

            // Si es decimal de Excel (ej: 0.5 = 12:00)
            if (is_numeric($hora) && $hora < 1) {
                $minutosTotales = $hora * 24 * 60;
                $horas = floor($minutosTotales / 60);
                $minutos = $minutosTotales % 60;
                return sprintf('%02d:%02d', $horas, $minutos);
            }

            // Intentar parsear como time
            $timeParsed = Carbon::parse($hora);
            return $timeParsed->format('H:i');

        } catch (Exception $e) {
            return null;
        }
    }

    private function limpiarTexto($texto)
    {
        if (is_null($texto)) return null;
        return trim(strip_tags($texto));
    }

    private function validarFormatoHora($hora)
    {
        return preg_match('/^\d{1,2}:\d{2}$/', $hora) &&
               Carbon::parse($hora) !== false;
    }

    private function reiniciarContadores()
    {
        $this->errores = [];
        $this->exitosos = 0;
        $this->omitidos = 0;
    }

    private function obtenerResultadoImportacion()
    {
        return [
            'exitosos' => $this->exitosos,
            'errores' => count($this->errores),
            'omitidos' => $this->omitidos,
            'detalles_errores' => $this->errores,
            'archivo_procesado' => $this->archivo->getClientOriginalName()
        ];
    }
}
