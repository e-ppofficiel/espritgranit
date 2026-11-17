#!/usr/bin/env node
/**
 * eg-glb — outils en ligne de commande pour le configurateur 3D EspritGranit.
 *
 *   node eg-glb.mjs optimiser <fichier.glb> [--sortie sortie.glb] [--garder-draco] [--a-blanc]
 *   node eg-glb.mjs tuilable  <photo.jpg>   [--sortie sortie.webp] [--taille 2048] [--bande 0.3] [--qualite 88] [--sans-aplatir] [--forcer-fondu]
 *   node eg-glb.mjs tuilable  --dossier <dossier> [--sortie-dossier <dossier>] [--forcer] [--a-blanc] (mode lot : toutes les images du dossier)
 *
 * optimiser : retire les textures embarquées (le site applique la texture du granit choisi),
 *             convertit les textures uniformes de rugosité/métal en facteurs, et remet les
 *             coordonnées UV À L'ÉCHELLE RÉELLE (1 unité UV = 1 mètre) pour que la texture
 *             de granit ait la même taille de grain sur toutes les faces.
 * tuilable  : transforme une photo de granit en texture sans raccord (répétable), carrée, en WebP.
 */
import { NodeIO } from '@gltf-transform/core';
import { KHRDracoMeshCompression } from '@gltf-transform/extensions';
import draco3d from 'draco3dgltf';
import fs from 'node:fs';
import path from 'node:path';

/** Repère écrit dans asset.extras : le viewer sait ainsi que les UV sont en mètres. */
export const MARQUEUR = 'eg_uv_metres';
const VERSION = 1;

function aide(code = 0) {
  console.log(`Usage :
  node eg-glb.mjs optimiser <fichier.glb> [--sortie <fichier.glb>] [--garder-draco] [--a-blanc]
  node eg-glb.mjs tuilable  <photo>       [--sortie <fichier.webp>] [--taille 2048] [--bande 0.3] [--qualite 88] [--sans-aplatir]
  node eg-glb.mjs tuilable  --dossier <dossier> [--sortie-dossier <dossier>] [--forcer] [--a-blanc]

  --a-blanc       (optimiser) n'écrit rien : affiche seulement ce qui serait fait
  --dossier       (tuilable) traite toutes les images du dossier ; sortie dans <dossier>/tuilables/ par défaut
  --forcer        (tuilable --dossier) refait aussi les fichiers déjà produits
  --garder-draco  (optimiser) recompresse la géométrie en Draco (par défaut : non compressée, plus simple à charger)`);
  process.exit(code);
}

function lireArgs(argv) {
  const pos = [], opt = {};
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a.startsWith('--')) {
      const cle = a.slice(2);
      const suivant = argv[i + 1];
      if (suivant !== undefined && !suivant.startsWith('--')) { opt[cle] = suivant; i++; } else opt[cle] = true;
    } else pos.push(a);
  }
  return { pos, opt };
}

const ko = (o) => (o / 1024).toFixed(o > 1048576 ? 0 : 1);
const taille = (o) => (o >= 1048576 ? (o / 1048576).toFixed(2) + ' Mo' : (o / 1024).toFixed(0) + ' Ko');

/* ───────────────────────── optimiser ───────────────────────── */

async function creerIO(avecEncodeur) {
  const io = new NodeIO().registerExtensions([KHRDracoMeshCompression]);
  const deps = { 'draco3d.decoder': await draco3d.createDecoderModule() };
  if (avecEncodeur) deps['draco3d.encoder'] = await draco3d.createEncoderModule();
  return io.registerDependencies(deps);
}

/** Matrice monde d'un nœud : le tableau est en colonnes (gl-matrix), comme glTF. */
function versMonde(W, x, y, z) {
  return [
    W[0] * x + W[4] * y + W[8] * z + W[12],
    W[1] * x + W[5] * y + W[9] * z + W[13],
    W[2] * x + W[6] * y + W[10] * z + W[14],
  ];
}

