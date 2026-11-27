<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Références libres d'un devis (reprises sur la commande à la transformation) :
 *  - reference_client  : la référence de l'agence / de son client final ;
 *  - reference_interne : la référence interne de l'agence ;
 *  - reference_usine   : la référence de l'usine (renseignée par l'administrateur uniquement).
 *
 * Migration réentrante : une colonne déjà présente n'est pas recréée.
 * Retour arrière : down() supprime uniquement ces colonnes.
 */
return new class extends Migration
{
    private const COLONNES = ['reference_client', 'reference_interne', 'reference_usine'];

    public function up(): void
    {
        foreach (['devis', 'commandes'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (self::COLONNES as $colonne) {
                    if (! Schema::hasColumn($table, $colonne)) {
                        $blueprint->string($colonne, 100)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['devis', 'commandes'] as $table) {
            foreach (self::COLONNES as $colonne) {
                if (Schema::hasColumn($table, $colonne)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($colonne));
                }
            }
        }
    }
};
