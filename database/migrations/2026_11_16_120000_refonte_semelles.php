<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Refonte des semelles (réunion du 16/11/2026) :
 *  - 4 types : SEMELLE-B, SEMELLE-P, SEMELLE-R-P (revers d'eau poli), SEMELLE-R-B (revers d'eau bouchardée) ;
 *    l'ancien type SEMELLE-R devient SEMELLE-R-P (même id : les liens existants sont conservés) ;
 *  - une « référence » sur chaque dimension de semelle prédéfinie (ex. SEMELLE-B-140X240) ;
 *  - une table de prix des semelles (référence × monument × granit) ;
 *  - le choix de semelle mémorisé dans les devis et les commandes.
 *
 * Rien n'est supprimé. Migration réentrante : relancée, elle ne casse rien.
 */
return new class extends Migration
{
    private const TYPES = [
        'SEMELLE-B' => ['label' => 'semelle bouchardée', 'ordre' => 1],
        'SEMELLE-P' => ['label' => 'semelle plate', 'ordre' => 2],
        'SEMELLE-R-P' => ['label' => "semelle à revers d'eau poli", 'ordre' => 3],
        'SEMELLE-R-B' => ['label' => "semelle à revers d'eau bouchardée", 'ordre' => 4],
    ];

    public function up(): void
    {
        $maintenant = now();

        // 1. Types de semelle
        $ancien = DB::table('semelle_types')->where('code', 'SEMELLE-R')->first();
        if ($ancien && ! DB::table('semelle_types')->where('code', 'SEMELLE-R-P')->exists()) {
            DB::table('semelle_types')->where('id', $ancien->id)->update(['code' => 'SEMELLE-R-P', 'updated_at' => $maintenant]);
        }
        foreach (self::TYPES as $code => $definition) {
            if (DB::table('semelle_types')->where('code', $code)->exists()) {
                DB::table('semelle_types')->where('code', $code)->update([
                    'label' => $definition['label'],
                    'ordre_affichage' => $definition['ordre'],
                    'updated_at' => $maintenant,
                ]);
            } else {
                DB::table('semelle_types')->insert([
                    'code' => $code,
                    'label' => $definition['label'],
                    'ordre_affichage' => $definition['ordre'],
                    'actif' => 1,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]);
            }
        }

        // 2. Référence des dimensions prédéfinies (pré-remplie : code du type + largeur X longueur)
        if (! Schema::hasColumn('monument_semelle_dimensions', 'reference')) {
            Schema::table('monument_semelle_dimensions', function (Blueprint $table) {
                $table->string('reference')->nullable()->index();
            });
        }
        $codes = DB::table('semelle_types')->pluck('code', 'id');
        DB::table('monument_semelle_dimensions')->whereNull('reference')->orderBy('id')->each(function ($ligne) use ($codes) {
            $code = $ligne->semelle_type_id ? ($codes[$ligne->semelle_type_id] ?? null) : null;
            DB::table('monument_semelle_dimensions')->where('id', $ligne->id)->update([
                'reference' => ($code ?: 'SEMELLE').'-'.self::nombre($ligne->largeur).'X'.self::nombre($ligne->longueur),
            ]);
        });

        // 3. Prix des semelles : une ligne par monument, granit et référence de semelle
        if (! Schema::hasTable('monument_semelle_prix')) {
            Schema::create('monument_semelle_prix', function (Blueprint $table) {
                $table->id();
                $table->foreignId('monument_id')->constrained()->cascadeOnDelete();
                $table->foreignId('granit_id')->constrained()->cascadeOnDelete();
                $table->string('reference');
                $table->decimal('prix_fob', 10, 2)->nullable();
                $table->decimal('taux_marge', 5, 4)->nullable();
                $table->decimal('poids_kg', 8, 2)->nullable();
                $table->timestamps();

                $table->unique(['monument_id', 'granit_id', 'reference'], 'monument_semelle_prix_unique');
                $table->index('reference');
            });
        }

        // 4. Choix de semelle mémorisé (colonnes facultatives : les anciens devis ne changent pas)
        if (! Schema::hasColumn('devis', 'semelle_reference')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->foreignId('semelle_type_id')->nullable()->constrained('semelle_types')->nullOnDelete();
                $table->foreignId('semelle_dimension_id')->nullable()->constrained('monument_semelle_dimensions')->nullOnDelete();
                $table->string('semelle_reference')->nullable();
                $table->string('semelle_libelle')->nullable();
                $table->decimal('prix_semelle', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('commandes', 'semelle_reference')) {
            Schema::table('commandes', function (Blueprint $table) {
                $table->string('semelle_reference')->nullable();
                $table->string('semelle_libelle')->nullable();
                $table->decimal('prix_semelle', 10, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commandes', 'semelle_reference')) {
            Schema::table('commandes', function (Blueprint $table) {
                $table->dropColumn(['semelle_reference', 'semelle_libelle', 'prix_semelle']);
            });
        }
        if (Schema::hasColumn('devis', 'semelle_reference')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->dropConstrainedForeignId('semelle_type_id');
                $table->dropConstrainedForeignId('semelle_dimension_id');
                $table->dropColumn(['semelle_reference', 'semelle_libelle', 'prix_semelle']);
            });
        }
        Schema::dropIfExists('monument_semelle_prix');
        if (Schema::hasColumn('monument_semelle_dimensions', 'reference')) {
            Schema::table('monument_semelle_dimensions', function (Blueprint $table) {
                $table->dropIndex(['reference']);
                $table->dropColumn('reference');
            });
        }

        // Types : retour à l'état d'avant (SEMELLE-R-B supprimé seulement s'il n'est utilisé nulle part)
        $rb = DB::table('semelle_types')->where('code', 'SEMELLE-R-B')->first();
        if ($rb) {
            $utilise = DB::table('monument_semelle_type')->where('semelle_type_id', $rb->id)->exists()
                || DB::table('monument_semelle_dimensions')->where('semelle_type_id', $rb->id)->exists();
            if (! $utilise) {
                DB::table('semelle_types')->where('id', $rb->id)->delete();
            }
        }
        if (! DB::table('semelle_types')->where('code', 'SEMELLE-R')->exists()) {
            DB::table('semelle_types')->where('code', 'SEMELLE-R-P')->update(['code' => 'SEMELLE-R', 'label' => 'À revers']);
        }
        DB::table('semelle_types')->where('code', 'SEMELLE-B')->update(['label' => 'Bouchardée', 'ordre_affichage' => 0]);
        DB::table('semelle_types')->where('code', 'SEMELLE-P')->update(['label' => 'Plate', 'ordre_affichage' => 0]);
    }

    /** 130.00 → "130", 137.50 → "137.5" */
    private static function nombre($valeur): string
    {
        return rtrim(rtrim(number_format((float) $valeur, 2, '.', ''), '0'), '.');
    }
};
