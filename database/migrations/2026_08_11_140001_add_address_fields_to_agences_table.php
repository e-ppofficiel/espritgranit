<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->string('code_postal')->nullable()->after('adresse');
            $table->string('commune')->nullable()->after('code_postal');
            $table->string('code_postal_livraison')->nullable()->after('adresse_livraison');
            $table->string('commune_livraison')->nullable()->after('code_postal_livraison');
            $table->string('contact_livraison')->nullable()->after('commune_livraison');
            $table->string('telephone_livraison')->nullable()->after('contact_livraison');
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropColumn([
                'code_postal',
                'commune',
                'code_postal_livraison',
                'commune_livraison',
                'contact_livraison',
                'telephone_livraison',
            ]);
        });
    }
};
