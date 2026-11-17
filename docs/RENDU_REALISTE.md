# Rendu réaliste du configurateur 3D

## Pourquoi la texture paraissait étirée
Dans le .glb de Sylver, chaque pièce a ses UV étalées de 0 à 1 : la photo de granit est « tendue » sur toute la pièce,
quelle que soit sa taille (une tuile mesurait de 75 cm à 250 cm selon la face, contre ~25-30 cm dans la réalité).
`tools/eg-glb optimiser` remet les UV à l'échelle réelle (1 unité UV = 1 mètre) ; le viewer répète ensuite la photo
`100 / taille_échantillon_cm` fois par mètre → même taille de grain partout.

## Mise en service (une fois)
1. `php artisan migrate` (ajoute `granits.echantillon_cm`, nullable).
2. Ajouter `'echantillon_cm'` au `$fillable` du modèle `App\Models\Granit` (si le modèle utilise `$fillable`).
3. Dans `GranitResource::form()`, après le champ `image` :
   ```php
   Forms\Components\TextInput::make('echantillon_cm')
       ->label('Taille réelle de l\'échantillon (cm)')
       ->helperText('Côté du carré photographié. Plus petit = grain plus fin sur le monument. Vide = 30 cm.')
       ->numeric()->minValue(5)->maxValue(200)->suffix('cm'),
   ```
4. Copier `config/configurateur.php` et `public/configurateur/studio.hdr` (éclairage studio, généré par `tools/eg-glb/env/generer_studio_hdr.py`).
5. `cd tools/eg-glb && npm install` (Node ≥ 20, sur votre poste ou sur le serveur).
6. Remplacer `viewer.blade.php`, `GlbInspector.php`, `InspecterGlb.php` par les versions livrées (ajouts uniquement).

## Brillance, luminosité, fond et éclairage (v6)
Mise en service : `php artisan migrate` (colonnes `granits.brillance` et `granits.luminosite`, vides par défaut), puis copier
`public/configurateur/studio-contraste.hdr`, `config/configurateur.php`, `Granit.php`, `GranitResource.php` et `viewer.blade.php`.

