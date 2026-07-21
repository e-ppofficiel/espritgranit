<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportZone extends Model
{
    protected $fillable = [
        'nom',
    ];

    public function departements()
    {
        return $this->hasMany(Departement::class);
    }

    public function tarifs()
    {
        return $this->hasMany(TransportTarif::class)->orderBy('poids_min');
    }
}
