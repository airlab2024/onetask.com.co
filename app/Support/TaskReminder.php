<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskReminder
{
    public static function sendOnce(Task $task, User $user, callable $send): bool
    {
        return DB::transaction(function () use ($task, $user, $send) {
            $start = $task->cronograma_inicio->format('Y-m-d');
            if (!DB::table('task_reminder_deliveries')->insertOrIgnore([
                'event_key' => hash('sha256', "{$task->id}:{$user->id}:{$start}"),
                'task_id' => $task->id, 'user_id' => $user->id,
                'scheduled_start' => $start, 'created_at' => now(),
            ])) {
                return false;
            }
            $send();
            return true;
        }, 3);
    }
}
