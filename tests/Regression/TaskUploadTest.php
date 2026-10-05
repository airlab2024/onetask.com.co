<?php

namespace Tests\Regression;

use App\Forms\Components\TaskFileUpload;
use App\Models\Task;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\TemporaryUploadedFile;

class UploadFormFixture extends Component implements HasForms
{
    use InteractsWithForms;
    public array $data = [];
}

class NamedUploadFixture extends TemporaryUploadedFile
{
    public string $originalName;
    public function __construct(string $path, string $name)
    {
        parent::__construct($path, 'local');
        $this->originalName = $name;
    }
    public function getClientOriginalName(): string { return $this->originalName; }
}

class TaskUploadTest extends IsolatedTestCase
{
    private function form(?Task $task = null): array
    {
        $component = TaskFileUpload::make('fotos_ingreso')->acceptedFileTypes(['image/png'])->image();
        $fixture = new UploadFormFixture();
        $form = ComponentContainer::make($fixture)->model($task ?? Task::class)->statePath('data')->schema([$component]);
        $form->fill($task ? $task->toArray() : []);
        return [$form, $component, $fixture];
    }

    private function upload(string $key, string $name): NamedUploadFixture
    {
        Storage::disk('local')->put('livewire-tmp/' . $key, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jLeoAAAAASUVORK5CYII='));
        return new NamedUploadFixture($key, $name);
    }

    public function test_multiple_photos_with_identical_names_use_distinct_private_paths(): void
    {
        [$form, $component] = $this->form();
        $name = 'Foto de recepción de equipo ' . str_repeat('nombre largo ', 10) . '.png';
        $component->state([
            'a' => $this->upload('a.png', $name),
            'b' => $this->upload('b.png', $name),
            'c' => $this->upload('c.png', $name),
        ]);
        $data = $form->getState();
        $this->assertCount(3, $data['fotos_ingreso']);
        $this->assertCount(3, array_unique($data['fotos_ingreso']));
        $this->assertGreaterThan(255, strlen(json_encode($data['original_filenames_fotos_ingreso'])));
        foreach ($data['fotos_ingreso'] as $path) {
            $this->assertTrue(Storage::disk('task_files')->exists($path));
            $this->assertFalse(Storage::disk('public')->exists($path));
            $this->assertSame($name, $data['original_filenames_fotos_ingreso'][$path]);
        }
    }

    public function test_edit_preserves_existing_files_and_names_even_when_a_file_is_missing(): void
    {
        $task = new Task(['fotos_ingreso' => ['photos/old.png'], 'original_filenames_fotos_ingreso' => ['photos/old.png' => 'Foto antigua.png']]);
        [$form] = $this->form($task);
        $data = $form->getState();
        $this->assertSame(['photos/old.png'], $data['fotos_ingreso']);
        $this->assertSame('Foto antigua.png', $data['original_filenames_fotos_ingreso']['photos/old.png']);
    }

    public function test_a_forged_reference_to_another_tasks_file_is_rejected(): void
    {
        [$form, $component] = $this->form(new Task(['fotos_ingreso' => ['photos/own.png']]));
        $component->state(['forged' => 'photos/other.png']);
        $this->expectException(ValidationException::class);
        $form->getState();
    }

    public function test_edit_keeps_legacy_numeric_names_when_a_new_photo_is_added(): void
    {
        $task = new Task(['fotos_ingreso' => ['photos/old.png'], 'original_filenames_fotos_ingreso' => ['Nombre original.png']]);
        [$form, $component] = $this->form($task);
        $component->state(array_merge($component->getState(), ['new' => $this->upload('new.png', 'Foto nueva.png')]));
        $data = $form->getState();
        $this->assertCount(2, $data['fotos_ingreso']);
        $this->assertSame('Nombre original.png', $data['original_filenames_fotos_ingreso']['photos/old.png']);
        $this->assertContains('Foto nueva.png', $data['original_filenames_fotos_ingreso']);
    }

    public function test_too_many_photos_are_rejected(): void
    {
        [$form, $component] = $this->form();
        $files = [];
        for ($index = 0; $index < 21; $index++) {
            $files[(string) $index] = $this->upload("{$index}.png", "Foto {$index}.png");
        }
        $component->state($files);
        $this->expectException(ValidationException::class);
        $form->getState();
    }

    public function test_non_image_upload_is_rejected_before_persistence(): void
    {
        [$form, $component] = $this->form();
        Storage::disk('local')->put('livewire-tmp/unsafe.txt', 'not an image');
        $component->state(['invalid' => new NamedUploadFixture('unsafe.txt', 'foto.png')]);
        $this->expectException(ValidationException::class);
        $form->getState();
    }
}
