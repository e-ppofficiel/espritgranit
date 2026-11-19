<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurations du configurateur 3D enregistrées côté serveur (granit, options, ambiance…).
     * L'adresse de la page ne contient qu'un code court (?c=…) : aucun réglage lisible ni modifiable côté client.
     */
    public function up(): void
    {
        Schema::create('configurations_partagees', function (Blueprint $table) {
            $table->id();
            // Début du MD5 de « monument|état » : une même configuration retrouve toujours le même code.
            $table->string('code', 32)->unique();
            // Supprimer un monument supprime ses configurations (ne bloque pas le ménage des monuments).
            $table->foreignId('monument_id')->constrained()->cascadeOnDelete();
            $table->json('etat');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('dernier_acces_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations_partagees');
    }
};
