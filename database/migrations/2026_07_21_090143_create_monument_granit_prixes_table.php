<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monument_granit_prix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granit_id')->constrained()->cascadeOnDelete();
            $table->decimal('prix_fob', 10, 2)->nullable();
            $table->decimal('taux_marge', 5, 4)->nullable();
            $table->timestamps();
            $table->unique(['monument_id', 'granit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monument_granit_prix');
    }
};
