@php
    $granitRecord = $granitRecord ?? null;
    $image = $granitRecord?->image;
    // Chemin enregistré en base s'il pointe vers un vrai fichier, sinon fichier nommé
    // d'après la référence interne (granits/{reference_interne}.jpg|png|…).
    $chemin = $granitRecord ? \App\Services\GranitImageLocator::chemin($granitRecord) : null;
    $viaReference = $chemin && $chemin !== $image;
    $cheminUrl = $chemin
        ? implode('/', array_map('rawurlencode', explode('/', ltrim($chemin, '/'))))
        : null;
    $url = $chemin
        ? asset('storage/' . $cheminUrl) . '?v=' . ($granitRecord->updated_at?->timestamp ?? 0)
        : null;
@endphp

<div wire:key="granit-apercu-{{ md5((string) $url) }}">
    @if (! $url)
        <div style="padding:12px 14px; border:1px dashed rgba(128,128,128,.5); border-radius:8px; font-size:.85rem; opacity:.85;">
            @if (! $granitRecord)
                L'aperçu en grand apparaîtra après l'enregistrement du granit.
            @else
                Aucune image trouvée pour ce granit.<br>
                Chemin en base : <code>{{ $image ?: '(vide)' }}</code><br>
                Fichier attendu d'après la référence interne : <code>granits/{{ $granitRecord->reference_interne }}.jpg</code> (ou .png, .jpeg, .webp)
            @endif
        </div>
    @else
        @if ($viaReference)
            <div style="margin-bottom:10px; padding:10px 12px; border:1px solid #f59e0b; border-radius:8px; font-size:.82rem; color:#f59e0b; line-height:1.45;">
                Image retrouvée grâce à la référence interne : <code>{{ $chemin }}</code>.<br>
                Le chemin enregistré en base (<code>{{ $image ?: 'vide' }}</code>) ne correspond à aucun fichier : le champ « Image source » est donc vide.
                N'enregistrez pas cette fiche avant d'avoir lancé <code>php artisan granits:reparer-images --appliquer</code>, sinon l'image serait effacée.
            </div>
        @endif
        <div
            x-data="{
                url: {{ \Illuminate\Support\Js::from($url) }},
                zoom: 3,
                loupe: true,
                survol: false,
                taille: 200,
                x: 0, y: 0, w: 0, h: 0,
                nw: 0, nh: 0,
                mesurer() {
                    const i = this.$refs.img;
                    if (i && i.complete && i.naturalWidth) {
                        this.nw = i.naturalWidth;
                        this.nh = i.naturalHeight;
                    }
                },
                deplacer(e) {
                    const r = this.$refs.img.getBoundingClientRect();
                    this.w = r.width;
                    this.h = r.height;
                    this.x = Math.max(0, Math.min(e.clientX - r.left, r.width));
                    this.y = Math.max(0, Math.min(e.clientY - r.top, r.height));
                    this.survol = true;
                },
                styleLoupe() {
                    const urlSure = this.url.replace(/'/g, '%27');
                    return {
                        'display': (this.loupe && this.survol) ? 'block' : 'none',
                        'left': (this.x - this.taille / 2) + 'px',
                        'top': (this.y - this.taille / 2) + 'px',
                        'width': this.taille + 'px',
                        'height': this.taille + 'px',
                        'background-image': 'url(\'' + urlSure + '\')',
                        'background-size': (this.w * this.zoom) + 'px ' + (this.h * this.zoom) + 'px',
                        'background-position': (-(this.x * this.zoom - this.taille / 2)) + 'px ' + (-(this.y * this.zoom - this.taille / 2)) + 'px'
                    };
                },
                btn(actif) {
                    return {
                        'padding': '4px 10px',
                        'border-radius': '6px',
                        'font-size': '.8rem',
                        'cursor': 'pointer',
                        'border': '1px solid ' + (actif ? '#d97706' : 'rgba(128,128,128,.45)'),
                        'background': actif ? '#d97706' : 'transparent',
                        'color': actif ? '#fff' : 'inherit'
                    };
                }
            }"
            x-init="mesurer()"
        >
            <div
                style="position:relative; display:inline-block; max-width:100%; line-height:0; border-radius:10px; overflow:hidden; border:1px solid rgba(128,128,128,.35);"
                x-bind:style="{ cursor: loupe ? 'crosshair' : 'default' }"
            >
                <img
                    x-ref="img"
                    src="{{ $url }}"
                    alt="{{ $granitRecord->nom_commercial }}"
                    draggable="false"
                    x-on:load="mesurer()"
                    x-on:pointermove="deplacer($event)"
                    x-on:pointerleave="survol = false"
                    style="display:block; max-width:100%; max-height:70vh; width:auto; height:auto; user-select:none;"
                >
                <div
                    aria-hidden="true"
                    style="position:absolute; display:none; box-sizing:border-box; border-radius:50%; pointer-events:none; border:2px solid #d97706; box-shadow:0 6px 24px rgba(0,0,0,.55), inset 0 0 0 1px rgba(255,255,255,.25); background-repeat:no-repeat; background-origin:border-box;"
                    x-bind:style="styleLoupe()"
                ></div>
            </div>

            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:10px;">
                <button type="button" x-on:click="loupe = ! loupe" x-bind:style="btn(loupe)">
                    <span x-text="loupe ? 'Loupe activée' : 'Loupe désactivée'"></span>
                </button>

                <template x-for="n in [2, 3, 4, 6]" :key="n">
                    <button type="button" x-on:click="zoom = n" x-bind:style="btn(zoom === n)" x-text="'×' + n"></button>
                </template>

                <a href="{{ $url }}" target="_blank" rel="noopener" style="font-size:.8rem; text-decoration:underline; margin-left:4px;">
                    Ouvrir l'image source
                </a>

                <span x-show="nw" x-text="'Source : ' + nw + ' × ' + nh + ' px'" style="font-size:.78rem; opacity:.7;"></span>
            </div>

            <p style="margin:8px 0 0; font-size:.8rem; opacity:.75;">
                Passez la souris sur l'image pour agrandir une zone. Les boutons ×2 à ×6 règlent l'agrandissement.
            </p>

            <p x-show="nw && nw < 1000" style="margin:6px 0 0; font-size:.8rem; color:#f59e0b;">
                Image source de faible résolution : la loupe ne peut pas faire apparaître des détails absents du fichier.
            </p>
        </div>
    @endif
</div>
