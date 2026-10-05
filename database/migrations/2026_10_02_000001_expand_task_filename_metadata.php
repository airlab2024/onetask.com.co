<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LONGTEXT preserves legacy JSON and permits multiple long original names.
        // Do not convert existing data to JSON until the real database is audited.
        Schema::table('tasks', function (Blueprint $table) {
            $table->longText('original_filenames_fotos_ingreso')->nullable()->change();
            $table->longText('original_filename')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally retain capacity: shrinking to VARCHAR(255) could lose data.
    }
};
