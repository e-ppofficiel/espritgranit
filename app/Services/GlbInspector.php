<?php

namespace App\Services;

use RuntimeException;

/**
 * Inspecte un fichier .glb (glTF binaire) et vérifie ses matériaux par rapport à la
 * nomenclature attendue par le configurateur 3D.
 *
 * Seul le bloc JSON du fichier est lu (en-tête + premier chunk) : un .glb de 50 Mo
 * ne charge donc que quelques Ko en mémoire. Aucune dépendance à Laravel.
 */
class GlbInspector
{
    /** Familles reconnues par le configurateur (le nom du matériau doit COMMENCER par l'une d'elles). */
    public const FAMILLES = ['SEMELLE-B', 'SEMELLE-P', 'SEMELLE-R', 'GRANIT', 'BICOLOR'];

    /** Familles qui ne peuvent pas partager une même pièce avec une autre famille. */
    public const SEMELLES = ['SEMELLE-B', 'SEMELLE-P', 'SEMELLE-R'];

    /**
     * Lit le bloc JSON d'un .glb (ou d'un .gltf texte).
     *
     * @return array<string, mixed>
     */
    public static function lireJson(string $chemin): array
    {
        if (! is_file($chemin) || ! is_readable($chemin)) {
            throw new RuntimeException("Fichier introuvable ou illisible : {$chemin}");
        }

        $f = fopen($chemin, 'rb');
        if ($f === false) {
            throw new RuntimeException("Impossible d'ouvrir : {$chemin}");
        }

        try {
            $entete = fread($f, 12);

            // .gltf texte (JSON brut)
            if ($entete !== false && str_starts_with(ltrim($entete), '{')) {
                rewind($f);
                $json = json_decode((string) stream_get_contents($f), true);
                if (! is_array($json)) {
                    throw new RuntimeException('Le fichier ressemble à un .gltf mais son JSON est invalide.');
                }

                return $json;
            }

            if ($entete === false || strlen($entete) < 12 || substr($entete, 0, 4) !== 'glTF') {
                throw new RuntimeException("Ce fichier n'est pas un .glb valide (signature « glTF » absente).");
            }

            $chunk = fread($f, 8);
            if ($chunk === false || strlen($chunk) < 8) {
                throw new RuntimeException('.glb tronqué : en-tête du premier bloc absent.');
            }

            $infos = unpack('Vlongueur/a4type', $chunk);
            if ($infos['type'] !== 'JSON') {
                throw new RuntimeException("Le premier bloc n'est pas du JSON (type « {$infos['type']} »).");
            }

            $contenu = fread($f, $infos['longueur']);
            if ($contenu === false || strlen($contenu) < $infos['longueur']) {
                throw new RuntimeException('.glb tronqué : bloc JSON incomplet.');
            }

            $json = json_decode(rtrim($contenu, " \0"), true);
            if (! is_array($json)) {
                throw new RuntimeException('Bloc JSON du .glb invalide : ' . json_last_error_msg());
            }

            return $json;
        } finally {
            fclose($f);
        }
    }

    /**
     * Famille reconnue d'un nom de matériau, ou null.
     */
    public static function famille(?string $nom): ?string
    {
        $n = static::normaliser($nom);

        foreach (static::FAMILLES as $famille) {
            if (str_starts_with($n, $famille)) {
                return $famille;
            }
        }

        return null;
    }

    /**
     * Message d'alerte pour un nom de matériau qui n'est PAS reconnu mais qui ressemble
     * à une famille (faute de frappe, type de semelle manquant…). Null si rien à signaler.
     */
    public static function alerte(?string $nom): ?string
    {
        if (static::famille($nom) !== null) {
            return null;
        }

        $n = static::normaliser($nom);
        if ($n === '') {
            return null;
        }

        $mot = static::motCle($n);

        if (str_starts_with($n, 'SEMELLE')) {
            $suggestion = static::plusProche($mot, static::SEMELLES);

            return $suggestion
                ? "Semelle non reconnue : « {$mot} » ressemble à {$suggestion}. Le type (B, P ou R) doit être exact."
                : 'Semelle sans type valide : le nom doit commencer par SEMELLE-B, SEMELLE-P ou SEMELLE-R.';
        }

        $suggestion = static::plusProche($mot, static::FAMILLES);

        return $suggestion ? "Faute probable : « {$mot} » ressemble à {$suggestion}." : null;
    }

