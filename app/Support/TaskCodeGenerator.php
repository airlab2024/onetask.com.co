<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TaskCodeGenerator
{
    public static function preview(): string
    {
        $prefix = 'Ticket-' . now()->format('Ymd') . '-';
        $reserved = Schema::hasTable('task_code_sequences')
            ? (int) DB::table('task_code_sequences')->where('date', now()->format('Ymd'))->value('last_number') : 0;
        return $prefix . (max($reserved, self::lastExisting($prefix)) + 1);
    }

    public static function reserve(): string
    {
        $date = now()->format('Ymd');
        $prefix = "Ticket-{$date}-";

        return DB::transaction(function () use ($date, $prefix) {
            DB::table('task_code_sequences')->insertOrIgnore(['date' => $date, 'last_number' => 0]);
            $sequence = DB::table('task_code_sequences')->where('date', $date)->lockForUpdate()->first();
            $next = max((int) $sequence->last_number, self::lastExisting($prefix)) + 1;
            DB::table('task_code_sequences')->where('date', $date)->update(['last_number' => $next]);
            return $prefix . $next;
        }, 3);
    }

    private static function lastExisting(string $prefix): int
    {
        return (int) Task::withTrashed()->where('task_short_code', 'like', $prefix . '%')
            ->pluck('task_short_code')->map(function ($code) use ($prefix) {
                $suffix = substr($code, strlen($prefix));
                return ctype_digit($suffix) ? (int) $suffix : 0;
            })->max();
    }
}
