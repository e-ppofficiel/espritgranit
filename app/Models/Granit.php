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
}
