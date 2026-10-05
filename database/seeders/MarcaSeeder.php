<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Marca;

class MarcaSeeder extends Seeder
{
    public function run()
    {
        // Añade aquí las marcas que necesites
        $marcas = [
            'SABIO',
            'AERIS',
            'HORIBA',
            'THERMO',
            'TISCH',
            'GRIMM',
            'TELEDYNE',
            'APEX',
            'FPI',
            // ... añade más marcas según necesites
        ];

        foreach ($marcas as $marca) {
            Marca::create(['nombre' => $marca]);
        }
    }
}