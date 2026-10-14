<?php

namespace App\Forms\Components;

use App\Models\Task;
use App\Support\TaskFiles;
use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TaskFileUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->disk('task_files')->visibility('private')->multiple()
            ->maxSize(10240)->maxFiles(20)->enableOpen()->enableDownload();
        $this->directory(fn (self $component) => TaskFiles::FIELDS[$component->getName()][0]);
        $this->storeFileNamesIn(TaskFiles::NAMES[$this->getName()]);
        $this->getUploadedFileNameForStorageUsing(
            fn (TemporaryUploadedFile $file) => Str::uuid() . '.' . ($file->guessExtension() ?: 'bin')
        );
        $this->helperText(function (?Task $record, self $component): ?string {
            if (!$record) {
                return null;
            }
            foreach (TaskFiles::paths($record->{$component->getName()}) as $path) {
                if (!TaskFiles::resolve($component->getName(), $path)) {
                    return 'Hay archivos anteriores que no están disponibles. Solicita su recuperación al administrador.';
                }
            }
            return null;
        });

        // Preserve missing legacy references instead of silently deleting them on edit.
        $this->afterStateHydrated(function (self $component, $state, \Filament\Forms\Set $set, ?Task $record): void {
            $files = [];
            foreach (TaskFiles::paths($state) as $file) {
                $files[(string) Str::uuid()] = $file;
            }
            $component->state($files);
            if ($record) {
                $names = [];
                foreach ($files as $file) {
                    $names[$file] = TaskFiles::originalName($record, $component->getName(), $file);
                }
                $set(TaskFiles::NAMES[$component->getName()], $names);
            }
        });

        $this->getUploadedFileUsing(function (self $component, string $file, ?Task $record): ?array {
            $resolved = $record ? TaskFiles::resolve($component->getName(), $file) : null;
            if (!$resolved) {
                return null;
            }
            return [
                'name' => TaskFiles::originalName($record, $component->getName(), $file),
                'size' => filesize($resolved['absolute']),
                'type' => (new \finfo(FILEINFO_MIME_TYPE))->file($resolved['absolute']),
                'url' => TaskFiles::url($record, $component->getName(), $file),
            ];
        });

    }

    public function getValidationRules(): array
    {
        $rules = parent::getValidationRules();
        // Filament applies rule() to each temporary file, so membership must
        // validate the full array here, including existing string references.
        $rules[] = function (string $attribute, $value, Closure $fail): void {
            $record = $this->getRecord();
            $existing = $record ? TaskFiles::paths($record->{$this->getName()}) : [];
            foreach ((array) $value as $file) {
                if (!$file instanceof TemporaryUploadedFile && (!is_string($file) || !in_array($file, $existing, true))) {
                    $fail('El archivo no pertenece a esta tarea.');
                    return;
                }
            }
        };
        return $rules;
    }
}
