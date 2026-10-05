<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Support\TaskFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeTaskFiles extends Command
{
    protected $signature = 'tasks:privatize-files {--apply : Copy, verify and remove the public originals}';
    protected $description = 'Preview or migrate legacy task files into protected storage';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $seen = []; $failures = 0; $count = 0;
        foreach (Task::withTrashed()->cursor() as $task) {
            foreach (TaskFiles::FIELDS as $field => $directories) {
                foreach (TaskFiles::paths($task->{$field}) as $path) {
                    $file = TaskFiles::resolve($field, $path, ['public']);
                    if (!$file) {
                        if (!TaskFiles::resolve($field, $path, ['task_files'])) {
                            $this->warn("Missing file: task {$task->id}, {$field}, {$path}");
                            $failures++;
                        }
                        continue;
                    }
                    if (isset($seen[$file['absolute']])) {
                        continue;
                    }
                    $seen[$file['absolute']] = true;
                    $count++;
                    $this->line(($apply ? 'Migrating: ' : 'Would migrate: ') . $file['path']);
                    if (!$apply) {
                        continue;
                    }
                    $private = Storage::disk('task_files');
                    if (!$private->exists($file['path'])) {
                        $stream = fopen($file['absolute'], 'rb');
                        try {
                            $private->put($file['path'], $stream);
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }
                    }
                    $copy = TaskFiles::resolve($field, $file['path'], ['task_files']);
                    if (!$copy || !hash_equals(hash_file('sha256', $file['absolute']), hash_file('sha256', $copy['absolute']))) {
                        $this->error('Verification failed; public original retained: ' . $file['path']);
                        $failures++;
                        continue;
                    }
                    if (!Storage::disk('public')->delete($file['path'])) {
                        $this->error('Cannot remove public original: ' . $file['path']);
                        $failures++;
                    }
                }
            }
        }
        $this->info("Files: {$count}; missing or failed: {$failures}. " . ($apply ? 'Migration completed.' : 'Preview only; no files were changed.'));
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