/** Mètres par unité UV (u, v) d'une primitive, pondérés par la surface. null si impossible. */
function metresParUV(prim, W) {
  const pos = prim.getAttribute('POSITION');
  const uv = prim.getAttribute('TEXCOORD_0');
  if (!pos || !uv) return null;
  const P = pos.getArray(), U = uv.getArray();
  const idx = prim.getIndices();
  const I = idx ? idx.getArray() : null;
  const n = pos.getCount();
  const w = new Float64Array(n * 3);
  for (let i = 0; i < n; i++) {
    const p = versMonde(W, P[i * 3], P[i * 3 + 1], P[i * 3 + 2]);
    w[i * 3] = p[0]; w[i * 3 + 1] = p[1]; w[i * 3 + 2] = p[2];
  }
  const tri = I ? I.length / 3 : n / 3;
  let A = 0, Lu = 0, Lv = 0;
  const minU = [1e9, 1e9], maxU = [-1e9, -1e9];
  for (let t = 0; t < tri; t++) {
    const a = I ? I[t * 3] : t * 3, b = I ? I[t * 3 + 1] : t * 3 + 1, c = I ? I[t * 3 + 2] : t * 3 + 2;
    const ab = [0, 1, 2].map((k) => w[b * 3 + k] - w[a * 3 + k]);
    const ac = [0, 1, 2].map((k) => w[c * 3 + k] - w[a * 3 + k]);
    const cr = [ab[1] * ac[2] - ab[2] * ac[1], ab[2] * ac[0] - ab[0] * ac[2], ab[0] * ac[1] - ab[1] * ac[0]];
    const a3 = Math.hypot(cr[0], cr[1], cr[2]) / 2;
    if (a3 < 1e-12) continue;
    const du1 = U[b * 2] - U[a * 2], dv1 = U[b * 2 + 1] - U[a * 2 + 1];
    const du2 = U[c * 2] - U[a * 2], dv2 = U[c * 2 + 1] - U[a * 2 + 1];
    const det = du1 * dv2 - du2 * dv1;
    if (Math.abs(det) < 1e-15) continue;
    const Pu = [0, 1, 2].map((k) => (ab[k] * dv2 - ac[k] * dv1) / det); // dP/du
    const Pv = [0, 1, 2].map((k) => (ac[k] * du1 - ab[k] * du2) / det); // dP/dv
    Lu += Math.hypot(Pu[0], Pu[1], Pu[2]) * a3;
    Lv += Math.hypot(Pv[0], Pv[1], Pv[2]) * a3;
    A += a3;
  }
  if (A <= 0) return null;
  return { u: Lu / A, v: Lv / A };
}