    /**
     * Analyse complète d'un fichier.
     *
     * @return array<string, mixed>
     */
    public static function analyser(string $chemin): array
    {
        $json = static::lireJson($chemin);

        $materiaux = $json['materials'] ?? [];
        $meshes = $json['meshes'] ?? [];
        $nodes = $json['nodes'] ?? [];

        // matériau -> pièces (nœuds) et nombre de primitives qui le portent
        $usage = [];
        // nœud -> familles présentes sur cette pièce
        $famillesParNoeud = [];

        foreach ($nodes as $indexNoeud => $noeud) {
            if (! isset($noeud['mesh'], $meshes[$noeud['mesh']])) {
                continue;
            }

            $mesh = $meshes[$noeud['mesh']];
            $nomPiece = $noeud['name'] ?? ($mesh['name'] ?? "noeud #{$indexNoeud}");

            foreach ($mesh['primitives'] ?? [] as $primitive) {
                if (! isset($primitive['material'])) {
                    continue;
                }

                $m = $primitive['material'];
                $usage[$m]['noeuds'][$nomPiece] = true;
                $usage[$m]['primitives'] = ($usage[$m]['primitives'] ?? 0) + 1;

                $famille = static::famille($materiaux[$m]['name'] ?? null);
                if ($famille) {
                    $famillesParNoeud[$nomPiece][$famille] = true;
                }
            }
        }

        $lignes = [];
        $familles = array_fill_keys(static::FAMILLES, ['materiaux' => 0, 'primitives' => 0, 'pieces' => []]);
        $problemes = [];
        $inutilises = [];

        foreach ($materiaux as $index => $materiau) {
            $nom = $materiau['name'] ?? null;
            $famille = static::famille($nom);
            $alerte = static::alerte($nom);
            $pieces = array_keys($usage[$index]['noeuds'] ?? []);
            $primitives = $usage[$index]['primitives'] ?? 0;

            $lignes[] = [
                'index' => $index,
                'nom' => $nom,
                'famille' => $famille,
                'alerte' => $alerte,
                'pieces' => $pieces,
                'primitives' => $primitives,
            ];

            if ($famille) {
                $familles[$famille]['materiaux']++;
                $familles[$famille]['primitives'] += $primitives;
                foreach ($pieces as $piece) {
                    $familles[$famille]['pieces'][$piece] = true;
                }
            }

            if ($alerte) {
                $problemes[] = ['niveau' => 'erreur', 'message' => "Matériau #{$index} « " . ($nom ?? '') . " » : {$alerte}"];
            }

            if ($famille && $primitives === 0) {
                $inutilises[$famille] = ($inutilises[$famille] ?? 0) + 1;
            }
        }

        foreach ($inutilises as $famille => $nombre) {
            $problemes[] = [
                'niveau' => 'alerte',
                'message' => "{$nombre} matériau(x) {$famille} reconnu(s) mais appliqué(s) à aucune pièce du fichier (sans effet, à supprimer ou à utiliser).",
            ];
        }

        foreach ($familles as $famille => $infos) {
            if ($infos['materiaux'] === 0) {
                $problemes[] = ['niveau' => 'erreur', 'message' => "Aucun matériau {$famille} : l'option correspondante restera sans effet."];
            }
        }

        foreach ($famillesParNoeud as $piece => $presentes) {
            $presentes = array_keys($presentes);

            if (count($presentes) > 1) {
                $problemes[] = [
                    'niveau' => 'erreur',
                    'message' => "La pièce « {$piece} » porte à la fois " . implode(' et ', $presentes)
                        . ' : masquer ou colorer l\'un affecte aussi l\'autre.',
                ];
            }
        }

        // Indice : une semelle appliquée à une pièce dont le nom évoque la stèle.
        $dejaSignale = [];
        foreach ($lignes as $ligne) {
            if ($ligne['famille'] && in_array($ligne['famille'], static::SEMELLES, true)) {
                foreach ($ligne['pieces'] as $piece) {
                    $cle = $ligne['famille'] . '|' . $piece;
                    if (isset($dejaSignale[$cle])) {
                        continue;
                    }

                    if (preg_match('/st[eèé]le|plaque|tombe|monument/iu', (string) $piece)) {
                        $dejaSignale[$cle] = true;
                        $problemes[] = [
                            'niveau' => 'alerte',
                            'message' => "{$ligne['famille']} est appliqué à la pièce « {$piece} », dont le nom évoque la stèle : à vérifier.",
                        ];
                    }
                }
            }
        }

        // Indice : un groupe nommé comme une semelle (« SEMELLE PLATE », « Sem130P »…) dont aucun matériau
        // n'est nommé SEMELLE-B/P/R. Le configurateur ne regarde que les noms de MATÉRIAUX : ce groupe
        // ne sera jamais masqué ni changé de type.
        foreach (static::groupesSemelleSansMateriau($nodes, $meshes, $materiaux) as $groupe) {
            $problemes[] = [
                'niveau' => 'erreur',
                'message' => "Le groupe « {$groupe['nom']} » ressemble à une semelle, mais ses matériaux (« "
                    . implode(' », « ', $groupe['materiaux']) . " ») ne commencent pas par SEMELLE-B, SEMELLE-P ou SEMELLE-R : "
                    . 'le configurateur ne peut ni le masquer ni le rattacher à un type. Renommer le MATÉRIAU dans KeyShot (pas seulement le modèle).',
            ];
        }

        foreach ($familles as &$infos) {
            $infos['pieces'] = array_keys($infos['pieces']);
        }
        unset($infos);

        // Textures embarquées et échelle des UV : le configurateur applique SA texture de granit, les images
        // exportées par KeyShot ne servent à rien (et pèsent souvent 10 Mo+). Le repère eg_uv_metres est posé
        // par « tools/eg-glb optimiser » : il indique que 1 unité UV = 1 mètre (grain homogène sur toutes les faces).
        $nbImages = count($json['images'] ?? []);
        $octetsImages = 0;
        foreach ($json['images'] ?? [] as $image) {
            if (isset($image['bufferView'])) {
                $octetsImages += (int) ($json['bufferViews'][$image['bufferView']]['byteLength'] ?? 0);
            }
        }
        $uvMetres = (bool) ($json['extras']['eg_uv_metres'] ?? false);

        if (! $uvMetres) {
            $problemes[] = [
                'niveau' => 'alerte',
                'message' => 'Fichier non optimisé pour le rendu réaliste : les UV ne sont pas à l\'échelle réelle (la texture paraîtra étirée et de taille différente selon les faces)'
                    . ($nbImages > 0 ? ", et {$nbImages} texture(s) embarquée(s) pèsent " . number_format($octetsImages / 1048576, 1, ',', ' ') . ' Mo pour rien' : '')
                    . '. Lancer : node tools/eg-glb/eg-glb.mjs optimiser <fichier.glb>, puis réimporter le fichier produit.',
            ];
        }

        return [
            'fichier' => $chemin,
            'taille_octets' => filesize($chemin),
            'generateur' => $json['asset']['generator'] ?? null,
            'version_gltf' => $json['asset']['version'] ?? null,
            'nb_materiaux' => count($materiaux),
            'nb_images' => $nbImages,
            'octets_images' => $octetsImages,
            'uv_metres' => $uvMetres,
            'materiaux' => $lignes,
            'familles' => $familles,
            'problemes' => $problemes,
            'conforme' => count(array_filter($problemes, fn (array $p) => $p['niveau'] === 'erreur')) === 0,
        ];
    }

