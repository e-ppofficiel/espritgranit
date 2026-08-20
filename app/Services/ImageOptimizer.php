<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageOptimizer
{
    /**
     * Génère une vignette compressée à partir d'une image source stockée sur le disque 'public'.
     * Utilise l'extension GD native de PHP (pas de dépendance Composer supplémentaire à installer).
     *
     * @param  string  $cheminSource  Chemin relatif sur le disque 'public' (ex: "granits/xxx.jpg")
     * @param  int  $tailleMaxPx  Taille max du côté le plus long, en pixels
     * @param  int  $qualiteJpeg  Qualité JPEG de sortie (0-100)
     * @return string|null Chemin relatif de la vignette générée, ou null en cas d'échec
     */
    public static function genererVignette(string $cheminSource, int $tailleMaxPx = 400, int $qualiteJpeg = 75): ?string
    {
        if (! extension_loaded('gd')) {
            Log::warning("ImageOptimizer: extension GD non disponible, vignette non générée pour {$cheminSource}");
            return null;
        }

        if (! Storage::disk('public')->exists($cheminSource)) {
            return null;
        }

        try {
            $cheminAbsolu = Storage::disk('public')->path($cheminSource);
            $infos = getimagesize($cheminAbsolu);

            if (! $infos) {
                return null;
            }

            [$largeurOrigine, $hauteurOrigine, $type] = $infos;

            $image = match ($type) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($cheminAbsolu),
                IMAGETYPE_PNG => imagecreatefrompng($cheminAbsolu),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($cheminAbsolu) : null,
                default => null,
            };

            if (! $image) {
                return null;
            }

            $ratio = min($tailleMaxPx / $largeurOrigine, $tailleMaxPx / $hauteurOrigine, 1);
            $largeurCible = (int) round($largeurOrigine * $ratio);
            $hauteurCible = (int) round($hauteurOrigine * $ratio);

            $vignette = imagecreatetruecolor($largeurCible, $hauteurCible);

            // Fond blanc pour gérer proprement la transparence des PNG lors de la conversion en JPEG
            $blanc = imagecolorallocate($vignette, 255, 255, 255);
            imagefill($vignette, 0, 0, $blanc);

            imagecopyresampled(
                $vignette, $image,
                0, 0, 0, 0,
                $largeurCible, $hauteurCible,
                $largeurOrigine, $hauteurOrigine
            );

            $nomFichier = pathinfo($cheminSource, PATHINFO_FILENAME) . '_vignette.jpg';
            $cheminVignette = 'granits/vignettes/' . $nomFichier;
            $cheminVignetteAbsolu = Storage::disk('public')->path($cheminVignette);

            if (! is_dir(dirname($cheminVignetteAbsolu))) {
                mkdir(dirname($cheminVignetteAbsolu), 0755, true);
            }

            imagejpeg($vignette, $cheminVignetteAbsolu, $qualiteJpeg);

            imagedestroy($image);
            imagedestroy($vignette);

            return $cheminVignette;
        } catch (\Throwable $e) {
            Log::warning("ImageOptimizer: échec génération vignette pour {$cheminSource} - " . $e->getMessage());
            return null;
        }
    }
}
