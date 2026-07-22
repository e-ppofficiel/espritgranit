<?php

namespace App\Services;

use App\Models\TransportZone;

class TransportCalculator
{
    public static function calculer(string $codeDepartement, float $poidsKg): ?float
    {
        $zone = TransportZone::pourDepartement($codeDepartement);

        if (! $zone) {
            return null;
        }

        $tarif = $zone->tarifs()
            ->where('poids_min', '<=', $poidsKg)
            ->where(function ($query) use ($poidsKg) {
                $query->whereNull('poids_max')
                      ->orWhere('poids_max', '>=', $poidsKg);
            })
            ->first();

        return $tarif?->prix;
    }
}