    /**
     * Applique la règle du viewer (viewer.blade.php) aux types de semelle rattachés à un monument.
     *
     * Chaque type a un code en base (table semelle_types). Parmi les matériaux dont le nom commence
     * par SEMELLE, un matériau appartient à un type si son nom COMMENCE par le code de ce type.
     * Un matériau SEMELLE… qui ne commence par aucun code est traité comme semelle « générique » :
     * il reste visible quel que soit le type choisi.
     *
     * @param  array<string, mixed>  $resultat  Retour de analyser()
     * @param  array<int, array{code: ?string, label: ?string}>  $types
     * @return array{types: array<int, array<string, mixed>>, problemes: array<int, array<string, string>>}
     */
    public static function verifierTypesSemelle(array $resultat, array $types): array
    {
        $lignes = [];
        $problemes = [];
        $codes = [];

        foreach ($types as $type) {
            $code = mb_strtoupper((string) ($type['code'] ?? ''));
            $libelle = (string) ($type['label'] ?? $code);
            $nombre = 0;

            if ($code !== '') {
                $codes[] = $code;

                foreach ($resultat['materiaux'] as $m) {
                    $nom = mb_strtoupper(trim((string) ($m['nom'] ?? '')));

                    if (str_starts_with($nom, 'SEMELLE') && str_starts_with($nom, $code)) {
                        $nombre++;
                    }
                }
            }

            $lignes[] = ['code' => $code, 'label' => $libelle, 'materiaux' => $nombre];

            if ($code === '') {
                $problemes[] = ['niveau' => 'erreur', 'message' => "Le type de semelle « {$libelle} » a un code vide en base : il ne peut correspondre à aucun matériau."];
            } elseif ($nombre === 0) {
                $problemes[] = [
                    'niveau' => 'erreur',
                    'message' => "Type de semelle « {$libelle} » (code « {$code} » en base) : aucun matériau SEMELLE… ne commence par « {$code} ». "
                        . 'Choisir ce type ne montre ni ne masque rien. Soit le nom des matériaux, soit le code du type (admin > Types de semelle) est à corriger.',
                ];
            }
        }

        if ($codes !== []) {
            $generiques = [];

            foreach ($resultat['materiaux'] as $m) {
                $nom = mb_strtoupper(trim((string) ($m['nom'] ?? '')));

                if (! str_starts_with($nom, 'SEMELLE')) {
                    continue;
                }

                $correspond = false;
                foreach ($codes as $code) {
                    if (str_starts_with($nom, $code)) {
                        $correspond = true;
                        break;
                    }
                }

                if (! $correspond) {
                    $generiques[(string) $m['nom']] = true;
                }
            }

            foreach (array_keys($generiques) as $nom) {
                $problemes[] = [
                    'niveau' => 'alerte',
                    'message' => "« {$nom} » commence par SEMELLE mais par aucun code de type (" . implode(', ', $codes)
                        . ') : traité comme semelle générique, visible quel que soit le type choisi.',
                ];
            }
        }

        return ['types' => $lignes, 'problemes' => $problemes];
    }

