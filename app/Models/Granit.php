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
        'pays',
        'actif',
        'ordre_affichage',
        'restrictions',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

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
