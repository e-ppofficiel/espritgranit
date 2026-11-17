<?php

namespace App\Services;

use App\Models\MonumentSemelleDimension;
use App\Models\MonumentSemellePrix;

/**
 * Semelle choisie dans un devis : référence, libellé, prix de vente départ et poids.
 * Le prix vient de monument_semelle_prix (référence de la dimension × monument × granit).
 * Sans semelle choisie, rien n'est ajouté au devis (comportement d'avant).
 */
class SemelleDevis
{
    /**
     * @return array{type_id: ?int, dimension_id: ?int, reference: ?string, libelle: ?string, prix: ?float, poids_kg: float, avertissement: ?string}
     */
    public static function calculer(?int $monumentId, ?int $granitId, ?int $dimensionId): array
    {
        $vide = [
            'type_id' => null,
            'dimension_id' => null,
            'reference' => null,
            'libelle' => null,
            'prix' => null,
            'poids_kg' => 0.0,
            'avertissement' => null,
        ];

        if (! $dimensionId) {
            return $vide;
        }

        $dimension = $monumentId
            ? MonumentSemelleDimension::with('semelleType')->where('monument_id', $monumentId)->find($dimensionId)
            : null;

        if (! $dimension) {
            return $vide;
        }

        $resultat = array_merge($vide, [
            'type_id' => $dimension->semelle_type_id ? (int) $dimension->semelle_type_id : null,
            'dimension_id' => (int) $dimension->id,
            'reference' => $dimension->reference,
            'libelle' => trim(($dimension->semelleType?->label ?: 'semelle').' '.($dimension->label ?: MonumentSemelleDimension::libelleAuto($dimension->largeur, $dimension->longueur))),
        ]);

        if (! $granitId) {
            $resultat['avertissement'] = 'Choisissez un granit pour obtenir le prix de la semelle.';

            return $resultat;
        }

        $ligne = MonumentSemellePrix::where('monument_id', $monumentId)
            ->where('granit_id', $granitId)
            ->where('reference', $dimension->reference)
            ->first();

        if (! $ligne || $ligne->prix_fob === null || $ligne->taux_marge === null) {
            $resultat['avertissement'] = "Aucun prix pour la semelle {$dimension->reference} avec ce granit (onglet « Prix des semelles » du monument) : la semelle n'est pas chiffrée.";

            return $resultat;
        }

        $resultat['poids_kg'] = (float) $ligne->poids_kg;
        $resultat['prix'] = $ligne->prixVenteDepart();

        if ($resultat['prix'] === null) {
            $resultat['avertissement'] = "Prix de la semelle {$dimension->reference} incalculable : poids de la semelle ou paramètres de transport manquants.";
        }

        return $resultat;
    }
}
