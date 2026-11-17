<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('granits', function (Blueprint $table) {
            // Brillance de la pierre polie dans le configurateur 3D (0 = satiné, 50 = rendu standard, 100 = miroir).
            // NULL = valeur par défaut de config/configurateur.php (50) : le rendu des granits existants ne change pas.
            if (! Schema::hasColumn('granits', 'brillance')) {
                $table->unsignedTinyInteger('brillance')->nullable()->after('echantillon_cm');
            }

            // Luminosité de la texture en % (50 à 150 ; 100 = texture inchangée). NULL = 100.
            if (! Schema::hasColumn('granits', 'luminosite')) {
                $table->unsignedSmallInteger('luminosite')->nullable()->after('brillance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('granits', function (Blueprint $table) {
            foreach (['luminosite', 'brillance'] as $colonne) {
                if (Schema::hasColumn('granits', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
