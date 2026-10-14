<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reminder_deliveries', function (Blueprint $table) {
            $table->string('event_key', 64)->primary();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->date('scheduled_start');
            $table->timestamp('created_at');
        });
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('external_source', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['external_source']);
            $table->dropColumn('external_source');
        });
        Schema::dropIfExists('task_reminder_deliveries');
    }
};
