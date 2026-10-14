<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskUpcomingNotification;
use Carbon\Carbon;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action;
use App\Support\TaskReminder;

class NotificarTareasProximas extends Command
{
    protected $signature = 'notificaciones:tareas-proximas';
    protected $description = 'Notificar tareas con fecha de inicio próxima';

    public function handle()
    {
        $hoy = Carbon::now()->startOfDay();
        $fechaNotificacion = $hoy->copy()->addDays(2);

        $tareas = Task::where('cronograma_inicio', $fechaNotificacion->toDateString())->where('estado', '!=', 'Entregado')->get();
        $usuarios = User::role('SUPER ADMINISTRADOR')->get();

        if ($tareas->isEmpty()) {
            $this->info('✅ No hay tareas para notificar hoy.');
            return;
        }

        $sent = 0;
        foreach ($tareas as $tarea) {


            foreach ($usuarios as $usuario) {
                $sent += (int) TaskReminder::sendOnce($tarea, $usuario, function () use ($tarea, $usuario) {
                // Enviar correo
                $usuario->notify(new TaskUpcomingNotification($tarea, 2));

                // Enviar notificación en la aplicación
                FilamentNotification::make()
                    ->title("📅 Tarea próxima a iniciar en 2 días")
                    ->body("La tarea **{$tarea->task_short_code}** iniciará el **" . \Carbon\Carbon::parse($tarea->cronograma_inicio)->format('Y-m-d') . "**.")
                    ->icon('heroicon-o-clock')
                    ->actions([
                        Action::make('Ver tarea')
                            ->url(route('tasks.show', $tarea->id))
                            ->button()
                    ])
                    ->sendToDatabase($usuario);
                });
            }
        }

        $this->info("Notificaciones programadas: {$sent}. Recordatorios repetidos omitidos.");
    }
}
