<?php

namespace App\Console\Commands;

use App\Models\Granit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImporterGranitsDepuisDossier extends Command
{
    protected $signature = 'granits:importer-depuis-dossier {--dry-run : Affiche ce qui serait créé sans rien enregistrer} {--inclure-noms-suspects : Ne pas ignorer les fichiers dont le nom ressemble à un identifiant généré automatiquement (ULID)}';

    protected $description = 'Crée automatiquement une fiche Granit pour chaque image présente dans storage/app/public/granits qui n\'a pas encore de fiche correspondante';

    protected array $extensionsValides = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Détecte les noms de fichiers ressemblant à un ULID (26 caractères, généré automatiquement
     * par Filament quand un fichier est uploadé sans ->preserveFilenames()) — signe qu'il s'agit
     * d'un résidu de test manuel et non d'un vrai granit nommé.
     */
    protected function ressembleAUnUlid(string $nom): bool
    {
        return (bool) preg_match('/^[0-9A-Z]{26}$/', $nom);
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $inclureNomsSuspects = $this->option('inclure-noms-suspects');
        $disk = Storage::disk('public');

        if (! $disk->exists('granits')) {
            $this->error("Le dossier 'granits' n'existe pas sur le disque public. As-tu bien transféré les fichiers ?");
            return self::FAILURE;
        }

        // files() n'est pas récursif : le sous-dossier granits/vignettes n'est donc pas scanné.
        $fichiers = $disk->files('granits');

        $crees = 0;
        $ignores = 0;
        $suspects = 0;

        foreach ($fichiers as $chemin) {
            $extension = strtolower(pathinfo($chemin, PATHINFO_EXTENSION));
            if (! in_array($extension, $this->extensionsValides)) {
                continue;
            }

            $nom = pathinfo($chemin, PATHINFO_FILENAME);

            if (! $inclureNomsSuspects && $this->ressembleAUnUlid($nom)) {
                $this->line("[ignoré, nom suspect type ULID] {$nom}");
                $suspects++;
                continue;
            }

            if (Granit::where('nom_commercial', $nom)->exists()) {
                $ignores++;
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] Créerait : {$nom}");
                $crees++;
                continue;
            }

            Granit::create([
                'nom_commercial' => $nom,
                'reference_interne' => $nom,
                'image' => $chemin,
                'actif' => true,
                'ordre_affichage' => 0,
            ]);

            $this->line("Créé : {$nom}");
            $crees++;
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("{$crees} granit(s) seraient créés, {$ignores} déjà existant(s), {$suspects} nom(s) suspect(s) ignoré(s). Relance sans --dry-run pour appliquer.");
        } else {
            $this->info("{$crees} granit(s) créé(s), {$ignores} déjà existant(s), {$suspects} nom(s) suspect(s) ignoré(s).");
            $this->comment('Les vignettes compressées se génèrent automatiquement à la création (via le hook du modèle Granit).');
        }

        return self::SUCCESS;
    }
}
