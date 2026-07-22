<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devis extends Model
{
    protected $table = 'devis';

    protected $fillable = [
        'numero',
        'societe_id',
        'agence_id',
        'user_id',
        'monument_id',
        'granit_id',
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
    ];

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

    public function dimensions()
    {
        return $this->hasMany(DevisDimension::class);
    }
}
