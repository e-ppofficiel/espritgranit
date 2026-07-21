<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Granit extends Model
{
    protected $fillable = [
        'nom_commercial',
        'reference_interne',
        'image',
        'pays',
        'actif',
        'ordre_affichage',
        'restrictions',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function monuments()
    {
        return $this->belongsToMany(Monument::class, 'monument_granit_prix')
            ->withPivot(['prix_fob', 'taux_marge'])
            ->withTimestamps();
    }
}
