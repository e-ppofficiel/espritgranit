<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonumentBloc extends Model
{
    protected $fillable = [
        'monument_id',
        'nom_bloc',
        'reference_produit',
        'longueur_standard',
        'largeur_standard',
        'epaisseur_standard',
        'poids_kg',
        'prix_base',
        'prix_vente_depart',
        'commentaire',
        'ordre',
    ];

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }
}
