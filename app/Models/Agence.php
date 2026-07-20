<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agence extends Model
{
    protected $fillable = [
        'societe_id',
        'nom',
        'adresse',
        'adresse_livraison',
        'contact',
        'email',
        'telephone',
        'coefficient_vente',
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
