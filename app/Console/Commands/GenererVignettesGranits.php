<?php

namespace App\Console\Commands;

use App\Models\Granit;
use App\Services\ImageOptimizer;
use Illuminate\Console\Command;

class GenererVignettesGranits extends Command
{
    protected $signature = 'granits:generer-vignettes {--force : Régénère même si une vignette existe déjà}';

    protected $description = 'Génère les vignettes compressées manquantes pour les granits existants';

    public function handle(): int
    {
        $force = $this->option('force');

        $query = Granit::whereNotNull('image');
        if (! $force) {
            $query->whereNull('image_vignette');
        }

        $granits = $query->get();

        if ($granits->isEmpty()) {
            $this->info('Rien à faire, toutes les vignettes existent déjà (utilisez --force pour régénérer).');
            return self::SUCCESS;
        }

        $this->info("Génération de {$granits->count()} vignette(s)...");
        $bar = $this->output->createProgressBar($granits->count());

        $reussies = 0;
        foreach ($granits as $granit) {
            $vignette = ImageOptimizer::genererVignette($granit->image);
            if ($vignette) {
                $granit->updateQuietly(['image_vignette' => $vignette]);
                $reussies++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("{$reussies} / {$granits->count()} vignette(s) générée(s) avec succès.");

        if ($reussies < $granits->count()) {
            $this->warn('Certaines vignettes ont échoué, voir storage/logs/laravel.log pour le détail.');
        }

        return self::SUCCESS;
    }
}
