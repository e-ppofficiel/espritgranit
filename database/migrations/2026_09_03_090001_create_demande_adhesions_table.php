<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demande_adhesions', function (Blueprint $table) {
            $table->id();
            $table->string('enseigne');
            $table->string('raison_sociale');
            $table->string('siret');
            $table->string('adresse');
            $table->string('code_postal')->nullable();
            $table->string('commune')->nullable();
            $table->string('adresse_livraison')->nullable();
            $table->string('code_postal_livraison')->nullable();
            $table->string('commune_livraison')->nullable();
            $table->string('email');
            $table->string('telephone');
            $table->string('nom_contact');
            $table->string('statut')->default('en_attente'); // en_attente / acceptee / refusee
            $table->text('motif_refus')->nullable();
            $table->foreignId('societe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('traitee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_adhesions');
    }
};
