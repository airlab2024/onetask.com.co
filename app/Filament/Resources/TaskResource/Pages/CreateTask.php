<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->hasRole('TECNICO') && !auth()->user()?->hasRole('SUPER ADMINISTRADOR')) {
            $data['asignado_a'] = auth()->id();
        }
        return $data;
    }

}
