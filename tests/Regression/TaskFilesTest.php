<?php

namespace Tests\Regression;

use App\Http\Controllers\TaskFileController;
use App\Models\Task;
use App\Support\TaskFiles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TaskFilesTest extends IsolatedTestCase
{
    public function test_paths_accept_legacy_arrays_and_reject_traversal(): void
    {
        $this->assertSame(['photos/a.png'], TaskFiles::paths('["photos/a.png"]'));
        $this->assertSame(['remisiones/a.pdf'], TaskFiles::paths([['path' => 'remisiones/a.pdf', 'name' => 'Original.pdf']]));
        foreach (['../.env', 'photos/../../.env', 'photos\\..\\.env', '/etc/passwd', 'C:/secret', "photos/evil\0.png"] as $path) {
            $this->assertFalse(TaskFiles::safePath($path), $path);
            $this->assertNull(TaskFiles::resolve('fotos_ingreso', $path));
        }
        $this->assertNull(TaskFiles::resolve('unknown', 'photos/a.png'));
    }

    public function test_legacy_paths_are_resolved_without_losing_their_directory(): void
    {
        Storage::disk('public')->put('remisiones_ingreso/existing.pdf', '%PDF-1.4');
        $resolved = TaskFiles::resolve('remision_ingreso', 'remisiones_ingreso/existing.pdf');
        $this->assertSame('public', $resolved['disk']);
        $this->assertSame('remisiones_ingreso/existing.pdf', $resolved['path']);
        Storage::disk('task_files')->put('remisiones/ingreso/existing.pdf', '%PDF-1.4');
        $this->assertSame('task_files', TaskFiles::resolve('remision_ingreso', 'existing.pdf')['disk']);
        $this->assertNull(TaskFiles::resolve('documento', 'remisiones_ingreso/existing.pdf'));
    }

    public function test_download_requires_authorized_task_and_exact_file_membership(): void
    {
        $this->actingAs(7, ['View task']);
        Storage::disk('task_files')->put('photos/one.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jLeoAAAAASUVORK5CYII='));
        $task = new Task(['asignado_a' => 7, 'fotos_ingreso' => ['photos/one.png'], 'original_filenames_fotos_ingreso' => ['photos/one.png' => 'Foto equipo.png']]);
        $task->id = 1;
        $response = (new TaskFileController())->show(Request::create('/', 'GET', ['path' => 'photos/one.png']), $task, 'fotos_ingreso');
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('Foto', $response->headers->get('Content-Disposition'));
        $this->assertFileExists($response->getFile()->getPathname());
        try {
            (new TaskFileController())->show(Request::create('/', 'GET', ['path' => 'photos/other.png']), $task, 'fotos_ingreso');
            $this->fail('Unrelated file was allowed');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->actingAs(8, ['View task']);
        $this->expectException(AuthorizationException::class);
        (new TaskFileController())->show(Request::create('/', 'GET', ['path' => 'photos/one.png']), $task, 'fotos_ingreso');
    }

    public function test_thumbnail_preserves_legacy_paths_and_escapes_original_names(): void
    {
        $task = new Task(['remision_ingreso' => [['path' => 'remisiones_ingreso/old.pdf', 'name' => 'Informe <script>.pdf']]]);
        $task->id = 1;
        $html = view('filament.tables.columns.remision-ingreso-thumbnail', [
            'getRecord' => fn () => $task,
            'getState' => fn () => $task->remision_ingreso,
        ])->render();
        $this->assertStringContainsString('/tasks/1/files/remision_ingreso', $html);
        $this->assertStringContainsString('remisiones_ingreso%2Fold.pdf', $html);
        $this->assertStringContainsString('Informe &lt;script&gt;.pdf', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_missing_files_are_not_resolved_and_original_names_remain_readable(): void
    {
        $task = new Task(['fotos_ingreso' => ['photos/a.png'], 'original_filenames_fotos_ingreso' => ['Foto antigua.png']]);
        $this->assertSame('Foto antigua.png', TaskFiles::originalName($task, 'fotos_ingreso', 'photos/a.png'));
        $this->assertNull(TaskFiles::resolve('fotos_ingreso', 'photos/a.png'));
    }
}
