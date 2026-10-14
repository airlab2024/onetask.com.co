<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        return $user && ($user->hasRole('SUPER ADMINISTRADOR') || $user->can('Edit cliente') || $user->can('Update cliente'));
    }

}
