<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Container extends Model
{
    protected $fillable = [
        'reference',
        'statut',
        'date_depart',
        'date_arrivee_prevue',
        'date_arrivee_reelle',
        'informations_complementaires',
    ];

    protected $casts = [
        'date_depart' => 'date',
        'date_arrivee_prevue' => 'date',
        'date_arrivee_reelle' => 'date',
    ];

    public function commandes()
    {
        return $this->hasMany(Commande::class);
    }

    /**
     * Répercute le statut et les dates du container sur toutes les commandes rattachées.
     * Correspondance des statuts container -> commande.
     */
    public function repercuterSurCommandes(): void
    {
        $correspondanceStatuts = [
            'en_preparation' => 'en_production',
            'en_transit' => 'en_transit',
            'au_port_arrivee' => 'au_port_arrivee',
            'livre' => 'livree',
        ];

        $statutCommande = $correspondanceStatuts[$this->statut] ?? null;

        $this->commandes()->update(array_filter([
            'statut' => $statutCommande,
            'date_depart_prevue' => $this->date_depart,
            'date_arrivee_prevue' => $this->date_arrivee_prevue,
            'date_arrivee_reelle' => $this->date_arrivee_reelle,
        ], fn ($value) => $value !== null));
    }
}
