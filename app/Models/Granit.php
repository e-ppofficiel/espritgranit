<?php

namespace App\Models;

use App\Services\ImageOptimizer;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Granit extends Model
{
    use LogsActivity;

    protected $fillable = [
        'nom_commercial',
        'reference_interne',
        'image',
        'image_vignette',
        'echantillon_cm',
        'brillance',
        'luminosite',
        'pays',
        'actif',
        'ordre_affichage',
        'restrictions',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    // Réglages de rendu 3D : un champ numérique vidé dans Filament arrive en chaîne vide, que MySQL refuse
    // dans une colonne entière. On le range en NULL (= valeur par défaut du configurateur).
    public function setEchantillonCmAttribute($valeur): void
    {
        $this->attributes['echantillon_cm'] = self::entierOuNull($valeur);
    }

    public function setBrillanceAttribute($valeur): void
    {
        $this->attributes['brillance'] = self::entierOuNull($valeur);
    }

    public function setLuminositeAttribute($valeur): void
    {
        $this->attributes['luminosite'] = self::entierOuNull($valeur);
    }

    private static function entierOuNull($valeur): ?int
    {
        return ($valeur === null || $valeur === '' || ! is_numeric($valeur)) ? null : (int) round((float) $valeur);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nom_commercial', 'reference_interne', 'pays', 'actif'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted(): void
    {
        static::saved(function (Granit $granit) {
            if ($granit->image && $granit->wasChanged('image')) {
                $vignette = ImageOptimizer::genererVignette($granit->image);
                if ($vignette) {
                    // updateQuietly pour ne pas redéclencher cet événement 'saved' en boucle
                    // (et ne pas non plus polluer le journal d'activité avec cette
                    // régénération purement technique de la vignette).
                    $granit->updateQuietly(['image_vignette' => $vignette]);
                }
            }
        });
    }

    public function monuments()
    {
        return $this->belongsToMany(Monument::class, 'monument_granit_prix')
            ->withPivot(['prix_fob', 'taux_marge'])
            ->withTimestamps();
    }
}
