# Semelles — refonte (v12)

## Ce qui change

| Avant | Maintenant |
|---|---|
| 3 types (SEMELLE-B, SEMELLE-P, SEMELLE-R « à revers ») en boutons | **4 types** en liste déroulante « CODE : libellé » |
| Dimensions de semelle = simple liste (libellé) | Chaque dimension a une **référence** (ex. `SEMELLE-B-140X240`) et **un prix** par granit |
| Prix de la semelle absent des devis | Le prix de la semelle **s'ajoute** au prix du monument ; son poids entre dans le calcul du transport |

### Les 4 types
| Code | Libellé |
|---|---|
| `SEMELLE-B` | semelle bouchardée |
| `SEMELLE-P` | semelle plate |
| `SEMELLE-R-P` | semelle à revers d'eau poli (ancien `SEMELLE-R`, renommé : liens conservés) |
| `SEMELLE-R-B` | semelle à revers d'eau bouchardée (nouveau) |

### Les 12 références standard
3 tailles par type : `130 x 230`, `140 x 240` (**par défaut**), `150 x 250` →
`SEMELLE-B-130X230`, `SEMELLE-B-140X240`, `SEMELLE-B-150X250`, `SEMELLE-P-…`, `SEMELLE-R-P-…`, `SEMELLE-R-B-…`.
Référence = code du type + `LARGEURXLONGUEUR` (calculée automatiquement, modifiable, **non unique**, obligatoire).

## Administration d'un monument (onglets du monument)

1. **Types de semelle** : activer, monument par monument, les types proposés (comme avant) et le type par défaut.
2. **Dimensions de semelle prédéfinies** : ajouter une dimension (type, largeur, longueur, référence, libellé, « par défaut »).
   Le bouton **« Générer les références standard »** crée d'un coup les 3 tailles pour les types choisis (sans doublon).
   Une seule dimension « par défaut » par type et par monument (en cocher une décoche l'autre).
3. **Prix des semelles** (nouvel onglet) : une ligne par *(référence, granit)* : prix FOB, marge, poids de la semelle.
   Le tableau signale les références de la semelle qui n'ont pas encore de prix.

### Formule (identique à celle des granits)
`prix vente départ = arrondi_à_10( FOB + poids × transport/poids_conteneur + poids × transit/poids_conteneur + FOB × marge )`

Exemple (Excel `Sem140P5-7`) : FOB 375 €, marge 40 %, 324 kg → **620 €**.

## Import des prix de semelle

Page **Import des prix** → mode **« Prix d'une semelle (un prix par granit) »** → choisir le monument, la **référence** de la
semelle, coller les lignes Excel. L'analyse repère la cellule « 40% » : le prix FOB est 2 colonnes avant, le poids de la semelle
juste après. Vérifier l'écran de prévisualisation avant de valider. Le prix du monument n'est **pas** modifié.

## Devis (admin et agence)

* Nouveau champ **« Semelle »** (défaut : *Sans semelle*, pour ne rien changer aux devis existants). La liste affiche les dimensions
  du monument choisi ; si le prix de la référence manque pour le granit, un avertissement s'affiche et rien n'est ajouté.
* `prix total = prix du monument + prix de la semelle + transport` (transport calculé sur le poids monument + semelle) ; le coefficient
  société/agence s'applique ensuite comme avant.
* Le devis mémorise : type, dimension, **référence**, **libellé** et **prix** de la semelle (historique figé). Copiés dans la commande
  à la création.
* Les anciens devis ne changent pas (nouvelles colonnes vides).

## PDF du devis et commandes

* `resources/views/pdf/devis.blade.php` : bloc « Semelle » (libellé + référence) dans la configuration et ligne « Prix semelle » dans
  le tableau des prix, **uniquement si une semelle est choisie** (PDF inchangé sinon).
* `app/Filament/Resources/CommandeResource.php` (admin) : liste « Semelle » (dimensions du monument choisi, comme dans le devis) qui remplit
  automatiquement libellé, référence et prix (même calcul que le devis) ; « Référence de la semelle » et « Prix semelle » restent modifiables ;
  colonne « Semelle » (masquable) dans la liste. Changer de monument vide la semelle ; changer de granit recalcule le prix.
  Le prix final et le transport de la commande restent saisis à la main.
* `app/Filament/Agence/Resources/CommandeResource.php` (agence) : colonne « Semelle » (masquable) dans la liste.

## Configurateur (client)

Liste « Type de semelle » (« CODE : libellé ») + liste des dimensions du type choisi (+ celles valables pour tous les types).
La dimension **par défaut** du type est présélectionnée (« 140 x 240 (par défaut) »), au chargement et à chaque changement de type.
Le visuel 3D du devis reste inchangé.

> Les maillages 3D `SEMELLE-R-P` / `SEMELLE-R-B` n'existent pas encore dans les GLB : choisir ces types ne change donc pas
> l'affichage 3D tant que le modèle n'a pas de matériau de ce nom (la sélection et le prix fonctionnent).

## Base de données (migration `2026_11_16_120000_refonte_semelles`)

* `semelle_types` : `SEMELLE-R` → `SEMELLE-R-P` (même id), création de `SEMELLE-R-B`, libellés mis à jour.
* `monument_semelle_dimensions` : colonne `reference` (remplie pour l'existant).
* Nouvelle table `monument_semelle_prix` (unique : monument + granit + référence).
* `devis` et `commandes` : colonnes `semelle_*` et `prix_semelle` (nullables).
* `down()` : retire ce que `up()` a ajouté et remet `SEMELLE-R-P` en `SEMELLE-R`.
* Réentrante : peut être rejouée sans erreur.

## Hors périmètre / à venir

* `debug-panel.blade.php` : non modifié (liste interne `SEMELLE-B/P/R`, usage diagnostic uniquement).

## Tests réalisés (hors serveur)

Migration (up/down/up, idempotence, FK), calcul de prix admin + agence (**sans semelle : résultats identiques à l'ancien code**),
parseur (format monument inchangé + format semelle), règles du modèle (référence auto, un défaut par type), configurateur dans
Chromium (60 contrôles de non-régression + 4 types × dimensions par défaut) et widget du devis.
