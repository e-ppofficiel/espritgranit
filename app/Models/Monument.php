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
        'poids_total_kg',
        'ordre_affichage',
        'actif',
        'soubassement_noir_supplement_type',
        'soubassement_noir_supplement_valeur',
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

    /**
     * Poids du monument à utiliser pour les calculs (prix granit, transport).
     * Priorité au poids total saisi directement sur le monument (source de vérité
     * demandée par Christophe, alignée sur le fichier Excel).
     * Repli automatique sur la somme des blocs pour les monuments pas encore
     * mis à jour avec un poids total explicite.
     */
    public function poidsKg(): float
    {
        if ($this->poids_total_kg !== null) {
            return (float) $this->poids_total_kg;
        }

        return (float) $this->blocs()->sum('poids_kg');
    }

    public function granits()
    {
        return $this->belongsToMany(Granit::class, 'monument_granit_prix')
            ->withPivot(['prix_fob', 'taux_marge'])
            ->withTimestamps();
    }

    public function semelleTypes()
    {
        return $this->belongsToMany(SemelleType::class, 'monument_semelle_type')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    public function semelleDimensions()
    {
        return $this->hasMany(MonumentSemelleDimension::class)->orderBy('ordre_affichage');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'monument_tag')->withTimestamps();
    }
}
