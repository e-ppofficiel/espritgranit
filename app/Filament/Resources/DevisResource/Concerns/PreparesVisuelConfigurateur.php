<?php

namespace App\Filament\Resources\DevisResource\Concerns;

use App\Models\Monument;

trait PreparesVisuelConfigurateur
{
    /**
     * Retourne l'URL du fichier 3D du monument, les URLs de texture des granits
     * disponibles pour cette combinaison et leurs réglages de rendu (taille du grain,
     * brillance, luminosité ; null = valeur standard), pour alimenter l'aperçu 3D du
     * formulaire Devis. Appelé depuis Alpine.js via $wire.call().
     *
     * Le typage est volontairement souple (mixed) : lors du tout premier rendu de la page
     * d'édition, la valeur "entangle" côté Alpine peut transiter par un état intermédiaire
     * (ex: tableau vide) avant d'être résolue en entier. On normalise nous-mêmes plutôt
     * que de laisser Laravel lever une TypeError sur un typage strict.
     */
    public function getDonneesMonumentPourVisuel(mixed $monumentId = null): array
    {
        $monumentId = is_numeric($monumentId) ? (int) $monumentId : null;

        if (! $monumentId) {
            return ['glbUrl' => null, 'granits' => [], 'reglages' => []];
        }

        $monument = Monument::find($monumentId);

        if (! $monument || ! $monument->fichier_3d) {
            return ['glbUrl' => null, 'granits' => [], 'reglages' => []];
        }

        $granits = $monument->granits()
            ->wherePivotNotNull('prix_fob')
            ->get();

        // Colonnes de rendu ajoutées par la migration du rendu réaliste : lues seulement si elles existent.
        $reglage = fn ($granit, string $colonne) => array_key_exists($colonne, $granit->getAttributes())
            ? $granit->getAttribute($colonne)
            : null;

        return [
            'glbUrl' => asset('storage/' . $monument->fichier_3d),
            'granits' => $granits
                ->mapWithKeys(fn ($granit) => [
                    $granit->id => $granit->image ? asset('storage/' . $granit->image) : null,
                ])
                ->filter()
                ->all(),
            'reglages' => $granits
                ->mapWithKeys(fn ($granit) => [
                    $granit->id => [
                        'echantillon' => $reglage($granit, 'echantillon_cm'),
                        'brillance' => $reglage($granit, 'brillance'),
                        'luminosite' => $reglage($granit, 'luminosite'),
                    ],
                ])
                ->all(),
        ];
    }
}
