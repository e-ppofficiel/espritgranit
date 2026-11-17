# Optimisation automatique des .glb (v13)

## Ce que ça fait
Quand un fichier 3D (.glb) est envoyé dans l'admin (Monuments → champ « Fichier 3D »), le site lance `tools/eg-glb optimiser` à
l'enregistrement : textures embarquées retirées (le site applique la texture du granit choisi) et UV remis à l'échelle réelle
(1 unité = 1 mètre). Résultat typique : 14,2 Mo → 58 Ko, et réglages de grain / brillance par granit actifs.

* **Original conservé** : `monuments/3d/<nom>.original.glb` (jamais écrasé).
* **Déjà optimisé** : le fichier est laissé tel quel (« rien à faire »).
* **Jamais bloquant** : si Node est absent, si le fichier est illisible ou si le délai (300 s) est dépassé, le monument est enregistré
  avec le fichier d'origine et un avertissement s'affiche (le configurateur fonctionne, sans réglage de grain ni de brillance).
* Déclenché uniquement à la **création** d'un monument avec fichier ou au **remplacement** du fichier ; enregistrer un monument sans
  changer son fichier ne relance rien.

## Monuments déjà en base
```bash
cd /home/espritgranit.com/public_html
php artisan glb:optimiser --a-blanc      # liste ce qui serait fait, ne modifie rien
php artisan glb:optimiser 12             # un seul monument (id)
php artisan glb:optimiser                # tous (demande confirmation)
php artisan glb:optimiser --restaurer 12 # remet l'original du monument 12 (--a-blanc pour vérifier qu'il existe)
```
⚠ Le rendu 3D des monuments traités change (grain et brillance par granit). Le fichier gardant le même nom, faire Ctrl+F5 si un
ancien rendu reste affiché. Lancer la commande avec l'utilisateur du site (`runuser -u espri7368 -- php artisan …`) pour que les
fichiers créés lui appartiennent.

## Réglages (.env / config/configurateur.php, bloc `glb`)
| Réglage | Rôle |
|---|---|
| `EG_GLB_AUTO=false` | désactive l'optimisation automatique (fichier enregistré tel quel) |
| `EG_NODE_BIN=/chemin/node` | chemin de Node si la détection automatique échoue (cherche /usr/bin/node, /usr/local/bin/node, le PATH) |
| `delai_secondes` | durée maximale d'une optimisation (300) |

Après modification du `.env` : `php artisan optimize:clear`.

## Dépannage
* Avertissement « Node.js est introuvable » : renseigner `EG_NODE_BIN` (résultat de `which node`).
* « Dépendances manquantes » : `cd tools/eg-glb && npm install`.
* Les avertissements « n'a pas de coordonnées UV » concernent des éléments décoratifs (plantes, peinture) : sans effet sur la pierre.
* Fichiers `.original.glb` : à supprimer à la main quand on n'en a plus besoin (économie de disque).

## Fichiers
`app/Services/GlbOptimiseur.php` (service), `app/Console/Commands/OptimiserGlb.php` (commande), `app/Models/Monument.php`
(déclenchement à l'enregistrement), `config/configurateur.php` (bloc `glb`), `app/Filament/Resources/MonumentResource.php` (texte d'aide).
Aucune migration.
