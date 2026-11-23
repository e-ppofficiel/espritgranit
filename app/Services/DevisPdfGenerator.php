<?php

namespace App\Services;

use App\Models\Devis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DevisPdfGenerator
{
    /**
     * Fond fixe (RVB) sur lequel les visuels sont posés dans le PDF : un gris très clair, quel que soit le fond
     * choisi dans le configurateur. Les captures sont des PNG transparents : sans cette étape, la conversion
     * en JPEG remplace la transparence par du noir.
     */
    protected const FOND_VISUEL = [243, 244, 246];

    /**
     * Génère et retourne la réponse HTTP de téléchargement du PDF pour ce devis.
     */
    public static function telecharger(Devis $devis): \Illuminate\Http\Response
    {
        // Certaines images de monuments/visuels uploadées peuvent être volumineuses
        // (plusieurs Mo en PNG non compressé une fois décodées par GD) — la limite
        // mémoire globale du serveur (128M) est trop juste pour ce traitement ponctuel.
        // On l'augmente uniquement pour cette requête précise, sans toucher au réglage
        // global qui reste adapté au reste du site.
        ini_set('memory_limit', '512M');

        $donnees = static::preparerDonnees($devis);

        return Pdf::loadView('pdf.devis', $donnees)
            ->setPaper('a4')
            ->download("devis-{$devis->numero}.pdf");
    }

    protected static function preparerDonnees(Devis $devis): array
    {
        $devis->loadMissing(['societe', 'agence', 'monument', 'granit', 'departementLivraison']);

        $visuels = static::encoderVisuelsEnBase64($devis->visuel ?? []);
        $visuelParDefaut = false;

        // Repli sur l'image principale (non configurée) du monument si aucun visuel
        // n'a été capturé pour ce devis — mieux qu'un devis sans aucune image.
        if (empty($visuels) && $devis->monument?->image_principale) {
            $disk = Storage::disk('public');
            if ($disk->exists($devis->monument->image_principale)) {
                $image = static::redimensionnerEtEncoder($disk->path($devis->monument->image_principale));
                if ($image) {
                    $visuels = [$image];
                    $visuelParDefaut = true;
                }
            }
        }

        return [
            'devis' => $devis,
            // Regroupées par paires : affichage 2 images par ligne dans le PDF, chaque
            // image étant réduite (donc plus nette visuellement) que la version en pleine
            // largeur d'origine, dont la résolution est plafonnée par la capture d'écran.
            'visuelsParPaires' => array_chunk($visuels, 2),
            'visuelParDefaut' => $visuelParDefaut,
        ];
    }

    /**
     * Encode les images du visuel en base64 pour un rendu fiable dans DomPDF.
     * Chaque image est redimensionnée et recompressée avant intégration : les captures
     * du configurateur 3D peuvent être lourdes (PNG haute résolution), et les intégrer
     * telles quelles peut dépasser la mémoire PHP disponible et faire planter la
     * génération du PDF sur cet hébergement.
     */
    protected static function encoderVisuelsEnBase64(array $chemins): array
    {
        $disk = Storage::disk('public');
        $images = [];

        foreach ($chemins as $chemin) {
            if (! $disk->exists($chemin)) {
                continue;
            }

            $donnees = static::redimensionnerEtEncoder($disk->path($chemin));

            if ($donnees) {
                $images[] = $donnees;
            }
        }

        return $images;
    }

    /**
     * Redimensionne l'image et la réencode en JPEG. Affichées à moitié de la largeur de
     * page (2 par ligne), une résolution plus modeste qu'en pleine largeur suffit
     * largement — et allège d'autant plus le PDF.
     */
    protected static function redimensionnerEtEncoder(string $cheminAbsolu, int $largeurMax = 900, int $qualite = 88): ?string
    {
        if (! extension_loaded('gd')) {
            $mime = mime_content_type($cheminAbsolu) ?: 'image/png';
            return "data:{$mime};base64," . base64_encode(file_get_contents($cheminAbsolu));
        }

        try {
            $infos = getimagesize($cheminAbsolu);
            if (! $infos) {
                return null;
            }
            [$largeurOrigine, $hauteurOrigine, $type] = $infos;

            $source = match ($type) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($cheminAbsolu),
                IMAGETYPE_PNG => imagecreatefrompng($cheminAbsolu),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($cheminAbsolu) : null,
                default => null,
            };

            if (! $source) {
                return null;
            }

            $ratio = min($largeurMax / $largeurOrigine, 1);
            $largeurCible = (int) round($largeurOrigine * $ratio);
            $hauteurCible = (int) round($hauteurOrigine * $ratio);

            // 1. Redimensionnement vers une image intermédiaire qui CONSERVE la transparence
            //    (imagecopyresampled ne mélange pas la transparence avec le fond de destination).
            $redimensionnee = imagecreatetruecolor($largeurCible, $hauteurCible);
            imagealphablending($redimensionnee, false);
            imagesavealpha($redimensionnee, true);
            imagefill($redimensionnee, 0, 0, imagecolorallocatealpha($redimensionnee, 0, 0, 0, 127));
            imagecopyresampled($redimensionnee, $source, 0, 0, 0, 0, $largeurCible, $hauteurCible, $largeurOrigine, $hauteurOrigine);

            // 2. Pose sur le fond clair fixe (imagecopy mélange correctement les pixels transparents
            //    et semi-transparents, ombres comprises) puis encodage en JPEG.
            $cible = imagecreatetruecolor($largeurCible, $hauteurCible);
            [$r, $v, $b] = static::FOND_VISUEL;
            imagefill($cible, 0, 0, imagecolorallocate($cible, $r, $v, $b));
            imagealphablending($cible, true);
            imagecopy($cible, $redimensionnee, 0, 0, 0, 0, $largeurCible, $hauteurCible);

            ob_start();
            imagejpeg($cible, null, $qualite);
            $contenu = ob_get_clean();

            imagedestroy($source);
            imagedestroy($redimensionnee);
            imagedestroy($cible);

            return 'data:image/jpeg;base64,' . base64_encode($contenu);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
