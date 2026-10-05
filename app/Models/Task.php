<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskDeletedNotification;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Carbon\Carbon;
use App\Notifications\TaskUpdatedNotification;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Notifications\TaskEditedNotification;




class Task extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'task_short_code',
        'start_date',
        'recibido_por',
        'entregado_por',
        'cliente_id',
        'equipo',
        'marca_id',
        'modelo',
        'serial',
        'codigo_interno',
        'accesorios_entregados',
        'descripcion_falla_cliente',
        'observaciones',
        'fotos_ingreso',
        'prioridad',
        'asignado_a',
        'cronograma_inicio',
        'cronograma_fin',
        'avance_trabajo',
        'actividades_realizadas',
        'informe_diagnostico',
        'documento',
        'remision_ingreso',
        'remision_ingreso_nombre',
        'remision_salida',
        'remision_salida_nombre',
        'estado',
        'fecha_retiro',
        'retirado_por',
        'original_filename',
        'original_filenames_fotos_ingreso',
        'creado_por',
        'eliminado_por',

    ];

    public static function generateTaskShortCode()
    {
        $date = now()->format('Ymd');
        $prefix = "Ticket-{$date}-";
        $last = self::withTrashed()->where('task_short_code', 'like', $prefix . '%')
            ->pluck('task_short_code')->map(fn ($code) => (int) substr($code, strlen($prefix)))->max();
        $count = ($last ?? 0) + 1;
        return "Ticket-{$date}-{$count}";
    }

    protected static function booted()
    {
        static::creating(function ($task) {

            // Generar el código de tarea
            $task->task_short_code = self::generateTaskShortCode();

            // Asignar el usuario que crea la tarea
            $task->creado_por = auth()->id();

        });

        static::created(function ($task) {
            if ($assigned = $task->user) {
                $assigned->notify((new TaskAssignedNotification($task, auth()->user()->name ?? 'Sistema', $assigned->name))->afterCommit());
                Notification::make()->title('Nueva tarea asignada')
                    ->body("Se ha asignado la tarea {$task->task_short_code}.")
                    ->actions([Action::make('Ver tarea')->url(route('tasks.show', $task->id))->button()])
                    ->sendToDatabase($assigned);
            }
        });

        static::updated(function ($task) {
            $updatedBy = auth()->user()->name ?? 'Sistema';

            // Buscar el técnico asignado
            $tecnicoAsignado = \App\Models\User::find($task->asignado_a);

            // Buscar super administradores
            $superAdmins = \App\Models\User::whereHas('roles', function ($query) {
                $query->where('name', 'SUPER ADMINISTRADOR');
            })->get();

            // Detectar cambios reales (evitar updated_at)
            $changes = [];
            foreach ($task->getChanges() as $field => $newValue) {
                if (!in_array($field, ['updated_at'])) {
                    $original = $task->getOriginal($field);
                    $changes[$field] = [
                        'old' => $original,
                        'new' => $task->getAttribute($field),
                    ];
                }
            }

            if (!$changes) {
                return;
            }

            // 📩 Notificar Técnico asignado (solo si es técnico)
            if ($tecnicoAsignado && $tecnicoAsignado->hasRole('TECNICO')) {
                $tecnicoAsignado->notify((new \App\Notifications\TaskEditedNotification($task, $changes, $updatedBy))->afterCommit());

                // Notificación de aplicación
                \Filament\Notifications\Notification::make()
                    ->title('✏️ Tarea Actualizada')
                    ->icon('heroicon-o-pencil-alt')
                    ->body("La tarea **{$task->task_short_code}** fue actualizada por **{$updatedBy}**.")
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('Ver Tarea')
                            ->url(route('tasks.show', $task->id))
                            ->button(),
                    ])
                    ->sendToDatabase($tecnicoAsignado);
            }

            // 📩 Notificar Super Administradores
            if ($superAdmins->isNotEmpty()) {
                foreach ($superAdmins as $admin) {
                    $admin->notify((new \App\Notifications\TaskUpdatedNotification($task, $updatedBy, $changes))->afterCommit());

                    \Filament\Notifications\Notification::make()
                        ->title('📝 Tarea Actualizada (Detalle)')
                        ->icon('heroicon-o-pencil')
                        ->body("La tarea **{$task->task_short_code}** fue actualizada por **{$updatedBy}** con cambios importantes.")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('Ver Tarea')
                                ->url(route('tasks.show', $task->id))
                                ->button(),
                        ])
                        ->sendToDatabase($admin);
                }
            }


        });



        static::deleted(function ($task) {
            $task->eliminado_por = auth()->id(); // Guardar usuario que elimina la tarea
            $task->saveQuietly(); // Guardar sin disparar eventos

            $assignedUser = User::find($task->asignado_a);
            $deletedBy = auth()->user()->name ?? 'Sistema';

            if ($assignedUser) {
                $assignedUser->notify((new TaskDeletedNotification($task, $deletedBy))->afterCommit());

                Notification::make()
                    ->title('❌ Tarea Eliminada')
                    ->icon('heroicon-o-trash')
                    ->body("La tarea con código **{$task->task_short_code}** ha sido eliminada por **" . (auth()->user()->name ?? 'Sistema') . "**.")
                    ->sendToDatabase($assignedUser);
            }
        });

    }

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'cronograma_inicio' => 'date:Y-m-d',
        'cronograma_fin' => 'date:Y-m-d',
        'fecha_retiro' => 'date:Y-m-d',
        'fotos_ingreso' => \App\Casts\LegacyFileArray::class,
        'documento' => \App\Casts\LegacyFileArray::class,
        'remision_ingreso' => \App\Casts\LegacyFileArray::class,
        'remision_ingreso_nombre' => \App\Casts\LegacyFileArray::class,
        'remision_salida' => \App\Casts\LegacyFileArray::class,
        'remision_salida_nombre' => \App\Casts\LegacyFileArray::class,
        'original_filename' => \App\Casts\LegacyFileArray::class,
        'original_filenames_fotos_ingreso' => \App\Casts\LegacyFileArray::class,
    ];


    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function eliminador()
    {
        return $this->belongsTo(User::class, 'eliminado_por');
    }

    public function getDiasRestantesAttribute()
    {
        if (!$this->cronograma_fin) {
            return null; // No hay fecha, no se puede calcular
        }

        $fechaFin = \Carbon\Carbon::parse($this->cronograma_fin);
        $hoy = \Carbon\Carbon::today();

        return $hoy->diffInDays($fechaFin, false);
    }

    public function scopeFilter($query, $filters)
    {
        return $query->when(isset($filters['estado']), function ($q) use ($filters) {
            $q->where('estado', $filters['estado']);
        })
            ->when(isset($filters['prioridad']), function ($q) use ($filters) {
                $q->where('prioridad', $filters['prioridad']);
            })
            ->when(isset($filters['cliente_id']), function ($q) use ($filters) {
                $q->where('cliente_id', $filters['cliente_id']);
            })
            ->when(isset($filters['asignado_a']), function ($q) use ($filters) {
                $q->where('asignado_a', $filters['asignado_a']);
            })
            ->when(isset($filters['fecha_inicio']) && isset($filters['fecha_fin']), function ($q) use ($filters) {
                $q->whereBetween('created_at', [$filters['fecha_inicio'], $filters['fecha_fin']]);
            });
    }

    protected static array $fieldLabels = [
        'task_short_code' => 'TICKET',
        'start_date' => 'FECHA INGRESO',
        'recibido_por' => 'RECIBIDO POR',
        'entregado_por' => 'ENTREGADO POR',
        'cliente_id' => 'CLIENTE',
        'equipo' => 'EQUIPO',
        'marca_id' => 'MARCA',
        'modelo' => 'MODELO',
        'serial' => 'SERIAL',
        'codigo_interno' => 'CÓDIGO INTERNO',
        'descripcion_falla_cliente' => 'DESCRIPCIÓN FALLA SEGÚN CLIENTE',
        'observaciones' => 'OBSERVACIONES',
        'prioridad' => 'PRIORIDAD',
        'avance_trabajo' => 'AVANCE DEL TRABAJO',
        'informe_diagnostico' => 'INFORME DE DIAGNÓSTICO',
        'estado' => 'ESTADO',
        'fecha_retiro' => 'FECHA DE RETIRO',
        'retirado_por' => 'RETIRADO POR',
    ];

    protected $appends = ['dias_restantes'];

}
