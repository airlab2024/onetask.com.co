<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('SUPER ADMINISTRADOR') || $user->can('Cliente resource') || $user->can('List clientes');
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('SUPER ADMINISTRADOR') || $user->can('Cliente resource') || $user->can('Create cliente');
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->hasRole('SUPER ADMINISTRADOR') || $user->can('Cliente resource') || $user->can('Update cliente') || $user->can('Edit cliente');
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return ($user->hasRole('SUPER ADMINISTRADOR') || $user->can('Delete cliente'))
            && !$cliente->tasks()->withTrashed()->exists();
    }

    public function deleteAny(User $user): bool
    {
        // Bulk deletion can bypass the per-client check for existing orders.
        return false;
    }
}
