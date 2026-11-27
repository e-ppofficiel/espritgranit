@php
    $monumentIdInitial = $monumentIdInitial ?? null;
    $granitIdInitial = $granitIdInitial ?? null;
    $soubassementNoirInitial = $soubassementNoirInitial ?? false;

    // Rendu réaliste : mêmes réglages que le configurateur (resources/views/components/configurateur/viewer.blade.php),
    // pour que le visuel capturé pour le devis ressemble à ce que l'on voit dans le configurateur.
    $renduRealiste = (bool) config('configurateur.rendu_realiste', true);
    $envFichiers = (array) config('configurateur.environnements', [
        'studio' => 'configurateur/studio.hdr',
        'contraste' => 'configurateur/studio-contraste.hdr',
    ]);
    $envChemin = $envFichiers[config('configurateur.environnement', 'contraste')] ?? 'configurateur/studio.hdr';
    if (! file_exists(public_path($envChemin))) {
        $envChemin = 'configurateur/studio.hdr';
    }
    $maxVisuels = \App\Models\Devis::MAX_VISUELS;
    $envHdr = ($renduRealiste && file_exists(public_path($envChemin))) ? asset($envChemin) : null;
    $exposition = (float) config('configurateur.exposition', 1.5);
    $rendu = [
        'realiste' => $renduRealiste,
        'echantillonDefaut' => (float) config('configurateur.echantillon_defaut_cm', 30),
        'finitions' => config('configurateur.finitions', [
            'SEMELLE-B' => ['rugosite' => 0.85],
            'SEMELLE-P' => ['rugosite' => 0.3, 'brillance' => true],
            'SEMELLE' => ['rugosite' => 0.6],
            'BICOLOR' => ['rugosite' => 0.08, 'brillance' => true],
            'GRANIT' => ['rugosite' => 0.12, 'brillance' => true],
        ]),
        'brillance' => array_merge(
            ['defaut' => 50, 'facteur_miroir' => 0.15, 'rugosite_mate' => 0.55],
            (array) config('configurateur.brillance', [])
        ),
    ];
@endphp

<div
    wire:ignore
    wire:key="devis-visuel-capture-widget"
    x-data="devisVisuelCapture({{ Illuminate\Support\Js::from($monumentIdInitial) }}, {{ Illuminate\Support\Js::from($granitIdInitial) }}, {{ Illuminate\Support\Js::from($soubassementNoirInitial) }}, {{ Illuminate\Support\Js::from($rendu) }}, {{ (int) $maxVisuels }})"
    x-init="init()"
    style="border-radius: 12px; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);"