async function optimiser(fichier, opt) {
  if (!fichier || !fs.existsSync(fichier)) { console.error('Fichier introuvable : ' + fichier); process.exit(1); }
  const sortie = typeof opt.sortie === 'string'
    ? opt.sortie
    : path.join(path.dirname(fichier), path.basename(fichier, path.extname(fichier)) + '.optimise.glb');
  if (path.resolve(sortie) === path.resolve(fichier)) { console.error('La sortie doit être un autre fichier que l\'original.'); process.exit(1); }

  const avantOctets = fs.statSync(fichier).size;
  const io = await creerIO(!!opt['garder-draco']);
  const doc = await io.read(fichier);
  const racine = doc.getRoot();

  const deja = racine.getExtras()?.[MARQUEUR];
  if (deja) { console.log('Ce fichier est déjà optimisé (UV en mètres). Rien à faire.'); return; }

  const rapport = [];
  const avertissements = [];

  // 1) UV à l'échelle réelle
  const meshesVus = new Map();
  for (const noeud of racine.listNodes()) {
    const mesh = noeud.getMesh();
    if (!mesh) continue;
    const parents = mesh.listParents().filter((p) => p.propertyType === 'Node');
    meshesVus.set(mesh, (meshesVus.get(mesh) || 0) + 1);
    if (parents.length > 1) {
      avertissements.push(`Le maillage « ${mesh.getName() || '(sans nom)'} » est partagé par ${parents.length} nœuds : UV non modifiées (échelle potentiellement différente).`);
      continue;
    }
    const W = noeud.getWorldMatrix();
    for (const prim of mesh.listPrimitives()) {
      const nomMat = prim.getMaterial()?.getName() || '(sans matériau)';
      const uv = prim.getAttribute('TEXCOORD_0');
      if (!uv) { avertissements.push(`« ${nomMat} » (${noeud.getName()}) n'a pas de coordonnées UV : aucune texture ne pourra s'y appliquer.`); continue; }
      const m = metresParUV(prim, W);
      if (!m) { avertissements.push(`« ${nomMat} » (${noeud.getName()}) : surface UV dégénérée, UV non modifiées.`); continue; }
      const U = uv.getArray();
      for (let i = 0; i < uv.getCount(); i++) { U[i * 2] *= m.u; U[i * 2 + 1] *= m.v; }
      uv.setArray(U);
      rapport.push({ materiau: nomMat, noeud: noeud.getName(), avant: `${(m.u * 100).toFixed(0)} × ${(m.v * 100).toFixed(0)} cm`, ratio: m.u / m.v });
    }
  }

  // 2) textures embarquées → facteurs / suppression
  let nbTex = 0, octetsTex = 0;
  for (const t of racine.listTextures()) { nbTex++; octetsTex += t.getImage()?.byteLength || 0; }
  for (const mat of racine.listMaterials()) {
    const mr = mat.getMetallicRoughnessTexture();
    if (mr) {
      const echantillon = await moyenneMR(mr.getImage());
      if (echantillon) { mat.setRoughnessFactor(echantillon.rugosite); mat.setMetallicFactor(echantillon.metal); }
    }
    mat.setBaseColorTexture(null);
    mat.setMetallicRoughnessTexture(null);
    mat.setNormalTexture(null);
    mat.setOcclusionTexture(null);
    mat.setEmissiveTexture(null);
  }
  for (const t of racine.listTextures()) t.dispose();

  // 3) marqueur lisible par le viewer
  racine.setExtras({ ...(racine.getExtras() || {}), [MARQUEUR]: VERSION });

  // 4) sortie (par défaut sans Draco : la géométrie sans textures est minuscule)
  if (!opt['garder-draco']) {
    for (const ext of racine.listExtensionsUsed()) if (ext.extensionName === 'KHR_draco_mesh_compression') ext.dispose();
  }

  console.log(`\nMatériaux traités (échelle UV avant correction) :`);
  for (const r of rapport) console.log(`  ${r.materiau.padEnd(14)} ${String(r.noeud).padEnd(28)} 1 tuile = ${r.avant}`);
  console.log(`\nTextures embarquées retirées : ${nbTex} image(s), ${taille(octetsTex)}`);
  for (const a of avertissements) console.log('⚠ ' + a);

  if (opt['a-blanc']) { console.log('\n(--a-blanc) Aucun fichier écrit.'); return; }
  await io.write(sortie, doc);
  const apresOctets = fs.statSync(sortie).size;
  console.log(`\n${fichier}  (${taille(avantOctets)})\n→ ${sortie}  (${taille(apresOctets)})  soit ${(100 - (apresOctets / avantOctets) * 100).toFixed(1)} % de moins`);
  console.log('Importez ce fichier dans le monument à la place de l\'original. Les UV sont désormais en mètres.');
}

async function moyenneMR(buffer) {
  try {
    const sharp = (await import('sharp')).default;
    const { data, info } = await sharp(Buffer.from(buffer)).removeAlpha().raw().toBuffer({ resolveWithObject: true });
    const n = info.width * info.height;
    let g = 0, b = 0, gmin = 255, gmax = 0, bmin = 255, bmax = 0;
    for (let i = 0; i < n; i++) {
      const G = data[i * 3 + 1], B = data[i * 3 + 2];
      g += G; b += B; gmin = Math.min(gmin, G); gmax = Math.max(gmax, G); bmin = Math.min(bmin, B); bmax = Math.max(bmax, B);
    }
    if (gmax - gmin > 12 || bmax - bmin > 12) return null; // texture non uniforme : on ne simplifie pas
    return { rugosite: +(g / n / 255).toFixed(3), metal: +(b / n / 255).toFixed(3) };
  } catch { return null; }
}

/* ───────────────────────── tuilable ───────────────────────── */

