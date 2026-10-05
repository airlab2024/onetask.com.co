<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modificado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('eliminado_por')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['creado_por']);
            $table->dropForeign(['modificado_por']);
            $table->dropForeign(['eliminado_por']);

            $table->dropColumn(['creado_por', 'modificado_por', 'eliminado_por']);
        });
    }
};
