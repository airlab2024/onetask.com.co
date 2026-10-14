<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tasks')->select('task_short_code')->groupBy('task_short_code')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Hay códigos de órdenes duplicados. Revisarlos antes de aplicar el índice único.');
        }
        Schema::table('tasks', fn (Blueprint $table) => $table->unique('task_short_code', 'tasks_short_code_unique'));
        Schema::create('task_code_sequences', function (Blueprint $table) {
            $table->string('date', 8)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', fn (Blueprint $table) => $table->dropUnique('tasks_short_code_unique'));
        Schema::dropIfExists('task_code_sequences');
    }
};