function fenetre(n, bande) {
  // 0 aux bords, 1 au centre, transition en cosinus sur « bande » (fraction de n) de chaque côté
  const w = new Float32Array(n);
  const b = Math.max(2, Math.round(n * bande / 2));
  for (let i = 0; i < n; i++) {
    const d = Math.min(i, n - 1 - i);
    w[i] = d >= b ? 1 : 0.5 - 0.5 * Math.cos((Math.PI * d) / b);
  }
  return w;
}

/** Fabrique une texture sans raccord. Renvoie les chiffres de contrôle (pas d'affichage). */
const SEUIL_DEJA_SANS_RACCORD = 1.3; // une photo brute est à ×2,4 ou plus ; une texture déjà tuilable est à ~×1,0

async function fabriquerTuilable(fichier, sortie, { N, bande, qualite, aplatir, forcerFondu }) {
  const sharp = (await import('sharp')).default;

  // carré central, redimensionné
  const meta = await sharp(fichier).metadata();
  const c = Math.min(meta.width, meta.height);
  const { data } = await sharp(fichier).rotate().extract({ left: Math.floor((meta.width - c) / 2), top: Math.floor((meta.height - c) / 2), width: c, height: c })
    .resize(N, N, { kernel: 'lanczos3' }).removeAlpha().raw().toBuffer({ resolveWithObject: true });
  const src = Float32Array.from(data);

  // contrôle chiffré : écart bord/bord comparé à l'écart entre pixels voisins
  const g = (buf, x, y) => (buf[(y * N + x) * 3] + buf[(y * N + x) * 3 + 1] + buf[(y * N + x) * 3 + 2]) / 3;
  const ecart = (buf) => {
    let bord = 0, voisin = 0;
    for (let i = 0; i < N; i++) {
      bord += Math.abs(g(buf, 0, i) - g(buf, N - 1, i)) + Math.abs(g(buf, i, 0) - g(buf, i, N - 1));
      voisin += Math.abs(g(buf, 0, i) - g(buf, 1, i)) + Math.abs(g(buf, i, 0) - g(buf, i, 1));
    }
    return { bord: bord / (2 * N), voisin: voisin / (2 * N) };
  };
  const avant = ecart(Buffer.from(data));
  const ratioAvant = avant.bord / Math.max(avant.voisin, 1e-6);

  // Texture déjà sans raccord (ex. un fichier déjà passé par cet outil) : on la convertit sans la re-fondre,
  // sinon le fondu superposerait deux copies de l'image et dégraderait le résultat.
  if (ratioAvant <= SEUIL_DEJA_SANS_RACCORD && !forcerFondu) {
    fs.mkdirSync(path.dirname(sortie), { recursive: true });
    await sharp(Buffer.from(data), { raw: { width: N, height: N, channels: 3 } }).webp({ quality: qualite }).toFile(sortie);
    return {
      avant, apres: avant, ratioAvant, ratioApres: ratioAvant, dejaSansRaccord: true,
      octets: fs.statSync(sortie).size, largeurSource: meta.width, hauteurSource: meta.height,
    };
  }

  // 1) aplatir l'éclairage (dégradé basse fréquence = cause n°1 de raccords visibles)
  if (aplatir) {
    const flou = await sharp(Buffer.from(data), { raw: { width: N, height: N, channels: 3 } }).blur(Math.max(8, N / 8)).raw().toBuffer();
    const moy = [0, 0, 0];
    for (let i = 0; i < N * N; i++) for (let k = 0; k < 3; k++) moy[k] += flou[i * 3 + k];
    for (let k = 0; k < 3; k++) moy[k] /= N * N;
    for (let i = 0; i < N * N; i++) for (let k = 0; k < 3; k++) src[i * 3 + k] = Math.min(255, Math.max(0, (src[i * 3 + k] / Math.max(1, flou[i * 3 + k])) * moy[k]));
  }

  // 2) décalage de moitié (les bords deviennent continus, le raccord passe au centre),
  //    puis fondu entre l'original (centre) et la version décalée (bords)
  const wx = fenetre(N, bande);
  const sortieBuf = Buffer.alloc(N * N * 3);
  const h = N >> 1;
  for (let y = 0; y < N; y++) {
    const yy = (y + h) % N;
    for (let x = 0; x < N; x++) {
      const xx = (x + h) % N;
      const w = wx[x] * wx[y];
      for (let k = 0; k < 3; k++) {
        const v = src[(y * N + x) * 3 + k] * w + src[(yy * N + xx) * 3 + k] * (1 - w);
        sortieBuf[(y * N + x) * 3 + k] = Math.round(v);
      }
    }
  }
  fs.mkdirSync(path.dirname(sortie), { recursive: true });
  await sharp(sortieBuf, { raw: { width: N, height: N, channels: 3 } }).webp({ quality: qualite }).toFile(sortie);

  // 3) contrôle chiffré après traitement
  const apres = ecart(sortieBuf);
  return {
    avant, apres, ratioAvant, dejaSansRaccord: false,
    ratioApres: apres.bord / Math.max(apres.voisin, 1e-6),
    octets: fs.statSync(sortie).size,
    largeurSource: meta.width, hauteurSource: meta.height,
  };
}

