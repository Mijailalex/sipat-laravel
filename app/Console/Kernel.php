<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Validaciones automáticas cada hora durante el día
        $schedule->command('sipat:validaciones --forzar')
                ->hourly()
                ->between('6:00', '22:00')
                ->withoutOverlapping()
                ->runInBackground();

        // Backup completo diario a las 2:00 AM
        $schedule->command('sipat:backup --tipo=completo')
                ->dailyAt('2:00')
                ->withoutOverlapping();

        // Backup de datos cada 6 horas
        $schedule->command('sipat:backup --tipo=datos')
                ->cron('0 */6 * * *')
                ->withoutOverlapping();

        // Mantenimiento semanal los domingos a las 3:00 AM
        $schedule->command('sipat:mantenimiento --forzar')
                ->weeklyOn(0, '3:00')
                ->withoutOverlapping();

        // Generar métricas diarias a las 23:55
        $schedule->call(function () {
            \App\Models\MetricaDiaria::generarMetricasHoy();
        })->dailyAt('23:55');

        // Actualizar balances de rutas cortas cada 2 horas
        $schedule->call(function () {
            $tramos = \App\Models\RutaCorta::where('fecha', now()->toDateString())
                ->distinct('tramo')
                ->pluck('tramo');

            foreach ($tramos as $tramo) {
                \App\Models\BalanceRutasCortas::actualizarBalance(now()->toDateString(), $tramo);
            }
        })->cron('0 */2 * * *');

        // Limpiar logs antiguos semanalmente
        $schedule->command('queue:prune-batches --hours=168')
                ->weekly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
