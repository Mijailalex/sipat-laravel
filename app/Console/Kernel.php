<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Validaciones automáticas cada hora durante horario laboral
        $schedule->command('sipat:validaciones')
                 ->hourlyAt(0)
                 ->between('6:00', '22:00')
                 ->withoutOverlapping();

        // Backup diario a las 2:00 AM
        $schedule->command('sipat:backup --tipo=completo')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();

        // Mantenimiento semanal los domingos a las 3:00 AM
        $schedule->command('sipat:mantenimiento --forzar')
                 ->weeklyOn(0, '03:00')
                 ->withoutOverlapping();

        // Backup de datos cada 6 horas
        $schedule->command('sipat:backup --tipo=datos')
                 ->everySixHours()
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
