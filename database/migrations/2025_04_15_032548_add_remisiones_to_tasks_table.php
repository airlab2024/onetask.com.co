<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('remision_ingreso')->nullable()->after('documento');
            $table->string('remision_ingreso_nombre')->nullable()->after('remision_ingreso');
            $table->string('remision_salida')->nullable()->after('remision_ingreso_nombre');
            $table->string('remision_salida_nombre')->nullable()->after('remision_salida');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'remision_ingreso',
                'remision_ingreso_nombre',
                'remision_salida',
                'remision_salida_nombre',
            ]);
        });
    }
};
