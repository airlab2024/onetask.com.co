<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->enum('forma_pago', ['Contado', 'Crédito a 30 Días', 'Convenio'])
                  ->nullable()
                  ->after('encargado_de_cuenta');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('forma_pago');
        });
    }
};
