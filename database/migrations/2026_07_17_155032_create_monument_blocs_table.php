<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monument_blocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monument_id')->constrained()->cascadeOnDelete();
            $table->string('nom_bloc');
            $table->decimal('longueur_standard', 8, 2)->nullable();
            $table->decimal('largeur_standard', 8, 2)->nullable();
            $table->decimal('epaisseur_standard', 8, 2)->nullable();
            $table->text('commentaire')->nullable();
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monument_blocs');
    }
};
