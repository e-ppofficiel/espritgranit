<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemelleType extends Model
{
    protected $fillable = [
        'code',
        'label',
        'ordre_affichage',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function monuments()
    {
        return $this->belongsToMany(Monument::class, 'monument_semelle_type')
            ->withPivot(['is_default'])
            ->withTimestamps();
    }

    /** Ex. « SEMELLE-B : semelle bouchardée » (liste déroulante du configurateur, listes d'administration). */
    public function getLibelleCompletAttribute(): string
    {
        return $this->code.' : '.trim((string) $this->label);
    }
}
