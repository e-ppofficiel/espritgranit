<?php

namespace App\Models;

use App\Services\ImageOptimizer;
use Illuminate\Database\Eloquent\Model;

class Granit extends Model
{
    protected $fillable = [
        'nom_commercial',
        'reference_interne',
        'image',
        'image_vignette',
        'pays',
        'actif',
        'ordre_affichage',
        'restrictions',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (Granit $granit) {
            if ($granit->image && $granit->wasChanged('image')) {
                $vignette = ImageOptimizer::genererVignette($granit->image);
                if ($vignette) {
                    // updateQuietly pour ne pas redéclencher cet événement 'saved' en boucle
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
