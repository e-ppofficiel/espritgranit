<?php

use App\Services\FondVignette;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
 * Fonds du configurateur 3D gérables dans l'admin (v22) :
 *  - table `fonds` : couleurs de fond et ambiances (panoramas 360°) ;
 *  - première installation : reprend les fonds actuels de config/configurateur.php
 *    (Noir, Clair neutre, Studio et les 9 ambiances), leurs fichiers sont COPIÉS dans storage/app/public/fonds/
 *    (les originaux de public/configurateur/ ne sont ni déplacés ni modifiés) et une vignette est générée pour chaque ambiance.
 *
 * Migration réentrante : si la table existe déjà et contient des fonds, rien n'est touché.
 * Retour arrière : down() supprime la table (les fichiers copiés restent sur le disque).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fonds')) {
            Schema::create('fonds', function (Blueprint $table) {
                $table->id();
                $table->string('cle', 80)->unique();            // identifiant stable des liens partagés (jamais modifié)
                $table->string('nom', 120);
                $table->string('type', 20)->index();            // couleur | ambiance
                $table->string('couleur', 7)->nullable();       // #rrggbb (type couleur)
                $table->string('fichier')->nullable();          // panorama .hdr / .jpg / .png (type ambiance), disque public
                $table->string('decor_fichier')->nullable();    // image de décor plus nette (optionnelle)
                $table->string('vignette')->nullable();
                $table->decimal('exposition', 4, 2)->default(1.50);
                $table->boolean('visible_decor')->default(true);
                $table->unsignedInteger('ordre')->default(0);
                $table->boolean('actif')->default(true);
                $table->timestamps();
            });
        }

        if (DB::table('fonds')->count() === 0) {
            $this->reprendreConfig();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fonds');
    }

    private function reprendreConfig(): void
    {
        $maintenant = now();
        $lignes = [];

        // Couleurs de fond
        $clair = (string) config('configurateur.fond_clair_couleur', '#e6e3de');
        $clair = preg_match('/^#[0-9a-fA-F]{6}$/', $clair) ? strtolower($clair) : '#e6e3de';
        $lignes[] = ['cle' => 'noir', 'nom' => 'Noir', 'type' => 'couleur', 'couleur' => '#0a0a0a', 'ordre' => 10];
        $lignes[] = ['cle' => 'blanc', 'nom' => 'Clair neutre', 'type' => 'couleur', 'couleur' => $clair, 'ordre' => 20];

        // Ambiance par défaut (éclairage studio) puis ambiances paysagères, dans l'ordre de la config.
        $exposition = (float) config('configurateur.exposition', 1.5);
        $environnements = (array) config('configurateur.environnements', [
            'studio' => 'configurateur/studio.hdr',
            'contraste' => 'configurateur/studio-contraste.hdr',
        ]);
        $chemin = $environnements[config('configurateur.environnement', 'contraste')] ?? 'configurateur/studio.hdr';
        if (! file_exists(public_path($chemin))) {
            $chemin = 'configurateur/studio.hdr';
        }
        $sources = [];
        if (file_exists(public_path($chemin))) {
            $sources[] = ['cle' => 'defaut', 'nom' => (string) config('configurateur.ambiance_defaut_libelle', 'Studio'),
                'fichier' => $chemin, 'decor' => false, 'exposition' => $exposition];
        }
        foreach ((array) config('configurateur.ambiances', []) as $cle => $a) {
            if ($cle === 'defaut' || empty($a['fichier']) || ! file_exists(public_path($a['fichier']))) {
                continue;
            }
            $sources[] = [
                'cle' => (string) $cle,
                'nom' => (string) ($a['libelle'] ?? ucfirst(str_replace('-', ' ', (string) $cle))),
                'fichier' => $a['fichier'],
                'decor' => $a['decor'] ?? null,        // false = pas de décor ; texte = image de décor dédiée ; null = le panorama
                'exposition' => (float) ($a['exposition'] ?? $exposition),
            ];
        }

        $disque = Storage::disk('public');
        $ordre = 100;
        foreach ($sources as $s) {
            $copie = $this->copier($s['fichier'], 'fonds');
            if (! $copie) {
                continue;
            }
            $decor = (is_string($s['decor']) && file_exists(public_path($s['decor']))) ? $this->copier($s['decor'], 'fonds/decors') : null;

            $vignette = null;
            $relatif = 'fonds/vignettes/' . $s['cle'] . '.jpg';
            if (FondVignette::generer($disque->path($copie), $disque->path($relatif))) {
                $vignette = $relatif;
            }

            $lignes[] = [
                'cle' => $s['cle'], 'nom' => $s['nom'], 'type' => 'ambiance', 'couleur' => null,
                'fichier' => $copie, 'decor_fichier' => $decor, 'vignette' => $vignette,
                'exposition' => $s['exposition'], 'visible_decor' => $s['decor'] !== false, 'ordre' => $ordre,
            ];
            $ordre += 10;
        }

        foreach ($lignes as $l) {
            DB::table('fonds')->insert($l + [
                'couleur' => null, 'fichier' => null, 'decor_fichier' => null, 'vignette' => null,
                'exposition' => 1.5, 'visible_decor' => true, 'actif' => true,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    /** Copie un fichier de public/ vers le disque public (dossier donné) ; renvoie le chemin relatif, ou null. */
    private function copier(string $cheminPublic, string $dossier): ?string
    {
        $disque = Storage::disk('public');
        $destination = $dossier . '/' . basename($cheminPublic);
        $disque->makeDirectory($dossier);
        if (! @copy(public_path($cheminPublic), $disque->path($destination))) {
            return null;
        }
        @chmod($disque->path($destination), 0644);

        return $destination;
    }
};