>
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>

    <div style="background:#0a0a0a; height: 360px; position: relative;">
        <model-viewer
            x-ref="viewer"
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
            style="width:100%; height:100%; background:#0a0a0a; --poster-color: transparent; display:block;"
        ></model-viewer>

        <div
            x-show="!glbUrl"
            style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.5); font-size:0.85rem; text-align:center; padding: 0 20px;"
        >
            Sélectionne un monument ci-dessus pour afficher l'aperçu 3D
        </div>
    </div>

    <div
        x-show="captures.length > 0"
        style="display:flex; gap:8px; padding: 10px 14px; background:#0f1420; flex-wrap:wrap;"
    >
        <template x-for="(capture, index) in captures" :key="capture.id">
            <div style="position:relative; width:64px; height:64px;">
                <img :src="capture.url" style="width:100%; height:100%; object-fit:cover; border-radius:6px; border:1px solid rgba(255,255,255,0.15);">
                <button
                    type="button"
                    x-on:click="retirerCapture(index)"
                    title="Retirer cette vue"
                    style="position:absolute; top:-6px; right:-6px; width:20px; height:20px; border-radius:50%; background:#dc2626; color:#fff; border:none; font-size:12px; line-height:1; cursor:pointer;"
                >×</button>
                <a
                    :href="capture.url"
                    :download="'visuel-devis-' + (index + 1) + '.png'"
                    title="Télécharger (secours si l'ajout auto échoue)"
                    style="position:absolute; bottom:-6px; right:-6px; width:20px; height:20px; border-radius:50%; background:#374151; color:#fff; border:none; font-size:11px; line-height:20px; text-align:center; text-decoration:none;"
                >↓</a>
            </div>
        </template>
    </div>

    <div x-ref="barre" style="display:flex; align-items:center; justify-content:space-between; padding: 10px 14px; background:#111827; gap: 12px; flex-wrap: wrap;">
        <span x-text="statut" style="font-size:0.78rem; color:rgba(255,255,255,0.6); flex:1;"></span>

        <div style="display:flex; gap:8px;">
            <button
                type="button"
                x-on:click="capturer()"
                x-bind:disabled="!glbUrl || captureEnCours || placesRestantes() <= 0"
                x-bind:title="placesRestantes() <= 0 ? ('Maximum ' + maxVisuels + ' photos par devis') : ''"
                x-bind:style="((!glbUrl || captureEnCours || placesRestantes() <= 0) ? 'opacity:0.5; cursor:not-allowed; ' : 'opacity:1; cursor:pointer; ')
                    + (fondClair ? 'background:#000; border:1px solid #000;' : 'background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2);')"
                style="color:#fff; padding:8px 14px; border-radius:6px; font-size:0.82rem; white-space:nowrap;"
            >
                <span x-show="!captureEnCours">+ Capturer cette vue</span>
                <span x-show="captureEnCours" x-cloak>Capture...</span>
            </button>

            <button
                type="button"
                x-on:click="validerCaptures()"
                x-show="captures.length > 0"
                style="background:#d97706; color:#fff; padding:8px 16px; border-radius:6px; font-size:0.82rem; border:none; white-space:nowrap; cursor:pointer;"
            >
                <span x-text="'Ajouter ' + captures.length + ' visuel(s) au champ'"></span>
            </button>
        </div>
    </div>
</div>

