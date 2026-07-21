<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monument_blocs', function (Blueprint $table) {
            if (! Schema::hasColumn('monument_blocs', 'reference_produit')) {
                $table->string('reference_produit')->nullable()->unique()->after('nom_bloc');
            }
            if (! Schema::hasColumn('monument_blocs', 'poids_kg')) {
                $table->decimal('poids_kg', 8, 2)->nullable()->after('epaisseur_standard');
            }
            if (! Schema::hasColumn('monument_blocs', 'prix_base')) {
                $table->decimal('prix_base', 10, 2)->nullable()->after('poids_kg');
            }
            if (! Schema::hasColumn('monument_blocs', 'prix_vente_depart')) {
                $table->decimal('prix_vente_depart', 10, 2)->nullable()->after('prix_base');
            }
        });
    }

    public function down(): void
    {
        //
    }
};
