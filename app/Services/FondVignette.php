<?php

namespace App\Services;

/**
 * Vignette (miniature JPG) d'un fond du configurateur, générée côté serveur avec GD.
 *
 *  - image JPG / PNG / WebP : simple réduction ;
 *  - panorama Radiance .hdr (RGBE, ce que sont les ambiances) : décodage puis conversion simple en image
 *    visible (exposition + gamma). Aucune bibliothèque externe : seule l'extension GD de PHP est nécessaire.
 *
 * Ne lève jamais d'exception : en cas d'échec (fichier illisible, GD absent…), renvoie false et l'appelant
 * affiche alors une vignette de repli. Le fichier d'origine n'est jamais modifié.
 */
class FondVignette
{
    public const LARGEUR = 320;
    public const HAUTEUR = 160;

    /** Écrit la vignette JPG de $source dans $destination (chemins absolus). */
    public static function generer(string $source, string $destination): bool
    {
        try {
            if (! extension_loaded('gd') || ! is_file($source)) {
                return false;
            }

            $dossier = dirname($destination);
            if (! is_dir($dossier) && ! @mkdir($dossier, 0755, true) && ! is_dir($dossier)) {
                return false;
            }

            $image = strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'hdr'
                ? self::depuisHdr($source)
                : self::depuisImage($source);
            if (! $image) {
                return false;
            }

            $ok = imagejpeg($image, $destination, 82);
            imagedestroy($image);
            if ($ok) {
                @chmod($destination, 0644);
            }

            return (bool) $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return \GdImage|false */
    private static function depuisImage(string $source)
    {
        $infos = @getimagesize($source);
        if (! $infos || ($infos[0] ?? 0) < 1 || ($infos[1] ?? 0) < 1 || $infos[0] * $infos[1] > 40_000_000) {
            return false;
        }
        $origine = match ($infos[2] ?? 0) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (! $origine) {
            return false;
        }

        return self::redimensionner($origine, imagesx($origine), imagesy($origine));
    }

    /** Recadre au format de la vignette (centré) puis réduit. */
    private static function redimensionner($origine, int $largeur, int $hauteur)
    {
        $ratio = self::LARGEUR / self::HAUTEUR;
        $sw = $largeur;
        $sh = $hauteur;
        if ($largeur / $hauteur > $ratio) {
            $sw = (int) round($hauteur * $ratio);
        } else {
            $sh = (int) round($largeur / $ratio);
        }
        $sx = (int) floor(($largeur - $sw) / 2);
        $sy = (int) floor(($hauteur - $sh) / 2);

        $sortie = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);
        imagecopyresampled($sortie, $origine, 0, 0, $sx, $sy, self::LARGEUR, self::HAUTEUR, max(1, $sw), max(1, $sh));
        imagedestroy($origine);

        return $sortie;
    }

    /** Décodage d'un .hdr Radiance (RGBE, compression RLE ou brut), directement réduit à la taille de la vignette. */
    private static function depuisHdr(string $source)
    {
        $f = @fopen($source, 'rb');
        if (! $f) {
            return false;
        }

        try {
            // En-tête : lignes de texte jusqu'à une ligne vide, puis « -Y h +X w ».
            $premiere = fgets($f, 256);
            if ($premiere === false || ! str_starts_with($premiere, '#?')) {
                return false;
            }
            $lignes = 0;
            do {
                $ligne = fgets($f, 512);
                $lignes++;
            } while ($ligne !== false && trim($ligne) !== '' && $lignes < 64);
            $resolution = fgets($f, 128);
            if ($resolution === false || ! preg_match('/^-Y (\d+) \+X (\d+)/', $resolution, $m)) {
                return false;
            }
            [$hauteur, $largeur] = [(int) $m[1], (int) $m[2]];
            if ($hauteur < 1 || $largeur < 1 || $largeur > 16384 || $hauteur > 8192) {
                return false;
            }

            // On n'échantillonne que les lignes et colonnes utiles (une sur « pas »).
            $pasY = max(1, intdiv($hauteur, self::HAUTEUR * 2));
            $pasX = max(1, intdiv($largeur, self::LARGEUR * 2));
            $wOut = intdiv($largeur - 1, $pasX) + 1;
            $hOut = intdiv($hauteur - 1, $pasY) + 1;
            $lumiere = array_fill(0, $hOut, null);

            for ($y = 0; $y < $hauteur; $y++) {
                $rgbe = self::lireLigne($f, $largeur);
                if ($rgbe === null) {
                    return false;
                }
                if ($y % $pasY !== 0) {
                    continue;
                }
                $ligne = [];
                for ($x = 0; $x < $largeur; $x += $pasX) {
                    $i = $x * 4;
                    $e = $rgbe[$i + 3];
                    if ($e === 0) {
                        $ligne[] = [0.0, 0.0, 0.0];
                        continue;
                    }
                    $k = 2.0 ** ($e - 136);
                    $ligne[] = [$rgbe[$i] * $k, $rgbe[$i + 1] * $k, $rgbe[$i + 2] * $k];
                }
                $lumiere[intdiv($y, $pasY)] = $ligne;
            }
            if (in_array(null, $lumiere, true)) {
                return false;
            }

            // Exposition automatique : la luminance moyenne est ramenée vers un gris moyen, puis gamma 2,2.
            $somme = 0.0;
            $n = 0;
            foreach ($lumiere as $ligne) {
                foreach ($ligne as $p) {
                    $somme += 0.2126 * $p[0] + 0.7152 * $p[1] + 0.0722 * $p[2];
                    $n++;
                }
            }
            $moyenne = $n > 0 ? $somme / $n : 0.0;
            $gain = $moyenne > 1e-6 ? 0.28 / $moyenne : 1.0;
            $gain = max(0.05, min(40.0, $gain));

            $petite = imagecreatetruecolor($wOut, $hOut);
            foreach ($lumiere as $y => $ligne) {
                foreach ($ligne as $x => $p) {
                    $c = [];
                    foreach ($p as $v) {
                        $v = $v * $gain;
                        $v = $v / (1.0 + $v);                    // compression douce des hautes lumières
                        $c[] = (int) round(255 * min(1.0, max(0.0, $v ** (1 / 2.2))));
                    }
                    imagesetpixel($petite, $x, $y, ($c[0] << 16) | ($c[1] << 8) | $c[2]);
                }
            }

            return self::redimensionner($petite, $wOut, $hOut);
        } finally {
            fclose($f);
        }
    }

    /** Une ligne de pixels RGBE : tableau plat [r,g,b,e, r,g,b,e, …] ou null si illisible. */
    private static function lireLigne($f, int $largeur): ?array
    {
        $tete = fread($f, 4);
        if ($tete === false || strlen($tete) < 4) {
            return null;
        }
        $o = array_values(unpack('C4', $tete));

        // Nouvelle compression RLE par plan : 2, 2, largeur (2 octets), puis 4 plans.
        if ($largeur >= 8 && $largeur < 32768 && $o[0] === 2 && $o[1] === 2 && ! ($o[2] & 0x80)) {
            if ((($o[2] << 8) | $o[3]) !== $largeur) {
                return null;
            }
            $plans = [[], [], [], []];
            for ($c = 0; $c < 4; $c++) {
                $plan = [];
                while (count($plan) < $largeur) {
                    $octet = fread($f, 1);
                    if ($octet === false || $octet === '') {
                        return null;
                    }
                    $compte = ord($octet);
                    if ($compte > 128) {
                        $compte -= 128;
                        $valeur = fread($f, 1);
                        if ($valeur === false || $valeur === '') {
                            return null;
                        }
                        $valeur = ord($valeur);
                        for ($i = 0; $i < $compte; $i++) {
                            $plan[] = $valeur;
                        }
                    } else {
                        $bloc = fread($f, $compte);
                        if ($bloc === false || strlen($bloc) < $compte || $compte === 0) {
                            return null;
                        }
                        foreach (unpack('C*', $bloc) as $v) {
                            $plan[] = $v;
                        }
                    }
                }
                if (count($plan) !== $largeur) {
                    return null;
                }
                $plans[$c] = $plan;
            }
            $sortie = [];
            for ($x = 0; $x < $largeur; $x++) {
                $sortie[] = $plans[0][$x];
                $sortie[] = $plans[1][$x];
                $sortie[] = $plans[2][$x];
                $sortie[] = $plans[3][$x];
            }

            return $sortie;
        }

        // Ligne non compressée : pixels de 4 octets (la première valeur est déjà lue).
        $reste = fread($f, ($largeur - 1) * 4);
        if ($reste === false || strlen($reste) < ($largeur - 1) * 4) {
            return null;
        }

        return array_merge($o, $largeur > 1 ? array_values(unpack('C*', $reste)) : []);
    }
}
