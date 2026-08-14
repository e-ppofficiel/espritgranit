<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonumentSemelleDimension extends Model
{
    protected $fillable = [
        'monument_id',
        'semelle_type_id',
        'label',
        'longueur',
        'largeur',
        'hauteur',
        'is_default',
        'ordre_affichage',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }

    public function semelleType()
    {
        return $this->belongsTo(SemelleType::class);
    }
}
