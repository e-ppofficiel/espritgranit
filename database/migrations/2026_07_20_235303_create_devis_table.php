<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('societe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('monument_id')->constrained()->restrictOnDelete();
            $table->foreignId('granit_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('origine', ['agence', 'admin'])->default('agence');
            $table->enum('statut', [
                'brouillon',
                'envoye',
                'en_attente',
                'accepte',
                'refuse',
                'expire',
                'transforme_commande',
            ])->default('brouillon');
            $table->foreignId('departement_livraison_id')->nullable()->constrained('departements')->nullOnDelete();
            $table->decimal('prix_granit_base', 10, 2)->nullable();
            $table->decimal('prix_transport', 10, 2)->nullable();
            $table->decimal('prix_total', 10, 2)->nullable();
            $table->text('commentaire')->nullable();
            $table->string('piece_jointe')->nullable();
            $table->string('visuel')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis');
    }
};
