{{--
    Panneau de diagnostic du configurateur 3D.

    Activation : ajouter ?debug=1 à l'URL du configurateur (réservé aux rôles admin).
    Aucune modification de la logique du viewer : le panneau observe les appels que le site
    fait sur les matériaux (couleur, transparence, texture) et les journalise.

    Inclusion (une seule ligne, à la fin de viewer.blade.php) :
        @include('components.configurateur.debug-panel')
--}}
@php
    $debugUtilisateur = null;
    try {
        $debugUtilisateur = \Filament\Facades\Filament::auth()->user();
    } catch (\Throwable $e) {
        $debugUtilisateur = null;
    }

    $debugAutorise = request()->boolean('debug')
        && $debugUtilisateur
        && method_exists($debugUtilisateur, 'hasAnyRole')
        && $debugUtilisateur->hasAnyRole(['super_admin', 'admin_esprit_granit', 'gestionnaire']);
@endphp

@if ($debugAutorise)
<style>
    #eg-debug { position: fixed; left: 12px; bottom: 12px; z-index: 99999; width: min(600px, calc(100vw - 24px)); max-height: 72vh; display: flex; flex-direction: column; background: rgba(10, 10, 10, .97); color: #e5e7eb; border: 1px solid rgba(255, 255, 255, .22); border-radius: 10px; box-shadow: 0 10px 40px rgba(0, 0, 0, .6); font: 12px/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    #eg-debug * { box-sizing: border-box; }
    #eg-debug .eg-d-tete { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 10px; border-bottom: 1px solid rgba(255, 255, 255, .15); }
    #eg-debug .eg-d-tete strong { color: #d97706; }
    #eg-debug .eg-d-etat { flex: 1; opacity: .75; }
    #eg-debug button { font: inherit; color: #e5e7eb; background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .25); border-radius: 6px; padding: 2px 8px; cursor: pointer; }
    #eg-debug button:hover { background: rgba(255, 255, 255, .18); }
    #eg-debug .eg-d-corps { overflow: auto; padding: 8px 10px 10px; }
    #eg-debug .eg-d-titre { margin: 10px 0 4px; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; font-size: 11px; }
    #eg-debug table { width: 100%; border-collapse: collapse; }
    #eg-debug th, #eg-debug td { text-align: left; vertical-align: top; padding: 3px 6px; border-bottom: 1px solid rgba(255, 255, 255, .1); }
    #eg-debug th { color: #9ca3af; font-weight: normal; }
    #eg-debug .eg-d-ok { color: #22c55e; }
    #eg-debug .eg-d-erreur { color: #f87171; }
    #eg-debug .eg-d-alerte { color: #fbbf24; }
    #eg-debug .eg-d-info { color: #9ca3af; }
    #eg-debug .eg-d-action { color: #60a5fa; }
    #eg-debug .eg-d-puce { display: inline-block; padding: 0 6px; border-radius: 8px; color: #0a0a0a; font-weight: bold; }
    #eg-debug .eg-d-ligne { white-space: pre-wrap; word-break: break-word; }
    #eg-debug .eg-d-journal { max-height: 240px; overflow: auto; border: 1px solid rgba(255, 255, 255, .12); border-radius: 6px; padding: 4px 6px; }
</style>

