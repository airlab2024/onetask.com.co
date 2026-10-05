<?php

namespace App\Notifications;

use App\Models\Task;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Filament\Notifications\Actions\Action;


class TaskAssignedNotification extends Notification implements ShouldQueue
{

    use Queueable;
    public Task $task;
    public string $assignedBy;
    public string $assignedTo;


    /**
     * Create a new notification instance.
     *
     * @param Task $task
     * @return void
     */
    public function __construct(Task $task, string $assignedBy, string $assignedTo)
    {
        $this->task = $task;
        $this->assignedBy = $assignedBy;
        $this->assignedTo = $assignedTo;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail']; // Asegúrate de que 'database' esté presente
    }
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📌 Nueva Tarea Asignada')
            ->greeting('👋 Hola ' . $notifiable->name . ',')
            ->line('🛠️ Se ha creado una nueva tarea en el sistema de servicios técnicos.')
            ->line('🧑‍💼 Asignado por: ' . $this->assignedBy)
            ->line('🧑 Asignado a: ' . $this->assignedTo)
            ->line('🔢 Código: ' . $this->task->task_short_code)
            ->line('🏢 Cliente: ' . ($this->task->cliente->nombre ?? '-'))
            ->line('🧪 Equipo: ' . $this->task->equipo)
            ->line('⚠️ Descripción de la falla: ' . $this->task->descripcion_falla_cliente)
            ->line('🗒️ Observaciones: ' . $this->task->observaciones)
            ->action('🔍 Ver Tarea', url('/tasks/' . $this->task->id))
            ->line('🙏 ¡Gracias por usar nuestro sistema!')
            ->attach(public_path('img/logo.png'), [
                'as' => 'logo.png',
                'mime' => 'image/png',
            ]);
    }
    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'message' => "Tarea asignada por {$this->assignedBy} a {$this->assignedTo}.",
            'task_id' => $this->task->id,
        ];
    }

}