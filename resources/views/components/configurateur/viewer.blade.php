@php
    $glbUrl = $monument->fichier_3d ? asset('storage/' . $monument->fichier_3d) : null;

    // Rendu réaliste (voir config/configurateur.php) : mettre 'rendu_realiste' à false pour retrouver
    // exactement l'ancien comportement (sans éclairage studio, sans échelle de grain, sans finitions).
    $renduRealiste = (bool) config('configurateur.rendu_realiste', true);
    // Éclairage : 'contraste' (reflets nets) ou 'studio' (doux, ancien) ; repli sur studio.hdr si le fichier manque.
    $envFichiers = (array) config('configurateur.environnements', [
        'studio' => 'configurateur/studio.hdr',
        'contraste' => 'configurateur/studio-contraste.hdr',
    ]);
    $envChemin = $envFichiers[config('configurateur.environnement', 'contraste')] ?? 'configurateur/studio.hdr';
    if (! file_exists(public_path($envChemin))) {
        $envChemin = 'configurateur/studio.hdr';
    }
    $envHdr = ($renduRealiste && file_exists(public_path($envChemin)))
        ? asset($envChemin)
        : null;
    $exposition = (float) config('configurateur.exposition', 1.5);
    $echantillonDefaut = (float) config('configurateur.echantillon_defaut_cm', 30);
    $finitions = config('configurateur.finitions', [
        'SEMELLE-B' => ['rugosite' => 0.85],
        'SEMELLE-P' => ['rugosite' => 0.1, 'brillance' => true],
        'SEMELLE' => ['rugosite' => 0.6],
        'BICOLOR' => ['rugosite' => 0.03, 'brillance' => true],
        'GRANIT' => ['rugosite' => 0.04, 'brillance' => true],
    ]);
    // Brillance par granit (0 à 100) : 'defaut' redonne exactement la rugosité de base de $finitions.
    $brillanceCfg = array_merge(
        ['defaut' => 100, 'facteur_miroir' => 0.15, 'rugosite_mate' => 0.55],
        (array) config('configurateur.brillance', [])
    );
    $fondDefaut = config('configurateur.fond_defaut', 'noir') === 'blanc' ? 'blanc' : 'noir';

    // Curseurs de réglage du granit : réservés aux administrateurs (la page admin passe :peut-regler="true").
    $peutReglerGranits = ($peutRegler ?? false) && $renduRealiste;

    // Ambiances proposées à tous les visiteurs (sélecteur « Ambiance ») : l'éclairage par défaut ci-dessus, puis des paysages
    // (config 'ambiances') dont les reflets apparaissent sur la pierre polie. Chaque ambiance a sa propre exposition.
    // Un fichier manquant est simplement ignoré ; sans rendu réaliste, aucune ambiance n'est proposée.
    $ambiances = [];
    if ($envHdr) {
        $ambiances['defaut'] = [
            'libelle' => config('configurateur.ambiance_defaut_libelle', 'Studio'),
            'url' => $envHdr,
            'exposition' => $exposition,
        ];
        foreach ((array) config('configurateur.ambiances', []) as $cle => $ambiance) {
            if ($cle === 'defaut' || empty($ambiance['fichier']) || ! file_exists(public_path($ambiance['fichier']))) {
                continue;
            }
            $ambiances[$cle] = [
                'libelle' => $ambiance['libelle'] ?? ucfirst(str_replace('-', ' ', $cle)),
                'url' => asset($ambiance['fichier']),
                'exposition' => (float) ($ambiance['exposition'] ?? $exposition),
            ];
        }
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
        #config-monument-viewer {
            width: 100%;
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
        /* Fond blanc (interrupteur « Fond blanc ») : le texte posé sur la scène passe en sombre. */
        #config-wrapper, #config-monument-viewer {
            transition: background-color 0.25s;
        }
        #config-wrapper.fond-clair,
        #config-wrapper.fond-clair #config-monument-viewer {
            background-color: #ffffff;
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
            padding: 14px 8px;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: rgba(255,255,255,0.35);
            cursor: not-allowed;
            border-bottom: 2px solid transparent;
        }
        .config-main-tab.active {
            color: #fff;
            border-bottom-color: #d97706;
            cursor: default;
        }
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
        #config-granit-grid {
            flex: 1;
            overflow-y: auto;
            padding: 4px 20px 20px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-content: start;
        }
        .config-granit-swatch {
            cursor: pointer;
            text-align: center;
            background: none;
            border: none;
            padding: 0;
        }
        .config-granit-swatch img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid transparent;
            display: block;
            box-shadow:
                0 6px 14px rgba(0, 0, 0, 0.45),
                inset 0 1px 1px rgba(255, 255, 255, 0.18),
                inset 0 -2px 3px rgba(0, 0, 0, 0.35);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .config-granit-swatch:hover img {
            transform: translateY(-2px);
            box-shadow:
                0 10px 18px rgba(0, 0, 0, 0.5),
                inset 0 1px 1px rgba(255, 255, 255, 0.2),
                inset 0 -2px 3px rgba(0, 0, 0, 0.35);
        }
        .config-granit-swatch.selected img {
            border-color: #d97706;
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
        #config-semelle-types {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .config-semelle-btn {
            cursor: pointer;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        .config-semelle-btn:hover {
            background: rgba(255,255,255,0.12);
        }
        .config-semelle-btn.selected {
            border-color: #d97706;
            color: #d97706;
            background: rgba(217,119,6,0.1);
        }
        #config-semelle-dimensions {
            width: 100%;
            margin-top: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        #config-semelle-dimensions option {
            background: #0a0a0a;
            color: #fff;
        }
    </style>

    <div id="config-wrapper" @if($fondDefaut === 'blanc') class="fond-clair" @endif>
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
                          camera-orbit="0deg 75deg 60%"
                          orbit-sensitivity="0.4"
                          camera-target="0.5m 0m 0m"
                          alt="{{ $monument->nom }}">
            </model-viewer>

            <div id="config-topbar">
                <div>
                    <div id="config-model-name"><span>Modèle :</span>{{ strtoupper($monument->nom) }}</div>
                </div>
                <button id="config-rotate-btn" type="button">⟳ 360°</button>
            </div>

            <div id="config-selected-label">
                <span id="config-selected-name">—</span>
                <span id="config-selected-swatch"></span>
            </div>

            <div id="config-sidebar">
                <div id="config-main-tabs">
                    <div class="config-main-tab">Accessoires</div>
                    <div class="config-main-tab">Vases</div>
                    <div class="config-main-tab">Fond</div>
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
                    <div class="config-option-row">
                        <span>Fond blanc</span>
                        <label class="config-toggle">
                            <input type="checkbox" id="config-fond-toggle" @if($fondDefaut === 'blanc') checked @endif>
                            <span class="config-toggle-slider"></span>
                        </label>
                    </div>
                    @if(count($ambiances) > 1)
                        <div class="config-option-row">
                            <span>Ambiance</span>
                            <select id="config-ambiance" aria-label="Ambiance (reflets sur la pierre)">
                                @foreach($ambiances as $cle => $ambiance)
                                    <option value="{{ $cle }}" @if($cle === 'defaut') selected @endif>{{ $ambiance['libelle'] }}</option>
                                @endforeach
                            </select>
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
                        <div id="config-semelle-types">
                            @foreach($semelleTypes as $type)
                                <button type="button"
                                        class="config-semelle-btn @if($type->pivot->is_default) selected @endif"
                                        data-code="{{ $type->code }}"
                                        data-type-id="{{ $type->id }}">
                                    {{ $type->label }}
                                </button>
                            @endforeach
                        </div>

                        @if(($semelleDimensions ?? collect())->isNotEmpty())
                            <select id="config-semelle-dimensions">
                                @foreach($semelleDimensions as $dimension)
                                    <option value="{{ $dimension->id }}"
                                            data-type="{{ $dimension->semelle_type_id }}"
                                            @if($dimension->is_default) selected @endif>
                                        {{ $dimension->label ?: ($dimension->longueur . ' x ' . $dimension->largeur . ' cm') }}
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
            const fondToggle = document.querySelector('#config-fond-toggle');
            const CLE_FOND = 'eg_configurateur_fond';
            const definirFond = (clair) => {
                configWrapper.classList.toggle('fond-clair', clair);
                if (fondToggle) fondToggle.checked = clair;
            };
            try {
                const fondMemorise = window.localStorage.getItem(CLE_FOND);
                if (fondMemorise === 'blanc' || fondMemorise === 'noir') definirFond(fondMemorise === 'blanc');
            } catch (erreur) { /* stockage indisponible : on garde le fond par défaut */ }
            if (fondToggle) {
                fondToggle.addEventListener('change', () => {
                    definirFond(fondToggle.checked);
                    try { window.localStorage.setItem(CLE_FOND, fondToggle.checked ? 'blanc' : 'noir'); } catch (erreur) {}
                });
            }

            // ==========================================
            // AMBIANCE (paysage reflété sur la pierre polie ; choix mémorisé dans le navigateur du visiteur)
            // ==========================================
            const AMBIANCES = @json($ambiances);
            const ambianceSelect = document.querySelector('#config-ambiance');
            const CLE_AMBIANCE = 'eg_configurateur_ambiance';
            const definirAmbiance = (cle) => {
                const ambiance = AMBIANCES[cle];
                if (!ambiance) return false;
                modelViewer.setAttribute('environment-image', ambiance.url);
                modelViewer.setAttribute('exposure', ambiance.exposition);
                if (ambianceSelect) ambianceSelect.value = cle;
                return true;
            };
            if (ambianceSelect) {
                try {
                    const ambianceMemorisee = window.localStorage.getItem(CLE_AMBIANCE);
                    if (ambianceMemorisee && ambianceMemorisee !== 'defaut') definirAmbiance(ambianceMemorisee);
                } catch (erreur) { /* stockage indisponible : ambiance par défaut */ }
                ambianceSelect.addEventListener('change', () => {
                    definirAmbiance(ambianceSelect.value);
                    try { window.localStorage.setItem(CLE_AMBIANCE, ambianceSelect.value); } catch (erreur) {}
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

            const semelleButtons = document.querySelectorAll('.config-semelle-btn');
            const semelleDimSelect = document.querySelector('#config-semelle-dimensions');
            const semelleTypeSection = document.querySelector('#config-semelle-type-section');
            const codesTypesSemelle = [...semelleButtons].map(b => b.dataset.code.toUpperCase());

            const mettreAJourSemelleVisibilite = () => {
                const materiauxSemelle = getMateriaux('SEMELLE');
                const semelleActivee = semelleToggle.checked;
                const boutonSelectionne = document.querySelector('.config-semelle-btn.selected');
                const codeSelectionne = boutonSelectionne ? boutonSelectionne.dataset.code.toUpperCase() : null;

                materiauxSemelle.forEach(material => {
                    if (!material.pbrMetallicRoughness) return;

                    // Un monument peut avoir plusieurs types de semelle modélisés (SEMELLE-B, SEMELLE-P, SEMELLE-R)
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
                if (!semelleDimSelect) return;
                const boutonSelectionne = [...semelleButtons].find(b => b.dataset.code.toUpperCase() === code.toUpperCase());
                const typeId = boutonSelectionne ? boutonSelectionne.dataset.typeId : null;

                let premiereOptionVisible = null;
                [...semelleDimSelect.options].forEach(option => {
                    const optionTypeId = option.dataset.type || '';
                    const correspond = optionTypeId === '' || optionTypeId === typeId;
                    option.hidden = !correspond;
                    if (correspond && !premiereOptionVisible) {
                        premiereOptionVisible = option;
                    }
                });

                if (premiereOptionVisible && semelleDimSelect.selectedOptions[0]?.hidden) {
                    semelleDimSelect.value = premiereOptionVisible.value;
                }
            };

            if (rotateBtn) {
                rotateBtn.addEventListener('click', () => {
                    modelViewer.autoRotate = !modelViewer.autoRotate;
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
                        swatches[0].click();
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
                    });
                });

                bicolorNoirToggle.addEventListener('change', mettreAJourBicolor);
                semelleToggle.addEventListener('change', () => {
                    mettreAJourSemelleVisibilite();
                    if (semelleTypeSection) {
                        semelleTypeSection.classList.toggle('disabled', !semelleToggle.checked);
                    }
                });

                if (semelleButtons.length > 0) {
                    semelleButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            const code = button.dataset.code;

                            semelleButtons.forEach(b => b.classList.remove('selected'));
                            button.classList.add('selected');

                            mettreAJourSemelleVisibilite();
                            filtrerDimensionsParType(code);
                        });
                    });

                    // État initial : applique le type par défaut (ou le premier) au chargement
                    const boutonInitial = document.querySelector('.config-semelle-btn.selected') || semelleButtons[0];
                    if (boutonInitial) {
                        boutonInitial.classList.add('selected');
                        filtrerDimensionsParType(boutonInitial.dataset.code);
                    }
                    if (semelleTypeSection) {
                        semelleTypeSection.classList.toggle('disabled', !semelleToggle.checked);
                    }
                }

                mettreAJourSemelleVisibilite();
            });
        });
    </script>

    {{-- Traceur de diagnostic : visible uniquement avec ?debug=1 et un role admin --}}
    @include('components.configurateur.debug-panel')
</div>
