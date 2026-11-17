<?php

/*
 * Réglages du configurateur 3D (rendu réaliste).
 *
 * Pour revenir EXACTEMENT à l'ancien rendu : 'rendu_realiste' => false
 * (pas d'éclairage studio, pas d'ambiances, pas d'échelle de grain, pas de finitions, pas de brillance ni de luminosité par granit).
 */
return [
    'rendu_realiste' => env('CONFIGURATEUR_RENDU_REALISTE', true),

    // Luminosité globale (exposition) de la scène 3D.
    'exposition' => 1.5,

    // Éclairage de la scène : clé de 'environnements'.
    //   'contraste' : sources dégradées + bandes verticales, reflets nets et brillants sur les faces polies ;
    //   'studio'    : éclairage doux de la première version du rendu réaliste (retour arrière).
    'environnement' => 'contraste',
    'environnements' => [
        'studio' => 'configurateur/studio.hdr',
        'contraste' => 'configurateur/studio-contraste.hdr',
    ],

    // Ambiances proposées à tous les visiteurs (sélecteur « Ambiance » du configurateur) : un paysage se reflète sur la pierre polie.
    // L'éclairage par défaut ('environnement' ci-dessus) est toujours proposé en premier, sous le nom 'ambiance_defaut_libelle'.
    // 'exposition' : luminosité propre à chaque ambiance (réglée pour que la pierre garde un niveau de lumière comparable).
    // Retour arrière : 'ambiances' => [] masque le sélecteur. Un fichier manquant est ignoré.
    // Images : photos HDRI Poly Haven (licence CC0), réduites en 1536×768 avec tools/eg-glb/env/convertir_hdri.py.
    'ambiance_defaut_libelle' => 'Studio',
    'ambiances' => [
        'kloppenheim-puresky'         => ['libelle' => 'Kloppenheim puresky',         'fichier' => 'configurateur/ambiances/kloppenheim-puresky.hdr',         'exposition' => 1.45],
        'meadow'                      => ['libelle' => 'Meadow',                      'fichier' => 'configurateur/ambiances/meadow.hdr',                      'exposition' => 0.9],
        'river-alcove'                => ['libelle' => 'River alcove',                'fichier' => 'configurateur/ambiances/river-alcove.hdr',                'exposition' => 0.95],
        'snowy-forest'                => ['libelle' => 'Snowy forest',                'fichier' => 'configurateur/ambiances/snowy-forest.hdr',                'exposition' => 1.55],
        'sunflowers-puresky'          => ['libelle' => 'Sunflowers puresky',          'fichier' => 'configurateur/ambiances/sunflowers-puresky.hdr',          'exposition' => 1.3],
        'tree-lined-driveway'         => ['libelle' => 'Tree lined driveway',         'fichier' => 'configurateur/ambiances/tree-lined-driveway.hdr',         'exposition' => 1.35],
        'citrus-orchard-road-puresky' => ['libelle' => 'Citrus orchard road puresky', 'fichier' => 'configurateur/ambiances/citrus-orchard-road-puresky.hdr', 'exposition' => 1.1],
        'epping-forest'               => ['libelle' => 'Epping forest',               'fichier' => 'configurateur/ambiances/epping-forest.hdr',               'exposition' => 1.5],
        'forest-slope'                => ['libelle' => 'Forest slope',                'fichier' => 'configurateur/ambiances/forest-slope.hdr',                'exposition' => 1.6],
    ],

    // Fond du viewer à l'ouverture : 'noir' ou 'blanc'. Chaque visiteur peut le changer (mémorisé dans son navigateur).
    'fond_defaut' => 'noir',

    // Taille réelle (en cm) du carré photographié, quand le granit n'a pas de valeur propre (colonne granits.echantillon_cm).
    // Plus ce nombre est PETIT, plus le grain paraît FIN sur le monument.
    'echantillon_defaut_cm' => 30,

    // Brillance par granit (colonne granits.brillance, 0 à 100) : elle module la rugosité des finitions marquées
    // 'brillance' => true ci-dessous.
    //   defaut         : brillance donnant exactement la rugosité de base de 'finitions'. 100 = rendu poli net par défaut ;
    //                    un granit moins brillant se règle entre 0 (satiné) et 99. (Ancienne valeur : 50.)
    //                    model-viewer ne rend pas de reflets plus nets qu'une rugosité d'environ 0,05 : au-delà, rien ne change.
    //   facteur_miroir : rugosité = base × ce facteur pour une brillance au-dessus de 'defaut' (sans effet si defaut = 100) ;
    //   rugosite_mate  : rugosité atteinte à brillance 0 (0 = miroir … 1 = mat).
    'brillance' => [
        'defaut' => 100,
        'facteur_miroir' => 0.15,
        'rugosite_mate' => 0.55,
    ],

    // Rugosité par famille de matériau (0 = miroir, 1 = mat). Le premier préfixe qui correspond au nom du matériau est utilisé.
    // 'brillance' => true : la brillance du granit choisi s'applique (surfaces polies). La bouchardée reste mate.
    // Anciennes valeurs (reflets plus flous) : SEMELLE-P 0.30, BICOLOR 0.08, GRANIT 0.12.
    'finitions' => [
        'SEMELLE-B' => ['rugosite' => 0.85],                      // bouchardée
        'SEMELLE-P' => ['rugosite' => 0.10, 'brillance' => true], // plate
        'SEMELLE-R-P' => ['rugosite' => 0.10, 'brillance' => true], // à revers d'eau, polie
        'SEMELLE-R-B' => ['rugosite' => 0.85],                    // à revers d'eau, bouchardée
        'SEMELLE'   => ['rugosite' => 0.60],
        'BICOLOR'   => ['rugosite' => 0.03, 'brillance' => true], // soubassement noir poli
        'GRANIT'    => ['rugosite' => 0.04, 'brillance' => true], // poli
    ],
];
