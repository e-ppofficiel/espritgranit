<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('granits', function (Blueprint $table) {
            $table->id();
            $table->string('nom_commercial');
            $table->string('reference_interne')->unique();
            $table->string('image')->nullable();
            $table->string('pays')->nullable();
            $table->boolean('actif')->default(true);
            $table->integer('ordre_affichage')->default(0);
            $table->text('restrictions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('granits');
    }
};
