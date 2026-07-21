<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_tarifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_zone_id')->constrained()->cascadeOnDelete();
            $table->decimal('poids_min', 8, 2)->default(0);
            $table->decimal('poids_max', 8, 2)->nullable();
            $table->decimal('prix', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_tarifs');
    }
};
