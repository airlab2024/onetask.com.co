<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use App\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;


class TaskDeletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Task $task;
    public string $deletedBy;

    public $updatedBy;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Task $task, string $deletedBy)
    {
        $this->task = $task;
        $this->deletedBy = $deletedBy;
    }


    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
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
            ->subject('❌ Tarea Eliminada')
            ->greeting('👋 Hola ' . $notifiable->name . ',')
            ->line('🛠️ Una tarea ha sido eliminada del sistema de gestión de servicios técnicos.')
            ->line('🔢 Código: ' . $this->task->task_short_code)
            ->line('🏢 Cliente: ' . ($this->task->cliente->nombre ?? '-'))
            ->line('🧪 Equipo: ' . $this->task->equipo)
            ->line('⚠️ Descripción de la falla: ' . $this->task->descripcion_falla_cliente)
            ->line('🗒️ Observaciones: ' . $this->task->observaciones)
            ->line('🧑‍💼 Eliminado por: ' . $this->deletedBy)
            ->line('🙏 Gracias por usar nuestro sistema!')
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
    public function toArray($notifiable): array
    {
        return [
            'message' => 'La tarea con código ' . $this->task->task_short_code . ' fue eliminada por ' . $this->deletedBy,
            'task_id' => $this->task->id,
        ];
    }
}
