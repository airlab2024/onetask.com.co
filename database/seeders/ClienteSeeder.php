<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cliente;

class ClienteSeeder extends Seeder
{
    public function run()
    {
        $clientes = [
            'CHEMILAB',
            'INDUANALISIS',
            'ECO AMBIENTE',
            'MUNDO AMBIENTAL',
            'GEXO',
            'SGS',
            'IHA',
            'AMBIGEST',
            'LABCCESTA',
            'ANALQUIM',
            'LAB CESTTA',
            'COMNAMBIENTE',
            'AIRLAB',
            'MAHT',
            'ASOAM'
        ];

        foreach ($clientes as $cliente) {
            Cliente::create(['nombre' => $cliente]);
        }
    }
}