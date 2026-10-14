<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Task;

class TaskUpcomingNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use Queueable;

    public Task $task;
    public int $diasRestantes;
    public string $scheduledStart;

    public function __construct(Task $task, int $diasRestantes)
    {
        $this->task = $task;
        $this->diasRestantes = $diasRestantes;
        $this->scheduledStart = $task->cronograma_inicio->format('Y-m-d');
    }

    public function shouldSend($notifiable, $channel): bool
    {
        $current = Task::find($this->task->id);
        return $current && $current->estado !== 'Entregado'
            && $current->cronograma_inicio?->format('Y-m-d') === $this->scheduledStart;
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
