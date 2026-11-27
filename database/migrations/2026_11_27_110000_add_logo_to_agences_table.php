<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Logo d'une agence (chemin d'un fichier du disque public, ex. agences/logos/xxx.png),
 * affiché dans l'en-tête du PDF de ses devis.
 *
 * Migration réentrante : une colonne déjà présente n'est pas recréée.
 * Retour arrière : down() supprime uniquement cette colonne (les fichiers envoyés restent sur le disque).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agences', 'logo')) {
            Schema::table('agences', function (Blueprint $table) {
                $table->string('logo')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agences', 'logo')) {
            Schema::table('agences', function (Blueprint $table) {
                $table->dropColumn('logo');
            });
        }
    }
};
