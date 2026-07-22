<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres_tarifs', function (Blueprint $table) {
            $table->decimal('coefficient_vente_general', 5, 2)->default(1)->after('cout_transit_conteneur');
        });
    }

    public function down(): void
    {
        Schema::table('parametres_tarifs', function (Blueprint $table) {
            $table->dropColumn('coefficient_vente_general');
        });
    }
};