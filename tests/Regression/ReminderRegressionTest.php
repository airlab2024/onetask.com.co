<?php

namespace Tests\Regression;

use App\Models\Task;
use App\Support\TaskReminder;
use Illuminate\Support\Facades\Schema;

class ReminderRegressionTest extends IsolatedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('task_reminder_deliveries', function ($table) {
            $table->string('event_key')->primary(); $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id'); $table->date('scheduled_start'); $table->timestamp('created_at');
        });
    }

    public function test_repeated_reminders_are_skipped_per_recipient_and_date(): void
    {
        $task = new Task(['cronograma_inicio' => '2026-10-16']); $task->id = 1;
        $user = $this->actingAs(7); $calls = 0;
        $send = function () use (&$calls) { $calls++; };
        $this->assertTrue(TaskReminder::sendOnce($task, $user, $send));
        $this->assertFalse(TaskReminder::sendOnce($task, $user, $send));
        $this->assertSame(1, $calls);
        $task->cronograma_inicio = '2026-10-17';
        $this->assertTrue(TaskReminder::sendOnce($task, $user, $send));
        $this->assertSame(2, $calls);
    }

    public function test_failed_delivery_can_be_retried(): void
    {
        $task = new Task(['cronograma_inicio' => '2026-10-16']); $task->id = 1;
        $user = $this->actingAs(7);
        try {
            TaskReminder::sendOnce($task, $user, fn () => throw new \RuntimeException('Queue unavailable'));
            $this->fail('Failure was swallowed');
        } catch (\RuntimeException $e) {
            $this->assertSame('Queue unavailable', $e->getMessage());
        }
        $this->assertTrue(TaskReminder::sendOnce($task, $user, fn () => null));
    }
}
