<?php

namespace Database\Seeders;

use App\Models\ParametreTarif;
use Illuminate\Database\Seeder;

class ParametreTarifSeeder extends Seeder
{
    public function run(): void
    {
        ParametreTarif::firstOrCreate([], [
            'poids_conteneur_kg' => 20000,
            'cout_transport_conteneur' => 3000,
            'cout_transit_conteneur' => 2600,
        ]);
    }
}
