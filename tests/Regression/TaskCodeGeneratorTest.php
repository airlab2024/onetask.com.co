<?php

namespace Tests\Regression;

use App\Models\Task;
use App\Support\TaskCodeGenerator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TaskCodeGeneratorTest extends IsolatedTestCase
{
    public function test_reservations_remain_unique_even_before_orders_are_saved(): void
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_14_000001_protect_task_short_codes.php';
        $migration->up();
        Carbon::setTestNow('2026-10-14 12:00:00');
        try {
            Task::withoutEvents(fn () => Task::create(['task_short_code' => 'Ticket-20261014-9', 'deleted_at' => now()]));
            $this->assertSame('Ticket-20261014-10', TaskCodeGenerator::preview());
            $this->assertSame(0, DB::table('task_code_sequences')->count());
            $codes = array_map(fn () => TaskCodeGenerator::reserve(), range(1, 20));
            $this->assertCount(20, array_unique($codes));
            $this->assertSame('Ticket-20261014-10', $codes[0]);
            $this->assertSame('Ticket-20261014-29', $codes[19]);
            $this->assertSame('Ticket-20261014-30', TaskCodeGenerator::preview());
            Carbon::setTestNow('2026-10-15 12:00:00');
            $this->assertSame('Ticket-20261015-1', TaskCodeGenerator::reserve());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_database_rejects_duplicate_order_codes(): void
    {
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_14_000001_protect_task_short_codes.php';
        $migration->up();
        Task::withoutEvents(fn () => Task::create(['task_short_code' => 'Ticket-20261014-1']));
        $this->expectException(QueryException::class);
        Task::withoutEvents(fn () => Task::create(['task_short_code' => 'Ticket-20261014-1']));
    }

    public function test_migration_refuses_existing_duplicates_without_renaming_orders(): void
    {
        Task::withoutEvents(fn () => Task::create(['task_short_code' => 'Ticket-20261014-1']));
        Task::withoutEvents(fn () => Task::create(['task_short_code' => 'Ticket-20261014-1']));
        $migration = require dirname(__DIR__, 2) . '/database/migrations/2026_10_14_000001_protect_task_short_codes.php';
        $this->expectException(\RuntimeException::class);
        $migration->up();
    }
}
