<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Support\Facades\Storage;

class TaskFiles
{
    public const FIELDS = [
        'fotos_ingreso' => ['photos'],
        'documento' => ['documents'],
        'remision_ingreso' => ['remisiones/ingreso', 'remisiones_ingreso', 'remisiones'],
        'remision_salida' => ['remisiones/salida', 'remisiones_salida', 'remisiones'],
    ];

    public const NAMES = [
        'fotos_ingreso' => 'original_filenames_fotos_ingreso',
        'documento' => 'original_filename',
        'remision_ingreso' => 'remision_ingreso_nombre',
        'remision_salida' => 'remision_salida_nombre',
    ];

    public static function paths($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        if (isset($value['path'])) {
            $value = [$value];
        }
        $paths = [];
        foreach ($value as $file) {
            $path = is_array($file) ? ($file['path'] ?? null) : $file;
            if (is_string($path) && self::safePath($path)) {
                $paths[] = $path;
            }
        }
        return array_values(array_unique($paths));
    }

    public static function safePath(string $path): bool
    {
        return $path !== '' && !preg_match('~[\\\\:\x00-\x1f]|^/|(^|/)\.\.?(/|$)~', $path);
    }

    public static function resolve(string $field, string $path, array $disks = ['task_files', 'public']): ?array
    {
        if (!isset(self::FIELDS[$field]) || !self::safePath($path)) {
            return null;
        }
        $candidates = [$path];
        if (!str_contains($path, '/')) {
            $candidates = array_map(fn ($directory) => $directory . '/' . $path, self::FIELDS[$field]);
        }
        foreach ($disks as $diskName) {
            $disk = Storage::disk($diskName);
            $root = realpath($disk->path(''));
            if (!$root) {
                continue;
            }
            foreach ($candidates as $candidate) {
                $allowed = false;
                foreach (self::FIELDS[$field] as $directory) {
                    $allowed = $allowed || str_starts_with($candidate, $directory . '/');
                }
                if (!$allowed) {
                    continue;
                }
                $absolute = realpath($disk->path($candidate));
                $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
                if ($absolute && is_file($absolute)
                    && str_starts_with(str_replace('\\', '/', $absolute), $prefix)) {
                    return ['disk' => $diskName, 'path' => $candidate, 'absolute' => $absolute];
                }
            }
        }
        return null;
    }

    public static function url(Task $task, string $field, string $path): string
    {
        return route('tasks.files', ['task' => $task->id, 'field' => $field, 'path' => $path]);
    }

    public static function originalName(Task $task, string $field, string $path): string
    {
        $names = $task->{self::NAMES[$field]} ?? [];
        $index = array_search($path, self::paths($task->{$field}), true);
        $fallback = basename($path);
        foreach ((array) $task->{$field} as $entry) {
            if (is_array($entry) && ($entry['path'] ?? null) === $path && is_string($entry['name'] ?? null)) {
                $fallback = $entry['name'];
                break;
            }
        }
        $name = is_array($names) ? ($names[$path] ?? ($index !== false ? ($names[$index] ?? $fallback) : $fallback)) : $fallback;
        return preg_replace('/[\x00-\x1f\x7f]/', '_', basename(str_replace('\\', '/', is_string($name) ? $name : basename($path)))) ?: 'archivo';
    }
}
