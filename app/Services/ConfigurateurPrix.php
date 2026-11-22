<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\Departement;
use App\Models\Monument;
use App\Models\Societe;

/**
 * Prix affiché en direct dans le configurateur 3D.
 *
 * Même formule que le devis (voir DevisResource::recalculerPrix), sans la modifier :
 *   prix = (prix granit [+ supplément soubassement noir] + semelle + transport) × coefficient de vente
 * où le coefficient est celui de l'agence, sinon de la société, sinon le coefficient général.
 *
 * Seuls des montants FINAUX (coefficient déjà appliqué) sortent de ce service : jamais de prix FOB,
 * de marge ni de coût de transport d'achat.
 *
 * « Base + supplément » : la base est le granit le moins cher défini pour ce monument ; le supplément du
 * granit choisi est la différence avec la base. Les montants sont arrondis au centime comme dans le devis.
 */
class ConfigurateurPrix
{
    /** Mêmes rôles que User::canAccessPanel() pour le panel admin. */
    public const ROLES_ADMIN = ['super_admin', 'admin_esprit_granit', 'gestionnaire'];

    public static function estAdmin($utilisateur): bool
    {
        return $utilisateur
            && method_exists($utilisateur, 'hasAnyRole')
            && $utilisateur->hasAnyRole(self::ROLES_ADMIN);
    }

    /**
     * Coefficient de vente applicable. Un administrateur peut choisir l'agence dont il veut voir le prix
     * (sans choix : coefficient général) ; tout autre utilisateur n'obtient QUE le coefficient de sa propre agence.
     */
    public static function coefficientPour($utilisateur, $agenceDemandee = null): float
    {
        $agence = null;
        $societe = null;

        if (self::estAdmin($utilisateur)) {
            $id = self::entierPositif($agenceDemandee);
            $agence = $id ? Agence::find($id) : null;
            $societe = $agence && $agence->societe_id ? Societe::find($agence->societe_id) : null;
        } elseif ($utilisateur) {
            $agence = $utilisateur->agence_id ? Agence::find($utilisateur->agence_id) : null;
            $societe = $utilisateur->societe_id ? Societe::find($utilisateur->societe_id) : null;
        }

        return CoefficientResolver::resoudre($agence, $societe);
    }

    /** @return array<int, ?float> prix de vente départ (avant coefficient) par granit actif ; null = pas de prix défini */
    public static function prixGranitsDepart(Monument $monument): array
    {
        $prix = [];

        foreach ($monument->granits()->where('granits.actif', true)->get() as $granit) {
            $fob = $granit->pivot->prix_fob;
            $marge = $granit->pivot->taux_marge;
            // Même condition que le devis : un prix FOB et une marge définis.
            $prix[(int) $granit->id] = ($fob && $marge !== null)
                ? PrixCalculator::prixVenteDepart($monument, (float) $fob, (float) $marge)
                : null;
        }

        return $prix;
    }

    /**
     * Base et supplément de chaque granit (coefficient appliqué).
     *
     * @param  array<int, ?float>  $prixDepart
     * @return array{base: ?float, granits: array<int, array{id: int, montant: ?float}>}
     */
    public static function supplements(array $prixDepart, float $coefficient): array
    {
        $finals = [];
        foreach ($prixDepart as $id => $prix) {
            $finals[(int) $id] = $prix === null ? null : round($prix * $coefficient, 2);
        }

        $valides = array_filter($finals, static fn ($p) => $p !== null);
        $base = $valides === [] ? null : min($valides);

        $granits = [];
        foreach ($finals as $id => $final) {
            $granits[] = [
                'id' => $id,
                'montant' => ($final === null || $base === null) ? null : round($final - $base, 2),
            ];
        }

        return ['base' => $base, 'granits' => $granits];
    }

    /**
     * Données utiles à l'affichage initial (vignettes de granit, listes déroulantes).
     * Le contexte initial est celui de l'utilisateur ; pour un administrateur : coefficient général.
     */
    public static function donneesInitiales(Monument $monument, $utilisateur): array
    {
        $admin = self::estAdmin($utilisateur);
        $coefficient = self::coefficientPour($utilisateur, null);
        $sup = self::supplements(self::prixGranitsDepart($monument), $coefficient);

        $departements = Departement::orderBy('code')->get(['id', 'code', 'nom'])
            ->map(static fn ($d) => ['id' => (int) $d->id, 'libelle' => trim($d->code . ' – ' . $d->nom)])
            ->values()
            ->all();

        $agences = [];
        if ($admin) {
            $enseignes = Societe::pluck('enseigne', 'id');
            $agences = Agence::orderBy('nom')->get()
                ->map(static fn ($a) => [
                    'id' => (int) $a->id,
                    'libelle' => trim((! empty($enseignes[$a->societe_id]) ? $enseignes[$a->societe_id] . ' — ' : '') . $a->nom),
                ])
                ->values()
                ->all();
        }

        return [
            'admin' => $admin,
            'base' => $sup['base'],
            'supplements' => $sup['granits'],
            'departements' => $departements,
            'agences' => $agences,
            'tva' => (float) config('configurateur.tva', 20),
        ];
    }

