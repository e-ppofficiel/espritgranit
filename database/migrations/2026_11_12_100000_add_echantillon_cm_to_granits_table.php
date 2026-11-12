<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('granits', 'echantillon_cm')) {
            return;
        }

        Schema::table('granits', function (Blueprint $table) {
            // Taille réelle, en cm, du carré photographié (sert à régler la taille du grain dans le configurateur 3D).
            // NULL = valeur par défaut de config/configurateur.php (30 cm).
            $table->unsignedSmallInteger('echantillon_cm')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('granits', 'echantillon_cm')) {
            Schema::table('granits', function (Blueprint $table) {
                $table->dropColumn('echantillon_cm');
            });
        }
    }
};
