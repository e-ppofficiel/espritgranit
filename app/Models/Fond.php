<?php

namespace App\Models;

use App\Services\FondVignette;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fond du configurateur 3D, géré dans l'admin (rubrique « Fonds du configurateur »).
 *
 *  - type « couleur »  : fond uni (champ `couleur`, #rrggbb) ;
 *  - type « ambiance » : panorama 360° (champ `fichier` : .hdr, .jpg ou .png) dont les reflets apparaissent sur la pierre polie
 *                         et qui peut aussi s'afficher en décor (`visible_decor`, image plus nette optionnelle `decor_fichier`).
 *
 * `cle` est l'identifiant stable utilisé dans les liens partagés (?c=…) : créé une fois, il ne change plus.
 * Tous les fichiers sont dans le disque public (storage/app/public/fonds/…).
 */
class Fond extends Model
{
    public const TYPES = ['couleur', 'ambiance'];

    protected $table = 'fonds';

    protected $fillable = [
        'cle', 'nom', 'type', 'couleur', 'fichier', 'decor_fichier', 'vignette',
        'exposition', 'visible_decor', 'ordre', 'actif',
    ];

    protected $casts = [
        'exposition' => 'float',
        'visible_decor' => 'boolean',
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    /** Une couleur vide ou invalide est rangée en NULL ; une couleur valide est normalisée en minuscules. */
    public function setCouleurAttribute($valeur): void
    {
        $this->attributes['couleur'] = (is_string($valeur) && preg_match('/^#[0-9a-fA-F]{6}$/', $valeur))
            ? strtolower($valeur)
            : null;
    }

    /** Un champ numérique vidé arrive en chaîne vide : on le range à la valeur standard. */
    public function setExpositionAttribute($valeur): void
    {
        $n = is_numeric($valeur) ? (float) $valeur : 1.5;
        $this->attributes['exposition'] = max(0.1, min(4.0, round($n, 2)));
    }

    public function scopeActifs($requete)
    {
        return $requete->where('actif', true);
    }

    /** URL publique d'un fichier du disque public, ou null s'il est vide ou absent du disque. */
    public static function urlStockage(?string $chemin): ?string
    {
        if (! $chemin || str_contains($chemin, '..')) {
            return null;
        }

        return Storage::disk('public')->exists($chemin) ? asset('storage/' . $chemin) : null;
    }

    public function urlFichier(): ?string
    {
        return self::urlStockage($this->fichier);
    }

    public function urlVignette(): ?string
    {
        return self::urlStockage($this->vignette);
    }

    /** Image du décor : l'image dédiée si elle existe, sinon le panorama lui-même. */
    public function urlDecor(): ?string
    {
        return self::urlStockage($this->decor_fichier) ?: self::urlStockage($this->fichier);
    }

    protected static function booted(): void
    {
        static::creating(function (Fond $fond) {
            if (! $fond->cle) {
                $fond->cle = self::cleUnique($fond->nom);
            }
        });

        static::saved(function (Fond $fond) {
            // Anciens fichiers à retirer : relevés AVANT toute mise à jour silencieuse (qui effacerait la liste des changements).
            $remplaces = [];
            foreach (['fichier', 'decor_fichier', 'vignette'] as $champ) {
                if ($fond->wasChanged($champ) && $fond->getOriginal($champ)) {
                    $remplaces[$champ] = $fond->getOriginal($champ);
                }
            }

            if ($fond->type === 'ambiance' && $fond->fichier) {
                // « wasRecentlyCreated » reste vrai sur une instance déjà créée puis modifiée : une création n'a aucun changement relevé.
                $creation = $fond->wasRecentlyCreated && $fond->getChanges() === [];
                $fichierChange = $creation || $fond->wasChanged('fichier');
                $vignetteChange = $creation ? (bool) $fond->vignette : $fond->wasChanged('vignette');

                // Vignette absente, ou panorama remplacé sans nouvelle vignette envoyée : on la (re)génère à partir du panorama.
                if (! $fond->vignette || ($fichierChange && ! $vignetteChange)) {
                    $nouvelle = self::genererVignette($fond->fichier, $fond->cle);
                    if ($nouvelle) {
                        if ($fond->vignette) {
                            $remplaces['vignette'] = $fond->vignette;
                        }
                        $fond->updateQuietly(['vignette' => $nouvelle]);
                    }
                }
            }

            // Fichiers remplacés : on retire les anciens (jamais un fichier encore utilisé par un fond).
            foreach ($remplaces as $ancien) {
                self::supprimerFichier($ancien);
            }
        });

        static::deleted(function (Fond $fond) {
            foreach (['fichier', 'decor_fichier', 'vignette'] as $champ) {
                self::supprimerFichier($fond->{$champ}, $fond->id);
            }
        });
    }

    /** Génère fonds/vignettes/<cle>-xxxxxx.jpg à partir d'un fichier du disque public ; null en cas d'échec. */
    public static function genererVignette(?string $chemin, string $cle): ?string
    {
        if (! $chemin || ! Storage::disk('public')->exists($chemin)) {
            return null;
        }
        $relatif = 'fonds/vignettes/' . Str::slug($cle) . '-' . Str::lower(Str::random(6)) . '.jpg';
        $ok = FondVignette::generer(Storage::disk('public')->path($chemin), Storage::disk('public')->path($relatif));

        return $ok ? $relatif : null;
    }

    /** Supprime un fichier du dossier fonds/ du disque public, sauf s'il sert encore à un autre fond. */
    private static function supprimerFichier(?string $chemin, ?int $sauf = null): void
    {
        if (! $chemin || ! str_starts_with($chemin, 'fonds/') || str_contains($chemin, '..')) {
            return;
        }
        $utilise = static::query()
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))
            ->where(fn ($q) => $q->where('fichier', $chemin)->orWhere('decor_fichier', $chemin)->orWhere('vignette', $chemin))
            ->exists();
        if (! $utilise) {
            Storage::disk('public')->delete($chemin);
        }
    }

    private static function cleUnique(?string $nom): string
    {
        $base = Str::slug((string) $nom) ?: 'fond';
        $cle = $base;
        $i = 2;
        while (static::where('cle', $cle)->exists()) {
            $cle = $base . '-' . $i++;
        }

        return $cle;
    }
}
