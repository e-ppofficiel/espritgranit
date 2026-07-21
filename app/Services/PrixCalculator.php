<?php

namespace App\Services;

use App\Models\Monument;
use App\Models\ParametreTarif;

class PrixCalculator
{
    public static function prixVenteDepart(Monument $monument, float $prixFob, float $tauxMarge): ?float
    {
        $poidsKg = $monument->blocs()->sum('poids_kg');

        if ($poidsKg <= 0 || $prixFob <= 0) {
            return null;
        }

        $params = ParametreTarif::courant();

        if (! $params->cout_transport_conteneur || ! $params->cout_transit_conteneur) {
            return null;
        }

        $tauxTransportParKg = $params->cout_transport_conteneur / $params->poids_conteneur_kg;
        $tauxTransitParKg = $params->cout_transit_conteneur / $params->poids_conteneur_kg;

        $prixTransport = $tauxTransportParKg * $poidsKg;
        $prixTransit = $tauxTransitParKg * $poidsKg;
        $marge = $prixFob * $tauxMarge;

        $total = $prixFob + $prixTransport + $prixTransit + $marge;

        return round($total / 10) * 10;
    }
}
