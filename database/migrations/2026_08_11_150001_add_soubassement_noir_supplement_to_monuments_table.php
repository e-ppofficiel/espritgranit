<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monuments', function (Blueprint $table) {
            // 'fixe' (montant en €) ou 'pourcentage' (% du prix granit de base). Null = pas d'option pour ce monument.
            $table->enum('soubassement_noir_supplement_type', ['fixe', 'pourcentage'])->nullable()->after('actif');
            $table->decimal('soubassement_noir_supplement_valeur', 8, 2)->nullable()->after('soubassement_noir_supplement_type');
        });
    }

    public function down(): void
    {
        Schema::table('monuments', function (Blueprint $table) {
            $table->dropColumn(['soubassement_noir_supplement_type', 'soubassement_noir_supplement_valeur']);
        });
    }
};