    /**
     * Calcul complet pour un état de configuration déjà normalisé (clés g, sb, se, sd).
     *
     * statut : 'ok' | 'granit_absent' | 'granit_sans_prix' | 'departement_manquant'
     */
    public static function calculer(Monument $monument, array $etat, ?int $departementId, float $coefficient, bool $admin = false): array
    {
        $tva = (float) config('configurateur.tva', 20);
        $prixDepart = self::prixGranitsDepart($monument);
        $sup = self::supplements($prixDepart, $coefficient);

        $resultat = [
            'statut' => 'ok',
            'granit' => null,
            'base' => $sup['base'],
            'lignes' => [],
            'total_ht' => null,
            'total_ttc' => null,
            'tva' => $tva,
            'notes' => [],
            'semelle_sur_demande' => false,
            'supplements' => $sup['granits'],
            'coefficient' => $admin ? $coefficient : null,
        ];

        $granitId = $etat['g'] ?? null;
        if (! $granitId) {
            $resultat['statut'] = 'granit_absent';

            return $resultat;
        }

        $granit = $monument->granits()->where('granits.id', $granitId)->first();
        $resultat['granit'] = $granit?->nom_commercial;

        $prixGranit = $prixDepart[(int) $granitId] ?? null;
        if ($prixGranit === null || $sup['base'] === null) {
            $resultat['statut'] = 'granit_sans_prix';

            return $resultat;
        }

        $poidsKg = $monument->poidsKg();
        $partsBrutes = ['granit' => $prixGranit];

        // Soubassement noir : même règle que le devis (supplément sur le prix granit, en % ou en €).
        $prixSoubassement = 0.0;
        if (! empty($etat['sb']) && $monument->soubassement_noir_supplement_valeur) {
            $prixSoubassement = $monument->soubassement_noir_supplement_type === 'pourcentage'
                ? round($prixGranit * ((float) $monument->soubassement_noir_supplement_valeur / 100), 2)
                : (float) $monument->soubassement_noir_supplement_valeur;
        }

        // Semelle : prix et poids viennent de monument_semelle_prix (référence de la dimension × monument × granit).
        $semelle = null;
        if (! empty($etat['se']) && ! empty($etat['sd'])) {
            $semelle = SemelleDevis::calculer((int) $monument->id, (int) $granitId, (int) $etat['sd']);
            $poidsKg += $semelle['poids_kg'];
        }
        $prixSemelle = $semelle['prix'] ?? null;
        // Semelle demandée mais sans prix pour ce granit : ligne « sur demande » et total « hors semelle » (comme le devis,
        // qui n'ajoute alors rien pour la semelle ni son poids).
        $semelleSurDemande = $semelle !== null && $semelle['dimension_id'] !== null && $prixSemelle === null;

        // Transport : dépend du département choisi et du poids total (monument + semelle).
        $prixTransport = null;
        $departement = $departementId ? Departement::find($departementId) : null;
        if (! $departement) {
            $resultat['statut'] = 'departement_manquant';
        } elseif ($poidsKg > 0) {
            $prixTransport = TransportCalculator::calculer((string) $departement->code, $poidsKg);
            if ($prixTransport === null) {
                $resultat['notes'][] = 'Tarif de transport non défini pour ce département : prix hors transport.';
            }
        }

        // Lignes affichées (coefficient appliqué, centimes).
        $base = $sup['base'];
        $finalGranit = round($prixGranit * $coefficient, 2);
        $lignes = [['cle' => 'granit', 'libelle' => $granit?->nom_commercial ?? 'granit', 'montant' => round($finalGranit - $base, 2)]];
        if ($prixSoubassement > 0) {
            $lignes[] = ['cle' => 'soubassement', 'libelle' => 'soubassement noir', 'montant' => round($prixSoubassement * $coefficient, 2)];
        }
        if ($prixSemelle !== null) {
            $lignes[] = ['cle' => 'semelle', 'libelle' => $semelle['libelle'] ?: ($semelle['reference'] ?: 'semelle'), 'montant' => round($prixSemelle * $coefficient, 2)];
        } elseif ($semelleSurDemande) {
            $lignes[] = ['cle' => 'semelle', 'libelle' => $semelle['libelle'] ?: ($semelle['reference'] ?: 'semelle'), 'montant' => null, 'sur_demande' => true];
        }
        if ($prixTransport !== null) {
            $lignes[] = ['cle' => 'transport', 'libelle' => 'transport ' . trim($departement->code . ' – ' . $departement->nom), 'montant' => round($prixTransport * $coefficient, 2)];
        }

        // Total : exactement le calcul du devis (somme AVANT coefficient, puis arrondi), pour que le prix affiché
        // soit celui du devis. L'éventuel écart d'un centime d'arrondi entre les lignes et le total est reporté sur la base.
        if ($resultat['statut'] === 'ok') {
            $sommeBrute = $prixGranit + $prixSoubassement + ($prixSemelle ?? 0) + ($prixTransport ?? 0);
            $totalHt = round($sommeBrute * $coefficient, 2);
            $somme = $base;
            foreach ($lignes as $ligne) {
                $somme += $ligne['montant'] ?? 0;
            }
            $base = round($base + ($totalHt - $somme), 2);

            $resultat['total_ht'] = $totalHt;
            $resultat['total_ttc'] = round($totalHt * (1 + $tva / 100), 2);
        }

        $resultat['base'] = $base;
        $resultat['lignes'] = $lignes;
        $resultat['semelle_sur_demande'] = $semelleSurDemande;

        return $resultat;
    }

    private static function entierPositif($valeur): ?int
    {
        if (is_int($valeur)) {
            return $valeur > 0 ? $valeur : null;
        }
        if (is_string($valeur) && ctype_digit($valeur) && strlen($valeur) <= 12) {
            return (int) $valeur > 0 ? (int) $valeur : null;
        }

        return null;
    }
}
