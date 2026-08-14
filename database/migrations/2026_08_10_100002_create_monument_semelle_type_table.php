<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monument_semelle_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semelle_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['monument_id', 'semelle_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monument_semelle_type');
    }
};
