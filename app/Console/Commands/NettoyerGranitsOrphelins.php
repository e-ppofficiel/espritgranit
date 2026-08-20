<?php

namespace App\Console\Commands;

use App\Models\Granit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NettoyerGranitsOrphelins extends Command
{
    protected $signature = 'granits:nettoyer-orphelins {--force : Supprime réellement (sans cette option, affiche seulement un aperçu)}';

    protected $description = 'Supprime les fiches Granit dont le nom ressemble à un identifiant généré automatiquement (résidus d\'anciens tests d\'upload), ainsi que leurs fichiers image associés';

    public function handle(): int
    {
        $force = $this->option('force');

        $suspects = Granit::whereRaw('nom_commercial REGEXP ?', ['^[0-9A-Z]{26}$'])->get();

        if ($suspects->isEmpty()) {
            $this->info('Aucun granit suspect trouvé.');
            return self::SUCCESS;
        }

        $this->warn("{$suspects->count()} granit(s) suspect(s) trouvé(s) :");
        foreach ($suspects as $granit) {
            $this->line("- {$granit->nom_commercial} (id: {$granit->id})");
        }

        if (! $force) {
            $this->newLine();
            $this->comment('Aperçu seulement. Relance avec --force pour supprimer réellement ces fiches et leurs fichiers.');
            return self::SUCCESS;
        }

        $disk = Storage::disk('public');
        $supprimes = 0;

        foreach ($suspects as $granit) {
            if ($granit->image && $disk->exists($granit->image)) {
                $disk->delete($granit->image);
            }
            if ($granit->image_vignette && $disk->exists($granit->image_vignette)) {
                $disk->delete($granit->image_vignette);
            }
            $granit->delete();
            $supprimes++;
        }

        $this->newLine();
        $this->info("{$supprimes} granit(s) supprimé(s), avec leurs fichiers associés.");

        return self::SUCCESS;
    }
}
