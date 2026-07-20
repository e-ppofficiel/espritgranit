<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Societe extends Model
{
    protected $fillable = [
        'enseigne',
        'raison_sociale',
        'siret',
        'adresse_siege',
        'email',
        'telephone',
        'contact_principal',
        'statut_compte',
        'coefficient_vente',
    ];

    public function agences()
    {
        return $this->hasMany(Agence::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
