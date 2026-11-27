<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agence extends Model
{
    protected $fillable = [
        'societe_id',
        'nom',
        'adresse',
        'code_postal',
        'commune',
        'adresse_livraison',
        'code_postal_livraison',
        'commune_livraison',
        'contact',
        'contact_livraison',
        'telephone_livraison',
        'email',
        'telephone',
        'coefficient_vente',
        'logo',
    ];

    public function societe()
    {
        return $this->belongsTo(Societe::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
