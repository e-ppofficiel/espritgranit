@php
    $glbUrl = $monument->fichier_3d ? asset('storage/' . $monument->fichier_3d) : null;

    // Rendu réaliste (voir config/configurateur.php) : mettre 'rendu_realiste' à false pour retrouver
    // exactement l'ancien comportement (sans éclairage studio, sans échelle de grain, sans finitions).
    $renduRealiste = (bool) config('configurateur.rendu_realiste', true);
    $exposition = (float) config('configurateur.exposition', 1.5);
    $echantillonDefaut = (float) config('configurateur.echantillon_defaut_cm', 30);
    $finitions = config('configurateur.finitions', [
        'SEMELLE-B' => ['rugosite' => 0.85],
        'SEMELLE-P' => ['rugosite' => 0.1, 'brillance' => true],
        'SEMELLE-R-P' => ['rugosite' => 0.1, 'brillance' => true],
        'SEMELLE-R-B' => ['rugosite' => 0.85],
        'SEMELLE' => ['rugosite' => 0.6],
        'BICOLOR' => ['rugosite' => 0.03, 'brillance' => true],
        'GRANIT' => ['rugosite' => 0.04, 'brillance' => true],
    ]);
    // Rotation automatique du monument (le visiteur l'arrête / la relance avec le bouton « ⟳ 360° » ; son choix est mémorisé).
    // Réglages facultatifs dans config/configurateur.php (valeurs par défaut ci-dessous si absents) :
    //   'rotation_auto' => true (false : le monument ne tourne que si le visiteur clique sur « ⟳ 360° »),
    //   'rotation_auto_vitesse' => 12 (degrés par seconde),
    //   'rotation_auto_delai' => 4000 (millisecondes d'attente après une manipulation du visiteur avant de reprendre).
    $rotationAuto = (bool) config('configurateur.rotation_auto', true);
    $rotationVitesse = max(1.0, min(60.0, (float) config('configurateur.rotation_auto_vitesse', 12)));
    $rotationDelai = max(0, (int) config('configurateur.rotation_auto_delai', 4000));
    // Brillance par granit (0 à 100) : 'defaut' redonne exactement la rugosité de base de $finitions.
    $brillanceCfg = array_merge(
        ['defaut' => 100, 'facteur_miroir' => 0.15, 'rugosite_mate' => 0.55],
        (array) config('configurateur.brillance', [])
    );
    // Fonds (v22) : couleurs de fond et ambiances viennent de la rubrique « Fonds du configurateur » de l'admin
    // (table `fonds`) ; sans table ni ligne utilisable, repli sur config/configurateur.php (voir ConfigurateurFonds).
    $fonds = \App\Services\ConfigurateurFonds::charger();
    $fondsCouleurs = $fonds['couleurs'];
    $fondDefaut = $fonds['couleur_defaut'];
    $fondDefautInfos = $fondsCouleurs[0];
    foreach ($fondsCouleurs as $fondCouleurListe) {
        if ($fondCouleurListe['cle'] === $fondDefaut) {
            $fondDefautInfos = $fondCouleurListe;
            break;
        }
    }
    // Décor paysager 360° (interrupteur « Décor jardin ») : le paysage de l'ambiance choisie s'affiche en fond.
    $decorCfg = array_merge(['actif' => true, 'defaut' => false, 'hauteur_facteur' => 0.6, 'angle_camera' => 83], (array) config('configurateur.decor', []));

    // État relu depuis le code ?c=… de l'adresse (déjà revalidé côté serveur par la page) : null = ouverture normale.
    $etatInitialJs = is_array($etatInitial ?? null) ? $etatInitial : null;

    // Curseurs de réglage du granit : réservés aux administrateurs (la page admin passe :peut-regler="true").
    $peutReglerGranits = ($peutRegler ?? false) && $renduRealiste;

    // Ambiances proposées à tous les visiteurs (onglet « Fond ») : paysages dont les reflets apparaissent sur la pierre polie,
    // chacune avec sa propre exposition. Vide sans rendu réaliste. L'ambiance par défaut règle l'éclairage de départ.
    $ambiances = $fonds['ambiances'];
    $ambianceDefaut = $fonds['ambiance_defaut'];
    if ($ambianceDefaut) {
        $envHdr = $ambiances[$ambianceDefaut]['url'];
        $exposition = (float) $ambiances[$ambianceDefaut]['exposition'];
    } else {
        $envHdr = null;
    }
    $decorDisponible = array_filter($ambiances, fn ($a) => ! empty($a['decor'])) !== [];

    // Prix interactif (HT / TTC, département de livraison). Le serveur ne fournit que des montants finaux (coefficient
    // appliqué). Un souci de calcul (table absente, etc.) masque simplement le bloc : le configurateur reste utilisable.
    $prixInit = null;
    try {
        $prixInit = \App\Services\ConfigurateurPrix::donneesInitiales($monument, \Filament\Facades\Filament::auth()->user());
    } catch (\Throwable $e) {
        report($e);
    }
@endphp

