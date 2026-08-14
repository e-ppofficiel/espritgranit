<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semelle_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // SEMELLE-B, SEMELLE-P, SEMELLE-R
            $table->string('label');           // Bouchardée, Plate, À revers
            $table->integer('ordre_affichage')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semelle_types');
    }
};
