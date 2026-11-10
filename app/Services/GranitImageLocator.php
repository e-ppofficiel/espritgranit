<?php

namespace App\Services;

use App\Models\Granit;
use Illuminate\Support\Facades\Storage;

/**
 * Retrouve le fichier image source d'un granit sur le disque public.
 *
 * Convention : les images sont stockées dans storage/app/public/granits/ et nommées
 * d'après la référence interne du granit (ex. « Amazone-Hindi Red.jpg »). Le chemin
 * enregistré en base (colonne `image`) peut ne plus correspondre à un fichier réel
 * (ancien upload Filament nommé par identifiant aléatoire, fichier supprimé ou renommé…).
 */
class GranitImageLocator
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const DOSSIER = 'granits';

    /**
     * Chemin relatif (sur le disque public) de l'image du granit :
     * d'abord le chemin enregistré s'il pointe vers un vrai fichier,
     * sinon le fichier nommé d'après la référence interne.
     */
    public static function chemin(Granit $granit): ?string
    {
        $disk = Storage::disk('public');

        if ($granit->image && $disk->exists($granit->image)) {
            return $granit->image;
        }

        return static::parReference($granit);
    }

    /**
     * Cherche « granits/{reference_interne}.{ext} », d'abord à l'identique, puis en
     * ignorant la casse (« .JPG », « amazone-hindi red.jpg ») et la forme des accents
     * (NFC/NFD, fréquente sur des fichiers déposés depuis un autre système).
     */
    public static function parReference(Granit $granit): ?string
    {
        $reference = trim((string) $granit->reference_interne);

        if ($reference === '') {
            return null;
        }

        $disk = Storage::disk('public');

        foreach (static::EXTENSIONS as $extension) {
            $candidat = static::DOSSIER . '/' . $reference . '.' . $extension;

            if ($disk->exists($candidat)) {
                return $candidat;
            }
        }

        $cible = static::normaliser($reference);

        foreach ($disk->files(static::DOSSIER) as $fichier) {
            $infos = pathinfo($fichier);
            $extension = mb_strtolower($infos['extension'] ?? '');

            if (in_array($extension, static::EXTENSIONS, true)
                && static::normaliser($infos['filename']) === $cible) {
                return $fichier;
            }
        }

        return null;
    }

    protected static function normaliser(string $texte): string
    {
        if (class_exists(\Normalizer::class)) {
            $texte = \Normalizer::normalize($texte, \Normalizer::FORM_C) ?: $texte;
        }

        return mb_strtolower(trim($texte));
    }
}
