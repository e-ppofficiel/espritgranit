<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Commande extends Model
{
    use LogsActivity;

    protected $fillable = [
        'numero',
        'devis_id',
        'societe_id',
        'agence_id',
        'user_id',
        'monument_id',
        'granit_id',
        'commande_par',
        'adresse_livraison',
        'departement_livraison_id',
        'statut',
        'container_id',
        'prix_transport',
        'prix_final',
        'semelle_reference',
        'semelle_libelle',
        'prix_semelle',
        'date_depart_prevue',
        'date_arrivee_prevue',
        'date_arrivee_reelle',
        'commentaire',
        'document_cgv',
        'annulee_at',
    ];

    protected $casts = [
        'date_depart_prevue' => 'date',
        'date_arrivee_prevue' => 'date',
        'date_arrivee_reelle' => 'date',
        'annulee_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'statut',
                'container_id',
                'prix_transport',
                'prix_final',
                'date_depart_prevue',
                'date_arrivee_prevue',
                'date_arrivee_reelle',
                'commentaire',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted(): void
    {
        static::creating(function (Commande $commande) {
            if (empty($commande->numero)) {
                $commande->numero = static::genererNumero();
            }
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->format('Y');
        $dernierNumero = static::where('numero', 'like', "CMD-{$annee}-%")
            ->orderByDesc('id')
            ->value('numero');

        $prochainIndex = 1;
        if ($dernierNumero) {
            $parties = explode('-', $dernierNumero);
            $prochainIndex = (int) end($parties) + 1;
        }

        return sprintf('CMD-%s-%04d', $annee, $prochainIndex);
    }

    /**
     * Crée une commande à partir d'un devis validé, en reprenant ses informations.
     */
    public static function creerDepuisDevis(Devis $devis): self
    {
        $commande = static::create([
            'devis_id' => $devis->id,
            'societe_id' => $devis->societe_id,
            'agence_id' => $devis->agence_id,
            'user_id' => $devis->user_id,
            'monument_id' => $devis->monument_id,
            'granit_id' => $devis->granit_id,
            'departement_livraison_id' => $devis->departement_livraison_id,
            'prix_transport' => $devis->prix_transport,
            'prix_final' => $devis->prix_final,
            'semelle_reference' => $devis->semelle_reference,
            'semelle_libelle' => $devis->semelle_libelle,
            'prix_semelle' => $devis->prix_semelle,
            'statut' => 'en_preparation',
        ]);

        $devis->update(['statut' => 'transforme_commande']);

        return $commande;
    }

    public function devis()
    {
        return $this->belongsTo(Devis::class);
    }

    public function societe()
    {
        return $this->belongsTo(Societe::class);
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }

    public function granit()
    {
        return $this->belongsTo(Granit::class);
    }

    public function departementLivraison()
    {
        return $this->belongsTo(Departement::class, 'departement_livraison_id');
    }

    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    public function photos()
    {
        return $this->hasMany(CommandePhoto::class)->orderBy('ordre');
    }
}
