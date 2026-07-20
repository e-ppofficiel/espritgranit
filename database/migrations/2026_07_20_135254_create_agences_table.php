<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('societe_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('adresse')->nullable();
            $table->string('adresse_livraison')->nullable();
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->decimal('coefficient_vente', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
