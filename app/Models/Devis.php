<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Devis extends Model
{
    use LogsActivity;

    protected $table = 'devis';

    protected $casts = [
    'soubassement_noir' => 'boolean',
    'visuel' => 'array',
    ];

    protected $fillable = [
        'numero',
        'societe_id',
        'agence_id',
        'user_id',
        'monument_id',
        'granit_id',
        'soubassement_noir',
        'origine',
        'statut',
        'departement_livraison_id',
        'prix_granit_base',
        'prix_transport',
        'prix_total',
        'coefficient_applique',
        'prix_final',
        'commentaire',
        'piece_jointe',
        'visuel',
        'semelle_type_id',
        'semelle_dimension_id',
        'semelle_reference',
        'semelle_libelle',
        'prix_semelle',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        // Champs volontairement ciblés (pas ->logFillable()) : les champs fichiers
        // (piece_jointe, visuel) sont exclus car peu utiles à comparer en "avant/après"
        // et 'visuel' (tableau) fait planter l'affichage du détail dans Filament.
        return LogOptions::defaults()
            ->logOnly([
                'statut',
                'monument_id',
                'granit_id',
                'soubassement_noir',
                'departement_livraison_id',
                'prix_granit_base',
                'semelle_reference',
                'prix_semelle',
                'prix_transport',
                'prix_total',
                'coefficient_applique',
                'prix_final',
                'commentaire',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted(): void
    {
        static::creating(function (Devis $devis) {
            if (empty($devis->numero)) {
                $devis->numero = static::genererNumero();
            }
        });
    }

    public static function genererNumero(): string
    {
        $annee = now()->format('Y');
        $dernierNumero = static::where('numero', 'like', "DEV-{$annee}-%")
            ->orderByDesc('id')
            ->value('numero');

        $prochainIndex = 1;
        if ($dernierNumero) {
            $parties = explode('-', $dernierNumero);
            $prochainIndex = (int) end($parties) + 1;
        }

        return sprintf('DEV-%s-%04d', $annee, $prochainIndex);
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

    public function semelleType()
    {
        return $this->belongsTo(SemelleType::class);
    }

    public function semelleDimension()
    {
        return $this->belongsTo(MonumentSemelleDimension::class, 'semelle_dimension_id');
    }

    public function dimensions()
    {
        return $this->hasMany(DevisDimension::class);
    }
}