    /**
     * Groupes (nœuds) dont le nom évoque une semelle mais dont aucun matériau n'appartient à une famille SEMELLE.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $meshes
     * @param  array<int, array<string, mixed>>  $materiaux
     * @return array<int, array{nom: string, materiaux: array<int, string>}>
     */
    protected static function groupesSemelleSansMateriau(array $nodes, array $meshes, array $materiaux): array
    {
        $noms = function (int $index, array &$vus) use (&$noms, $nodes, $meshes, $materiaux): array {
            if (isset($vus[$index]) || ! isset($nodes[$index])) {
                return [];
            }
            $vus[$index] = true;

            $resultat = [];
            $noeud = $nodes[$index];

            if (isset($noeud['mesh'], $meshes[$noeud['mesh']])) {
                foreach ($meshes[$noeud['mesh']]['primitives'] ?? [] as $primitive) {
                    if (isset($primitive['material'])) {
                        $resultat[] = (string) ($materiaux[$primitive['material']]['name'] ?? '(sans nom)');
                    }
                }
            }

            foreach ($noeud['children'] ?? [] as $enfant) {
                $resultat = array_merge($resultat, $noms((int) $enfant, $vus));
            }

            return $resultat;
        };

        $groupes = [];

        foreach ($nodes as $index => $noeud) {
            $nom = (string) ($noeud['name'] ?? '');

            if ($nom === '' || ! preg_match('/semelle|^sem\d/iu', $nom)) {
                continue;
            }

            $vus = [];
            $liste = array_values(array_unique($noms($index, $vus)));

            if ($liste === []) {
                continue;
            }

            $avecFamille = array_filter($liste, fn (string $m) => in_array(static::famille($m), static::SEMELLES, true));

            if ($avecFamille === []) {
                $groupes[$nom . '|' . implode('|', $liste)] = ['nom' => $nom, 'materiaux' => $liste];
            }
        }

        return array_values($groupes);
    }

    /** Majuscules, sans espaces de bord. */
    protected static function normaliser(?string $nom): string
    {
        return mb_strtoupper(trim((string) $nom));
    }

    /** Début du nom jusqu'au premier séparateur (tiret bas, espace, # ou point). */
    protected static function motCle(string $nomNormalise): string
    {
        return preg_split('/[_\s#.]/u', $nomNormalise, 2)[0] ?? $nomNormalise;
    }

    /**
     * Famille la plus proche (distance de Levenshtein de 1 ou 2), ou null.
     *
     * @param  array<int, string>  $candidats
     */
    protected static function plusProche(string $mot, array $candidats): ?string
    {
        $meilleure = null;
        $distanceMin = 3;
        $egalites = 0;

        foreach ($candidats as $candidat) {
            $d = levenshtein($mot, $candidat);

            if ($d < 1 || $d >= 3) {
                continue;
            }

            if ($d < $distanceMin) {
                $distanceMin = $d;
                $meilleure = $candidat;
                $egalites = 1;
            } elseif ($d === $distanceMin) {
                $egalites++;
            }
        }

        // Plusieurs candidats à égale distance (ex. « SEMELLE- » : B, P ou R ?) : on ne devine pas.
        return $egalites === 1 ? $meilleure : null;
    }
}