function optionsTuilable(opt) {
  return {
    N: Math.max(256, parseInt(opt.taille || '2048', 10)),
    bande: Math.min(0.9, Math.max(0.05, parseFloat(opt.bande || '0.3'))),
    qualite: parseInt(opt.qualite || '88', 10),
    aplatir: !opt['sans-aplatir'],
    forcerFondu: !!opt['forcer-fondu'],
  };
}

async function tuilable(fichier, opt) {
  if (opt.dossier) return tuilableLot(typeof opt.dossier === 'string' ? opt.dossier : fichier, opt);
  if (!fichier || !fs.existsSync(fichier)) { console.error('Image introuvable : ' + fichier); process.exit(1); }
  const sortie = typeof opt.sortie === 'string'
    ? opt.sortie
    : path.join(path.dirname(fichier), path.basename(fichier, path.extname(fichier)) + '.tuilable.webp');

  const r = await fabriquerTuilable(fichier, sortie, optionsTuilable(opt));
  if (r.dejaSansRaccord) {
    console.log(`Cette image est déjà sans raccord (×${r.ratioAvant.toFixed(1)}) : convertie sans fondu. (--forcer-fondu pour traiter quand même)`);
    console.log(`→ ${sortie}  (${optionsTuilable(opt).N}×${optionsTuilable(opt).N}, ${taille(r.octets)})`);
    return;
  }
  console.log(`Raccord (écart bord-à-bord / écart entre pixels voisins) :`);
  console.log(`  avant : ${r.avant.bord.toFixed(1)} / ${r.avant.voisin.toFixed(1)}  (×${r.ratioAvant.toFixed(1)})`);
  console.log(`  après : ${r.apres.bord.toFixed(1)} / ${r.apres.voisin.toFixed(1)}  (×${r.ratioApres.toFixed(1)})  — proche de ×1 = sans raccord visible`);
  console.log(`→ ${sortie}  (${optionsTuilable(opt).N}×${optionsTuilable(opt).N}, ${taille(r.octets)})`);
  console.log('Importez ce fichier comme image du granit (il sert aussi de vignette).');
}

/* ───────────────────────── tuilable --dossier (mode lot) ───────────────────────── */

const EXTENSIONS_IMAGE = new Set(['.jpg', '.jpeg', '.png', '.webp', '.tif', '.tiff']);