<div wire:ignore>
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>

    <style>
        #config-wrapper {
            position: relative;
            width: 100%;
            height: 75vh;
            background-color: #0a0a0a;
            border-radius: 8px;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        /* La sidebar (320 px) est posée par-dessus la droite du wrapper : le viewer occupe seulement la
           zone visible à sa gauche. Le monument est ainsi centré dans ce qu'on voit, et le pivot de
           rotation (centre de la boîte englobante du GLB) est le monument lui-même, quelle que soit
           la taille ou la position du GLB dans son repère. */
        #config-monument-viewer {
            display: block;
            width: calc(100% - 320px);
            height: 100%;
            background-color: #0a0a0a;
            --poster-color: transparent;
        }
        #config-topbar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 16px 20px;
            color: #fff;
            z-index: 10;
            pointer-events: none;
        }
        /* Fond clair neutre (interrupteur « Fond clair », couleur de la config 'fond_clair_couleur') :
           le texte posé sur la scène passe en sombre. */
        #config-wrapper, #config-monument-viewer {
            transition: background-color 0.25s;
        }
        #config-wrapper.fond-perso,
        #config-wrapper.fond-perso #config-monument-viewer {
            background-color: var(--fond-clair, #0a0a0a);
        }
        #config-wrapper.fond-clair #config-topbar,
        #config-wrapper.fond-clair #config-selected-label {
            color: #111;
        }
        #config-wrapper.fond-clair #config-rotate-btn {
            background: rgba(0,0,0,0.05);
            border-color: rgba(0,0,0,0.25);
            color: #111;
        }
        #config-wrapper.fond-clair #config-rotate-btn:hover {
            background: rgba(0,0,0,0.12);
        }
        #config-wrapper.fond-clair #config-selected-swatch {
            border-color: rgba(0,0,0,0.4);
        }
        /* Décor paysager 360° (interrupteur « Décor jardin ») : le paysage remplace le fond uni, quel qu'il soit.
           Les textes posés sur la photo repassent en clair, avec une ombre pour rester lisibles sur un ciel clair. */
        #config-wrapper.avec-decor #config-topbar,
        #config-wrapper.avec-decor #config-selected-label {
            color: #fff;
            text-shadow: 0 1px 3px rgba(0,0,0,0.65);
        }
        #config-wrapper.avec-decor #config-rotate-btn {
            background: rgba(0,0,0,0.35);
            border-color: rgba(255,255,255,0.45);
            color: #fff;
        }
        #config-wrapper.avec-decor #config-rotate-btn:hover {
            background: rgba(0,0,0,0.55);
        }
        #config-wrapper.avec-decor #config-selected-swatch {
            border-color: rgba(255,255,255,0.6);
        }
        #config-model-name {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        #config-model-name span {
            opacity: 0.6;
            font-weight: 400;
            margin-right: 6px;
        }
        #config-rotate-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            cursor: pointer;
            pointer-events: auto;
        }
        #config-rotate-btn:hover {
            background: rgba(255,255,255,0.15);
        }
        /* Rotation automatique en cours : contour ambre (bouton « enfoncé »). */
        #config-wrapper #config-rotate-btn[aria-pressed="true"] {
            border-color: #d97706;
            box-shadow: 0 0 0 1px #d97706 inset;
        }
        #config-sidebar {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 320px;
            background: rgba(15,15,15,0.92);
            backdrop-filter: blur(6px);
            border-left: 1px solid rgba(255,255,255,0.08);
            display: flex;
            flex-direction: column;
            z-index: 10;
        }
        #config-main-tabs {
            display: flex;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .config-main-tab {
            flex: 1;
            text-align: center;
            padding: 14px 2px;
            font-size: 0.72rem;
            letter-spacing: 0.2px;
            color: rgba(255,255,255,0.35);
            cursor: not-allowed;
            border-bottom: 2px solid transparent;
        }
        .config-main-tab.active {
            color: #fff;
            border-bottom-color: #d97706;
            cursor: default;
        }
        /* Onglets utilisables (Monument, Fond) ; les autres restent grisés. */
        .config-main-tab[data-onglet] {
            cursor: pointer;
            color: rgba(255,255,255,0.6);
        }
        .config-main-tab[data-onglet]:hover { color: #fff; }
        .config-main-tab[data-onglet].active { cursor: default; color: #fff; }
        .config-main-tab[data-onglet]:focus-visible { outline: 2px solid #d97706; outline-offset: -2px; }
        #config-sub-tabs {
            display: flex;
            gap: 24px;
            padding: 16px 20px 12px;
        }
        .config-sub-tab {
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: rgba(255,255,255,0.3);
            cursor: not-allowed;
            padding-bottom: 4px;
        }
        .config-sub-tab.active {
            color: #fff;
            border-bottom: 2px solid #fff;
            cursor: default;
        }
        #config-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 4px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .config-option-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.8);
        }
        .config-option-row select {
            max-width: 60%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.76rem;
        }
        .config-option-row select option {
            background: #0a0a0a;
            color: #fff;
        }
        .config-toggle {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }
        .config-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .config-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.2);
            border-radius: 22px;
            transition: 0.2s;
        }
        .config-toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
            background-color: #fff;
            border-radius: 50%;
            transition: 0.2s;
        }
        .config-toggle input:checked + .config-toggle-slider {
            background-color: #d97706;
        }
        .config-toggle input:checked + .config-toggle-slider:before {
            transform: translateX(18px);
        }
        /* Onglet « Fond » (v22) : l'onglet Monument garde tout son contenu ; l'onglet Fond le remplace. */
        #config-fonds-panel {
            display: none;
            flex: 1;
            flex-direction: column;
            gap: 8px;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 12px 16px 14px;
        }
        #config-sidebar.onglet-fond > :not(#config-main-tabs):not(#config-fonds-panel) { display: none !important; }
        #config-sidebar.onglet-fond #config-fonds-panel { display: flex; }
        .config-fonds-titre {
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.4px;
            color: #fff;
            margin-top: 4px;
        }
        /* v22.1 : vignettes réduites (mêmes proportions) pour que l'onglet tienne sans défilement.
           minmax(0, 1fr) : une image ne peut plus élargir sa colonne (cause du débordement à droite). */
        .config-fonds-grille {
            display: grid;
            gap: 8px 10px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .config-fonds-grille.ambiances { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        /* Vignettes de fond en relief, comme celles des granits : voile (::before) posé sur la pastille. */
        .config-fond-vignette {
            position: relative;
            cursor: pointer;
            text-align: center;
            background: none;
            border: none;
            padding: 0;
            color: inherit;
            font: inherit;
            min-width: 0;
            transition: transform 0.15s ease;
        }
        .config-fond-vignette:hover { transform: translateY(-2px); }
        .config-fond-vignette:focus-visible { outline: 2px solid #d97706; outline-offset: 3px; border-radius: 10px; }
        .config-fond-pastille {
            display: block;
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 8px;
            border: 1.5px solid rgba(255,255,255,0.14);
            box-shadow: 0 5px 9px rgba(0,0,0,0.6), 0 1px 3px rgba(0,0,0,0.55);
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
            box-sizing: border-box;
        }
        .config-fonds-grille.ambiances .config-fond-pastille { aspect-ratio: 2 / 1; }
        .config-fond-vide {
            background: linear-gradient(135deg, #2b3340, #11151b);
        }
        .config-fond-vignette::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            aspect-ratio: 1;
            border-radius: 8px;
            pointer-events: none;
            background: radial-gradient(120% 120% at 28% 22%,
                rgba(255,255,255,0.24) 0%, rgba(255,255,255,0.06) 38%, rgba(0,0,0,0) 55%, rgba(0,0,0,0.34) 100%);
            box-shadow:
                inset 1px 1px 2px rgba(255,255,255,0.4),
                inset -1px -2px 3px rgba(0,0,0,0.6),
                inset 0 0 0 1px rgba(0,0,0,0.35);
        }
        .config-fonds-grille.ambiances .config-fond-vignette::before { aspect-ratio: 2 / 1; }
        .config-fond-vignette.selected .config-fond-pastille {
            border-color: #d97706;
            box-shadow: 0 0 0 1px rgba(217,119,6,0.55), 0 0 12px rgba(217,119,6,0.45), 0 5px 9px rgba(0,0,0,0.6);
        }
        .config-fond-vignette span.config-fond-nom {
            display: block;
            margin-top: 4px;
            font-size: 0.62rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .config-fond-vignette.selected span.config-fond-nom { color: #d97706; font-weight: 700; }
        .config-fonds-note {
            display: none;
            font-size: 0.68rem;
            line-height: 1.3;
            color: rgba(255,255,255,0.5);
        }
        #config-wrapper.avec-decor .config-fonds-note.decor-actif { display: block; }
        #config-fonds-panel .config-option-row { margin-top: 4px; }
        #config-granit-grid {
            flex: 1;
            overflow-y: auto;
            padding: 4px 20px 20px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-content: start;
        }
        /* Vignettes de granit en relief (v20) : voile posé par-dessus l'image (biseau clair en haut à gauche,
           arête sombre en bas à droite, reflet doux), ombre portée marquée, cadre doré à la sélection.
           Un box-shadow « inset » sur une <img> est masqué par l'image : d'où le pseudo-élément ::before. */
        .config-granit-swatch {
            position: relative;
            cursor: pointer;
            text-align: center;
            background: none;
            border: none;
            padding: 0;
            transition: transform 0.15s ease;
        }
        .config-granit-swatch img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid rgba(255, 255, 255, 0.14);
            display: block;
            box-shadow:
                0 9px 16px rgba(0, 0, 0, 0.65),
                0 2px 4px rgba(0, 0, 0, 0.55);
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .config-granit-swatch::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            aspect-ratio: 1;
            border-radius: 10px;
            pointer-events: none;
            background: radial-gradient(120% 120% at 28% 22%,
                rgba(255, 255, 255, 0.24) 0%,
                rgba(255, 255, 255, 0.06) 38%,
                rgba(0, 0, 0, 0) 55%,
                rgba(0, 0, 0, 0.34) 100%);
            box-shadow:
                inset 2px 2px 3px rgba(255, 255, 255, 0.4),
                inset -2px -3px 5px rgba(0, 0, 0, 0.6),
                inset 0 0 0 1px rgba(0, 0, 0, 0.35);
        }
        .config-granit-swatch:hover {
            transform: translateY(-2px);
        }
        .config-granit-swatch:hover img {
            box-shadow:
                0 13px 22px rgba(0, 0, 0, 0.7),
                0 3px 6px rgba(0, 0, 0, 0.55);
        }
        .config-granit-swatch.selected img {
            border-color: #d97706;
            box-shadow:
                0 0 0 1px rgba(217, 119, 6, 0.55),
                0 0 16px rgba(217, 119, 6, 0.45),
                0 9px 16px rgba(0, 0, 0, 0.65);
        }
        .config-granit-swatch.selected::before {
            box-shadow:
                inset 2px 2px 3px rgba(255, 255, 255, 0.3),
                inset -2px -3px 5px rgba(0, 0, 0, 0.5);
        }
        .config-granit-swatch span {
            display: block;
            margin-top: 6px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
            line-height: 1.2;
        }
        .config-granit-swatch.selected span {
            color: #d97706;
            font-weight: 700;
        }
        /* Supplément de prix sous le nom (« Base », « +90 € », « sur demande »). */
        .config-granit-prix {
            display: block;
            margin-top: 2px;
            font-size: 0.64rem;
            line-height: 1.2;
            color: rgba(255,255,255,0.45);
        }
        .config-granit-prix:empty { display: none; }
        .config-granit-swatch.selected .config-granit-prix {
            color: #d97706;
        }
        #config-selected-label {
            position: absolute;
            bottom: 16px;
            left: 20px;
            color: #fff;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 10;
        }
        /* Prix interactif (v21) : carte en haut à droite de la zone 3D, à gauche du panneau de 320 px. Sur fond sombre
           translucide quel que soit le fond choisi, donc lisible partout. Sur écran étroit : sous le nom du modèle. */
        #config-prix {
            position: absolute;
            top: 12px;
            right: 336px;
            z-index: 11;
            width: 340px;
            box-sizing: border-box;
            padding: 12px 14px;
            color: #fff;
            background: rgba(10, 10, 10, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            backdrop-filter: blur(6px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
            font-size: 0.78rem;
            line-height: 1.35;
            transition: opacity 0.15s;
        }
        #config-prix.calcul { opacity: 0.65; }
        @media (max-width: 1500px) {
            #config-prix { top: 56px; left: 20px; right: auto; }
        }
        #config-prix-montants {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        #config-prix-ht { font-size: 1.55rem; font-weight: 700; color: #d97706; letter-spacing: 0.3px; }
        #config-prix-ht small, #config-prix-ttc small { font-size: 0.7rem; font-weight: 600; opacity: 0.8; }
        #config-prix-ttc { font-size: 0.95rem; font-weight: 600; color: rgba(255,255,255,0.85); }
        #config-prix-message { margin-top: 2px; font-weight: 600; color: #fbbf24; }
        #config-prix-message:empty { display: none; }
        #config-prix-detail { margin-top: 6px; color: rgba(255,255,255,0.8); }
        #config-prix-detail div { display: flex; justify-content: space-between; gap: 10px; }
        #config-prix-detail div span:last-child { white-space: nowrap; font-variant-numeric: tabular-nums; }
        #config-prix-detail:empty { display: none; }
        #config-prix-detail div.prix-sous { margin-top: -1px; font-size: 0.68rem; color: rgba(255,255,255,0.5); }
        #config-prix-detail div.prix-demande { color: #fbbf24; font-weight: 600; }
        #config-prix-notes { margin-top: 6px; font-size: 0.7rem; font-style: italic; color: rgba(255,255,255,0.55); }
        #config-prix-notes:empty { display: none; }
        #config-prix-selecteurs { margin-top: 8px; display: flex; flex-direction: column; gap: 6px; }
        #config-prix-selecteurs label { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.7rem; color: rgba(255,255,255,0.55); }
        #config-prix-selecteurs select {
            width: 62%;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.22);
            color: #fff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.74rem;
        }
        #config-prix-selecteurs select option { background: #0a0a0a; color: #fff; }
        #config-prix-selecteurs select.a-choisir { border-color: #d97706; }
        /* Bouton plein écran : en bas à droite de la zone 3D (à gauche de la barre latérale de 320 px). */
        #config-fullscreen-btn {
            position: absolute;
            bottom: 16px;
            right: 336px;
            width: 38px;
            height: 38px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            z-index: 11;
            transition: background 0.2s;
        }
        #config-fullscreen-btn:hover { background: rgba(255,255,255,0.18); }
        #config-fullscreen-btn:focus-visible { outline: 2px solid #d97706; outline-offset: 2px; }
        #config-fullscreen-btn svg { width: 20px; height: 20px; display: block; }
        #config-fullscreen-btn .icone-reduire { display: none; }
        #config-wrapper.en-plein-ecran #config-fullscreen-btn .icone-agrandir { display: none; }
        #config-wrapper.en-plein-ecran #config-fullscreen-btn .icone-reduire { display: block; }
        #config-wrapper.fond-clair #config-fullscreen-btn {
            background: rgba(0,0,0,0.05);
            border-color: rgba(0,0,0,0.25);
            color: #111;
        }
        #config-wrapper.fond-clair #config-fullscreen-btn:hover { background: rgba(0,0,0,0.12); }
        #config-wrapper.avec-decor #config-fullscreen-btn {
            background: rgba(0,0,0,0.35);
            border-color: rgba(255,255,255,0.45);
            color: #fff;
        }
        #config-wrapper.avec-decor #config-fullscreen-btn:hover { background: rgba(0,0,0,0.55); }
        /* Plein écran natif : la scène occupe tout l'écran (le 75vh ne s'applique plus). Règles séparées,
           un sélecteur inconnu invaliderait toute la règle. */
        #config-wrapper:fullscreen { height: 100% !important; width: 100% !important; border-radius: 0; }
        #config-wrapper:-webkit-full-screen { height: 100% !important; width: 100% !important; border-radius: 0; }
        /* Repli (iPhone Safari, sans API plein écran sur un élément) : on simule en recouvrant la fenêtre. */
        #config-wrapper.pseudo-plein-ecran {
            position: fixed;
            top: 0; left: 0;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            border-radius: 0;
            z-index: 2147483000;
        }
        /* Bulle d'invitation au plein écran : au-dessus du bouton, à chaque ouverture, pendant quelques secondes. */
        #config-fullscreen-bulle {
            position: absolute;
            bottom: 64px;
            right: 336px;
            z-index: 12;
            padding: 8px 12px;
            font-family: inherit;
            font-size: 0.8rem;
            line-height: 1.2;
            white-space: nowrap;
            color: #fff;
            background: rgba(17,17,17,0.92);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.35);
            cursor: pointer;
            animation: eg-bulle-entree 0.35s ease-out;
        }
        #config-fullscreen-bulle[hidden] { display: none; }
        #config-fullscreen-bulle::after {
            content: '';
            position: absolute;
            bottom: -6px;
            right: 15px;
            width: 10px;
            height: 10px;
            background: rgb(17,17,17);
            border-right: 1px solid rgba(255,255,255,0.25);
            border-bottom: 1px solid rgba(255,255,255,0.25);
            transform: rotate(45deg);
        }
        @keyframes eg-bulle-entree {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            #config-fullscreen-bulle { animation: none; }
        }
        #config-selected-swatch {
            width: 14px;
            height: 14px;
            border-radius: 3px;
            border: 1px solid rgba(255,255,255,0.4);
        }
        #config-no-model {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #fff;
            opacity: 0.6;
        }
        #config-reglages {
            padding: 0 20px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            max-height: 60%;
            overflow-y: auto;
        }
        #config-reglages summary {
            cursor: pointer;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            padding: 2px 0 8px;
        }
        #config-reglages[open] summary {
            color: #d97706;
        }
        #config-reglages-nom {
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 10px;
        }
        .config-reglage {
            margin-bottom: 10px;
            font-size: 0.76rem;
            color: rgba(255,255,255,0.8);
        }
        .config-reglage-tete {
            display: flex;
            justify-content: space-between;
        }
        .config-reglage output {
            color: #d97706;
            font-variant-numeric: tabular-nums;
        }
        .config-reglage input[type=range] {
            width: 100%;
            accent-color: #d97706;
        }
        .config-reglage input[type=range]:disabled {
            opacity: 0.35;
        }
        .config-reglage select {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 0.76rem;
        }
        .config-reglage select option {
            background: #0a0a0a;
            color: #fff;
        }
        .config-reglage-aide {
            font-size: 0.66rem;
            color: rgba(255,255,255,0.4);
            line-height: 1.3;
        }
        #config-reglages-actions {
            display: flex;
            gap: 8px;
            margin-top: 4px;
        }
        #config-reglages-actions button {
            cursor: pointer;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.85);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
        }
        #config-reglages-actions button:hover:not(:disabled) {
            background: rgba(255,255,255,0.12);
        }
        #config-reglages-actions button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        #config-reglage-enregistrer {
            border-color: #d97706 !important;
            color: #d97706 !important;
        }
        #config-reglages-statut {
            margin-top: 8px;
            min-height: 1em;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
        }
        #config-reglages-statut.erreur {
            color: #f87171;
        }
        #config-reglages-statut.ok {
            color: #4ade80;
        }
        #config-semelle-type-section {
            padding: 4px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            transition: opacity 0.2s;
        }
        #config-semelle-type-section.disabled {
            opacity: 0.35;
            pointer-events: none;
        }
        .config-semelle-label {
            font-size: 0.7rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            margin: 0 0 8px;
        }
        #config-semelle-type-select,
        #config-semelle-dimensions {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        #config-semelle-dimensions {
            margin-top: 8px;
        }
        #config-semelle-type-select option,
        #config-semelle-dimensions option {
            background: #0a0a0a;
            color: #fff;
        }
    </style>

    <div id="config-wrapper" style="--fond-clair: {{ $fondDefautInfos['couleur'] }}" class="fond-perso{{ ! empty($fondDefautInfos['clair']) ? ' fond-clair' : '' }}">
        @if($glbUrl)
            <model-viewer id="config-monument-viewer"
                          src="{{ $glbUrl }}"
                          camera-controls
                          touch-action="pan-y"
                          shadow-intensity="1"
                          @if($envHdr)
                          environment-image="{{ $envHdr }}"
                          exposure="{{ $exposition }}"
                          tone-mapping="neutral"
                          @endif
                          min-camera-orbit="auto 15deg auto"
                          max-camera-orbit="auto 90deg auto"
                          camera-orbit="0deg 75deg 80%"
                          orbit-sensitivity="0.4"
                          @if($rotationAuto) auto-rotate @endif
                          auto-rotate-delay="{{ $rotationDelai }}"
                          rotation-per-second="{{ $rotationVitesse }}deg"
                          alt="{{ $monument->nom }}">
            </model-viewer>

            <div id="config-topbar">
                <div>
                    <div id="config-model-name"><span>Modèle :</span>{{ strtoupper($monument->nom) }}</div>
                </div>
                <button id="config-rotate-btn" type="button">⟳ 360°</button>
            </div>

            @if($prixInit)
                <div id="config-prix" aria-live="polite">
                    <div id="config-prix-montants">
                        <span id="config-prix-ht"></span>
                        <span id="config-prix-ttc"></span>
                    </div>
                    <div id="config-prix-message">Choisissez un granit pour afficher le prix.</div>
                    <div id="config-prix-detail"></div>
                    <div id="config-prix-notes"></div>
                    <div id="config-prix-selecteurs">
                        @if($prixInit['admin'])
                            <label>Prix pour l'agence
                                <select id="config-prix-agence" aria-label="Agence dont on affiche le prix">
                                    <option value="">Coefficient général</option>
                                    @foreach($prixInit['agences'] as $agencePrix)
                                        <option value="{{ $agencePrix['id'] }}">{{ $agencePrix['libelle'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label>Département de livraison
                            <select id="config-prix-departement" aria-label="Département de livraison">
                                <option value="">Choisir…</option>
                                @foreach($prixInit['departements'] as $departementPrix)
                                    <option value="{{ $departementPrix['id'] }}">{{ $departementPrix['libelle'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>
            @endif

            <div id="config-selected-label">
                <span id="config-selected-name">—</span>
                <span id="config-selected-swatch"></span>
            </div>

            <button id="config-fullscreen-btn" type="button" aria-label="Plein écran" title="Plein écran" aria-pressed="false">
                <svg class="icone-agrandir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/></svg>
                <svg class="icone-reduire" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h5V3M21 8h-5V3M3 16h5v5M21 16h-5v5"/></svg>
            </button>
            <button type="button" id="config-fullscreen-bulle" hidden>Cliquez pour le plein écran</button>
            {{-- Donne au JS l'accès à la page Livewire (enregistrement de la configuration ; toujours présent, pas seulement pour les admins). --}}
            <div x-data x-init="window.egWire = $wire" hidden></div>

            <div id="config-sidebar">
                <div id="config-main-tabs">
                    <div class="config-main-tab active" data-onglet="monument" role="tab" tabindex="0" aria-selected="true">Monument</div>
                    <div class="config-main-tab">Accessoires</div>
                    <div class="config-main-tab">Vases</div>
                    <div class="config-main-tab" data-onglet="fond" role="tab" tabindex="0" aria-selected="false">Fond</div>
                </div>
                <div id="config-sub-tabs">
                    <div class="config-sub-tab active">Granits</div>
                    <div class="config-sub-tab">Plantes</div>
                </div>
                <div id="config-options">
                    <div class="config-option-row">
                        <span>Soubassement noir</span>
                        <label class="config-toggle">
                            <input type="checkbox" id="config-bicolor-noir-toggle">
                            <span class="config-toggle-slider"></span>
                        </label>
                    </div>
                    <div class="config-option-row">
                        <span>Avec semelle</span>
                        <label class="config-toggle">
                            <input type="checkbox" id="config-semelle-toggle" checked>
                            <span class="config-toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div id="config-fonds-panel" role="tabpanel" aria-label="Fond du configurateur">
                    <div class="config-fonds-titre">Couleur de fond</div>
                    <div class="config-fonds-grille" id="config-fonds-couleurs">
                        @foreach($fondsCouleurs as $fondCouleur)
                            <button type="button" class="config-fond-vignette" data-type="couleur" data-cle="{{ $fondCouleur['cle'] }}" aria-pressed="false" title="{{ $fondCouleur['libelle'] }}">
                                <span class="config-fond-pastille" style="background: {{ $fondCouleur['couleur'] }}"></span>
                                <span class="config-fond-nom">{{ $fondCouleur['libelle'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="config-fonds-note decor-actif">La couleur réapparaît quand le décor est désactivé.</div>

                    @if(count($ambiances) > 1)
                        <div class="config-fonds-titre">Ambiance</div>
                        <div class="config-fonds-grille ambiances" id="config-fonds-ambiances">
                            @foreach($ambiances as $cleAmbiance => $ambiance)
                                <button type="button" class="config-fond-vignette" data-type="ambiance" data-cle="{{ $cleAmbiance }}" aria-pressed="false" title="{{ $ambiance['libelle'] }}{{ empty($ambiance['decor']) ? ' (reflets seuls, sans décor)' : '' }}">
                                    @if(! empty($ambiance['vignette']))
                                        <img class="config-fond-pastille" src="{{ $ambiance['vignette'] }}" alt="" loading="lazy">
                                    @else
                                        <span class="config-fond-pastille config-fond-vide"></span>
                                    @endif
                                    <span class="config-fond-nom">{{ $ambiance['libelle'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @if($decorDisponible)
                        <div class="config-option-row">
                            <span>Afficher en décor (paysage 360°)</span>
                            <label class="config-toggle">
                                <input type="checkbox" id="config-decor-toggle">
                                <span class="config-toggle-slider"></span>
                            </label>
                        </div>
                    @endif
                </div>

                @if($peutReglerGranits)
                    <details id="config-reglages">
                        <summary>Réglages du granit (admin)</summary>
                        {{-- Donne au JS du viewer l'accès à la page Livewire (appel de enregistrerReglagesGranit). --}}
                        <div x-data x-init="window.egWire = $wire" hidden></div>
                        <div id="config-reglages-nom">Aucun granit sélectionné</div>

                        <div class="config-reglage">
                            <div class="config-reglage-tete"><span>Taille du grain</span><output id="config-reglage-echantillon-val"></output></div>
                            <input type="range" id="config-reglage-echantillon" min="5" max="200" step="1" value="{{ $echantillonDefaut }}">
                            <div class="config-reglage-aide" id="config-reglage-echantillon-aide">Côté réel de l'échantillon : plus petit = grain plus fin.</div>
                        </div>
                        <div class="config-reglage">
                            <div class="config-reglage-tete"><span>Brillance</span><output id="config-reglage-brillance-val"></output></div>
                            <input type="range" id="config-reglage-brillance" min="0" max="100" step="1" value="{{ $brillanceCfg['defaut'] }}">
                            <div class="config-reglage-aide" id="config-reglage-brillance-aide">@if($brillanceCfg['defaut'] >= 100)0 = satiné · 100 = poli net (rendu par défaut).@else 0 = satiné · {{ $brillanceCfg['defaut'] }} = standard · 100 = miroir.@endif</div>
                        </div>
                        <div class="config-reglage">
                            <div class="config-reglage-tete"><span>Luminosité</span><output id="config-reglage-luminosite-val"></output></div>
                            <input type="range" id="config-reglage-luminosite" min="50" max="150" step="1" value="100">
                            <div class="config-reglage-aide">100 % = texture inchangée.</div>
                        </div>

                        <div id="config-reglages-actions">
                            <button type="button" id="config-reglage-enregistrer" disabled>Enregistrer pour ce granit</button>
                            <button type="button" id="config-reglage-standard" disabled>Valeurs standard</button>
                        </div>
                        <div id="config-reglages-statut"></div>
                    </details>
                @endif

                @if(($semelleTypes ?? collect())->isNotEmpty())
                    <div id="config-semelle-type-section">
                        <div class="config-semelle-label">Type de semelle</div>
                        <select id="config-semelle-type-select" aria-label="Type de semelle">
                            @foreach($semelleTypes as $type)
                                <option value="{{ $type->id }}"
                                        data-code="{{ $type->code }}"
                                        data-type-id="{{ $type->id }}"
                                        @if($type->pivot->is_default) selected @endif>
                                    {{ $type->code }} : {{ $type->label }}
                                </option>
                            @endforeach
                        </select>

                        @if(($semelleDimensions ?? collect())->isNotEmpty())
                            <select id="config-semelle-dimensions">
                                @foreach($semelleDimensions as $dimension)
                                    <option value="{{ $dimension->id }}"
                                            data-type="{{ $dimension->semelle_type_id }}"
                                            data-default="{{ $dimension->is_default ? '1' : '0' }}"
                                            @if($dimension->is_default) selected @endif>
                                        {{ $dimension->label ?: ($dimension->longueur . ' x ' . $dimension->largeur . ' cm') }}@if($dimension->is_default) (par défaut)@endif
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                @endif

                <div id="config-granit-grid">
                    @foreach($granits as $granit)
                        @if($granit->image)
                            @php
                                // Taille réelle (cm) de l'échantillon photographié ; colonne ajoutée par la migration du rendu réaliste.
                                $echantillon = array_key_exists('echantillon_cm', $granit->getAttributes())
                                    ? $granit->echantillon_cm
                                    : null;
                                // Brillance (0-100) et luminosité (%) : NULL = valeurs standard.
                                $brillance = array_key_exists('brillance', $granit->getAttributes())
                                    ? $granit->brillance
                                    : null;
                                $luminosite = array_key_exists('luminosite', $granit->getAttributes())
                                    ? $granit->luminosite
                                    : null;
                            @endphp
                            <button type="button"
                                    class="config-granit-swatch"
                                    data-id="{{ $granit->id }}"
                                    data-texture="{{ asset('storage/' . $granit->image) }}"
                                    data-echantillon="{{ $echantillon ?: $echantillonDefaut }}"
                                    data-brillance="{{ $brillance ?? '' }}"
                                    data-luminosite="{{ $luminosite ?? '' }}"
                                    data-nom="{{ $granit->nom_commercial }}">
                                <img src="{{ asset('storage/' . ($granit->image_vignette ?? $granit->image)) }}" alt="{{ $granit->nom_commercial }}">
                                <span>{{ $granit->nom_commercial }}</span>
                                <small class="config-granit-prix"></small>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        @else
            <div id="config-no-model">Aucun fichier 3D associé à ce monument.</div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modelViewer = document.querySelector('#config-monument-viewer');
            const swatches = document.querySelectorAll('.config-granit-swatch');
            const rotateBtn = document.querySelector('#config-rotate-btn');
            const selectedName = document.querySelector('#config-selected-name');
            const selectedSwatch = document.querySelector('#config-selected-swatch');
            const bicolorNoirToggle = document.querySelector('#config-bicolor-noir-toggle');
            const semelleToggle = document.querySelector('#config-semelle-toggle');

            if (!modelViewer) return;

            let currentTexture = null;

            // ==========================================
            // FOND BLANC / NOIR (choix mémorisé dans le navigateur du visiteur)
            // ==========================================
            const configWrapper = document.querySelector('#config-wrapper');

            // ==========================================
            // ÉTAT ENREGISTRÉ (code ?c=… de l'adresse, sinon mémoire du navigateur, sinon valeurs par défaut)
            // ==========================================
            const MONUMENT_ID = @json($monument->id ?? 0);
            const ETAT_INITIAL = @json($etatInitialJs);
            const PRIX_INIT = @json($prixInit);
            const CLE_ETAT = 'eg_configurateur_etat_' + MONUMENT_ID;
            const lireLocal = (cle) => { try { return window.localStorage.getItem(cle); } catch (erreur) { return null; } };
            const ecrireLocal = (cle, valeur) => { try { window.localStorage.setItem(cle, valeur); } catch (erreur) {} };
            // Granit, soubassement noir et semelle : propres à chaque monument (restaurés plus bas, au chargement du modèle).
            let etatDepart = ETAT_INITIAL;
            if (!etatDepart) {
                try { etatDepart = JSON.parse(lireLocal(CLE_ETAT) || 'null'); } catch (erreur) { etatDepart = null; }
                if (!etatDepart || typeof etatDepart !== 'object') etatDepart = null;
            }
            // Fond, décor et ambiance : communs à tous les monuments (mémoire existante). Un lien ?c=… les remplace avant leur initialisation.
            if (ETAT_INITIAL) {
                if (typeof ETAT_INITIAL.fo === 'string' && ETAT_INITIAL.fo !== '') ecrireLocal('eg_configurateur_fond', ETAT_INITIAL.fo);
                if (typeof ETAT_INITIAL.de === 'boolean') ecrireLocal('eg_configurateur_decor', ETAT_INITIAL.de ? '1' : '0');
                if (typeof ETAT_INITIAL.am === 'string') ecrireLocal('eg_configurateur_ambiance', ETAT_INITIAL.am);
            }

            // Fonds (v22) : couleurs et ambiances de l'admin ; le choix de chaque visiteur est mémorisé dans son navigateur.
            const FONDS_COULEURS = @json($fondsCouleurs);
            const FOND_DEFAUT = @json($fondDefaut);
            const AMBIANCE_DEFAUT = @json($ambianceDefaut);
            const CLE_FOND = 'eg_configurateur_fond';
            let fondCourant = FOND_DEFAUT;
            let ambianceCourante = AMBIANCE_DEFAUT;
            const marquerVignettes = () => {
                document.querySelectorAll('.config-fond-vignette').forEach((bouton) => {
                    const choisi = bouton.dataset.cle === (bouton.dataset.type === 'couleur' ? fondCourant : ambianceCourante);
                    bouton.classList.toggle('selected', choisi);
                    bouton.setAttribute('aria-pressed', choisi ? 'true' : 'false');
                });
            };
            const definirFond = (cle) => {
                const fond = FONDS_COULEURS.find((f) => f.cle === cle);
                if (!fond) return false;
                fondCourant = cle;
                configWrapper.style.setProperty('--fond-clair', fond.couleur);
                configWrapper.classList.add('fond-perso');
                configWrapper.classList.toggle('fond-clair', !!fond.clair);
                marquerVignettes();
                return true;
            };
            // Mémoire inconnue (fond désactivé ou supprimé depuis) : on garde le fond par défaut.
            const fondMemorise = lireLocal(CLE_FOND);
            if (fondMemorise) definirFond(fondMemorise);
            marquerVignettes();

            // ==========================================
            // AMBIANCE (paysage reflété sur la pierre polie ; choix mémorisé dans le navigateur du visiteur)
            // ==========================================
            const AMBIANCES = @json($ambiances);
            const CLE_AMBIANCE = 'eg_configurateur_ambiance';
            function definirAmbiance(cle) {
                const ambiance = AMBIANCES[cle];
                if (!ambiance) return false;
                ambianceCourante = cle;
                modelViewer.setAttribute('environment-image', ambiance.url);
                modelViewer.setAttribute('exposure', ambiance.exposition);
                marquerVignettes();
                // Une ambiance sans décor (Studio, ciel seul) : l'interrupteur « Décor jardin » repasse sur off.
                if (decorActif && !ambiance.decor) definirDecor(false);
                else appliquerDecor();
                return true;
            }

            // ==========================================
            // DÉCOR JARDIN 360° (le paysage de l'ambiance choisie en arrière-plan ; choix mémorisé dans le navigateur)
            // L'image n'est téléchargée qu'à l'activation de l'interrupteur. skybox-height pose le paysage sur un sol
            // (sans lui le monument semble flotter) : il se règle selon la taille du modèle, car les GLB n'ont pas la même échelle.
            // ==========================================
            const DECOR_FACTEUR_HAUTEUR = @json((float) $decorCfg['hauteur_facteur']);
            const DECOR_ANGLE_CAMERA = @json(max(15.0, min(90.0, (float) $decorCfg['angle_camera'])));
            const decorToggle = document.querySelector('#config-decor-toggle');
            const CLE_DECOR = 'eg_configurateur_decor';
            let decorActif = false;
            const hauteurSkybox = () => {
                let plusGrande = 0;
                try {
                    const d = modelViewer.getDimensions();
                    plusGrande = Math.max(d.x, d.y, d.z);
                } catch (erreur) { /* modèle pas encore chargé : réappliqué au chargement */ }
                return ((plusGrande > 0 ? plusGrande : 1) * DECOR_FACTEUR_HAUTEUR).toFixed(3) + 'm';
            };
            // Vu de haut (75°), le sol du panorama est un gazon étiré et flou ; près de l'horizontale on voit le paysage, ses arbres et le ciel.
            // À l'affichage du décor la caméra descend donc à DECOR_ANGLE_CAMERA (sans toucher à l'azimut ni au zoom), et revient à son
            // angle d'avant quand on l'éteint. Un visiteur déjà plus bas n'est pas déplacé.
            let decorAffiche = false;
            let angleAvantDecor = null;
            function orienterPourDecor(visible) {
                let o;
                try { o = modelViewer.getCameraOrbit(); } catch (erreur) { return; }
                const angle = o.phi * 180 / Math.PI;
                if (visible && angle < DECOR_ANGLE_CAMERA - 0.5) {
                    angleAvantDecor = angle;
                    modelViewer.cameraOrbit = o.theta + 'rad ' + DECOR_ANGLE_CAMERA + 'deg ' + o.radius + 'm';
                } else if (!visible && angleAvantDecor !== null) {
                    modelViewer.cameraOrbit = o.theta + 'rad ' + angleAvantDecor + 'deg ' + o.radius + 'm';
                    angleAvantDecor = null;
                }
            }
            function appliquerDecor() {
                const ambiance = AMBIANCES[ambianceCourante];
                const visible = !!(decorActif && ambiance && ambiance.decor);
                if (visible) {
                    modelViewer.setAttribute('skybox-height', hauteurSkybox());
                    if (modelViewer.getAttribute('skybox-image') !== ambiance.decor) modelViewer.setAttribute('skybox-image', ambiance.decor);
                } else {
                    modelViewer.removeAttribute('skybox-image');
                    // « 0m » (valeur par défaut de model-viewer) plutôt que retirer l'attribut : sans valeur, model-viewer lève une erreur interne.
                    modelViewer.setAttribute('skybox-height', '0m');
                }
                configWrapper.classList.toggle('avec-decor', visible);
                if (visible !== decorAffiche && modelViewer.loaded) {
                    decorAffiche = visible;
                    orienterPourDecor(visible);
                }
            }
            function definirDecor(actif) {
                decorActif = actif;
                if (decorToggle) decorToggle.checked = actif;
                appliquerDecor();
                try { window.localStorage.setItem(CLE_DECOR, actif ? '1' : '0'); } catch (erreur) {}
            }
            const premiereAmbianceAvecDecor = () => Object.keys(AMBIANCES).find((cle) => AMBIANCES[cle].decor) || null;
            // model-viewer ne recalcule la distance maximale de la caméra (plan lointain) que lorsque max-camera-orbit change.
            // Sans cela, un décor activé après le chargement du modèle est coupé : sol visible, ciel noir. On le force donc, une fois
            // le paysage chargé (événement environment-change), par une variation imperceptible (0,001°) de l'angle maximal.
            const MAX_ORBIT_A = 'auto 90deg auto';
            const MAX_ORBIT_B = 'auto 89.999deg auto';
            let minuteurPlanLointain = null;
            const planifierPlanLointain = () => {
                // Anti-rebond : plusieurs événements peuvent se suivre (éclairage puis décor) ; un seul ajustement, après le dernier.
                clearTimeout(minuteurPlanLointain);
                minuteurPlanLointain = setTimeout(() => {
                    if (!modelViewer.hasAttribute('skybox-image')) return;
                    modelViewer.setAttribute('max-camera-orbit', modelViewer.getAttribute('max-camera-orbit') === MAX_ORBIT_A ? MAX_ORBIT_B : MAX_ORBIT_A);
                }, 250);
            };
            modelViewer.addEventListener('environment-change', planifierPlanLointain);
            // Le chargement du seul décor ne déclenche pas environment-change : on écoute aussi la fin du chargement (progress à 100 %).
            modelViewer.addEventListener('progress', (e) => { if (e.detail && e.detail.totalProgress === 1) planifierPlanLointain(); });
            modelViewer.addEventListener('load', appliquerDecor);

            // Restauration des choix du visiteur : décor d'abord, puis ambiance (qui peut le désactiver si elle n'a pas de décor).
            try {
                decorActif = !!decorToggle && (window.localStorage.getItem(CLE_DECOR) ?? @json($decorCfg['defaut'] ? '1' : '0')) === '1';
            } catch (erreur) { decorActif = !!decorToggle && @json((bool) $decorCfg['defaut']); }
            if (Object.keys(AMBIANCES).length > 1) {
                const ambianceMemorisee = lireLocal(CLE_AMBIANCE);
                if (ambianceMemorisee && ambianceMemorisee !== AMBIANCE_DEFAUT) definirAmbiance(ambianceMemorisee);
            }
            // Clic sur une vignette de l'onglet « Fond » : couleur ou ambiance.
            document.querySelectorAll('.config-fond-vignette').forEach((bouton) => {
                bouton.addEventListener('click', () => {
                    const cle = bouton.dataset.cle;
                    const couleur = bouton.dataset.type === 'couleur';
                    if (cle === (couleur ? fondCourant : ambianceCourante)) return; // déjà le choix courant
                    if (couleur ? definirFond(cle) : definirAmbiance(cle)) {
                        ecrireLocal(couleur ? CLE_FOND : CLE_AMBIANCE, cle);
                        // Comme un interrupteur : l'enregistrement du lien partageable écoute les « change » qui remontent jusqu'au cadre.
                        bouton.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });
            // Onglets de la colonne de droite : Monument (granits, options) / Fond.
            const colonne = document.querySelector('#config-sidebar');
            const ongletsActifs = document.querySelectorAll('.config-main-tab[data-onglet]');
            const choisirOnglet = (onglet) => {
                if (colonne) colonne.classList.toggle('onglet-fond', onglet.dataset.onglet === 'fond');
                ongletsActifs.forEach((o) => {
                    o.classList.toggle('active', o === onglet);
                    o.setAttribute('aria-selected', o === onglet ? 'true' : 'false');
                });
            };
            ongletsActifs.forEach((onglet) => {
                onglet.addEventListener('click', () => choisirOnglet(onglet));
                onglet.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choisirOnglet(onglet); } });
            });
            // Décor mémorisé « actif » mais ambiance courante sans décor (ex. Studio) : on prend la première ambiance avec décor.
            if (decorActif && !(AMBIANCES[ambianceCourante] && AMBIANCES[ambianceCourante].decor)) {
                const cle = premiereAmbianceAvecDecor();
                if (cle) definirAmbiance(cle); else decorActif = false;
            }
            if (decorToggle) {
                decorToggle.checked = decorActif;
                appliquerDecor();
                decorToggle.addEventListener('change', () => {
                    if (decorToggle.checked && !(AMBIANCES[ambianceCourante] && AMBIANCES[ambianceCourante].decor)) {
                        const cle = premiereAmbianceAvecDecor();
                        if (!cle) { decorToggle.checked = false; return; }
                        definirAmbiance(cle);
                        try { window.localStorage.setItem(CLE_AMBIANCE, cle); } catch (erreur) {}
                    }
                    definirDecor(decorToggle.checked);
                });
            }

            // ==========================================
            // RENDU RÉALISTE (grain à l'échelle réelle + finitions)
            // ==========================================
            const RENDU_REALISTE = @json($renduRealiste);
            const ECHANTILLON_DEFAUT_CM = @json($echantillonDefaut);
            const FINITIONS = @json($finitions);
            const BRILLANCE = @json($brillanceCfg);

            // Réglages du granit sélectionné : NULL en base = valeurs standard (rendu inchangé).
            let brillanceCourante = BRILLANCE.defaut;
            let luminositeCourante = 100;

            const nombreOuNull = (valeur) => {
                if (valeur === undefined || valeur === null || valeur === '') return null;
                const n = parseFloat(valeur);
                return Number.isFinite(n) ? n : null;
            };

            // Appelé quand un granit est choisi : met à jour les curseurs d'admin (défini plus bas, si le panneau existe).
            let synchroniserReglages = () => {};

            // Brillance 0-100 → rugosité : à « defaut » on retrouve exactement la rugosité de base ;
            // au-dessus elle diminue jusqu'à base × facteur_miroir ; en dessous elle monte jusqu'à rugosite_mate.
            const rugositeSelonBrillance = (rugositeBase, brillance) => {
                const defaut = BRILLANCE.defaut;
                const b = Math.min(100, Math.max(0, brillance));
                if (b >= defaut) {
                    return rugositeBase * (1 - (1 - BRILLANCE.facteur_miroir) * (b - defaut) / Math.max(1, 100 - defaut));
                }
                const mate = Math.max(BRILLANCE.rugosite_mate, rugositeBase);
                return rugositeBase + (mate - rugositeBase) * (defaut - b) / Math.max(1, defaut);
            };

            // Un .glb passé dans « eg-glb optimiser » porte le repère eg_uv_metres : ses UV sont en mètres.
            // On ne lit que le début du fichier (en-tête + bloc JSON), jamais le .glb entier.
            const lireMarqueurGlb = async (url) => {
                try {
                    const rep = await fetch(url, { headers: { Range: 'bytes=0-262143' } });
                    if (!rep.ok || !rep.body) return false;
                    const lecteur = rep.body.getReader();
                    let recu = new Uint8Array(0);
                    let besoin = 20;
                    let json = null;
                    while (recu.length < besoin) {
                        const { done, value } = await lecteur.read();
                        if (done) break;
                        const suite = new Uint8Array(recu.length + value.length);
                        suite.set(recu);
                        suite.set(value, recu.length);
                        recu = suite;
                        if (besoin === 20 && recu.length >= 20) {
                            const vue = new DataView(recu.buffer);
                            if (vue.getUint32(0, true) !== 0x46546C67) break; // pas « glTF »
                            besoin = 20 + vue.getUint32(12, true);
                        }
                    }
                    lecteur.cancel().catch(() => {});
                    if (besoin > 20 && recu.length >= besoin) {
                        json = JSON.parse(new TextDecoder().decode(recu.subarray(20, besoin)));
                    }
                    return !!(json && json.extras && json.extras.eg_uv_metres);
                } catch (erreur) {
                    console.warn('[configurateur] lecture du repère UV impossible :', erreur);
                    return false;
                }
            };
            const uvMetresPret = RENDU_REALISTE ? lireMarqueurGlb(modelViewer.src) : Promise.resolve(false);

            // Une unité UV = 1 m : la texture (un échantillon de N cm) est répétée 100/N fois par mètre.
            let echelleCourante = null;

            const definirEchelle = (echantillonCm) => {
                const cm = parseFloat(echantillonCm) > 0 ? parseFloat(echantillonCm) : ECHANTILLON_DEFAUT_CM;
                const repetitions = 100 / cm;
                echelleCourante = { u: repetitions, v: repetitions };
            };

            // model-viewer remet l'échelle de la texture à 1×1 à CHAQUE setTexture (il reprend celle de
            // l'emplacement du matériau) : il faut donc la réappliquer après chaque série de setTexture.
            const reappliquerEchelle = () => {
                if (echelleCourante && currentTexture) {
                    currentTexture.sampler.setScale(echelleCourante);
                }
            };

            // Rugosité / métal par famille de matériau (le .glb optimisé ne porte plus de texture de rugosité).
            const appliquerFinitions = () => {
                const prefixes = Object.keys(FINITIONS);
                modelViewer.model.materials.forEach(material => {
                    if (!material.name || !material.pbrMetallicRoughness) return;
                    const nom = material.name.trim().toUpperCase();
                    const prefixe = prefixes.find(p => nom.startsWith(p.toUpperCase()));
                    if (!prefixe) return;
                    const finition = FINITIONS[prefixe];
                    const rugosite = finition.brillance
                        ? rugositeSelonBrillance(finition.rugosite, brillanceCourante)
                        : finition.rugosite;
                    material.pbrMetallicRoughness.setRoughnessFactor(rugosite);
                    material.pbrMetallicRoughness.setMetallicFactor(finition.metal || 0);
                });
            };

            const getMateriaux = (prefixe) => modelViewer.model.materials.filter(
                mat => mat.name && mat.name.trim().toUpperCase().startsWith(prefixe)
            );

            // Applique la luminosité courante aux matériaux déjà texturés (curseur d'admin) ; la transparence est conservée.
            const appliquerLuminosite = () => {
                if (!currentTexture) return;
                const lum = luminositeCourante / 100;
                const cibles = [...getMateriaux('GRANIT'), ...getMateriaux('SEMELLE')];
                if (!bicolorNoirToggle.checked) cibles.push(...getMateriaux('BICOLOR'));
                cibles.forEach(material => {
                    const pbr = material.pbrMetallicRoughness;
                    if (!pbr) return;
                    const alpha = (pbr.baseColorFactor || [1, 1, 1, 1])[3];
                    pbr.setBaseColorFactor([lum, lum, lum, alpha]);
                });
            };

            const appliquerTextureSur = (materiaux, texture) => {
                materiaux.forEach(material => {
                    if (material.pbrMetallicRoughness) {
                        // Luminosité du granit (100 % = texture inchangée) : facteur appliqué à la couleur de base.
                        const lum = luminositeCourante / 100;
                        material.pbrMetallicRoughness.setBaseColorFactor([lum, lum, lum, 1]);
                        if (material.pbrMetallicRoughness.baseColorTexture) {
                            material.pbrMetallicRoughness.baseColorTexture.setTexture(texture);
                        }
                    }
                });
            };

            // Couleur d'origine des matériaux passés au noir (clé : index du matériau dans le modèle),
            // pour pouvoir la restaurer quand aucun granit n'a encore été choisi.
            const couleursOrigine = new Map();

            const mettreNoir = (materiaux) => {
                materiaux.forEach(material => {
                    if (material.pbrMetallicRoughness) {
                        const cle = modelViewer.model.materials.indexOf(material);
                        if (!couleursOrigine.has(cle)) {
                            couleursOrigine.set(cle, (material.pbrMetallicRoughness.baseColorFactor || [1, 1, 1, 1]).slice());
                        }
                        material.pbrMetallicRoughness.setBaseColorFactor([0, 0, 0, 1]);
                    }
                });
            };

            const restaurerCouleurOrigine = (materiaux) => {
                materiaux.forEach(material => {
                    const origine = couleursOrigine.get(modelViewer.model.materials.indexOf(material));
                    if (origine && material.pbrMetallicRoughness) {
                        material.pbrMetallicRoughness.setBaseColorFactor(origine);
                    }
                });
            };

            const mettreAJourBicolor = () => {
                const materiauxBicolor = getMateriaux('BICOLOR');
                if (bicolorNoirToggle.checked) {
                    mettreNoir(materiauxBicolor);
                } else if (currentTexture) {
                    appliquerTextureSur(materiauxBicolor, currentTexture);
                    reappliquerEchelle();
                } else {
                    restaurerCouleurOrigine(materiauxBicolor);
                }
            };

            const semelleTypeSelect = document.querySelector('#config-semelle-type-select');
            const semelleDimSelect = document.querySelector('#config-semelle-dimensions');
            const semelleTypeSection = document.querySelector('#config-semelle-type-section');
            const codesTypesSemelle = semelleTypeSelect ? [...semelleTypeSelect.options].map(o => o.dataset.code.toUpperCase()) : [];
            const typeSemelleSelectionne = () => (semelleTypeSelect ? semelleTypeSelect.selectedOptions[0] || null : null);

            const mettreAJourSemelleVisibilite = () => {
                const materiauxSemelle = getMateriaux('SEMELLE');
                const semelleActivee = semelleToggle.checked;
                const typeSelectionne = typeSemelleSelectionne();
                const codeSelectionne = typeSelectionne ? typeSelectionne.dataset.code.toUpperCase() : null;

                materiauxSemelle.forEach(material => {
                    if (!material.pbrMetallicRoughness) return;

                    // Un monument peut avoir plusieurs types de semelle modélisés (SEMELLE-B, SEMELLE-P, SEMELLE-R-P, SEMELLE-R-B)
                    // ou un seul groupe générique "SEMELLE" (monuments sans variante de type).
                    const nomMajuscule = material.name.trim().toUpperCase();
                    const codeCorrespondant = codesTypesSemelle.find(code => nomMajuscule.startsWith(code));

                    let visible = semelleActivee;
                    if (semelleActivee && codeCorrespondant && codeSelectionne) {
                        visible = codeCorrespondant === codeSelectionne;
                    }

                    material.setAlphaMode(visible ? 'OPAQUE' : 'BLEND');
                    const facteurActuel = material.pbrMetallicRoughness.baseColorFactor || [1, 1, 1, 1];
                    material.pbrMetallicRoughness.setBaseColorFactor([
                        facteurActuel[0], facteurActuel[1], facteurActuel[2], visible ? 1 : 0
                    ]);
                });
            };

            const filtrerDimensionsParType = (code) => {
                if (!semelleDimSelect || !semelleTypeSelect) return;
                const optionType = [...semelleTypeSelect.options].find(o => o.dataset.code.toUpperCase() === code.toUpperCase());
                const typeId = optionType ? optionType.dataset.typeId : null;

                // Dimensions proposées : celles du type choisi, plus celles valables pour tous les types.
                // On présélectionne la dimension « par défaut » du type (à défaut, une dimension par défaut
                // valable pour tous les types, sinon la première).
                let premiereOptionVisible = null;
                let defautDuType = null;
                let defautGeneral = null;
                [...semelleDimSelect.options].forEach(option => {
                    const optionTypeId = option.dataset.type || '';
                    const correspond = optionTypeId === '' || optionTypeId === typeId;
                    option.hidden = !correspond;
                    option.disabled = !correspond;
                    if (!correspond) return;
                    if (!premiereOptionVisible) premiereOptionVisible = option;
                    if (option.dataset.default === '1') {
                        if (optionTypeId === typeId && !defautDuType) defautDuType = option;
                        if (optionTypeId === '' && !defautGeneral) defautGeneral = option;
                    }
                });

                const aSelectionner = defautDuType || defautGeneral || premiereOptionVisible;
                if (aSelectionner) {
                    semelleDimSelect.value = aSelectionner.value;
                }
            };

            // ==========================================
            // ROTATION AUTOMATIQUE : le monument tourne seul ; elle se met en pause quand le visiteur manipule le monument
            // (reprise après auto-rotate-delay, géré par model-viewer) et le bouton « ⟳ 360° » l'arrête / la relance pour de bon.
            // Choix mémorisé dans le navigateur ; sans choix mémorisé, pas de rotation pour qui a demandé « moins d'animations ».
            // ==========================================
            const ROTATION_AUTO = @json($rotationAuto);
            const CLE_ROTATION = 'eg_configurateur_rotation';
            let rotationActive = ROTATION_AUTO;
            let rotationMemorisee = null;
            try { rotationMemorisee = window.localStorage.getItem(CLE_ROTATION); } catch (erreur) { /* stockage indisponible : on garde le réglage par défaut */ }
            if (rotationMemorisee === '1') rotationActive = true;
            else if (rotationMemorisee === '0') rotationActive = false;
            else if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) rotationActive = false;

            function appliquerRotation(actif, memoriser) {
                rotationActive = actif;
                modelViewer.autoRotate = actif;
                modelViewer.toggleAttribute('auto-rotate', actif);
                if (rotateBtn) {
                    rotateBtn.setAttribute('aria-pressed', actif ? 'true' : 'false');
                    rotateBtn.title = actif ? 'Arrêter la rotation automatique' : 'Lancer la rotation automatique';
                }
                if (memoriser) {
                    try { window.localStorage.setItem(CLE_ROTATION, actif ? '1' : '0'); } catch (erreur) { /* sans conséquence */ }
                }
            }
            appliquerRotation(rotationActive, false);

            if (rotateBtn) {
                rotateBtn.addEventListener('click', () => {
                    appliquerRotation(!rotationActive, true);
                });
            }

            // ==========================================
            // RÉGLAGES DU GRANIT (administrateurs) : curseurs en direct + enregistrement
            // ==========================================
            const panneauReglages = document.querySelector('#config-reglages');
            if (panneauReglages && RENDU_REALISTE) {
                const ch = {
                    ech: document.querySelector('#config-reglage-echantillon'),
                    bri: document.querySelector('#config-reglage-brillance'),
                    lum: document.querySelector('#config-reglage-luminosite'),
                };
                const sortie = {
                    ech: document.querySelector('#config-reglage-echantillon-val'),
                    bri: document.querySelector('#config-reglage-brillance-val'),
                    lum: document.querySelector('#config-reglage-luminosite-val'),
                };
                const nomGranit = document.querySelector('#config-reglages-nom');
                const boutonEnregistrer = document.querySelector('#config-reglage-enregistrer');
                const boutonStandard = document.querySelector('#config-reglage-standard');
                const statut = document.querySelector('#config-reglages-statut');
                const DEFAUTS = { echantillon: ECHANTILLON_DEFAUT_CM, brillance: BRILLANCE.defaut, luminosite: 100 };

                const granitSelectionne = () => document.querySelector('.config-granit-swatch.selected');
                const message = (texte, classe) => { statut.textContent = texte; statut.className = classe || ''; };
                const afficher = () => {
                    sortie.ech.textContent = ch.ech.value + ' cm';
                    sortie.bri.textContent = ch.bri.value;
                    sortie.lum.textContent = ch.lum.value + ' %';
                };

                // Le grain et la brillance n'agissent que sur un .glb optimisé (UV en mètres, sans texture de rugosité).
                uvMetresPret.then(uvMetres => {
                    if (uvMetres) return;
                    ch.ech.disabled = true;
                    ch.bri.disabled = true;
                    document.querySelector('#config-reglage-echantillon-aide').textContent = 'Indisponible : le fichier 3D de ce monument n\'est pas optimisé.';
                    document.querySelector('#config-reglage-brillance-aide').textContent = 'Indisponible : le fichier 3D de ce monument n\'est pas optimisé.';
                });

                synchroniserReglages = () => {
                    const btn = granitSelectionne();
                    nomGranit.textContent = btn ? btn.dataset.nom : 'Aucun granit sélectionné';
                    [ch.ech, ch.bri, ch.lum].forEach(c => { if (!btn) c.disabled = true; });
                    boutonEnregistrer.disabled = !btn;
                    boutonStandard.disabled = !btn;
                    if (!btn) return;
                    uvMetresPret.then(uvMetres => {
                        ch.ech.disabled = !uvMetres;
                        ch.bri.disabled = !uvMetres;
                    });
                    ch.lum.disabled = false;
                    ch.ech.value = parseFloat(btn.dataset.echantillon) > 0 ? parseFloat(btn.dataset.echantillon) : DEFAUTS.echantillon;
                    ch.bri.value = brillanceCourante;
                    ch.lum.value = luminositeCourante;
                    afficher();
                    message('');
                };
                afficher();
                [ch.ech, ch.bri, ch.lum].forEach(c => { c.disabled = true; }); // activés dès qu'un granit est sélectionné

                ch.ech.addEventListener('input', async () => {
                    afficher();
                    if (!(await uvMetresPret)) return;
                    definirEchelle(ch.ech.value);
                    reappliquerEchelle();
                });
                ch.bri.addEventListener('input', async () => {
                    afficher();
                    brillanceCourante = parseFloat(ch.bri.value);
                    if (await uvMetresPret) appliquerFinitions();
                });
                ch.lum.addEventListener('input', () => {
                    afficher();
                    luminositeCourante = parseFloat(ch.lum.value);
                    appliquerLuminosite();
                });

                boutonStandard.addEventListener('click', () => {
                    ch.ech.value = DEFAUTS.echantillon;
                    ch.bri.value = DEFAUTS.brillance;
                    ch.lum.value = DEFAUTS.luminosite;
                    [ch.ech, ch.bri, ch.lum].forEach(c => { if (!c.disabled) c.dispatchEvent(new Event('input')); });
                    message('Valeurs standard rétablies (pas encore enregistrées).');
                });

                // Appel de la méthode Livewire de la page admin ($wire exposé par le petit bloc x-init du panneau).
                const appelerServeur = (methode, ...parametres) => {
                    if (window.egWire && typeof window.egWire.call === 'function') {
                        return window.egWire.call(methode, ...parametres);
                    }
                    return Promise.reject(new Error('liaison avec la page indisponible, rechargez la page'));
                };

                boutonEnregistrer.addEventListener('click', async () => {
                    const btn = granitSelectionne();
                    if (!btn) return;
                    // Une valeur standard est enregistrée « vide » : le granit suivra les valeurs par défaut de la config.
                    const valeurs = {
                        echantillon: parseFloat(ch.ech.value) === DEFAUTS.echantillon ? null : parseFloat(ch.ech.value),
                        brillance: parseFloat(ch.bri.value) === DEFAUTS.brillance ? null : parseFloat(ch.bri.value),
                        luminosite: parseFloat(ch.lum.value) === DEFAUTS.luminosite ? null : parseFloat(ch.lum.value),
                    };
                    // Sur un .glb non optimisé, grain et brillance n'ont pas été réglés ici : on ne touche pas à leurs valeurs en base.
                    const conserve = {
                        echantillon: nombreOuNull(btn.dataset.echantillon) === DEFAUTS.echantillon ? null : nombreOuNull(btn.dataset.echantillon),
                        brillance: nombreOuNull(btn.dataset.brillance),
                    };
                    if (ch.ech.disabled) valeurs.echantillon = conserve.echantillon;
                    if (ch.bri.disabled) valeurs.brillance = conserve.brillance;

                    boutonEnregistrer.disabled = true;
                    message('Enregistrement…');
                    try {
                        const reponse = await appelerServeur('enregistrerReglagesGranit', Number(btn.dataset.id), valeurs.echantillon, valeurs.brillance, valeurs.luminosite);
                        if (reponse && reponse.ok) {
                            btn.dataset.echantillon = valeurs.echantillon === null ? DEFAUTS.echantillon : valeurs.echantillon;
                            btn.dataset.brillance = valeurs.brillance === null ? '' : valeurs.brillance;
                            btn.dataset.luminosite = valeurs.luminosite === null ? '' : valeurs.luminosite;
                            message('Enregistré pour « ' + btn.dataset.nom + ' ».', 'ok');
                        } else {
                            message((reponse && reponse.message) || 'Enregistrement refusé.', 'erreur');
                        }
                    } catch (erreur) {
                        message('Erreur : ' + (erreur && erreur.message ? erreur.message : erreur), 'erreur');
                    } finally {
                        boutonEnregistrer.disabled = !granitSelectionne();
                    }
                });
            }

            modelViewer.addEventListener('load', () => {
                uvMetresPret.then(uvMetres => {
                    if (!uvMetres) return;
                    appliquerFinitions();
                    // Le .glb optimisé n'a plus de texture embarquée : on sélectionne un premier granit
                    // pour ne pas afficher un monument blanc au chargement.
                    if (!document.querySelector('.config-granit-swatch.selected') && swatches[0]) {
                        // Granit mémorisé (ou du lien ?c=…) s'il existe encore, sinon le premier.
                        const granitDepart = etatDepart && etatDepart.g != null
                            ? [...swatches].find(s => s.dataset.id === String(etatDepart.g))
                            : null;
                        (granitDepart || swatches[0]).click();
                    }
                });

                swatches.forEach(swatch => {
                    swatch.addEventListener('click', async (event) => {
                        const btn = event.currentTarget;
                        const texturePath = btn.dataset.texture;
                        const nom = btn.dataset.nom;

                        currentTexture = await modelViewer.createTexture(texturePath);
                        if (await uvMetresPret) {
                            definirEchelle(btn.dataset.echantillon);
                        }
                        if (RENDU_REALISTE) {
                            const brillance = nombreOuNull(btn.dataset.brillance);
                            const luminosite = nombreOuNull(btn.dataset.luminosite);
                            brillanceCourante = brillance === null ? BRILLANCE.defaut : brillance;
                            luminositeCourante = luminosite === null ? 100 : Math.min(150, Math.max(50, luminosite));
                        }

                        appliquerTextureSur(getMateriaux('GRANIT'), currentTexture);
                        appliquerTextureSur(getMateriaux('SEMELLE'), currentTexture);
                        mettreAJourBicolor();
                        reappliquerEchelle();
                        mettreAJourSemelleVisibilite();
                        if (await uvMetresPret) {
                            appliquerFinitions();
                        }

                        swatches.forEach(s => s.classList.remove('selected'));
                        btn.classList.add('selected');
                        selectedName.textContent = nom;
                        selectedSwatch.style.backgroundImage = `url(${texturePath})`;
                        selectedSwatch.style.backgroundSize = 'cover';
                        synchroniserReglages();
                        configWrapper.dispatchEvent(new CustomEvent('eg:granit-choisi'));
                    });
                });

                bicolorNoirToggle.addEventListener('change', mettreAJourBicolor);
                semelleToggle.addEventListener('change', () => {
                    mettreAJourSemelleVisibilite();
                    if (semelleTypeSection) {
                        semelleTypeSection.classList.toggle('disabled', !semelleToggle.checked);
                    }
                });

                if (semelleTypeSelect && semelleTypeSelect.options.length > 0) {
                    semelleTypeSelect.addEventListener('change', () => {
                        const option = typeSemelleSelectionne();
                        mettreAJourSemelleVisibilite();
                        if (option) filtrerDimensionsParType(option.dataset.code);
                    });

                    // État initial : applique le type par défaut (ou le premier) au chargement
                    const optionInitiale = typeSemelleSelectionne() || semelleTypeSelect.options[0];
                    if (optionInitiale) {
                        optionInitiale.selected = true;
                        filtrerDimensionsParType(optionInitiale.dataset.code);
                    }
                    if (semelleTypeSection) {
                        semelleTypeSection.classList.toggle('disabled', !semelleToggle.checked);
                    }
                }

                // Restauration du soubassement noir et de la semelle (lien ?c=… ou mémoire du navigateur).
                if (etatDepart) {
                    if (typeof etatDepart.sb === 'boolean') bicolorNoirToggle.checked = etatDepart.sb;
                    if (typeof etatDepart.se === 'boolean') semelleToggle.checked = etatDepart.se;
                    if (semelleTypeSelect && etatDepart.st != null) {
                        const optionType = [...semelleTypeSelect.options].find(o => o.value === String(etatDepart.st));
                        if (optionType) {
                            optionType.selected = true;
                            filtrerDimensionsParType(optionType.dataset.code);
                        }
                    }
                    if (semelleDimSelect && etatDepart.sd != null) {
                        const optionDim = [...semelleDimSelect.options].find(o => o.value === String(etatDepart.sd) && !o.disabled);
                        if (optionDim) semelleDimSelect.value = optionDim.value;
                    }
                    if (semelleTypeSection) {
                        semelleTypeSection.classList.toggle('disabled', !semelleToggle.checked);
                    }
                }

                mettreAJourSemelleVisibilite();
            });

            // ── Enregistrement de l'état : mémoire du navigateur tout de suite, serveur (code ?c=…) après une courte pause ──
            (function () {
                let pret = false;       // devient vrai une fois l'état d'ouverture appliqué : avant, on n'enregistre rien
                let minuterie = null;
                let dernierJson = null; // dernier état connu du serveur (évite les envois inutiles)

                const etatCourant = () => {
                    const granit = document.querySelector('.config-granit-swatch.selected');
                    const entier = (valeur) => { const n = parseInt(valeur, 10); return Number.isFinite(n) ? n : null; };
                    return {
                        g: granit ? entier(granit.dataset.id) : null,
                        sb: !!bicolorNoirToggle.checked,
                        se: !!semelleToggle.checked,
                        st: semelleTypeSelect ? entier(semelleTypeSelect.value) : null,
                        sd: semelleDimSelect ? entier(semelleDimSelect.value) : null,
                        fo: FONDS_COULEURS.length ? fondCourant : null,
                        de: decorToggle ? !!decorToggle.checked : null,
                        am: Object.keys(AMBIANCES).length > 1 ? ambianceCourante : null,
                    };
                };

                const majAdresse = (code) => {
                    try {
                        const url = new URL(window.location.href);
                        if (url.searchParams.get('c') === code) return;
                        url.searchParams.set('c', code);
                        window.history.replaceState(window.history.state, '', url.toString());
                    } catch (erreur) { /* adresse non modifiable : le configurateur reste utilisable */ }
                };

                const envoyer = async () => {
                    const etat = etatCourant();
                    const json = JSON.stringify(etat);
                    if (json === dernierJson) return;
                    if (!window.egWire || typeof window.egWire.call !== 'function') return;
                    try {
                        const code = await window.egWire.call('enregistrerConfiguration', etat);
                        if (typeof code === 'string' && /^[0-9a-f]{12,32}$/.test(code)) {
                            dernierJson = json;
                            majAdresse(code);
                        }
                    } catch (erreur) { /* enregistrement impossible : on réessaiera au prochain changement */ }
                };

                const planifier = () => {
                    if (!pret) return;
                    const etat = etatCourant();
                    ecrireLocal(CLE_ETAT, JSON.stringify({ g: etat.g, sb: etat.sb, se: etat.se, st: etat.st, sd: etat.sd }));
                    clearTimeout(minuterie);
                    minuterie = setTimeout(envoyer, 900);
                };

                const demarrer = () => {
                    if (pret) return;
                    pret = true;
                    // Ouvert par un lien ?c=… : le serveur connaît déjà cet état. Sinon on l'enregistre pour obtenir le code.
                    if (ETAT_INITIAL) dernierJson = JSON.stringify(etatCourant());
                    planifier();
                };

                // Premier choix de granit terminé = état d'ouverture en place. Garde-fou si aucun granit ne peut être choisi.
                configWrapper.addEventListener('eg:granit-choisi', () => { demarrer(); planifier(); });
                modelViewer.addEventListener('load', () => { setTimeout(demarrer, 6000); });
                // Tout changement d'un interrupteur ou d'une liste (soubassement, semelle, type, dimension, fond, décor, ambiance).
                configWrapper.addEventListener('change', planifier);
            })();

            // ── Prix interactif : le serveur renvoie des montants finaux (HT, TTC, détail) pour la configuration affichée ──
            (function () {
                const bloc = document.querySelector('#config-prix');
                if (!bloc || !PRIX_INIT) return;

                const elHt = bloc.querySelector('#config-prix-ht');
                const elTtc = bloc.querySelector('#config-prix-ttc');
                const elMessage = bloc.querySelector('#config-prix-message');
                const elDetail = bloc.querySelector('#config-prix-detail');
                const elNotes = bloc.querySelector('#config-prix-notes');
                const selDepartement = bloc.querySelector('#config-prix-departement');
                const selAgence = bloc.querySelector('#config-prix-agence');
                const CLE_DEPARTEMENT = 'eg_configurateur_departement';
                const CLE_AGENCE_PRIX = 'eg_configurateur_agence_prix';

                const formatEntier = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                const formatCentimes = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const euro = (montant) => (Number.isInteger(montant) ? formatEntier : formatCentimes).format(montant).replace(/ | /g, ' ') + ' €';
                const nombre = (valeur) => (typeof valeur === 'number' && Number.isFinite(valeur) ? valeur : null);

                // Texte sous chaque vignette de granit : « Base », « +90 € » ou « sur demande ».
                const majSupplements = (liste) => {
                    if (!Array.isArray(liste)) return;
                    const parId = new Map(liste.map(l => [String(l.id), nombre(l.montant)]));
                    swatches.forEach(swatch => {
                        const cible = swatch.querySelector('.config-granit-prix');
                        if (!cible) return;
                        // Granit sans ligne de prix pour ce monument : « sur demande » (comme un granit sans prix défini).
                        const montant = parId.has(swatch.dataset.id) ? parId.get(swatch.dataset.id) : null;
                        cible.textContent = montant === null ? 'sur demande' : (montant > 0 ? '+' + euro(montant) : 'Base');
                    });
                };
                majSupplements(PRIX_INIT.supplements);

                // Choix mémorisés dans le navigateur (jamais dans le lien partagé).
                const restaurer = (select, cle) => {
                    if (!select) return;
                    const memorise = lireLocal(cle);
                    if (memorise === null) return;
                    const option = [...select.options].find(o => o.value === memorise);
                    if (option) select.value = option.value;
                };
                restaurer(selDepartement, CLE_DEPARTEMENT);
                restaurer(selAgence, CLE_AGENCE_PRIX);

                const ligne = (libelle, montant) => {
                    const div = document.createElement('div');
                    const gauche = document.createElement('span');
                    const droite = document.createElement('span');
                    gauche.textContent = libelle;
                    droite.textContent = montant;
                    div.append(gauche, ' ', droite);
                    return div;
                };
                const avecUnite = (element, texte, unite) => {
                    element.textContent = texte;
                    const petit = document.createElement('small');
                    petit.textContent = ' ' + unite;
                    element.append(petit);
                };

                const afficher = (rep) => {
                    elHt.textContent = '';
                    elTtc.textContent = '';
                    elMessage.textContent = '';
                    elDetail.textContent = '';
                    elNotes.textContent = '';
                    if (selDepartement) selDepartement.classList.remove('a-choisir');

                    if (!rep || rep.ok !== true) {
                        elMessage.textContent = 'Prix indisponible pour le moment.';
                        return;
                    }
                    majSupplements(rep.supplements);

                    if (rep.statut === 'granit_absent') { elMessage.textContent = 'Choisissez un granit pour afficher le prix.'; return; }
                    if (rep.statut === 'granit_sans_prix') { elMessage.textContent = 'Prix sur demande pour ce granit.'; return; }

                    const lignes = Array.isArray(rep.lignes) ? rep.lignes : [];
                    const maj = (texte) => String(texte).charAt(0).toUpperCase() + String(texte).slice(1);
                    const base = nombre(rep.base);
                    // 1) Le granit choisi, avec son prix complet ; en dessous, d'où il vient : base + supplément.
                    const granit = lignes.find(l => l.cle === 'granit');
                    const supplementGranit = granit ? nombre(granit.montant) : null;
                    if (granit && base !== null && supplementGranit !== null) {
                        elDetail.append(ligne('Granit ' + granit.libelle, euro(base + supplementGranit)));
                        if (supplementGranit > 0) {
                            const origine = ligne('base ' + euro(base) + ' + ' + euro(supplementGranit), '');
                            origine.classList.add('prix-sous');
                            elDetail.append(origine);
                        }
                    }
                    // 2) Les options : soubassement noir, semelle (ou « sur demande »), transport.
                    lignes.forEach(l => {
                        if (l.cle === 'granit') return;
                        const montant = nombre(l.montant);
                        if (montant !== null) {
                            elDetail.append(ligne('+ ' + maj(l.libelle), '+' + euro(montant)));
                        } else if (l.sur_demande) {
                            const demande = ligne('+ ' + maj(l.libelle), 'sur demande');
                            demande.classList.add('prix-demande');
                            elDetail.append(demande);
                        }
                    });

                    if (rep.statut === 'departement_manquant') {
                        elMessage.textContent = 'Sélectionnez un département de livraison pour afficher le total.';
                        if (selDepartement) selDepartement.classList.add('a-choisir');
                    } else if (nombre(rep.total_ht) !== null) {
                        avecUnite(elHt, euro(rep.total_ht), 'HT');
                        if (nombre(rep.total_ttc) !== null) avecUnite(elTtc, euro(rep.total_ttc), 'TTC');
                        if (rep.semelle_sur_demande) elMessage.textContent = 'Total hors semelle (prix sur demande).';
                    }

                    const notes = Array.isArray(rep.notes) ? rep.notes.slice() : [];
                    if (nombre(rep.coefficient) !== null) notes.push('Coefficient appliqué : ×' + String(rep.coefficient).replace('.', ','));
                    elNotes.textContent = notes.join(' ');
                };

                const etatPrix = () => {
                    const granit = document.querySelector('.config-granit-swatch.selected');
                    const entier = (valeur) => { const n = parseInt(valeur, 10); return Number.isFinite(n) ? n : null; };
                    return {
                        g: granit ? entier(granit.dataset.id) : null,
                        sb: !!bicolorNoirToggle.checked,
                        se: !!semelleToggle.checked,
                        sd: semelleDimSelect ? entier(semelleDimSelect.value) : null,
                    };
                };

                let minuterie = null;
                let sequence = 0;
                let demarre = false;

                const calculer = async () => {
                    demarre = true;
                    const numero = ++sequence;
                    if (!window.egWire || typeof window.egWire.call !== 'function') { afficher(null); return; }
                    bloc.classList.add('calcul');
                    try {
                        const departement = selDepartement && selDepartement.value ? parseInt(selDepartement.value, 10) : null;
                        const agence = selAgence && selAgence.value ? parseInt(selAgence.value, 10) : null;
                        const rep = await window.egWire.call('calculerPrixConfiguration', etatPrix(), departement, agence);
                        if (numero === sequence) afficher(rep);
                    } catch (erreur) {
                        if (numero === sequence) afficher(null);
                    } finally {
                        if (numero === sequence) bloc.classList.remove('calcul');
                    }
                };
                const planifier = (delai) => { clearTimeout(minuterie); minuterie = setTimeout(calculer, delai); };

                // Premier granit choisi = configuration d'ouverture en place ; sinon, garde-fou après le chargement du modèle.
                configWrapper.addEventListener('eg:granit-choisi', () => planifier(demarre ? 300 : 0));
                modelViewer.addEventListener('load', () => { setTimeout(() => { if (!demarre) planifier(0); }, 6500); });

                // Soubassement, semelle (case, type, dimension), département, agence : recalcul après une courte pause.
                configWrapper.addEventListener('change', (evenement) => {
                    const cible = evenement.target;
                    if (!cible || !cible.matches) return;
                    if (cible === selDepartement) ecrireLocal(CLE_DEPARTEMENT, cible.value);
                    if (cible === selAgence) ecrireLocal(CLE_AGENCE_PRIX, cible.value);
                    if (cible.matches('#config-bicolor-noir-toggle, #config-semelle-toggle, #config-semelle-type-select, #config-semelle-dimensions, #config-prix-departement, #config-prix-agence')) {
                        planifier(300);
                    }
                });
            })();

            // ── Plein écran (toute la scène : visualiseur + barre latérale) ──
            (function () {
                const btn = document.querySelector('#config-fullscreen-btn');
                if (!btn || !configWrapper) return;

                let pseudo = false;

                // Bulle d'invitation : affichée à chaque ouverture, quelques secondes. Tant qu'elle est là, un simple clic
                // (sans glisser) sur la scène 3D ouvre le vrai plein écran, une seule fois. Le navigateur exige ce geste.
                const bulle = document.querySelector('#config-fullscreen-bulle');
                let arme = false;
                let minuterieBulle = null;
                let departClic = null;

                function desarmer() {
                    arme = false;
                    clearTimeout(minuterieBulle);
                    if (bulle) bulle.hidden = true;
                }
                function armer() {
                    if (!bulle || arme || actif()) return;
                    arme = true;
                    bulle.hidden = false;
                    minuterieBulle = setTimeout(desarmer, 8000);
                }

                function elementPleinEcran() {
                    return document.fullscreenElement || document.webkitFullscreenElement || null;
                }
                function actif() {
                    return pseudo || elementPleinEcran() === configWrapper;
                }
                function rafraichir() {
                    const on = actif();
                    if (on) desarmer();
                    configWrapper.classList.toggle('en-plein-ecran', on);
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    const libelle = on ? 'Quitter le plein écran' : 'Plein écran';
                    btn.setAttribute('aria-label', libelle);
                    btn.title = libelle;
                    // Le visualiseur doit recalculer sa taille (la fenêtre a changé).
                    window.dispatchEvent(new Event('resize'));
                }
                function entrerPseudo() {
                    pseudo = true;
                    configWrapper.classList.add('pseudo-plein-ecran');
                    document.documentElement.style.overflow = 'hidden';
                    rafraichir();
                }
                function sortirPseudo() {
                    pseudo = false;
                    configWrapper.classList.remove('pseudo-plein-ecran');
                    document.documentElement.style.overflow = '';
                    rafraichir();
                }
                function entrer() {
                    const demander = configWrapper.requestFullscreen || configWrapper.webkitRequestFullscreen;
                    if (!demander) { entrerPseudo(); return; }
                    try {
                        const r = demander.call(configWrapper);
                        if (r && typeof r.catch === 'function') r.catch(entrerPseudo);
                    } catch (e) {
                        entrerPseudo();
                    }
                }
                function sortir() {
                    if (pseudo) { sortirPseudo(); return; }
                    const quitter = document.exitFullscreen || document.webkitExitFullscreen;
                    if (quitter) {
                        try {
                            const r = quitter.call(document);
                            if (r && typeof r.catch === 'function') r.catch(function () {});
                        } catch (e) {}
                    }
                }

                btn.addEventListener('click', function () {
                    desarmer();
                    if (actif()) sortir(); else entrer();
                });

                if (bulle) {
                    bulle.addEventListener('click', function () {
                        desarmer();
                        if (!actif()) entrer();
                    });
                    modelViewer.addEventListener('pointerdown', function (e) {
                        departClic = { x: e.clientX, y: e.clientY };
                    });
                    modelViewer.addEventListener('click', function (e) {
                        if (!arme) return;
                        // Un glissement (rotation du monument) n'est pas un clic.
                        if (departClic && Math.hypot(e.clientX - departClic.x, e.clientY - departClic.y) > 6) return;
                        desarmer();
                        if (!actif()) entrer();
                    });
                    // La bulle apparaît quand le monument est affiché (ou au bout de 3 s si le chargement traîne).
                    let bulleProgrammee = false;
                    const programmerBulle = () => {
                        if (bulleProgrammee) return;
                        bulleProgrammee = true;
                        setTimeout(armer, 600);
                    };
                    modelViewer.addEventListener('load', programmerBulle);
                    setTimeout(programmerBulle, 3000);
                }
                document.addEventListener('fullscreenchange', rafraichir);
                document.addEventListener('webkitfullscreenchange', rafraichir);
                // Le mode simulé n'a pas de touche Échap native.
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && pseudo) sortirPseudo();
                });
            })();
        });
    </script>

    {{-- Traceur de diagnostic : visible uniquement avec ?debug=1 et un role admin --}}
    @include('components.configurateur.debug-panel')
</div>
