<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'nom',
    ];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            // Normalisation légère pour limiter les quasi-doublons ("Moderne " avec espace, etc.)
            $tag->nom = trim($tag->nom);
        });
    }

    public function monuments()
    {
        return $this->belongsToMany(Monument::class, 'monument_tag')->withTimestamps();
    }
}
