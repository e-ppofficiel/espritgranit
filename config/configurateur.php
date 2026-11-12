<?php

/*
 * Réglages du configurateur 3D (rendu réaliste).
 *
 * Pour revenir EXACTEMENT à l'ancien rendu : 'rendu_realiste' => false
 * (pas d'éclairage studio, pas d'échelle de grain, pas de finitions).
 */
return [
    'rendu_realiste' => env('CONFIGURATEUR_RENDU_REALISTE', true),

    // Luminosité globale (exposition) de la scène 3D.
    'exposition' => 1.5,

    // Taille réelle (en cm) du carré photographié, quand le granit n'a pas de valeur propre (colonne granits.echantillon_cm).
    // Plus ce nombre est PETIT, plus le grain paraît FIN sur le monument.
    'echantillon_defaut_cm' => 30,

    // Rugosité par famille de matériau (0 = miroir, 1 = mat). Le premier préfixe qui correspond au nom du matériau est utilisé.
    'finitions' => [
        'SEMELLE-B' => ['rugosite' => 0.85], // bouchardée
        'SEMELLE-P' => ['rugosite' => 0.30], // plate
        'SEMELLE'   => ['rugosite' => 0.60],
        'BICOLOR'   => ['rugosite' => 0.08], // soubassement noir poli
        'GRANIT'    => ['rugosite' => 0.12], // poli
    ],
];
