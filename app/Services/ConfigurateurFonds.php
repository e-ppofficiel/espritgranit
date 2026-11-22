<?php

namespace App\Services;

use App\Models\Fond;
use Illuminate\Support\Facades\Schema;

/**
 * Fonds proposés par le configurateur 3D : couleurs de fond et ambiances (panoramas 360°).
 *
 * Source : la table `fonds` (rubrique « Fonds du configurateur » de l'admin). Si la table est absente, vide, ou
 * n'a aucune ligne utilisable pour un type, on retombe sur config/configurateur.php (comportement d'avant la v22) :
 * le configurateur ne se retrouve donc jamais sans fond. Ne lève jamais d'exception.
 *
 * Forme renvoyée par charger() :
 *   couleurs        liste de {cle, libelle, couleur (#rrggbb), clair (bool : texte sombre par-dessus)}
 *   couleur_defaut  clé de la couleur à l'ouverture
 *   ambiances       [cle => {libelle, url, exposition, decor (url|null), vignette (url|null)}]  (vide sans rendu réaliste)
 *   ambiance_defaut clé de l'ambiance à l'ouverture (null si aucune)
 */
class ConfigurateurFonds
{
    /** Clés historiques toujours acceptées dans un lien partagé (ancien interrupteur « Fond clair »). */
    public const COULEURS_HISTORIQUES = ['blanc', 'noir'];

    private static ?array $lignes = null;

    public static function oublier(): void
    {
        self::$lignes = null;
    }

    /** @return array<int, array<string, mixed>> lignes actives de la table, ordre d'affichage ; [] si indisponible */
    private static function lignes(): array
    {
        if (self::$lignes !== null) {
            return self::$lignes;
        }
        try {
            if (! Schema::hasTable('fonds')) {
                return self::$lignes = [];
            }

            return self::$lignes = Fond::actifs()->orderBy('ordre')->orderBy('id')->get()
                ->map(static fn (Fond $f) => [
                    'cle' => (string) $f->cle,
                    'nom' => (string) $f->nom,
                    'type' => (string) $f->type,
                    'couleur' => $f->couleur,
                    'exposition' => (float) $f->exposition,
                    'visible_decor' => (bool) $f->visible_decor,
                    'url' => $f->type === 'ambiance' ? $f->urlFichier() : null,
                    'decor' => $f->type === 'ambiance' && $f->visible_decor ? $f->urlDecor() : null,
                    'vignette' => $f->urlVignette(),
                ])->all();
        } catch (\Throwable $e) {
            report($e);

            return self::$lignes = [];
        }
    }