- **Brillance (fiche granit, 0 à 100)** : 0 = satiné, **100 = poli net, rendu standard** (ou vide). *(Jusqu'à la v10 : 50 = standard et 100 = miroir ; voir « Reflets nets (v11) ».)* Elle module la rugosité des
  surfaces polies (`GRANIT`, `BICOLOR`, `SEMELLE-P`) ; la bouchardée (`SEMELLE-B`) reste mate. Sans effet sur un .glb non optimisé.
  Formule : à `defaut` (100) on retrouve la rugosité de base ; en dessous, elle monte vers 0,55 à brillance 0 (rugosité = base + (0,55 − base) × (defaut − b) / defaut).
- **Luminosité (fiche granit, 50 à 150 %)** : 100 = texture inchangée (ou vide). Multiplie la couleur de la texture : utile pour
  éclaircir ou foncer un granit sans retoucher la photo.
- **Fond blanc / noir** : interrupteur « Fond blanc » dans le panneau de droite ; le choix est mémorisé dans le navigateur du visiteur.
  Fond à l'ouverture : `fond_defaut` (`noir` ou `blanc`) dans la config. Indépendant de `rendu_realiste`.
- **Éclairage** : `environnement` = `contraste` (défaut : sources dégradées et bandes verticales, reflets nets) ou `studio`
  (l'éclairage doux d'avant). Si le fichier est absent, le viewer retombe sur `studio.hdr`.
  Pour régénérer : `python3 tools/eg-glb/env/generer_studio_hdr.py sortie.hdr 1024 contraste` (ou `studio`).
  Le style `studio` régénéré est identique octet pour octet au fichier livré.
- Retour arrière : `'environnement' => 'studio'` (éclairage), `'rendu_realiste' => false` (tout le rendu réaliste, y compris brillance
  et luminosité ; le fond blanc/noir reste disponible).

### Curseurs de réglage dans le configurateur (v7, administrateurs)
Sur la page admin du configurateur (`/adminIkksPov/configurateur/{monument}`), le panneau « Réglages du granit (admin) » (replié par défaut) règle
en direct, pour le granit sélectionné : taille du grain, brillance, luminosité, plus un aperçu de l'éclairage (non enregistré).
- « Enregistrer pour ce granit » écrit `echantillon_cm`, `brillance` et `luminosite` (méthode `enregistrerReglagesGranit` de la page admin ;
  réservée aux rôles `super_admin`, `admin_esprit_granit`, `gestionnaire` ; valeurs bornées 5–200, 0–100, 50–150).
- Une valeur égale au standard (30 cm, 100, 100 %) est enregistrée « vide » : le granit suit alors les valeurs par défaut de la config.
- « Valeurs standard » remet les curseurs aux valeurs par défaut (à enregistrer ensuite si besoin).
- Sur un .glb non optimisé, grain et brillance sont désactivés (seule la luminosité agit).
- Le panneau n'existe pas côté agence (la page agence ne passe pas `peut-regler`).

## Pour chaque monument : optimiser le .glb
```
node tools/eg-glb/eg-glb.mjs optimiser storage/app/public/monuments/3d/MONUMENT.glb
# → MONUMENT.optimise.glb (textures embarquées retirées, UV en mètres) ; à réimporter dans le monument
php artisan glb:inspecter <id>   # « UV en mètres : oui »
```
`--a-blanc` affiche le rapport sans rien écrire. Un fichier déjà optimisé est refusé (pas de double correction).
Un .glb NON optimisé continue de fonctionner comme avant (pas d'échelle de grain, pas de sélection automatique).

## Pour chaque granit : texture tuilable
```
node tools/eg-glb/eg-glb.mjs tuilable Balmoral.jpg
# → Balmoral.tuilable.webp (2048², sans raccord, ~1 Mo) ; à importer comme image du granit
```
Le script affiche un contrôle chiffré du raccord (« ×1.0 » = invisible). Options : `--bande 0.3` (largeur du fondu),
`--sans-aplatir` (ne corrige pas le dégradé de lumière de la photo), `--taille`, `--qualite`.
Renseigner ensuite « Taille réelle de l'échantillon » dans la fiche du granit (30 cm par défaut).

### Mode lot : tout un dossier de photos
```
node tools/eg-glb/eg-glb.mjs tuilable --dossier storage/app/public/granits --a-blanc   # liste ce qui serait fait
node tools/eg-glb/eg-glb.mjs tuilable --dossier storage/app/public/granits             # traite tout
```
- Les originaux ne sont jamais modifiés : les textures vont dans `<dossier>/tuilables/` (`--sortie-dossier` pour changer).
- Non récursif ; ignore les fichiers déjà `*.tuilable.*` et les sorties déjà produites (`--forcer` pour les refaire).
- Un fichier illisible n'arrête pas le lot : il est signalé en erreur et le code de sortie vaut 1.
- Le résumé liste les textures « à vérifier » (raccord encore visible, ratio > ×1,5) : essayer `--bande 0.5` sur celles-ci.
- Une image **déjà sans raccord** (écart ≈ ×1,0, par exemple une texture déjà passée par l'outil) est convertie sans fondu ; `--forcer-fondu` pour traiter quand même.

### Appliquer les textures aux granits : `php artisan granits:tuilables`
Les textures du lot sont nommées d'après l'image de chaque granit (`granits/Carrara.jpg` → `granits/tuilables/Carrara.tuilable.webp`) :
la commande les retrouve et remplace l'image en base.
```
php artisan granits:tuilables              # SIMULATION : tableau de ce qui serait remplacé, rien n'est modifié
php artisan granits:tuilables --appliquer  # remplace l'image des granits (vignettes régénérées par le modèle)
php artisan granits:tuilables --granit=4   # limiter à un granit (option répétable)
php artisan granits:tuilables --annuler    # restaure les anciennes images (dernière application)
```
Statuts : `à remplacer` / `remplacé`, `déjà tuilable` (rien à faire), `texture absente` (le lot n'a pas produit de fichier),
`ambigu` (deux images de même nom d'extensions différentes : à traiter à la main). Les fichiers originaux ne sont jamais supprimés ;
les anciens chemins sont enregistrés dans `storage/app/backups/granits-tuilables-<date>.json`.
Penser ensuite à régler « Taille réelle de l'échantillon » (cm) de chaque granit (30 cm par défaut).

## Node.js sur le serveur (une fois)
```
cat /etc/os-release | head -3
# AlmaLinux / Rocky 8 ou 9 :
dnf module reset -y nodejs && dnf module enable -y nodejs:22 && dnf install -y nodejs npm
# AlmaLinux / Rocky 10 (pas de modules) :
dnf install -y nodejs npm
# Ubuntu / Debian (méthode NodeSource) :
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt-get install -y nodejs
node -v   # v22.x attendu (20 minimum)
cd /home/espritgranit.com/public_html/tools/eg-glb && npm install
chown -R espri7368:espri7368 /home/espritgranit.com/public_html/tools
```

## Réglages (config/configurateur.php)
- `rendu_realiste` : `false` (ou `CONFIGURATEUR_RENDU_REALISTE=false` dans .env) = ancien rendu à l'identique.
- `exposition` : luminosité globale (1.5).
- `environnement` / `environnements` : éclairage (`contraste` ou `studio`), voir plus haut.
- `fond_defaut` : `noir` ou `blanc`.
- `brillance` : `defaut` (100), `facteur_miroir` (0.15, sans effet tant que defaut = 100), `rugosite_mate` (0.55).
- `echantillon_defaut_cm` : 30. Plus petit = grain plus fin.
- `finitions` : rugosité par famille (0 miroir … 1 mat) : GRANIT 0.04, BICOLOR 0.03, SEMELLE-P 0.10, SEMELLE-B 0.85 ;
  `'brillance' => true` indique que la brillance du granit s'applique à la famille.

## Visuel des devis (aperçu 3D du formulaire Devis)
Le widget `filament/forms/components/devis-visuel-capture.blade.php` reprend le rendu du configurateur : même éclairage
(`environnement`, `exposition`), même taille de grain (échantillon par granit), mêmes finitions, brillance et luminosité par granit,
et le soubassement (BICOLOR) reçoit la texture du granit. Il lit les réglages via `getDonneesMonumentPourVisuel()` (clé `reglages`).
- Uniquement pour les `.glb` optimisés (`eg-glb optimiser`) ; un ancien `.glb` garde son rendu d'avant (seule la luminosité d'un granit, si elle n'est pas à 100 %, multiplie sa teinte).
- `rendu_realiste => false` : ancien rendu à l'identique.
- Les visuels déjà enregistrés dans des devis sont des images : ils ne changent pas. Il faut recapturer la vue pour obtenir le nouveau rendu.

## Ambiances (reflets d'un paysage sur la pierre)
Le sélecteur « Ambiance » (panneau d'options du configurateur, visible par tous les visiteurs) change l'image d'environnement : le paysage se
reflète sur la pierre polie et éclaire le monument. Le choix est mémorisé dans le navigateur du visiteur (`eg_configurateur_ambiance`).
- Réglages : `config/configurateur.php`, clés `ambiances` (fichier, libellé, exposition de chaque ambiance) et `ambiance_defaut_libelle`.
  « Studio » (l'éclairage par défaut, clé `environnement`) est toujours proposé en premier.
- Images : `public/configurateur/ambiances/*.hdr`, photos HDRI Poly Haven (licence CC0 : usage commercial autorisé, mention non obligatoire),
  réduites à 1536×768 (3,3 à 4,3 Mo, chargées seulement quand le visiteur choisit l'ambiance).
- Ajouter une ambiance : `python3 tools/eg-glb/env/convertir_hdri.py source_2k.hdr public/configurateur/ambiances/nom.hdr`, puis une ligne dans `ambiances`.
  L'éclairage d'une photo dépend de sa luminosité : régler `exposition` en regardant un granit clair et un granit noir (viser un niveau comparable aux autres ambiances).
- Retour arrière : `'ambiances' => []` masque le sélecteur ; `rendu_realiste => false` désactive tout le rendu réaliste.
- Le visuel des devis (`resources/views/filament/forms/components/devis-visuel-capture.blade.php`) garde toujours l'éclairage par défaut.
- Les reflets d'un paysage restent ceux d'une image de 1536×768 : on voit nettement les masses (ciel, feuillages, troncs), pas une photo.
- L'ancien menu « Éclairage (aperçu) » du panneau de réglages admin est remplacé par ce sélecteur.

## Reflets nets (v11)
Les reflets étaient trop flous et peu visibles. Changements :
- Rugosité de base réduite : GRANIT 0.12 → **0.04**, BICOLOR 0.08 → **0.03**, SEMELLE-P 0.30 → **0.10** (`finitions` dans `config/configurateur.php`).
  Cela rend aussi l'éclairage « Studio » et le visuel des devis plus brillants (ils lisent la même config).
- Échelle de brillance : `brillance.defaut` passe de 50 à **100**. La rugosité minimale rendue par model-viewer (three.js) est d'environ 0,05 :
  l'ancienne moitié haute du curseur (50 → 100, jusqu'à base × 0,15) n'aurait plus rien changé. Désormais 100 = poli net et 0 = satiné, sur toute la course.
- Les valeurs de brillance déjà enregistrées avec l'ancienne échelle paraîtront plus satinées. Les relire :
  `SELECT id, nom_commercial, brillance FROM granits WHERE brillance IS NOT NULL;` Les granits « vides » (standard) ne changent pas de sens et deviennent simplement plus brillants. Une valeur enregistrée supérieure à 50 (ancien « plus brillant que standard ») rend désormais moins brillant : la remonter vers 100. Une valeur inférieure à 50 reste satinée, un peu plus qu'avant.
- Images d'ambiance 1536×768 au lieu de 1024×512 (`convertir_hdri.py`, largeur par défaut 1536).
- Retour arrière : remettre les anciennes rugosités (commentées dans `finitions`) et `'defaut' => 50`.

## Limites connues
- Les veines ne se raccordent pas d'une face à l'autre (il faudrait un shader sur mesure, hors de portée de model-viewer).
- La taille réelle de l'échantillon est une estimation (30 cm) à affiner à l'œil, granit par granit.
- Rugosité et éclairage : valeurs de départ à valider visuellement sur votre écran.
