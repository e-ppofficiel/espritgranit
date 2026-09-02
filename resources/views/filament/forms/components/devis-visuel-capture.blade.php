@php
    $monumentIdInitial = $monumentIdInitial ?? null;
    $granitIdInitial = $granitIdInitial ?? null;
    $soubassementNoirInitial = $soubassementNoirInitial ?? false;
@endphp

<div
    wire:ignore
    wire:key="devis-visuel-capture-widget"
    x-data="devisVisuelCapture({{ Illuminate\Support\Js::from($monumentIdInitial) }}, {{ Illuminate\Support\Js::from($granitIdInitial) }}, {{ Illuminate\Support\Js::from($soubassementNoirInitial) }})"
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

    <div style="display:flex; align-items:center; justify-content:space-between; padding: 10px 14px; background:#111827; gap: 12px; flex-wrap: wrap;">
        <span x-text="statut" style="font-size:0.78rem; color:rgba(255,255,255,0.6); flex:1;"></span>

        <div style="display:flex; gap:8px;">
            <button
                type="button"
                x-on:click="capturer()"
                x-bind:disabled="!glbUrl || captureEnCours"
                x-bind:style="(!glbUrl || captureEnCours) ? 'opacity:0.5; cursor:not-allowed;' : 'opacity:1; cursor:pointer;'"
                style="background:rgba(255,255,255,0.1); color:#fff; padding:8px 14px; border-radius:6px; font-size:0.82rem; border:1px solid rgba(255,255,255,0.2); white-space:nowrap;"
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
    function devisVisuelCapture(monumentIdInitial, granitIdInitial, soubassementNoirInitial) {
        return {
            glbUrl: null,
            granitsData: {},
            statut: 'En attente de sélection...',
            captureEnCours: false,
            soubassementNoir: !! soubassementNoirInitial,
            dernierMonumentCharge: null,
            ecouteurAttache: false,
            captures: [],

            estIdExploitable(valeur) {
                if (valeur === null || valeur === undefined) return false;
                if (Array.isArray(valeur) || typeof valeur === 'object') return false;
                return valeur !== '' && ! isNaN(Number(valeur));
            },

            init() {
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
                this.glbUrl = donnees.glbUrl;

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
                        const texture = await viewer.createTexture(texturePath);
                        viewer.model.materials.forEach((mat) => {
                            const nom = (mat.name || '').trim().toUpperCase();
                            if ((nom.startsWith('GRANIT') || nom.startsWith('SEMELLE')) && mat.pbrMetallicRoughness?.baseColorTexture) {
                                mat.pbrMetallicRoughness.baseColorTexture.setTexture(texture);
                            }
                        });
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
                viewer.model.materials.forEach((mat) => {
                    const nom = (mat.name || '').trim().toUpperCase();
                    if (nom.startsWith('BICOLOR') && mat.pbrMetallicRoughness) {
                        mat.pbrMetallicRoughness.setBaseColorFactor(this.soubassementNoir ? [0, 0, 0, 1] : [1, 1, 1, 1]);
                    }
                });
            },

            async capturer() {
                const viewer = this.$refs.viewer;
                if (! viewer || this.captureEnCours || ! this.glbUrl) {
                    return;
                }

                this.captureEnCours = true;
                this.statut = 'Capture en cours...';

                try {
                    const blob = await viewer.toBlob({ idealAspect: true, mimeType: 'image/png' });
                    const url = URL.createObjectURL(blob);
                    this.captures.push({ id: Date.now() + '-' + Math.random(), blob, url });
                    this.statut = this.captures.length + ' vue(s) capturée(s), pas encore ajoutée(s) au champ.';
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