    /** Luminance perçue d'une couleur #rrggbb (0 = noir, 1 = blanc). */
    public static function luminance(string $hex): float
    {
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));

        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    }

    /** @return array{couleurs: array, couleur_defaut: string, ambiances: array, ambiance_defaut: ?string} */
    public static function charger(): array
    {
        $couleurs = self::couleurs();
        $demande = in_array(config('configurateur.fond_defaut', 'noir'), ['blanc', 'clair'], true) ? 'blanc' : 'noir';
        $cles = array_column($couleurs, 'cle');
        $defaut = in_array($demande, $cles, true) ? $demande : $cles[0];

        $ambiances = self::ambiances();
        $ambianceDefaut = $ambiances === [] ? null : (array_key_exists('defaut', $ambiances) ? 'defaut' : (string) array_key_first($ambiances));

        return [
            'couleurs' => $couleurs,
            'couleur_defaut' => $defaut,
            'ambiances' => $ambiances,
            'ambiance_defaut' => $ambianceDefaut,
        ];
    }

    private static function couleurs(): array
    {
        $liste = [];
        foreach (self::lignes() as $l) {
            if ($l['type'] === 'couleur' && is_string($l['couleur']) && preg_match('/^#[0-9a-f]{6}$/', $l['couleur'])) {
                $liste[] = [
                    'cle' => $l['cle'],
                    'libelle' => $l['nom'],
                    'couleur' => $l['couleur'],
                    'clair' => self::luminance($l['couleur']) > 0.55,
                ];
            }
        }
        if ($liste !== []) {
            return $liste;
        }

        // Repli : config (noir + fond clair neutre).
        $clair = (string) config('configurateur.fond_clair_couleur', '#e6e3de');
        $clair = preg_match('/^#[0-9a-fA-F]{6}$/', $clair) ? strtolower($clair) : '#e6e3de';

        return [
            ['cle' => 'noir', 'libelle' => 'Noir', 'couleur' => '#0a0a0a', 'clair' => false],
            ['cle' => 'blanc', 'libelle' => 'Clair neutre', 'couleur' => $clair, 'clair' => true],
        ];
    }

    private static function ambiances(): array
    {
        if (! (bool) config('configurateur.rendu_realiste', true)) {
            return [];
        }
        $decorActif = (bool) (config('configurateur.decor', [])['actif'] ?? true);

        $liste = [];
        foreach (self::lignes() as $l) {
            if ($l['type'] !== 'ambiance' || ! $l['url']) {
                continue; // fichier manquant : ignoré, comme dans la config
            }
            $liste[$l['cle']] = [
                'libelle' => $l['nom'],
                'url' => $l['url'],
                'exposition' => $l['exposition'],
                'decor' => ($decorActif && $l['visible_decor']) ? $l['decor'] : null,
                'vignette' => $l['vignette'],
            ];
        }

        return $liste !== [] ? $liste : self::ambiancesConfig($decorActif);
    }

    /** Repli : exactement l'ancien calcul à partir de config/configurateur.php. */
    private static function ambiancesConfig(bool $decorActif): array
    {
        $fichiers = (array) config('configurateur.environnements', [
            'studio' => 'configurateur/studio.hdr',
            'contraste' => 'configurateur/studio-contraste.hdr',
        ]);
        $chemin = $fichiers[config('configurateur.environnement', 'contraste')] ?? 'configurateur/studio.hdr';
        if (! file_exists(public_path($chemin))) {
            $chemin = 'configurateur/studio.hdr';
        }
        if (! file_exists(public_path($chemin))) {
            return [];
        }
        $exposition = (float) config('configurateur.exposition', 1.5);

        $liste = ['defaut' => [
            'libelle' => config('configurateur.ambiance_defaut_libelle', 'Studio'),
            'url' => asset($chemin),
            'exposition' => $exposition,
            'decor' => null,
            'vignette' => null,
        ]];
        foreach ((array) config('configurateur.ambiances', []) as $cle => $ambiance) {
            if ($cle === 'defaut' || empty($ambiance['fichier']) || ! file_exists(public_path($ambiance['fichier']))) {
                continue;
            }
            $decorFichier = $ambiance['decor'] ?? $ambiance['fichier'];
            $liste[$cle] = [
                'libelle' => $ambiance['libelle'] ?? ucfirst(str_replace('-', ' ', (string) $cle)),
                'url' => asset($ambiance['fichier']),
                'exposition' => (float) ($ambiance['exposition'] ?? $exposition),
                'decor' => ($decorActif && $decorFichier !== false && file_exists(public_path($decorFichier))) ? asset($decorFichier) : null,
                'vignette' => null,
            ];
        }

        return $liste;
    }

    /** Clés de fond de couleur acceptées dans un lien partagé (revalidation côté serveur). */
    public static function clesCouleurs(): array
    {
        $cles = self::COULEURS_HISTORIQUES;
        foreach (self::lignes() as $l) {
            if ($l['type'] === 'couleur') {
                $cles[] = $l['cle'];
            }
        }

        return array_values(array_unique($cles));
    }

    /** Clés d'ambiance acceptées dans un lien partagé (revalidation côté serveur). */
    public static function clesAmbiances(): array
    {
        $cles = ['defaut'];
        foreach (array_keys((array) config('configurateur.ambiances', [])) as $cle) {
            $cles[] = (string) $cle;
        }
        foreach (self::lignes() as $l) {
            if ($l['type'] === 'ambiance') {
                $cles[] = $l['cle'];
            }
        }

        return array_values(array_unique($cles));
    }
}
