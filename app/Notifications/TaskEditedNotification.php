<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\User;

class TaskEditedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $task;
    public $changes;
    public $editedBy;

    public function __construct($task, $changes, $editedBy)
    {
        $this->task = $task;
        $this->changes = $changes;
        $this->editedBy = $editedBy;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject('📝 Tarea Actualizada: ' . $this->task->task_short_code)
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line("La tarea {$this->task->task_short_code} fue actualizada por {$this->editedBy}.")
            ->line('Cambios realizados:');

            foreach ($this->changes as $field => $data) {
                $label = $this->getFieldLabel($field);

                $new = $data['new'];

                // Si el valor nuevo es un array de documentos (JSON o array PHP)
                if (is_array($new)) {
                    $fileNames = implode(', ', array_map(function ($item) {
                        return is_array($item)
                            ? ($item['name'] ?? basename($item['path'] ?? 'archivo'))
                            : basename($item);
                    }, $new));

                    $mail->line("- **{$label}**: Se cargaron: {$fileNames}");
                } else {
                    $old = is_array($data['old']) ? '...' : $data['old']; // No nos interesa mostrar documentos viejos
                    $mail->line("- **{$label}**: '{$old}' → '{$new}'");
                }
            }



        return $mail->action('🔍 Ver Tarea', url('/tasks/' . $this->task->id));
    }

    public function toArray($notifiable)
    {
        return [
            'task_id' => $this->task->id,
            'task_short_code' => $this->task->task_short_code,
            'edited_by' => $this->editedBy,
        ];
    }

    private function getFieldLabel(string $field): string
    {
        $labels = [
            'task_short_code' => 'TICKET',
            'start_date' => 'FECHA INGRESO',
            'recibido_por' => 'RECIBIDO POR',
            'entregado_por' => 'ENTREGADO POR',
            'cliente_id' => 'CLIENTE',
            'marca_id' => 'MARCA',
            'modelo' => 'MODELO',
            'serial' => 'SERIAL',
            'codigo_interno' => 'CODIGO INTERNO',
            'descripcion_falla_cliente' => 'DESCRIPCIÓN FALLA CLIENTE',
            'observaciones' => 'OBSERVACIONES',
            'asignado_a' => 'TECNICO ASIGNADO',
            'prioridad' => 'PRIORIDAD',
            'cronograma_inicio' => 'FECHA INICIO',
            'cronograma_fin' => 'FECHA FIN',
            'avance_trabajo' => 'AVANCE TRABAJO',
            'estado' => 'ESTADO',
            'documento' => 'DOCUMENTOS',
            'fotos_ingreso' => 'FOTOS DE INGRESO',
        ];

        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function formatValues(string $field, array $data): array
    {
        $old = $data['old'];
        $new = $data['new'];

        if ($field === 'cliente_id') {
            $old = Cliente::find($old)?->nombre ?? $old;
            $new = Cliente::find($new)?->nombre ?? $new;
        }

        if ($field === 'marca_id') {
            $old = Marca::find($old)?->nombre ?? $old;
            $new = Marca::find($new)?->nombre ?? $new;
        }

        if ($field === 'asignado_a') {
            $old = User::find($old)?->name ?? $old;
            $new = User::find($new)?->name ?? $new;
        }

        if (is_array($old)) {
            $old = implode(', ', array_map(fn($item) => pathinfo($item, PATHINFO_BASENAME), $old));
        }

        if (is_array($new)) {
            $new = implode(', ', array_map(fn($item) => pathinfo($item, PATHINFO_BASENAME), $new));
        }

        return [$old, $new];
    }
}
