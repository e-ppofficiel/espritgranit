<?php

namespace App\Services;

use App\Models\Granit;

class ImportPrixGranitsParser
{
    /**
     * Parse un texte collé (format Excel, colonnes séparées par tabulation) et retourne
     * un tableau de lignes analysées, prêtes à être affichées dans un écran de prévisualisation.
     *
     * Colonnes attendues (0-indexées) d'après le format observé :
     *   2  : nom du granit (ex: "Hindi Red")
     *   5  : préfixe famille, optionnel (ex: "Amazone" → nom complet "Amazone-Hindi Red")
     *   6  : catégorie / mot-clé (ex: "Contemporain")
     *   7  : pays d'origine (ex: "Indes")
     *   11 : prix FOB (ex: "853,0 €")
     *   13 : taux de marge (ex: "35%")
     *   14 : poids du monument en kg, constant sur toutes les lignes d'un même monument (ex: "738")
     *
     * Toutes les autres colonnes (transport, transit, prix vente calculé...) sont ignorées :
     * elles sont recalculées en direct par PrixCalculator / le coefficient société-agence.
     */
    public static function parse(string $texteColle): array
    {
        $lignesBrutes = preg_split('/\r\n|\r|\n/', trim($texteColle));
        $resultats = [];

        foreach ($lignesBrutes as $ligneBrute) {
            if (trim($ligneBrute) === '') {
                continue;
            }

            $colonnes = explode("\t", $ligneBrute);

            $nomGranit = trim($colonnes[2] ?? '');
            $prefixe = trim($colonnes[5] ?? '');
            $categorie = trim($colonnes[6] ?? '');
            $pays = trim($colonnes[7] ?? '');
            $prixFob = self::parseMontant($colonnes[11] ?? null);
            $tauxMarge = self::parsePourcentage($colonnes[13] ?? null);
            $poids = self::parseMontant($colonnes[14] ?? null);

            if ($nomGranit === '') {
                $resultats[] = [
                    'ligne_brute' => $ligneBrute,
                    'nom_detecte' => null,
                    'granit_id' => null,
                    'prix_fob' => null,
                    'taux_marge' => null,
                    'poids_detecte' => null,
                    'categorie_detectee' => null,
                    'pays_detecte' => null,
                    'erreur' => 'Nom du granit introuvable sur cette ligne (format inattendu).',
                ];
                continue;
            }

            $granit = self::trouverGranit($nomGranit, $prefixe);

            $resultats[] = [
                'ligne_brute' => $ligneBrute,
                'nom_detecte' => $prefixe ? "{$prefixe}-{$nomGranit}" : $nomGranit,
                'granit_id' => $granit?->id,
                'prix_fob' => $prixFob,
                'taux_marge' => $tauxMarge,
                'poids_detecte' => $poids,
                'categorie_detectee' => $categorie ?: null,
                'pays_detecte' => $pays ?: null,
                'erreur' => ($prixFob === null || $tauxMarge === null)
                    ? 'Prix FOB ou taux de marge illisible sur cette ligne.'
                    : null,
            ];
        }

        return $resultats;
    }

    protected static function trouverGranit(string $nomGranit, string $prefixe): ?Granit
    {
        // 1. Nom complet "Préfixe-Nom" si un préfixe est présent (ex: "Amazone-Hindi Red")
        if ($prefixe !== '') {
            $granit = Granit::whereRaw('LOWER(nom_commercial) = ?', [strtolower("{$prefixe}-{$nomGranit}")])->first();
            if ($granit) {
                return $granit;
            }
        }

        // 2. Nom seul, correspondance exacte insensible à la casse
        return Granit::whereRaw('LOWER(nom_commercial) = ?', [strtolower($nomGranit)])->first();
    }

    protected static function parseMontant(?string $valeur): ?float
    {
        if ($valeur === null) {
            return null;
        }

        $nettoye = str_replace(["€", " ", "\xc2\xa0"], '', trim($valeur));
        $nettoye = str_replace(',', '.', $nettoye);

        return is_numeric($nettoye) ? (float) $nettoye : null;
    }

    protected static function parsePourcentage(?string $valeur): ?float
    {
        if ($valeur === null) {
            return null;
        }

        $nettoye = str_replace('%', '', trim($valeur));
        $nettoye = str_replace(',', '.', $nettoye);

        return is_numeric($nettoye) ? ((float) $nettoye) / 100 : null;
    }
}
