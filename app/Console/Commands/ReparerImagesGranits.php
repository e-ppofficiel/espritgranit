<?php

namespace App\Console\Commands;

use App\Models\Granit;
use App\Services\GranitImageLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReparerImagesGranits extends Command
{
    protected $signature = 'granits:reparer-images
                            {--appliquer : Écrit réellement les corrections en base (sans cette option : simple simulation)}';

    protected $description = 'Repointe la colonne image des granits dont le chemin est introuvable vers le fichier nommé d\'après leur référence interne';

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $disk = Storage::disk('public');

        $this->info($appliquer
            ? 'MODE APPLICATION : les corrections seront écrites en base.'
            : 'MODE SIMULATION : rien n\'est modifié (ajouter --appliquer pour écrire).');
        $this->newLine();

        $deja = 0;
        $corrigeables = 0;
        $introuvables = 0;
        $lignes = [];

        foreach (Granit::orderBy('id')->get() as $granit) {
            if ($granit->image && $disk->exists($granit->image)) {
                $deja++;
                continue;
            }

            $trouve = GranitImageLocator::parReference($granit);
            $actuel = $granit->image ?: '(vide)';

            if (! $trouve) {
                $introuvables++;
                $lignes[] = [$granit->id, $granit->reference_interne, $actuel, '— aucun fichier trouvé —', 'À TRAITER À LA MAIN'];
                continue;
            }

            $corrigeables++;

            if ($appliquer) {
                // save() déclenche le hook du modèle Granit : la vignette est régénérée
                // automatiquement puisque la colonne image change.
                $granit->image = $trouve;
                $granit->save();
            }

            $lignes[] = [$granit->id, $granit->reference_interne, $actuel, $trouve, $appliquer ? 'corrigé' : 'à corriger'];
        }

        if ($lignes !== []) {
            $this->table(['ID', 'Référence interne', 'Chemin en base', 'Fichier trouvé', 'Statut'], $lignes);
            $this->newLine();
        }

        $this->line("Déjà corrects          : {$deja}");
        $this->line('Corrigeables           : ' . $corrigeables . ($appliquer ? ' (corrigés)' : ' (non appliqués : simulation)'));
        $this->line("Fichier introuvable    : {$introuvables}");

        if (! $appliquer && $corrigeables > 0) {
            $this->newLine();
            $this->comment('Relancer avec --appliquer pour écrire ces corrections.');
        }

        return self::SUCCESS;
    }
}
