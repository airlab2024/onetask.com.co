<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    // 👇 Aquí va el arreglo de comandos personalizados
    protected $commands = [
        \App\Console\Commands\NotificarTareasProximas::class,
    ];
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('notificaciones:tareas-proximas')->dailyAt('08:00')->timezone(config('app.timezone'))->withoutOverlapping(); // Ejemplo: todos los días a las 8:00 AM
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
