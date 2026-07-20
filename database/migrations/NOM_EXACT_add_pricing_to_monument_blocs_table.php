<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monument_blocs', function (Blueprint $table) {
            $table->string('reference_produit')->nullable()->unique()->after('nom_bloc');
            $table->decimal('poids_kg', 8, 2)->nullable()->after('epaisseur_standard');
            $table->decimal('prix_base', 10, 2)->nullable()->after('poids_kg');
            $table->decimal('prix_vente_depart', 10, 2)->nullable()->after('prix_base');
        });
    }

    public function down(): void
    {
        Schema::table('monument_blocs', function (Blueprint $table) {
            $table->dropColumn(['reference_produit', 'poids_kg', 'prix_base', 'prix_vente_depart']);
        });
    }
};
