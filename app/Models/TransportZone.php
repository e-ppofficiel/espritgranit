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

    public static function pourDepartement(string $codeDepartement): ?self
    {
        return static::whereHas('departements', function ($query) use ($codeDepartement) {
            $query->where('code', $codeDepartement);
        })->first();
    }
}
