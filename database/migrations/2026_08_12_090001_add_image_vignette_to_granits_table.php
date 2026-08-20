<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('granits', function (Blueprint $table) {
            // Version compressée générée automatiquement à partir de 'image', utilisée pour
            // les vignettes (liste admin, grille du configurateur). 'image' reste inchangée
            // et continue de servir de texture haute qualité dans le rendu 3D.
            $table->string('image_vignette')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('granits', function (Blueprint $table) {
            $table->dropColumn('image_vignette');
        });
    }
};
