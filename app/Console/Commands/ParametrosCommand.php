<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Parametro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ParametrosCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sipat:parametros
                           {accion : Acción a ejecutar (listar|obtener|establecer|resetear|cache-clear)}
                           {--clave= : Clave del parámetro}
                           {--valor= : Nuevo valor del parámetro}
                           {--categoria= : Filtrar por categoría}
                           {--forzar : Forzar operación sin confirmación}';

    /**
     * The console command description.
     */
    protected $description = 'Gestionar parámetros de configuración del sistema SIPAT';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accion = $this->argument('accion');

        switch ($accion) {
            case 'listar':
                return $this->listarParametros();
            case 'obtener':
                return $this->obtenerParametro();
            case 'establecer':
                return $this->establecerParametro();
            case 'resetear':
                return $this->resetearParametro();
            case 'cache-clear':
                return $this->limpiarCache();
            default:
                $this->error("Acción no válida: {$accion}");
                return Command::FAILURE;
        }
    }

    private function listarParametros()
    {
        $query = Parametro::query();

        if ($categoria = $this->option('categoria')) {
            $query->where('categoria', $categoria);
        }

        $parametros = $query->orderBy('categoria')->orderBy('clave')->get();

        if ($parametros->isEmpty()) {
            $this->info('No se encontraron parámetros.');
            return Command::SUCCESS;
        }

        $this->table(
            ['Categoría', 'Clave', 'Valor', 'Tipo', 'Modificable'],
            $parametros->map(function ($parametro) {
                return [
                    $parametro->categoria,
                    $parametro->clave,
                    $parametro->valor_formateado,
                    $parametro->tipo,
                    $parametro->modificable ? 'Sí' : 'No'
                ];
            })
        );

        return Command::SUCCESS;
    }

    private function obtenerParametro()
    {
        $clave = $this->option('clave');

        if (!$clave) {
            $this->error('Debe especificar la clave del parámetro con --clave');
            return Command::FAILURE;
        }

        try {
            $valor = Parametro::obtenerValor($clave);

            if ($valor === null) {
                $this->error("Parámetro '{$clave}' no encontrado.");
                return Command::FAILURE;
            }

            $this->info("Valor de '{$clave}': {$valor}");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error al obtener parámetro: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function establecerParametro()
    {
        $clave = $this->option('clave');
        $valor = $this->option('valor');

        if (!$clave || $valor === null) {
            $this->error('Debe especificar --clave y --valor');
            return Command::FAILURE;
        }

        try {
            $parametro = Parametro::where('clave', $clave)->first();

            if (!$parametro) {
                $this->error("Parámetro '{$clave}' no encontrado.");
                return Command::FAILURE;
            }

            if (!$parametro->modificable) {
                $this->error("Parámetro '{$clave}' no es modificable.");
                return Command::FAILURE;
            }

            $valorAnterior = $parametro->valor;

            if (!$this->option('forzar')) {
                if (!$this->confirm("¿Cambiar '{$clave}' de '{$valorAnterior}' a '{$valor}'?")) {
                    $this->info('Operación cancelada.');
                    return Command::SUCCESS;
                }
            }

            Parametro::establecerValor($clave, $valor, 1); // Usuario sistema

            $this->info("Parámetro '{$clave}' actualizado exitosamente.");
            $this->line("Valor anterior: {$valorAnterior}");
            $this->line("Valor nuevo: {$valor}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error al establecer parámetro: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function resetearParametro()
    {
        $clave = $this->option('clave');

        if (!$clave) {
            $this->error('Debe especificar la clave del parámetro con --clave');
            return Command::FAILURE;
        }

        try {
            $parametro = Parametro::where('clave', $clave)->first();

            if (!$parametro) {
                $this->error("Parámetro '{$clave}' no encontrado.");
                return Command::FAILURE;
            }

            $valorAnterior = $parametro->valor;
            $valorDefecto = $parametro->valor_por_defecto;

            if (!$this->option('forzar')) {
                if (!$this->confirm("¿Resetear '{$clave}' al valor por defecto '{$valorDefecto}'?")) {
                    $this->info('Operación cancelada.');
                    return Command::SUCCESS;
                }
            }

            $parametro->restaurarValorDefecto();

            $this->info("Parámetro '{$clave}' reseteado al valor por defecto.");
            $this->line("Valor anterior: {$valorAnterior}");
            $this->line("Valor por defecto: {$valorDefecto}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error al resetear parámetro: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function limpiarCache()
    {
        try {
            if ($categoria = $this->option('categoria')) {
                Parametro::limpiarCache($categoria);
                $this->info("Cache limpiado para la categoría '{$categoria}'.");
            } else {
                Parametro::limpiarCache();
                $this->info('Cache de parámetros limpiado completamente.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error al limpiar cache: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
