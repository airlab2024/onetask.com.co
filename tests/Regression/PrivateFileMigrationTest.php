<?php

namespace Tests\Regression;

use App\Console\Commands\PrivatizeTaskFiles;
use App\Models\Task;
use App\Support\TaskFiles;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Tester\CommandTester;

class PrivateFileMigrationTest extends IsolatedTestCase
{
    private function command(): CommandTester
    {
        $command = new PrivatizeTaskFiles();
        $command->setLaravel($this->app);
        return new CommandTester($command);
    }

    public function test_preview_does_not_change_files_and_apply_verifies_the_copy(): void
    {
        $path = 'photos/legacy.png';
        Storage::disk('public')->put($path, 'existing photo bytes');
        $task = Task::withoutEvents(fn () => Task::create(['fotos_ingreso' => [$path]]));
        $tester = $this->command();
        $this->assertSame(0, $tester->execute([]));
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertFalse(Storage::disk('task_files')->exists($path));
        $this->assertSame(0, $tester->execute(['--apply' => true]));
        $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertSame('existing photo bytes', Storage::disk('task_files')->get($path));
        $this->assertSame([$path], $task->fresh()->fotos_ingreso);
        $this->assertSame('task_files', TaskFiles::resolve('fotos_ingreso', $path)['disk']);
        $this->assertSame(0, $tester->execute(['--apply' => true]));
    }

    public function test_a_conflicting_private_copy_never_overwrites_or_deletes_the_public_original(): void
    {
        $path = 'documents/conflict.pdf';
        Storage::disk('public')->put($path, 'original');
        Storage::disk('task_files')->put($path, 'different');
        Task::withoutEvents(fn () => Task::create(['documento' => [$path]]));
        $this->assertSame(1, $this->command()->execute(['--apply' => true]));
        $this->assertSame('original', Storage::disk('public')->get($path));
        $this->assertSame('different', Storage::disk('task_files')->get($path));
    }
}
