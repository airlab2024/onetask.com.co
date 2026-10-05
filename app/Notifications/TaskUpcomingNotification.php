<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Task;

class TaskUpcomingNotification extends Notification
{
    use Queueable;

    public Task $task;
    public int $diasRestantes;

    public function __construct(Task $task, int $diasRestantes)
    {
        $this->task = $task;
        $this->diasRestantes = $diasRestantes;
    }

    public function via($notifiable)
    {
        return ['mail']; // Solo correo
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("📅 Tarea próxima a iniciar en {$this->diasRestantes} días")
            ->greeting("Hola {$notifiable->name},")
            ->line("La tarea con código **{$this->task->task_short_code}** está programada para iniciar el **{$this->task->cronograma_inicio}**.")
            ->action('Ver Tarea', route('tasks.show', $this->task->id)) // Reemplaza con la ruta real si es diferente
            ->line('Por favor realiza el seguimiento correspondiente.');
    }
}
