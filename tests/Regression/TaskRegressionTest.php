<?php

namespace Tests\Regression;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use App\Policies\TaskPolicy;
use App\Support\SafeHtml;
use App\Support\TaskInputValidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskRegressionTest extends IsolatedTestCase
{
    public function test_filename_migration_keeps_large_metadata_without_truncation(): void
    {
        $names = array_map(fn ($index) => 'Foto de recepción con un nombre muy largo ' . str_repeat('equipo-', 30) . $index . '.png', range(1, 10));
        $task = Task::withoutEvents(fn () => Task::create(['original_filenames_fotos_ingreso' => $names, 'original_filename' => $names]));
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_02_000001_expand_task_filename_metadata.php';
        $migration->up();
        $task->refresh();
        $this->assertSame($names, $task->original_filenames_fotos_ingreso);
        $this->assertSame($names, $task->original_filename);
        $this->assertGreaterThan(255, strlen(DB::table('tasks')->value('original_filenames_fotos_ingreso')));
        $columns = DB::select('PRAGMA table_info(tasks)');
        $types = array_column($columns, 'type', 'name');
        $this->assertContains(strtoupper($types['original_filenames_fotos_ingreso']), ['TEXT', 'CLOB']);
        $migration->down();
        $this->assertSame($names, $task->fresh()->original_filenames_fotos_ingreso);
    }

    public function test_resource_permissions_match_real_filament_authorization(): void
    {
        $task = new Task(['asignado_a' => 7]);
        $this->actingAs(7);
        $this->assertFalse(TaskResource::canViewAny());
        $this->assertFalse(TaskResource::canCreate());
        $this->assertFalse(TaskResource::canEdit($task));
        $this->actingAs(7, ['List tasks', 'View task', 'Edit task']);
        $this->assertTrue(TaskResource::canViewAny());
        $this->assertTrue(TaskResource::canView($task));
        $this->assertTrue(TaskResource::canEdit($task));
        $this->assertFalse(TaskResource::canCreate());
        $this->assertFalse(TaskResource::canDelete($task));
        $this->actingAs(8, ['List tasks', 'View task', 'Edit task']);
        $this->assertFalse(TaskResource::canView($task));
        $this->assertFalse(TaskResource::canEdit($task));
        $this->actingAs(1, [], ['SUPER ADMINISTRADOR']);
        $this->assertTrue(TaskResource::canCreate());
        $this->assertTrue(TaskResource::canDelete($task));
    }

    public function test_inline_dates_reject_end_before_start(): void
    {
        $this->actingAs(7, ['Edit task']);
        $task = new Task(['asignado_a' => 7, 'cronograma_inicio' => '2026-10-10']);
        $this->expectException(ValidationException::class);
        TaskInputValidator::update($task, 'cronograma_fin', '2026-10-09');
    }

    public function test_zero_days_and_missing_dates_are_distinct(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        try {
            $this->assertSame(0, (new Task(['cronograma_fin' => '2026-10-02']))->dias_restantes);
            $this->assertNull((new Task())->dias_restantes);
            $this->assertSame(-1, (new Task(['cronograma_fin' => '2026-10-01']))->dias_restantes);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_short_code_does_not_reuse_a_soft_deleted_code(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        try {
            Task::withoutEvents(fn () => Task::create(['task_short_code' => 'Ticket-20261002-3']));
            DB::table('tasks')->update(['deleted_at' => '2026-10-02 12:00:00']);
            $this->assertSame('Ticket-20261002-4', Task::generateTaskShortCode());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rich_html_preserves_formatting_and_removes_executable_content(): void
    {
        $html = SafeHtml::sanitize('<p><strong>Diagnóstico</strong></p><script>alert(1)</script><a href="javascript:alert(1)">Abrir</a><img src="x" onerror="alert(1)">');
        $this->assertStringContainsString('<strong>Diagnóstico</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }
}