<script>
    function devisVisuelCapture(monumentIdInitial, granitIdInitial, soubassementNoirInitial, rendu, maxVisuels) {
        maxVisuels = Number(maxVisuels) > 0 ? Number(maxVisuels) : 4;
        rendu = rendu || { realiste: false, echantillonDefaut: 30, finitions: {}, brillance: { defaut: 50, facteur_miroir: 0.15, rugosite_mate: 0.55 } };

        // Couleur de base d'origine de chaque matériau (sur un ancien .glb elle porte la teinte du granit : on la multiplie, on ne la remplace pas).
        const couleursOrigine = new WeakMap();

        return {
            glbUrl: null,
            granitsData: {},
            reglagesData: {},
            // Rendu réaliste : true seulement pour un .glb passé dans « eg-glb optimiser » (UV en mètres).
            uvMetresPret: Promise.resolve(false),
            uvMetres: false,
            brillance: rendu.brillance.defaut,
            luminosite: 100,
            statut: 'En attente de sélection...',
            captureEnCours: false,
            soubassementNoir: !! soubassementNoirInitial,
            dernierMonumentCharge: null,
            ecouteurAttache: false,
            captures: [],
            // Limite de photos par devis : `existants` = photos déjà dans le champ « Visuel » (relevé périodique).
            maxVisuels: maxVisuels,
            existants: 0,
            // Vrai quand la barre de boutons a un fond clair : le bouton « Capturer cette vue » passe alors en noir.
            fondClair: false,

            placesRestantes() {
                return this.maxVisuels - this.existants - this.captures.length;
            },

            lireExistants() {
                try {
                    const racine = document.querySelector('[data-devis-visuel-field] .filepond--root');
                    const pond = (racine && typeof FilePond !== 'undefined') ? FilePond.find(racine) : null;
                    this.existants = pond ? pond.getFiles().length : 0;
                } catch (e) {
                    this.existants = 0;
                }
            },

            // Détecte si le fond réel de la barre est clair (luminance > 0.6 sur un fond opaque).
            detecterFond() {
                try {
                    const barre = this.$refs.barre;
                    const couleur = barre ? getComputedStyle(barre).backgroundColor : '';
                    const m = couleur.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?/);
                    if (! m) { this.fondClair = false; return; }
                    let alpha = m[4] === undefined ? 1 : (m[4].endsWith('%') ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
                    const lum = (0.2126 * Number(m[1]) + 0.7152 * Number(m[2]) + 0.0722 * Number(m[3])) / 255;
                    this.fondClair = alpha > 0.5 && lum > 0.6;
                } catch (e) {
                    this.fondClair = false;
                }
            },

            estIdExploitable(valeur) {
                if (valeur === null || valeur === undefined) return false;
                if (Array.isArray(valeur) || typeof valeur === 'object') return false;
                return valeur !== '' && ! isNaN(Number(valeur));
            },

            init() {
                this.detecterFond();
                this.lireExistants();
                setInterval(() => { this.detecterFond(); this.lireExistants(); }, 700);

                if (! this.ecouteurAttache) {
                    window.addEventListener('devis-configurateur-change', (event) => {
                        const detail = event.detail[0] ?? event.detail;
                        this.soubassementNoir = !! detail.soubassementNoir;
                        this.onMonumentChange(detail.monumentId).then(() => {
                            this.onGranitChange(detail.granitId);
                        });
                    });
                    this.ecouteurAttache = true;
                }

                // Un monument sans granit choisi reçoit quand même ses finitions (le .glb optimisé n'a plus de texture de rugosité).
                if (rendu.realiste && this.$refs.viewer) {
                    this.$refs.viewer.addEventListener('load', async () => {
                        if (await this.uvMetresPret) {
                            this.appliquerFinitions();
                        }
                    });
                }

                if (this.estIdExploitable(monumentIdInitial)) {
                    this.onMonumentChange(monumentIdInitial).then(() => {
                        if (this.estIdExploitable(granitIdInitial)) {
                            this.onGranitChange(granitIdInitial);
                        }
                    });
                }
            },

            async onMonumentChange(monumentId) {
                if (! this.estIdExploitable(monumentId)) {
                    return;
                }
                monumentId = Number(monumentId);
                if (monumentId === this.dernierMonumentCharge) {
                    return;
                }
                this.dernierMonumentCharge = monumentId;
                this.statut = 'Chargement du modèle 3D...';

                const donnees = await this.$wire.call('getDonneesMonumentPourVisuel', monumentId);
                this.granitsData = donnees.granits || {};
                this.reglagesData = donnees.reglages || {};
                this.glbUrl = donnees.glbUrl;
                this.uvMetresPret = (rendu.realiste && this.glbUrl) ? this.lireMarqueurGlb(this.glbUrl) : Promise.resolve(false);

                const viewer = this.$refs.viewer;
                if (this.glbUrl) {
                    viewer.src = this.glbUrl;
                    this.statut = 'Modèle chargé.';
                } else {
                    this.statut = 'Aucun fichier 3D disponible pour ce monument.';
                }
            },

            async onGranitChange(granitId) {
                const viewer = this.$refs.viewer;
                if (! this.estIdExploitable(granitId) || ! this.glbUrl || ! viewer) {
                    return;
                }
                const texturePath = this.granitsData[granitId];
                if (! texturePath) {
                    return;
                }

                const appliquer = async () => {
                    try {
                        const uvMetres = await this.uvMetresPret;
                        this.uvMetres = uvMetres;
                        const reglages = this.reglagesData[granitId] || {};
                        this.brillance = (rendu.realiste && reglages.brillance != null) ? Number(reglages.brillance) : rendu.brillance.defaut;
                        this.luminosite = (rendu.realiste && reglages.luminosite != null)
                            ? Math.min(150, Math.max(50, Number(reglages.luminosite)))
                            : 100;

                        const texture = await viewer.createTexture(texturePath);
                        viewer.model.materials.forEach((mat) => {
                            const nom = (mat.name || '').trim().toUpperCase();
                            // Sur un .glb optimisé, le soubassement (BICOLOR) porte lui aussi la texture du granit, comme dans le configurateur.
                            const recoitTexture = nom.startsWith('GRANIT') || nom.startsWith('SEMELLE') || (uvMetres && nom.startsWith('BICOLOR'));
                            if (recoitTexture && mat.pbrMetallicRoughness?.baseColorTexture) {
                                mat.pbrMetallicRoughness.baseColorTexture.setTexture(texture);
                            }
                            // Luminosité du granit. Ancien .glb à 100 % : on ne touche à rien (rendu inchangé).
                            if (rendu.realiste && (uvMetres || this.luminosite !== 100)
                                && (nom.startsWith('GRANIT') || nom.startsWith('SEMELLE'))
                                && mat.pbrMetallicRoughness) {
                                const facteur = this.luminosite / 100;
                                if (! couleursOrigine.has(mat)) {
                                    couleursOrigine.set(mat, [...(mat.pbrMetallicRoughness.baseColorFactor || [1, 1, 1, 1])]);
                                }
                                const origine = couleursOrigine.get(mat);
                                mat.pbrMetallicRoughness.setBaseColorFactor(uvMetres
                                    ? [facteur, facteur, facteur, origine[3]]
                                    : [origine[0] * facteur, origine[1] * facteur, origine[2] * facteur, origine[3]]);
                            }
                        });

                        if (rendu.realiste && uvMetres) {
                            // Une unité UV = 1 m : la texture (échantillon de N cm) est répétée 100/N fois par mètre.
                            // À faire APRÈS tous les setTexture : model-viewer remet l'échelle à 1×1 à chaque appel.
                            const cm = Number(reglages.echantillon) > 0 ? Number(reglages.echantillon) : rendu.echantillonDefaut;
                            texture.sampler.setScale({ u: 100 / cm, v: 100 / cm });
                            this.appliquerFinitions();
                        }
                        this.statut = 'Texture appliquée.';
                        this.appliquerSoubassement();
                    } catch (e) {
                        console.error('[devis-visuel] erreur application texture', e);
                        this.statut = "Impossible d'appliquer cette texture (fichier introuvable ou accès refusé).";
                    }
                };

                if (viewer.loaded && viewer.model) {
                    appliquer();
                } else {
                    viewer.addEventListener('load', appliquer, { once: true });
                }
            },

            appliquerSoubassement() {
                const viewer = this.$refs.viewer;
                if (! viewer || ! viewer.model) {
                    return;
                }
                // Luminosité du granit : uniquement sur un .glb optimisé (le soubassement y porte la texture du granit).
                const facteur = (rendu.realiste && this.uvMetres) ? this.luminosite / 100 : 1;
                viewer.model.materials.forEach((mat) => {
                    const nom = (mat.name || '').trim().toUpperCase();
                    if (nom.startsWith('BICOLOR') && mat.pbrMetallicRoughness) {
                        mat.pbrMetallicRoughness.setBaseColorFactor(this.soubassementNoir ? [0, 0, 0, 1] : [facteur, facteur, facteur, 1]);
                    }
                });
            },

            // Lit le début du .glb (jamais le fichier entier) pour savoir s'il a été optimisé (repère eg_uv_metres).
            // Même logique que viewer.blade.php.
            async lireMarqueurGlb(url) {
                try {
                    const rep = await fetch(url, { headers: { Range: 'bytes=0-262143' } });
                    if (! rep.ok || ! rep.body) return false;
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
                    return !! (json && json.extras && json.extras.eg_uv_metres);
                } catch (e) {
                    console.warn('[devis-visuel] lecture du repère UV impossible', e);
                    return false;
                }
            },

            // Brillance 0-100 → rugosité : mêmes règles que viewer.blade.php.
            rugositeSelonBrillance(rugositeBase, brillance) {
                const cfg = rendu.brillance;
                const b = Math.min(100, Math.max(0, brillance));
                if (b >= cfg.defaut) {
                    return rugositeBase * (1 - (1 - cfg.facteur_miroir) * (b - cfg.defaut) / Math.max(1, 100 - cfg.defaut));
                }
                const mate = Math.max(cfg.rugosite_mate, rugositeBase);
                return rugositeBase + (mate - rugositeBase) * (cfg.defaut - b) / Math.max(1, cfg.defaut);
            },

            // Rugosité / métal par famille de matériau (le .glb optimisé ne porte plus de texture de rugosité).
            appliquerFinitions() {
                const viewer = this.$refs.viewer;
                if (! viewer || ! viewer.model) {
                    return;
                }
                const prefixes = Object.keys(rendu.finitions);
                viewer.model.materials.forEach((mat) => {
                    if (! mat.name || ! mat.pbrMetallicRoughness) return;
                    const nom = mat.name.trim().toUpperCase();
                    const prefixe = prefixes.find((p) => nom.startsWith(p.toUpperCase()));
                    if (! prefixe) return;
                    const finition = rendu.finitions[prefixe];
                    const rugosite = finition.brillance ? this.rugositeSelonBrillance(finition.rugosite, this.brillance) : finition.rugosite;
                    mat.pbrMetallicRoughness.setRoughnessFactor(rugosite);
                    mat.pbrMetallicRoughness.setMetallicFactor(finition.metal || 0);
                });
            },

            async capturer() {
                const viewer = this.$refs.viewer;
                if (! viewer || this.captureEnCours || ! this.glbUrl) {
                    return;
                }
                this.lireExistants();
                if (this.placesRestantes() <= 0) {
                    this.statut = 'Maximum ' + this.maxVisuels + ' photos par devis : retire une photo pour en capturer une autre.';
                    return;
                }

                this.captureEnCours = true;
                this.statut = 'Capture en cours...';

                try {
                    const blob = await viewer.toBlob({ idealAspect: true, mimeType: 'image/png' });
                    const url = URL.createObjectURL(blob);
                    this.captures.push({ id: Date.now() + '-' + Math.random(), blob, url });
                    this.statut = this.captures.length + ' vue(s) capturée(s), pas encore ajoutée(s) au champ.'
                        + (this.placesRestantes() <= 0 ? ' Maximum ' + this.maxVisuels + ' photos atteint.' : '');
                } catch (e) {
                    console.error('[devis-visuel] erreur capture', e);
                    this.statut = 'Erreur lors de la capture.';
                } finally {
                    this.captureEnCours = false;
                }
            },

            retirerCapture(index) {
                URL.revokeObjectURL(this.captures[index].url);
                this.captures.splice(index, 1);
            },

            // Diffuse un évènement navigateur simple, écouté directement par le champ
            // Filament "Visuel" via son propre x-on (voir ->extraAlpineAttributes() côté PHP).
            // C'est le champ lui-même qui appelle pond.addFile() dans son propre contexte
            // Alpine, là où l'instance FilePond est réellement accessible.
            validerCaptures() {
                if (this.captures.length === 0) {
                    return;
                }

                const fichiers = this.captures.map((c, i) =>
                    new File([c.blob], 'visuel-devis-' + Date.now() + '-' + i + '.png', { type: 'image/png' })
                );

                console.log('[devis-visuel] diffusion de', fichiers.length, 'fichier(s) vers le champ Filament');
                window.dispatchEvent(new CustomEvent('devis-visuel-captures-ready', { detail: fichiers }));

                this.statut = fichiers.length + ' visuel(s) envoyé(s) au champ ci-dessous.';
                this.captures.forEach((c) => URL.revokeObjectURL(c.url));
                this.captures = [];
            },
        };
    }
</script>
