<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonumentSemelleDimension extends Model
{
    /** Tailles standard proposées pour chaque type : [largeur, longueur, par défaut]. */
    public const TAILLES_STANDARD = [
        [130, 230, false],
        [140, 240, true],
        [150, 250, false],
    ];

    protected $fillable = [
        'monument_id',
        'semelle_type_id',
        'reference',
        'label',
        'longueur',
        'largeur',
        'hauteur',
        'is_default',
        'ordre_affichage',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Filet de sécurité : une dimension enregistrée sans libellé ni référence (import, tinker…) en reçoit une.
        static::saving(function (self $dimension) {
            if (blank($dimension->label)) {
                $dimension->label = static::libelleAuto($dimension->largeur, $dimension->longueur);
            }
            if (blank($dimension->reference)) {
                $dimension->reference = static::referenceAuto(
                    $dimension->semelle_type_id ? SemelleType::find($dimension->semelle_type_id)?->code : null,
                    $dimension->largeur,
                    $dimension->longueur
                );
            }
        });

        // Une seule dimension par défaut par monument et par type de semelle.
        static::saved(function (self $dimension) {
            if (! $dimension->is_default) {
                return;
            }
            $autres = static::query()
                ->where('monument_id', $dimension->monument_id)
                ->where('id', '!=', $dimension->id);
            $dimension->semelle_type_id === null
                ? $autres->whereNull('semelle_type_id')
                : $autres->where('semelle_type_id', $dimension->semelle_type_id);
            $autres->where('is_default', true)->update(['is_default' => false]);
        });
    }

    public function monument()
    {
        return $this->belongsTo(Monument::class);
    }

    public function semelleType()
    {
        return $this->belongsTo(SemelleType::class);
    }

    /** 130.00 → "130", 137.50 → "137.5" */
    public static function nombre($valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, '.', ''), '0'), '.');
    }

    /** « 140 x 240 » (largeur x longueur, comme le libellé historique). */
    public static function libelleAuto($largeur, $longueur): string
    {
        return static::nombre($largeur).' x '.static::nombre($longueur);
    }

    /** « SEMELLE-B-140X240 » : code du type + largeur X longueur. Sans type : « SEMELLE-140X240 ». */
    public static function referenceAuto(?string $codeType, $largeur, $longueur): string
    {
        return ($codeType ?: 'SEMELLE').'-'.static::nombre($largeur).'X'.static::nombre($longueur);
    }

    /** Texte des listes déroulantes d'administration. */
    public function libelleSelect(): string
    {
        $type = $this->semelleType ? $this->semelleType->libelle_complet : 'Tous types';

        return $type.' — '.($this->label ?: static::libelleAuto($this->largeur, $this->longueur)).($this->is_default ? ' (par défaut)' : '');
    }
}
