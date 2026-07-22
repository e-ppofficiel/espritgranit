@php
    $glbUrl = $monument->fichier_3d ? asset('storage/' . $monument->fichier_3d) : null;
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
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
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
            border-radius: 6px;
            border: 2px solid transparent;
            display: block;
        }
        .config-granit-swatch.selected img {
            border-color: #d97706;
        }
        .config-granit-swatch span {
            display: block;
            margin-top: 4px;
            font-size: 0.65rem;
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
    </style>

    <div id="config-wrapper">
        @if($glbUrl)
            <model-viewer id="config-monument-viewer"
                          src="{{ $glbUrl }}"
                          camera-controls
                          touch-action="pan-y"
                          shadow-intensity="1"
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
                </div>
                <div id="config-granit-grid">
                    @foreach($granits as $granit)
                        @if($granit->image)
                            <button type="button"
                                    class="config-granit-swatch"
                                    data-texture="{{ asset('storage/' . $granit->image) }}"
                                    data-nom="{{ $granit->nom_commercial }}">
                                <img src="{{ asset('storage/' . $granit->image) }}" alt="{{ $granit->nom_commercial }}">
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

            const getMateriaux = (prefixe) => modelViewer.model.materials.filter(
                mat => mat.name && mat.name.trim().toUpperCase().startsWith(prefixe)
            );

            const appliquerTextureSur = (materiaux, texture) => {
                materiaux.forEach(material => {
                    if (material.pbrMetallicRoughness) {
                        material.pbrMetallicRoughness.setBaseColorFactor([1, 1, 1, 1]);
                        if (material.pbrMetallicRoughness.baseColorTexture) {
                            material.pbrMetallicRoughness.baseColorTexture.setTexture(texture);
                        }
                    }
                });
            };

            const mettreNoir = (materiaux) => {
                materiaux.forEach(material => {
                    if (material.pbrMetallicRoughness) {
                        material.pbrMetallicRoughness.setBaseColorFactor([0, 0, 0, 1]);
                    }
                });
            };

            const mettreAJourBicolor = () => {
                const materiauxBicolor = getMateriaux('BICOLOR');
                if (bicolorNoirToggle.checked) {
                    mettreNoir(materiauxBicolor);
                } else if (currentTexture) {
                    appliquerTextureSur(materiauxBicolor, currentTexture);
                }
            };

            const mettreAJourSemelleVisibilite = () => {
                const materiauxSemelle = getMateriaux('SEMELLE');
                const visible = semelleToggle.checked;
                materiauxSemelle.forEach(material => {
                    if (material.pbrMetallicRoughness) {
                        material.setAlphaMode(visible ? 'OPAQUE' : 'BLEND');
                        const facteurActuel = material.pbrMetallicRoughness.baseColorFactor || [1, 1, 1, 1];
                        material.pbrMetallicRoughness.setBaseColorFactor([
                            facteurActuel[0], facteurActuel[1], facteurActuel[2], visible ? 1 : 0
                        ]);
                    }
                });
            };

            if (rotateBtn) {
                rotateBtn.addEventListener('click', () => {
                    modelViewer.autoRotate = !modelViewer.autoRotate;
                });
            }

            modelViewer.addEventListener('load', () => {
                swatches.forEach(swatch => {
                    swatch.addEventListener('click', async (event) => {
                        const btn = event.currentTarget;
                        const texturePath = btn.dataset.texture;
                        const nom = btn.dataset.nom;

                        currentTexture = await modelViewer.createTexture(texturePath);

                        appliquerTextureSur(getMateriaux('GRANIT'), currentTexture);
                        appliquerTextureSur(getMateriaux('SEMELLE'), currentTexture);
                        mettreAJourBicolor();

                        swatches.forEach(s => s.classList.remove('selected'));
                        btn.classList.add('selected');
                        selectedName.textContent = nom;
                        selectedSwatch.style.backgroundImage = `url(${texturePath})`;
                        selectedSwatch.style.backgroundSize = 'cover';
                    });
                });

                bicolorNoirToggle.addEventListener('change', mettreAJourBicolor);
                semelleToggle.addEventListener('change', mettreAJourSemelleVisibilite);
            });
        });
    </script>
</div>
