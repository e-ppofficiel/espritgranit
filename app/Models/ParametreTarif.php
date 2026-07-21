<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametreTarif extends Model
{
    protected $table = 'parametres_tarifs';

    protected $fillable = [
        'poids_conteneur_kg',
        'cout_transport_conteneur',
        'cout_transit_conteneur',
    ];

    public static function courant(): self
    {
        return static::firstOrCreate([], [
            'poids_conteneur_kg' => 20000,
        ]);
    }
}
