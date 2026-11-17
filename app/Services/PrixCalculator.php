<?php

namespace App\Services;

use App\Models\Monument;
use App\Models\ParametreTarif;

class PrixCalculator
{
    public static function prixVenteDepart(Monument $monument, float $prixFob, float $tauxMarge): ?float
    {
        return static::prixVenteDepartPourPoids($monument->poidsKg(), $prixFob, $tauxMarge);
    }

    /**
     * Même formule que prixVenteDepart() pour un poids donné (utilisée aussi pour les semelles,
     * qui ont leur propre poids et leur propre prix FOB).
     */
    public static function prixVenteDepartPourPoids(float $poidsKg, float $prixFob, float $tauxMarge): ?float
    {
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