<div id="eg-debug">
    <div class="eg-d-tete">
        <strong>Diagnostic configurateur</strong>
        <span class="eg-d-etat" id="eg-d-etat">initialisation…</span>
        <button type="button" id="eg-d-copier">Copier le rapport</button>
        <button type="button" id="eg-d-vider">Vider le journal</button>
        <button type="button" id="eg-d-reduire">Réduire</button>
    </div>
    <div class="eg-d-corps" id="eg-d-corps">
        <div class="eg-d-titre">Verdict</div>
        <div id="eg-d-verdict"></div>
        <div class="eg-d-titre">Matériaux du modèle chargé</div>
        <div id="eg-d-table"></div>
        <div class="eg-d-titre">Journal des actions (chaque clic, et ce qu'il a modifié sur le modèle)</div>
        <div class="eg-d-journal" id="eg-d-journal"></div>
    </div>
</div>

<script>
(function () {
    'use strict';
    if (window.__egDebug) { return; }

    var FAMILLES = ['SEMELLE-B', 'SEMELLE-P', 'SEMELLE-R', 'GRANIT', 'BICOLOR'];
    var SEMELLES = ['SEMELLE-B', 'SEMELLE-P', 'SEMELLE-R'];
    var COULEURS = { 'GRANIT': '#22c55e', 'BICOLOR': '#a78bfa', 'SEMELLE-B': '#38bdf8', 'SEMELLE-P': '#f59e0b', 'SEMELLE-R': '#f472b6' };
    var DEBUT = Date.now();

    var etat = {
        mv: null, journal: [], glb: null, glbErreur: null, nbLoad: 0, enAttente: 0,
        interne: 0, action: null, minuteur: null, reduit: false, chargeOk: false
    };
    window.__egDebug = etat;

    /* ---------- Nomenclature (même règles que App\Services\GlbInspector) ---------- */

    function norm(nom) { return String(nom == null ? '' : nom).trim().toUpperCase(); }

    function famille(nom) {
        var n = norm(nom);
        for (var i = 0; i < FAMILLES.length; i++) {
            if (n.indexOf(FAMILLES[i]) === 0) { return FAMILLES[i]; }
        }
        return null;
    }

    function motCle(n) { return n.split(/[_\s#.]/)[0]; }

    function lev(a, b) {
        var prec = [], i, j;
        for (j = 0; j <= b.length; j++) { prec[j] = j; }
        for (i = 1; i <= a.length; i++) {
            var cour = [i];
            for (j = 1; j <= b.length; j++) {
                cour[j] = Math.min(prec[j] + 1, cour[j - 1] + 1, prec[j - 1] + (a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1));
            }
            prec = cour;
        }
        return prec[b.length];
    }

    function plusProche(mot, candidats) {
        var meilleure = null, min = 3, egal = 0;
        candidats.forEach(function (c) {
            var d = lev(mot, c);
            if (d < 1 || d >= 3) { return; }
            if (d < min) { min = d; meilleure = c; egal = 1; } else if (d === min) { egal++; }
        });
        return egal === 1 ? meilleure : null;
    }

    function alerte(nom) {
        if (famille(nom) !== null) { return null; }
        var n = norm(nom);
        if (n === '') { return null; }
        var mot = motCle(n), s;
        if (n.indexOf('SEMELLE') === 0) {
            s = plusProche(mot, SEMELLES);
            return s ? 'Semelle non reconnue : « ' + mot + ' » ressemble à ' + s + '. Le type (B, P ou R) doit être exact.'
                     : 'Semelle sans type valide : le nom doit commencer par SEMELLE-B, SEMELLE-P ou SEMELLE-R.';
        }
        s = plusProche(mot, FAMILLES);
        return s ? 'Faute probable : « ' + mot + ' » ressemble à ' + s + '.' : null;
    }

    /* ---------- Journal ---------- */

    function horodatage() { return ((Date.now() - DEBUT) / 1000).toFixed(2) + ' s'; }

    function log(type, texte) {
        etat.journal.push({ t: horodatage(), type: type, texte: texte });
        if (etat.journal.length > 400) { etat.journal.shift(); }
        try { console.log('[configurateur-debug] ' + type + ' : ' + texte); } catch (e) { /* console absente */ }
        dessinerJournal();
    }

    /* ---------- Suivi des actions : un clic ouvre une action, les appels sur les matériaux s'y rattachent ---------- */

    function armer() {
        clearTimeout(etat.minuteur);
        etat.minuteur = setTimeout(cloreAction, 800);
    }

    function ouvrirAction(libelle) {
        if (etat.action && etat.action.appels.length === 0 && (Date.now() - etat.action.debut) < 250) {
            return; // même geste vu deux fois (clic + change) : on garde la première action
        }
        cloreAction(true);
        etat.action = { libelle: libelle, appels: [], lignes: 0, debut: Date.now() };
        log('action', '▶ ' + libelle);
        armer();
    }

    function cloreAction(force) {
        var a = etat.action;
        if (!a) { return; }
        if (!force && etat.enAttente > 0 && (Date.now() - a.debut) < 15000) { armer(); return; }
        clearTimeout(etat.minuteur);
        etat.action = null;

        if (a.appels.length === 0) {
            log('alerte', '⚠ Aucun matériau modifié après « ' + a.libelle + ' » : cette action n\'a eu aucun effet sur le modèle (aucun matériau ne correspond, ou le site n\'a rien exécuté).');
        } else {
            var parFamille = {};
            a.appels.forEach(function (c) {
                var k = c.famille + ' — ' + c.methode;
                parFamille[k] = (parFamille[k] || 0) + 1;
            });
            var resume = Object.keys(parFamille).map(function (k) { return k + ' ×' + parFamille[k]; }).join(' ; ');
            log('ok', '✔ ' + a.appels.length + ' appel(s) sur le modèle : ' + resume);
        }
        rendre();
    }

    function enregistrer(mat, methode, detail) {
        if (etat.interne > 0) { return; }
        var nom = mat.name == null ? '(sans nom)' : mat.name;
        var fam = famille(nom) || 'hors famille';
        var ligne = '« ' + nom + ' » [' + fam + '] ' + methode + ' ' + detail;
        if (etat.action) {
            etat.action.appels.push({ materiau: nom, famille: fam, methode: methode });
            etat.action.lignes++;
            if (etat.action.lignes <= 30) { log('info', '    · ' + ligne); }
            else if (etat.action.lignes === 31) { log('info', '    · … (lignes suivantes regroupées dans le résumé)'); }
            armer();
        } else {
            log('info', '(hors action) ' + ligne);
        }
    }

    function arrondi(v) { return typeof v === 'number' ? Math.round(v * 100) / 100 : v; }

    function envelopper(mat, objet, methode, decrire) {
        var origine = objet && objet[methode];
        if (typeof origine !== 'function' || origine.__egEnveloppe) { return; }
        var enveloppe = function () {
            try { enregistrer(mat, methode, decrire.apply(null, arguments)); } catch (e) { /* ne jamais gêner le site */ }
            return origine.apply(this, arguments);
        };
        enveloppe.__egEnveloppe = true;
        try { objet[methode] = enveloppe; } catch (e) { log('alerte', 'Impossible de tracer ' + methode + ' (objet figé).'); }
    }

    function patcherMateriaux() {
        var mats = materiauxVivants();
        mats.forEach(function (m) {
            if (m.__egPatche) { return; }
            m.__egPatche = true;
            envelopper(m, m, 'setAlphaMode', function (mode) { return '→ ' + mode; });
            var pbr = m.pbrMetallicRoughness;
            if (pbr) {
                envelopper(m, pbr, 'setBaseColorFactor', function (f) { return '→ [' + Array.prototype.map.call(f || [], arrondi).join(', ') + ']'; });
                var tex = pbr.baseColorTexture;
                if (tex) { envelopper(m, tex, 'setTexture', function (t) { return t ? '→ texture appliquée' : '→ texture retirée'; }); }
            }
        });

        var mv = etat.mv;
        if (mv && typeof mv.createTexture === 'function' && !mv.createTexture.__egEnveloppe) {
            var orig = mv.createTexture;
            var env = function (url) {
                var court = String(url).split('/').slice(-2).join('/');
                etat.enAttente++;
                log('info', '    · createTexture(' + court + ') demandé');
                var fin = function () { etat.enAttente = Math.max(0, etat.enAttente - 1); };
                var p;
                try { p = orig.apply(this, arguments); } catch (e) { fin(); log('erreur', '✖ createTexture a levé une exception : ' + e.message); throw e; }
                return Promise.resolve(p).then(function (r) { fin(); log('info', '    · createTexture(' + court + ') : texture prête'); return r; },
                    function (e) { fin(); log('erreur', '✖ createTexture(' + court + ') a échoué : ' + (e && e.message ? e.message : e) + ' (fichier image introuvable, 403 ou 404 ?)'); throw e; });
            };
            env.__egEnveloppe = true;
            try { mv.createTexture = env; } catch (e) { /* ignore */ }
        }
    }

    /* ---------- Matériaux du modèle ---------- */

    function materiauxVivants() {
        var m = etat.mv && etat.mv.model && etat.mv.model.materials;
        return m ? Array.prototype.slice.call(m) : [];
    }

    function materiauxNommes(nom) {
        return materiauxVivants().filter(function (m) { return (m.name == null ? '' : m.name) === nom; });
    }

    function groupes() {
        var g = {}, ordre = [];
        materiauxVivants().forEach(function (m) {
            var nom = m.name == null ? '' : m.name;
            if (!g[nom]) { g[nom] = { nom: nom, n: 0, alpha: {}, texture: false, facteur: null }; ordre.push(nom); }
            var x = g[nom];
            x.n++;
            x.alpha[m.alphaMode] = true;
            var pbr = m.pbrMetallicRoughness;
            if (pbr) {
                var t = pbr.baseColorTexture;
                if (t && t.texture) { x.texture = true; }
                if (x.facteur === null && pbr.baseColorFactor) { x.facteur = Array.prototype.map.call(pbr.baseColorFactor, arrondi); }
            }
        });
        return ordre.map(function (nom) { return g[nom]; });
    }

    /* ---------- Fichier .glb : quelles pièces portent quel matériau ---------- */

    function lireGlb(url) {
        function extraire(buf) {
            var dv = new DataView(buf);
            if (buf.byteLength < 20 || dv.getUint32(0, true) !== 0x46546C67) { throw new Error('signature glTF absente (fichier non binaire ou tronqué)'); }
            var longueur = dv.getUint32(12, true);
            if (dv.getUint32(16, true) !== 0x4E4F534A) { throw new Error('le premier bloc du fichier n\'est pas du JSON'); }
            if (buf.byteLength < 20 + longueur) { throw new Error('bloc JSON incomplet'); }
            return JSON.parse(new TextDecoder('utf-8').decode(new Uint8Array(buf, 20, longueur)));
        }
        return fetch(url, { headers: { Range: 'bytes=0-19' } }).then(function (r) {
            return r.arrayBuffer().then(function (tete) {
                if (r.status === 206 && tete.byteLength === 20) {
                    var longueur = new DataView(tete).getUint32(12, true);
                    return fetch(url, { headers: { Range: 'bytes=20-' + (19 + longueur) } })
                        .then(function (r2) { return r2.arrayBuffer(); })
                        .then(function (corps) {
                            var tout = new Uint8Array(20 + corps.byteLength);
                            tout.set(new Uint8Array(tete), 0);
                            tout.set(new Uint8Array(corps), 20);
                            return extraire(tout.buffer);
                        });
                }
                return extraire(tete); // le serveur a ignoré Range : fichier complet reçu
            });
        });
    }

    function analyserGlb(j) {
        var mats = j.materials || [], meshes = j.meshes || [], nodes = j.nodes || [];
        var parNom = {}, noeuds = {};
        nodes.forEach(function (nd, i) {
            if (nd.mesh == null || !meshes[nd.mesh]) { return; }
            var piece = nd.name || meshes[nd.mesh].name || ('noeud #' + i);
            (meshes[nd.mesh].primitives || []).forEach(function (p) {
                if (p.material == null || !mats[p.material]) { return; }
                var nom = mats[p.material].name == null ? '' : mats[p.material].name;
                var g = parNom[nom] = parNom[nom] || { primitives: 0, pieces: {} };
                g.primitives++;
                g.pieces[piece] = true;
                var f = famille(nom);
                if (f) { (noeuds[piece] = noeuds[piece] || {})[f] = true; }
            });
        });
        return { nbMateriaux: mats.length, generateur: (j.asset && j.asset.generator) || null, parNom: parNom, noeuds: noeuds };
    }

    /* ---------- Boutons « Type de semelle » : même règle que le viewer ----------
       Le viewer lit le code de chaque bouton (data-code, issu de la table semelle_types), puis
       considère qu'un matériau « SEMELLE… » appartient à ce type si son nom COMMENCE par ce code
       (seuls les matériaux dont le nom commence par SEMELLE sont examinés).
       Un matériau SEMELLE… qui ne commence par aucun code est traité comme semelle « générique » :
       il reste visible quel que soit le type choisi. */

    function boutonsSemelle() {
        var boutons = document.querySelectorAll('.config-semelle-btn');
        var vivants = materiauxVivants();
        return Array.prototype.map.call(boutons, function (b) {
            var code = String(b.getAttribute('data-code') || '').toUpperCase();
            var n = code === '' ? 0 : vivants.filter(function (m) { var nm = m.name ? String(m.name).trim().toUpperCase() : ''; return nm.indexOf('SEMELLE') === 0 && nm.indexOf(code) === 0; }).length;
            return { code: code, libelle: (b.textContent || '').replace(/\s+/g, ' ').trim(), selectionne: b.classList.contains('selected'), materiaux: n };
        });
    }

    function semellesGeneriques(boutons) {
        var codes = boutons.map(function (b) { return b.code; }).filter(function (c) { return c !== ''; });
        var vus = {};
        materiauxVivants().forEach(function (m) {
            var n = norm(m.name);
            if (n.indexOf('SEMELLE') === 0 && !codes.some(function (c) { return n.indexOf(c) === 0; })) { vus[m.name] = true; }
        });
        return Object.keys(vus);
    }

    /* ---------- Verdict ---------- */

    function calculerVerdict() {
        var liste = [], vivants = materiauxVivants(), compte = {}, vus = {};
        FAMILLES.forEach(function (f) { compte[f] = 0; });

        vivants.forEach(function (m) {
            var f = famille(m.name);
            if (f) { compte[f]++; }
            var a = alerte(m.name), cle = (m.name == null ? '' : m.name) + '|' + a;
            if (a && !vus[cle]) { vus[cle] = true; liste.push({ niveau: 'erreur', message: '« ' + (m.name == null ? '(sans nom)' : m.name) + ' » : ' + a }); }
        });

        if (vivants.length) {
            FAMILLES.forEach(function (f) {
                if (compte[f] === 0) { liste.push({ niveau: 'erreur', message: 'Aucun matériau ' + f + ' : l\'option correspondante restera sans effet.' }); }
            });
        }

        if (etat.glb) {
            Object.keys(etat.glb.noeuds).forEach(function (piece) {
                var fs = Object.keys(etat.glb.noeuds[piece]);
                if (fs.length > 1) { liste.push({ niveau: 'erreur', message: 'La pièce « ' + piece + ' » porte à la fois ' + fs.join(' et ') + ' : masquer ou colorer l\'un affecte aussi l\'autre.' }); }
            });
            var deja = {};
            Object.keys(etat.glb.parNom).forEach(function (nom) {
                var f = famille(nom);
                if (!f || SEMELLES.indexOf(f) < 0) { return; }
                Object.keys(etat.glb.parNom[nom].pieces).forEach(function (piece) {
                    var cle = f + '|' + piece;
                    if (!deja[cle] && /st[eèé]le|plaque|tombe|monument/i.test(piece)) {
                        deja[cle] = true;
                        liste.push({ niveau: 'alerte', message: f + ' est appliqué à la pièce « ' + piece + ' », dont le nom évoque la stèle : à vérifier.' });
                    }
                });
            });
        }

        var boutons = boutonsSemelle();
        if (vivants.length && boutons.length) {
            boutons.forEach(function (b) {
                if (b.code === '') {
                    liste.push({ niveau: 'erreur', message: 'Le bouton « ' + b.libelle + ' » a un code vide en base (table semelle_types) : il ne peut correspondre à aucun matériau.' });
                } else if (b.materiaux === 0) {
                    liste.push({ niveau: 'erreur', message: 'Bouton « ' + b.libelle + ' » (code « ' + b.code + ' » en base) : aucun matériau SEMELLE… ne commence par « ' + b.code + ' ». Choisir ce type ne montre ni ne masque rien. Soit le nom des matériaux, soit le code du type de semelle (admin > Types de semelle) est à corriger.' });
                }
            });
            semellesGeneriques(boutons).forEach(function (nom) {
                liste.push({ niveau: 'alerte', message: '« ' + nom + ' » commence par SEMELLE mais par aucun code de bouton (' + boutons.map(function (b) { return b.code; }).join(', ') + ') : il est traité comme semelle générique, visible quel que soit le type choisi.' });
            });
        }

        groupes().forEach(function (g) {
            var f = famille(g.nom);
            if (f && !g.texture) {
                liste.push({ niveau: 'info', message: '« ' + g.nom + ' » n\'a pas de texture de couleur dans le fichier : si le changement de granit n\'apparaît pas sur cette pièce, c\'est une piste.' });
            }
        });

        return { liste: liste, compte: compte, boutons: boutons };
    }

    /* ---------- Rendu ---------- */

    function el(balise, classe, texte) {
        var e = document.createElement(balise);
        if (classe) { e.className = classe; }
        if (texte != null) { e.textContent = texte; }
        return e;
    }

    function dessinerJournal() {
        var zone = document.getElementById('eg-d-journal');
        if (!zone) { return; }
        zone.textContent = '';
        etat.journal.forEach(function (l) {
            zone.appendChild(el('div', 'eg-d-ligne eg-d-' + (l.type === 'mat' ? 'info' : l.type), l.t + '  ' + l.texte));
        });
        zone.scrollTop = zone.scrollHeight;
    }

    function surligner(nom) {
        var mats = materiauxNommes(nom);
        mats.forEach(function (m) {
            if (typeof m.setEmissiveFactor !== 'function') { return; }
            var avant = m.emissiveFactor ? Array.prototype.slice.call(m.emissiveFactor) : [0, 0, 0];
            m.setEmissiveFactor([1, 0, 1]);
            setTimeout(function () { m.setEmissiveFactor(avant); }, 1800);
        });
        log('action', '◎ Surlignage en magenta de « ' + nom + ' » (' + mats.length + ' matériau(x)) pendant 2 s : repérez sur le modèle la zone concernée.');
    }

    function basculerMasque(nom) {
        var mats = materiauxNommes(nom), masques = 0;
        etat.interne++;
        try {
            mats.forEach(function (m) {
                var pbr = m.pbrMetallicRoughness;
                if (!pbr) { return; }
                var f = Array.prototype.slice.call(pbr.baseColorFactor || [1, 1, 1, 1]);
                if (!m.__egMasque) {
                    m.__egMasque = { alpha: m.alphaMode, facteur: f };
                    m.setAlphaMode('BLEND');
                    pbr.setBaseColorFactor([f[0], f[1], f[2], 0]);
                    masques++;
                } else {
                    m.setAlphaMode(m.__egMasque.alpha);
                    pbr.setBaseColorFactor(m.__egMasque.facteur);
                    m.__egMasque = null;
                }
            });
        } finally { etat.interne--; }
        log('action', '◎ ' + (masques ? 'Masquage' : 'Réaffichage') + ' de « ' + nom + ' » (' + mats.length + ' matériau(x)).');
        rendre();
    }

    function rendre() {
        var vivants = materiauxVivants();
        var statut = document.getElementById('eg-d-etat');
        if (statut) {
            statut.textContent = etat.mv
                ? (vivants.length + ' matériau(x) · load reçu ' + etat.nbLoad + ' fois' + (etat.glb ? ' · fichier .glb analysé' : (etat.glbErreur ? ' · .glb illisible' : '')))
                : 'viewer introuvable';
        }

        var verdict = document.getElementById('eg-d-verdict');
        var table = document.getElementById('eg-d-table');
        if (!verdict || !table) { return; }
        verdict.textContent = '';
        table.textContent = '';

        if (!etat.mv) { verdict.appendChild(el('div', 'eg-d-erreur', 'Aucun élément <model-viewer> trouvé sur la page.')); return; }
        if (!vivants.length) { verdict.appendChild(el('div', 'eg-d-alerte', 'Modèle pas encore chargé (aucun matériau disponible).')); return; }

        var v = calculerVerdict();
        var familles = el('div', null);
        FAMILLES.forEach(function (f) {
            var present = v.compte[f] > 0;
            var ligne = el('div', present ? 'eg-d-ok' : 'eg-d-erreur', (present ? '✔ ' : '✖ ') + f + ' : ' + v.compte[f] + ' matériau(x)');
            familles.appendChild(ligne);
        });
        verdict.appendChild(familles);

        if (v.boutons.length) {
            verdict.appendChild(el('div', 'eg-d-titre', 'Boutons « Type de semelle » (codes lus dans la page)'));
            v.boutons.forEach(function (b) {
                var ok = b.code !== '' && b.materiaux > 0;
                verdict.appendChild(el('div', ok ? 'eg-d-ok' : 'eg-d-erreur',
                    (ok ? '✔ ' : '✖ ') + '« ' + b.libelle + ' » · code « ' + b.code + ' » → ' + b.materiaux + ' matériau(x) SEMELLE… dont le nom commence par ce code' + (b.selectionne ? ' · sélectionné' : '')));
            });
        } else {
            verdict.appendChild(el('div', 'eg-d-info', 'Aucun bouton « Type de semelle » sur la page (aucun type rattaché à ce monument) : toutes les semelles forment un seul groupe.'));
        }

        if (etat.glbErreur) { verdict.appendChild(el('div', 'eg-d-alerte', 'Lecture du .glb impossible (pièces non listées) : ' + etat.glbErreur)); }
        v.liste.forEach(function (p) {
            verdict.appendChild(el('div', 'eg-d-ligne eg-d-' + p.niveau, (p.niveau === 'erreur' ? '✖ ' : (p.niveau === 'alerte' ? '⚠ ' : 'ℹ ')) + p.message));
        });
        if (!v.liste.some(function (p) { return p.niveau === 'erreur'; })) {
            verdict.appendChild(el('div', 'eg-d-ok', '✔ Aucune erreur de nomenclature détectée.'));
        }

        var t = el('table', null);
        var tete = el('tr', null);
        ['Nom (entre guillemets)', 'Famille', 'Pièces (dans le .glb)', 'Tex.', 'Alpha', 'Couleur', ''].forEach(function (h) { tete.appendChild(el('th', null, h)); });
        t.appendChild(tete);

        groupes().forEach(function (g) {
            var f = famille(g.nom), a = alerte(g.nom), tr = el('tr', null);
            tr.appendChild(el('td', null, JSON.stringify(g.nom) + (g.n > 1 ? ' ×' + g.n : '')));

            var tdFam = el('td', null);
            if (f) {
                var puce = el('span', 'eg-d-puce', f);
                puce.style.background = COULEURS[f];
                tdFam.appendChild(puce);
            } else {
                tdFam.appendChild(el('span', a ? 'eg-d-erreur' : 'eg-d-info', a ? 'NON RECONNU' : '—'));
            }
            tr.appendChild(tdFam);

            var infos = etat.glb && etat.glb.parNom[g.nom];
            var pieces = infos ? Object.keys(infos.pieces) : [];
            tr.appendChild(el('td', null, etat.glb ? (pieces.length ? pieces.slice(0, 3).join(', ') + (pieces.length > 3 ? ' +' + (pieces.length - 3) : '') : '(aucune pièce)') : '…'));
            tr.appendChild(el('td', g.texture ? 'eg-d-ok' : 'eg-d-info', g.texture ? '✓' : '✗'));
            tr.appendChild(el('td', null, Object.keys(g.alpha).join('/')));
            tr.appendChild(el('td', null, g.facteur ? '[' + g.facteur.join(', ') + ']' : '—'));

            var tdBtn = el('td', null);
            var b1 = el('button', null, 'Surligner'); b1.type = 'button';
            b1.addEventListener('click', function () { surligner(g.nom); });
            var b2 = el('button', null, 'Masquer'); b2.type = 'button';
            b2.addEventListener('click', function () { basculerMasque(g.nom); });
            tdBtn.appendChild(b1); tdBtn.appendChild(document.createTextNode(' ')); tdBtn.appendChild(b2);
            tr.appendChild(tdBtn);
            t.appendChild(tr);
        });
        table.appendChild(t);
    }

    /* ---------- Rapport à envoyer ---------- */

    function rapport() {
        var v = calculerVerdict();
        return {
            genere_le: new Date().toISOString(),
            page: location.href,
            navigateur: navigator.userAgent,
            modele: { src: etat.mv ? (etat.mv.getAttribute('src') || etat.mv.src || null) : null, evenements_load: etat.nbLoad },
            glb: etat.glb ? { generateur: etat.glb.generateur, nb_materiaux: etat.glb.nbMateriaux, pieces_par_materiau: etat.glb.parNom, familles_par_piece: etat.glb.noeuds } : { erreur: etat.glbErreur },
            familles: v.compte,
            boutons_semelle: v.boutons,
            problemes: v.liste,
            materiaux: groupes(),
            journal: etat.journal
        };
    }
    etat.rapport = rapport;

    function copier() {
        var texte = JSON.stringify(rapport(), null, 2);
        var bouton = document.getElementById('eg-d-copier');
        function retour(ok) {
            if (bouton) { bouton.textContent = ok ? 'Copié ✓' : 'Copie impossible'; setTimeout(function () { bouton.textContent = 'Copier le rapport'; }, 1800); }
        }
        function secours() {
            var zone = document.createElement('textarea');
            zone.value = texte;
            document.body.appendChild(zone);
            zone.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(zone);
            retour(ok);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texte).then(function () { retour(true); }, secours);
        } else { secours(); }
    }

    /* ---------- Gestes de l'utilisateur ---------- */

    function libelleDe(el_) {
        var lab = el_.closest ? el_.closest('label') : null;
        if (!lab && el_.id) { lab = document.querySelector('label[for="' + el_.id + '"]'); }
        var texte = ((lab ? lab.textContent : '') || el_.getAttribute('aria-label') || el_.textContent || el_.value || '').replace(/\s+/g, ' ').trim().slice(0, 50);
        var id = el_.id ? '#' + el_.id : '';
        var cls = (el_.classList && el_.classList.length) ? '.' + Array.prototype.slice.call(el_.classList).slice(0, 2).join('.') : '';
        var suite = '';
        if (el_.type === 'checkbox' || el_.type === 'radio') { suite = el_.checked ? ' → coché' : ' → décoché'; }
        else if (el_.tagName === 'SELECT') { suite = ' → « ' + (el_.options[el_.selectedIndex] ? el_.options[el_.selectedIndex].text.trim() : '') + ' »'; }
        return el_.tagName.toLowerCase() + id + cls + (texte ? ' « ' + texte + ' »' : '') + suite;
    }

    function horsPanneau(cible) {
        return cible && cible.closest && !cible.closest('#eg-debug') && !cible.closest('.fi-sidebar') && !cible.closest('.fi-topbar');
    }

    document.addEventListener('click', function (e) {
        var c = e.target && e.target.closest ? e.target.closest('button, [role="button"], .config-granit-swatch, a') : null;
        if (c && horsPanneau(c)) { ouvrirAction('clic sur ' + libelleDe(c)); }
    }, true);

    document.addEventListener('change', function (e) {
        var c = e.target;
        if (c && c.matches && c.matches('input, select, textarea') && horsPanneau(c)) { ouvrirAction('changement de ' + libelleDe(c)); }
    }, true);

    /* ---------- Initialisation ---------- */

    function surLoad() {
        etat.nbLoad++;
        etat.chargeOk = true;
        var vivants = materiauxVivants();
        log('ok', 'Évènement « load » n°' + etat.nbLoad + ' : ' + vivants.length + ' matériau(x) disponible(s).');
        if (etat.nbLoad > 1) { log('alerte', '⚠ « load » reçu ' + etat.nbLoad + ' fois : si le site attache ses boutons à chaque « load », chaque action s\'exécute ' + etat.nbLoad + ' fois.'); }
        patcherMateriaux();
        rendre();

        var src = etat.mv.getAttribute('src') || etat.mv.src;
        if (src) {
            lireGlb(src).then(function (j) { etat.glb = analyserGlb(j); etat.glbErreur = null; log('ok', 'Fichier .glb analysé : ' + etat.glb.nbMateriaux + ' matériau(x) dans le fichier' + (etat.glb.generateur ? ' (généré par ' + etat.glb.generateur + ')' : '') + '.'); rendre(); })
                .catch(function (e) { etat.glb = null; etat.glbErreur = e && e.message ? e.message : String(e); log('alerte', 'Lecture du .glb impossible : ' + etat.glbErreur); rendre(); });
        }
    }

    function demarrer() {
        etat.mv = document.querySelector('#config-monument-viewer') || document.querySelector('model-viewer');
        if (!etat.mv) {
            log('erreur', '✖ Aucun élément <model-viewer> sur la page : pas de modèle 3D à diagnostiquer.');
            rendre();
            return;
        }
        log('info', 'Viewer trouvé (' + (etat.mv.id ? '#' + etat.mv.id : 'model-viewer') + '), src = ' + (etat.mv.getAttribute('src') || etat.mv.src || '(vide)'));

        etat.mv.addEventListener('load', surLoad);
        etat.mv.addEventListener('error', function (e) { log('erreur', '✖ Erreur de chargement du modèle : ' + ((e && e.detail && (e.detail.sourceError && e.detail.sourceError.message || e.detail.type)) || 'voir la console du navigateur') + '. Sans « load », les boutons du site ne sont jamais branchés.'); });

        if (etat.mv.loaded && etat.mv.model) { surLoad(); }
        else {
            rendre();
            setTimeout(function () {
                if (!etat.chargeOk) { log('alerte', '⚠ Aucun évènement « load » après 8 s. Si le modèle est visible mais que les options restent inertes, les écouteurs du site (posés au « load ») ne sont jamais branchés.'); }
            }, 8000);
        }
    }

    document.getElementById('eg-d-copier').addEventListener('click', copier);
    document.getElementById('eg-d-vider').addEventListener('click', function () { etat.journal = []; dessinerJournal(); });
    document.getElementById('eg-d-reduire').addEventListener('click', function () {
        etat.reduit = !etat.reduit;
        document.getElementById('eg-d-corps').style.display = etat.reduit ? 'none' : '';
        this.textContent = etat.reduit ? 'Agrandir' : 'Réduire';
    });

    log('info', 'Diagnostic activé (?debug=1). Cliquez sur les options du configurateur : chaque action est tracée ici et dans la console (préfixe [configurateur-debug]).');
    demarrer();
})();
</script>
@endif
