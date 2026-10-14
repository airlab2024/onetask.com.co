<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('tickets')->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Hay códigos de tickets duplicados. Revisarlos antes de aplicar el índice único.');
        }
        Schema::table('tickets', fn (Blueprint $table) => $table->unique('code', 'tickets_code_unique'));
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $table) => $table->dropUnique('tickets_code_unique'));
    }
};
