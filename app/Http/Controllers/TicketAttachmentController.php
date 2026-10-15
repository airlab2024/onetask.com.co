<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TicketAttachmentController extends Controller
{
    public function show(Ticket $ticket, Media $media)
    {
        Gate::authorize('view', $ticket);
        abort_unless($media->model_type === $ticket->getMorphClass() && (int) $media->model_id === (int) $ticket->id, 404);
        abort_unless(in_array($media->disk, ['task_files', 'public'], true), 404);
        $root = realpath(Storage::disk($media->disk)->path(''));
        $path = realpath($media->getPath());
        abort_unless($root && $path && is_file($path) && str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR), 404);
        $name = preg_replace('/[\x00-\x1f\x7f]/', '_', basename(str_replace('\\', '/', $media->name))) ?: 'attachment';
        return response()->download($path, $name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
