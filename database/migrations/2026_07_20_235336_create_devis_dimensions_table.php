<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monument_bloc_id')->constrained()->cascadeOnDelete();
            $table->decimal('longueur_commandee', 8, 2)->nullable();
            $table->decimal('largeur_commandee', 8, 2)->nullable();
            $table->decimal('epaisseur_commandee', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis_dimensions');
    }
};
