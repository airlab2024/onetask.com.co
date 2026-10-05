<?php

namespace App\Forms\Components;

use App\Models\Task;
use App\Support\TaskFiles;
use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Livewire\TemporaryUploadedFile;

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

        // Preserve missing legacy references instead of silently deleting them on edit.
        $this->afterStateHydrated(function (self $component, $state, Closure $set, ?Task $record): void {
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

        $this->getUploadedFileUrlUsing(function (self $component, string $file, ?Task $record): ?string {
            return $record && TaskFiles::resolve($component->getName(), $file)
                ? TaskFiles::url($record, $component->getName(), $file) : null;
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
