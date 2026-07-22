<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\ParametreTarif;
use App\Models\Societe;

class CoefficientResolver
{
    /**
     * Résout le coefficient de vente applicable selon la règle de priorité :
     * coefficient de l'agence > coefficient de la société > coefficient général.
     */
    public static function resoudre(?Agence $agence, ?Societe $societe): float
    {
        if ($agence && $agence->coefficient_vente !== null) {
            return (float) $agence->coefficient_vente;
        }

        if ($societe && $societe->coefficient_vente !== null) {
            return (float) $societe->coefficient_vente;
        }

        return (float) ParametreTarif::courant()->coefficient_vente_general;
    }
}
