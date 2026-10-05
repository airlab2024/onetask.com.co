<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TaskInputValidator
{
    public static function update(Task $task, string $field, $value)
    {
        Gate::authorize('update', $task);
        abort_if($field === 'asignado_a' && auth()->user()?->hasRole('TECNICO'), 403);
        $rules = [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'recibido_por' => ['required', 'string', Rule::in(['GERMAN BARKER', 'PABLO GUERRERO', 'NORBEY BARAHONA', 'JOSE LOPEZ'])],
            'entregado_por' => ['required', 'string', 'max:255'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'marca_id' => ['required', 'integer', 'exists:marcas,id'],
            'asignado_a' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'cronograma_inicio' => ['required', 'date_format:Y-m-d'],
            'cronograma_fin' => ['required', 'date_format:Y-m-d'],
            'fecha_retiro' => ['nullable', 'date_format:Y-m-d'],
            'prioridad' => ['required', Rule::in(['Critica', 'Alta', 'Media', 'Baja'])],
            'avance_trabajo' => ['required', Rule::in(['No iniciado', 'Iniciado', 'En Curso', 'Detenido', 'Listo'])],
            'informe_diagnostico' => ['required', Rule::in(['LISTO', 'EN CURSO', 'DETENIDO', 'NO INICIADO'])],
            'estado' => ['required', Rule::in(['En Servicio Técnico', 'Pen. Aut Cliente', 'Pendiente de repuestos', 'Para entrega', 'Entrega sin reparación', 'Entregado'])],
            'equipo' => ['required', 'string', 'max:255'],
            'modelo' => ['required', 'string', 'max:255'],
            'serial' => ['required', 'string', 'max:255'],
            'codigo_interno' => ['nullable', 'string', 'max:255'],
            'retirado_por' => ['nullable', 'string', 'max:255'],
            'accesorios_entregados' => ['required', 'string', 'max:10000'],
            'descripcion_falla_cliente' => ['required', 'string', 'max:10000'],
            'observaciones' => ['required', 'string', 'max:10000'],
            'actividades_realizadas' => ['required', 'string', 'max:10000'],
        ];
        abort_unless(isset($rules[$field]), 422);
        if ($field === 'cronograma_fin' && $task->cronograma_inicio) {
            $rules[$field][] = 'after_or_equal:' . $task->cronograma_inicio->format('Y-m-d');
        }
        if ($field === 'cronograma_inicio' && $task->cronograma_fin) {
            $rules[$field][] = 'before_or_equal:' . $task->cronograma_fin->format('Y-m-d');
        }
        Validator::make([$field => $value], [$field => $rules[$field]])->validate();
        $task->{$field} = $value;
        $task->save();
        return $value;
    }
}
