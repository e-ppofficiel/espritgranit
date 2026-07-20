<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonumentBloc extends Model
{
    protected $fillable = [
        'monument_id',
        'nom_bloc',
        'longueur_standard',
        'largeur_standard',
        'epaisseur_standard',
        'commentaire',
        'ordre',
    ];

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }
}
