<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monuments', function (Blueprint $table) {
            $table->decimal('poids_total_kg', 8, 2)->nullable()->after('description');
        });

        // Rétro-remplissage : on initialise le poids total à partir de la somme des blocs
        // déjà saisis, pour que les monuments existants ne perdent pas leur poids connu.
        // Champ resté nullable ensuite : Christophe/l'admin peut corriger la valeur au cas par cas.
        $monuments = DB::table('monuments')->select('id')->get();

        foreach ($monuments as $monument) {
            $poidsBlocs = DB::table('monument_blocs')
                ->where('monument_id', $monument->id)
                ->sum('poids_kg');

            if ($poidsBlocs > 0) {
                DB::table('monuments')
                    ->where('id', $monument->id)
                    ->update(['poids_total_kg' => $poidsBlocs]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('monuments', function (Blueprint $table) {
            $table->dropColumn('poids_total_kg');
        });
    }
};
