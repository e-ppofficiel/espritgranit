<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('devis_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('societe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('monument_id')->constrained()->restrictOnDelete();
            $table->foreignId('granit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commande_par')->nullable();
            $table->text('adresse_livraison')->nullable();
            $table->foreignId('departement_livraison_id')->nullable()->constrained('departements')->nullOnDelete();
            $table->enum('statut', [
                'en_preparation',
                'confirmee',
                'en_production',
                'en_transit',
                'au_port_arrivee',
                'livree',
                'annulee',
                'archivee',
            ])->default('en_preparation');
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('prix_transport', 10, 2)->nullable();
            $table->decimal('prix_final', 10, 2)->nullable();
            $table->date('date_depart_prevue')->nullable();
            $table->date('date_arrivee_prevue')->nullable();
            $table->date('date_arrivee_reelle')->nullable();
            $table->text('commentaire')->nullable();
            $table->string('document_cgv')->nullable();
            $table->timestamp('annulee_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
