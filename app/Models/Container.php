<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Container extends Model
{
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut', 'date_depart', 'date_arrivee_prevue', 'date_arrivee_reelle', 'informations_complementaires'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function commandes()
    {
        return $this->hasMany(Commande::class);
    }

    /**
     * Répercute le statut et les dates du container sur toutes les commandes rattachées.
     * Correspondance des statuts container -> commande.
     *
     * Boucle avec ->save() individuel plutôt qu'une mise à jour groupée en une requête :
     * une mise à jour groupée via le query builder ne déclenche pas les évènements
     * Eloquent, donc ne serait jamais suivie dans le journal d'activité — alors que
     * cette action est explicitement citée au cahier des charges comme devant l'être.
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

        $valeurs = array_filter([
            'statut' => $statutCommande,
            'date_depart_prevue' => $this->date_depart,
            'date_arrivee_prevue' => $this->date_arrivee_prevue,
            'date_arrivee_reelle' => $this->date_arrivee_reelle,
        ], fn ($value) => $value !== null);

        if (empty($valeurs)) {
            return;
        }

        foreach ($this->commandes as $commande) {
            $commande->fill($valeurs)->save();
        }
    }
}
