<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->json('remision_ingreso_nombre')->nullable()->change();
            $table->json('remision_salida_nombre')->nullable()->change();
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
            $table->string('remision_ingreso_nombre', 255)->nullable()->change();
            $table->string('remision_salida_nombre', 255)->nullable()->change();
        });
    }
};
