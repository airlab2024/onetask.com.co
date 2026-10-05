<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('contacto')->nullable();
            $table->string('cargo')->nullable();
            $table->string('nit')->unique()->nullable();
            $table->string('ciudad')->nullable();
            $table->string('correo_electronico')->unique()->nullable();
            $table->string('encargado_de_cuenta')->nullable();
        });
    }

    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'contacto',
                'cargo',
                'nit',
                'ciudad',
                'correo_electronico',
                'encargado_de_cuenta'
            ]);
        });
    }
};
