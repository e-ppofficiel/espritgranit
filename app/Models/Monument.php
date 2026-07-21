<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Monument extends Model
{
    protected $fillable = [
        'reference_interne',
        'nom',
        'categorie',
        'fichier_3d',
        'image_principale',
        'description',
        'ordre_affichage',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function images()
    {
        return $this->hasMany(MonumentImage::class)->orderBy('ordre');
    }

    public function blocs()
    {
        return $this->hasMany(MonumentBloc::class)->orderBy('ordre');
    }

    public function granits()
    {
        return $this->belongsToMany(Granit::class, 'monument_granit_prix')
            ->withPivot(['prix_fob', 'taux_marge'])
            ->withTimestamps();
    }
}
