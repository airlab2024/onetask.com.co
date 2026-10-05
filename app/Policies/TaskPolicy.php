<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('SUPER ADMINISTRADOR') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('List tasks');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->can('View task') && (int) $task->asignado_a === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('Create task');
    }

    public function update(User $user, Task $task): bool
    {
        return ($user->can('Edit task') || $user->can('Update task'))
            && (int) $task->asignado_a === (int) $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return false;
    }
}
