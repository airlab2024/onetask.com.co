<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\TaskFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskFileController extends Controller
{
    public function show(Request $request, Task $task, string $field)
    {
        Gate::authorize('view', $task);
        abort_unless(isset(TaskFiles::FIELDS[$field]), 404);
        $path = $request->query('path');
        abort_unless(is_string($path) && in_array($path, TaskFiles::paths($task->{$field}), true), 404);
        $file = TaskFiles::resolve($field, $path);
        abort_unless($file, 404);

        $mime = mime_content_type($file['absolute']);
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'], true);
        $response = response()->file($file['absolute'], [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
        $response->setContentDisposition($inline ? 'inline' : 'attachment', TaskFiles::originalName($task, $field, $path));
        return $response;
    }
}
