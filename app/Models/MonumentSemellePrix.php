<?php

namespace App\Models;

use App\Services\PrixCalculator;
use Illuminate\Database\Eloquent\Model;

/**
 * Prix d'une semelle (référence de dimension) pour un monument et un granit.
 * Même logique que monument_granit_prix : prix FOB + taux de marge, avec le poids propre de la semelle.
 */
class MonumentSemellePrix extends Model
{
    protected $table = 'monument_semelle_prix';

    protected $fillable = [
        'monument_id',
        'granit_id',
        'reference',
        'prix_fob',
        'taux_marge',
        'poids_kg',
    ];

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }

    public function granit()
    {
        return $this->belongsTo(Granit::class);
    }

    /** Prix de vente départ (avant coefficient), ou null s'il manque le prix, la marge ou le poids. */
    public function prixVenteDepart(): ?float
    {
        if ($this->prix_fob === null || $this->taux_marge === null) {
            return null;
        }

        return PrixCalculator::prixVenteDepartPourPoids((float) $this->poids_kg, (float) $this->prix_fob, (float) $this->taux_marge);
    }
}
