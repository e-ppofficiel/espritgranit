<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevisDimension extends Model
{
    protected $fillable = [
        'devis_id',
        'monument_bloc_id',
        'longueur_commandee',
        'largeur_commandee',
        'epaisseur_commandee',
    ];

    public function devis()
    {
        return $this->belongsTo(Devis::class);
    }

    public function bloc()
    {
        return $this->belongsTo(MonumentBloc::class, 'monument_bloc_id');
    }
}