async function tuilableLot(dossier, opt) {
  if (!dossier || !fs.existsSync(dossier) || !fs.statSync(dossier).isDirectory()) {
    console.error('Dossier introuvable : ' + dossier);
    process.exit(1);
  }
  const sortieDossier = typeof opt['sortie-dossier'] === 'string' ? opt['sortie-dossier'] : path.join(dossier, 'tuilables');
  const forcer = !!opt.forcer;
  const options = optionsTuilable(opt);

  const fichiers = fs.readdirSync(dossier, { withFileTypes: true })
    .filter((e) => e.isFile() && EXTENSIONS_IMAGE.has(path.extname(e.name).toLowerCase()))
    .map((e) => e.name)
    .sort((a, b) => a.localeCompare(b));

  if (!fichiers.length) { console.log('Aucune image (jpg, png, webp, tif) dans ' + dossier); return; }
  console.log(`${fichiers.length} image(s) dans ${dossier}\nSortie : ${sortieDossier}  (les originaux ne sont jamais modifiés)\n`);

  const lignes = [];
  const sortiesPrises = new Set();
  let i = 0;
  for (const nom of fichiers) {
    i++;
    const base = path.basename(nom, path.extname(nom));
    const entree = path.join(dossier, nom);
    // deux fichiers de même nom et d'extensions différentes (a.jpg / a.png) : le second garde son extension
    let sortie = path.join(sortieDossier, base + '.tuilable.webp');
    if (sortiesPrises.has(sortie)) sortie = path.join(sortieDossier, nom + '.tuilable.webp');
    sortiesPrises.add(sortie);
    const etiquette = `[${String(i).padStart(String(fichiers.length).length)}/${fichiers.length}] ${nom}`;

    if (/\.tuilable$/i.test(base)) { lignes.push({ nom, statut: 'ignoré', detail: 'déjà une texture tuilable' }); console.log(`${etiquette} … ignoré (déjà tuilable)`); continue; }
    if (fs.existsSync(sortie) && !forcer) { lignes.push({ nom, statut: 'ignoré', detail: 'sortie existante (--forcer pour refaire)' }); console.log(`${etiquette} … ignoré (sortie existante)`); continue; }
    if (opt['a-blanc']) { lignes.push({ nom, statut: 'à faire', detail: '' }); console.log(`${etiquette} … à faire`); continue; }

    process.stdout.write(`${etiquette} … `);
    try {
      const r = await fabriquerTuilable(entree, sortie, options);
      const net = r.ratioApres <= 1.5;
      const texte = r.dejaSansRaccord
        ? `ok (déjà sans raccord ×${r.ratioAvant.toFixed(1)} : converti sans fondu, ${taille(r.octets)})`
        : `${net ? 'ok' : 'à vérifier'} (raccord ×${r.ratioAvant.toFixed(1)} → ×${r.ratioApres.toFixed(1)}, ${taille(r.octets)})`;
      console.log(texte);
      lignes.push({ nom, statut: net ? 'ok' : 'à vérifier', detail: `×${r.ratioAvant.toFixed(1)} → ×${r.ratioApres.toFixed(1)}`, taille: taille(r.octets), source: `${r.largeurSource}×${r.hauteurSource}` });
    } catch (erreur) {
      console.log('ERREUR : ' + (erreur.message || erreur).toString().split('\n')[0]);
      lignes.push({ nom, statut: 'erreur', detail: (erreur.message || String(erreur)).split('\n')[0] });
    }
  }

  const compte = (st) => lignes.filter((l) => l.statut === st).length;
  console.log(`\nRésumé : ${compte('ok')} ok, ${compte('à vérifier')} à vérifier, ${compte('ignoré')} ignoré(s), ${compte('erreur')} erreur(s)` + (opt['a-blanc'] ? `, ${compte('à faire')} à faire (--a-blanc : rien n'a été écrit)` : ''));
  const aVerifier = lignes.filter((l) => l.statut === 'à vérifier');
  if (aVerifier.length) {
    console.log('\nÀ vérifier à l\'œil (raccord encore visible : granit à veines ou photo très inégale ; essayez --bande 0.5) :');
    for (const l of aVerifier) console.log('  - ' + l.nom + '  ' + l.detail);
  }
  if (compte('ok') + compte('à vérifier')) {
    console.log(`\nFichiers produits dans : ${sortieDossier}\nImportez-les comme image de chaque granit, puis renseignez la taille de l'échantillon dans l'admin.`);
  }
  if (compte('erreur')) process.exitCode = 1;
}

/* ───────────────────────── main ───────────────────────── */

const [cmd, ...reste] = process.argv.slice(2);
const { pos, opt } = lireArgs(reste);
if (!cmd || cmd === '--aide' || cmd === 'aide' || cmd === '-h') aide(0);
if (cmd === 'optimiser') await optimiser(pos[0], opt);
else if (cmd === 'tuilable') await tuilable(pos[0], opt);
else aide(1);
