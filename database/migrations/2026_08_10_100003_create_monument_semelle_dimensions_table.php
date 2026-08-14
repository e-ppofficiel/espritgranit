<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monument_semelle_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semelle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable(); // ex: "150 x 250"
            $table->decimal('longueur', 8, 2);
            $table->decimal('largeur', 8, 2);
            $table->decimal('hauteur', 8, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('ordre_affichage')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monument_semelle_dimensions');
    }
};
