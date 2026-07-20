<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societes', function (Blueprint $table) {
            $table->id();
            $table->string('enseigne');
            $table->string('raison_sociale');
            $table->string('siret')->unique();
            $table->string('adresse_siege')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('contact_principal')->nullable();
            $table->enum('statut_compte', ['en_attente', 'actif', 'suspendu', 'refuse'])->default('en_attente');
            $table->decimal('coefficient_vente', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societes');
    }
};
