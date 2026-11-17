<?php

namespace App\Console\Commands;

use App\Models\Granit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Remplace l'image de chaque granit par sa version « tuilable » produite par
 *   node tools/eg-glb/eg-glb.mjs tuilable --dossier storage/app/public/granits
 * (fichiers dans <dossier>/tuilables/<nom>.tuilable.webp).
 *
 * Sans option : SIMULATION. --appliquer écrit en base (et enregistre les anciennes valeurs pour --annuler).
 * Les fichiers originaux ne sont jamais supprimés.
 */
class GranitsTuilables extends Command
{
    protected $signature = 'granits:tuilables
        {--appliquer : Remplace réellement l\'image des granits (sans cette option : simulation)}
        {--annuler : Restaure les images enregistrées lors de la dernière application}
        {--granit=* : Limiter à ces identifiants de granit}';

    protected $description = 'Remplace l\'image des granits par leur texture tuilable (tools/eg-glb), avec simulation et retour arrière';

    private const PREFIXE_SAUVEGARDE = 'granits-tuilables-';

    /** Chemin (disque public) de la texture tuilable attendue pour une image : granits/A.jpg → granits/tuilables/A.tuilable.webp */
    public static function cheminTuilable(string $image): string
    {
        $info = pathinfo($image);
        $dossier = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'] . '/';

        return $dossier . 'tuilables/' . $info['filename'] . '.tuilable.webp';
    }

    public function handle(): int
    {
        if ($this->option('annuler')) {
            return $this->annuler();
        }

        $disque = Storage::disk('public');
        $appliquer = (bool) $this->option('appliquer');
        $ids = array_filter((array) $this->option('granit'));

        $granits = Granit::query()
            ->whereNotNull('image')
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        if ($granits->isEmpty()) {
            $this->warn('Aucun granit avec image.');

            return self::SUCCESS;
        }

        // Deux images de même nom et d'extensions différentes (a.jpg / a.png) donneraient la même texture : ambigu.
        $occurrences = $granits->countBy(fn (Granit $g) => mb_strtolower(self::cheminTuilable($g->image)));

        $lignes = [];
        $sauvegarde = [];
        $compte = ['remplacé' => 0, 'à remplacer' => 0, 'déjà tuilable' => 0, 'texture absente' => 0, 'ambigu' => 0, 'erreur' => 0];

        foreach ($granits as $granit) {
            $ancienne = $granit->image;

            if (str_ends_with(mb_strtolower($ancienne), '.tuilable.webp')) {
                $statut = 'déjà tuilable';
                $nouvelle = '—';
            } else {
                $nouvelle = self::cheminTuilable($ancienne);
                if (($occurrences[mb_strtolower($nouvelle)] ?? 0) > 1) {
                    $statut = 'ambigu';
                } elseif (! $disque->exists($nouvelle)) {
                    $statut = 'texture absente';
                } elseif (! $appliquer) {
                    $statut = 'à remplacer';
                } else {
                    try {
                        $granit->update(['image' => $nouvelle]);
                        $sauvegarde[] = ['id' => $granit->id, 'ancienne' => $ancienne, 'nouvelle' => $nouvelle];
                        $statut = 'remplacé';
                    } catch (Throwable $e) {
                        // L'événement « saved » du modèle régénère la vignette : si cela échoue, l'image est
                        // quand même remplacée (la vignette précédente reste affichée).
                        if ($granit->fresh()?->image === $nouvelle) {
                            $sauvegarde[] = ['id' => $granit->id, 'ancienne' => $ancienne, 'nouvelle' => $nouvelle];
                            $statut = 'remplacé';
                            $this->warn("Granit #{$granit->id} : image remplacée, mais vignette non régénérée ({$e->getMessage()})");
                        } else {
                            $statut = 'erreur';
                            $this->error("Granit #{$granit->id} : {$e->getMessage()}");
                        }
                    }
                }
            }

            $compte[$statut]++;
            $lignes[] = [$granit->id, $granit->nom_commercial, $ancienne, $nouvelle, $statut];
        }

        $this->table(['ID', 'Granit', 'Image actuelle', 'Texture tuilable', 'Statut'], $lignes);
        $this->line(collect($compte)->filter()->map(fn ($n, $k) => "{$n} {$k}")->implode(', '));

        if ($appliquer && $sauvegarde) {
            $fichier = 'backups/' . self::PREFIXE_SAUVEGARDE . date('Ymd-His') . '.json';
            Storage::disk('local')->put($fichier, json_encode($sauvegarde, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info('Anciennes images enregistrées dans storage/app/' . $fichier . ' (retour arrière : php artisan granits:tuilables --annuler).');
            $this->line('Rappel : renseigner la taille de l\'échantillon (cm) de chaque granit dans l\'admin (30 cm par défaut).');
        } elseif (! $appliquer && $compte['à remplacer'] > 0) {
            $this->newLine();
            $this->comment('Simulation : rien n\'a été modifié. Relancer avec --appliquer pour remplacer les images.');
        }

        return $compte['erreur'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function annuler(): int
    {
        $disque = Storage::disk('local');
        $fichiers = collect($disque->files('backups'))
            ->filter(fn ($f) => str_contains($f, self::PREFIXE_SAUVEGARDE))
            ->sortDesc()
            ->values();

        if ($fichiers->isEmpty()) {
            $this->warn('Aucune sauvegarde trouvée dans storage/app/backups.');

            return self::SUCCESS;
        }

        $dernier = $fichiers->first();
        $entrees = json_decode($disque->get($dernier), true) ?: [];
        $this->line('Sauvegarde : ' . $dernier . ' (' . count($entrees) . ' granit(s))');

        if (! $this->confirm('Restaurer les anciennes images de ces granits ?')) {
            return self::SUCCESS;
        }

        $restaures = 0;
        foreach ($entrees as $entree) {
            $granit = Granit::find($entree['id']);
            if (! $granit) {
                continue;
            }
            if ($granit->image !== $entree['nouvelle']) {
                $this->warn("Granit #{$granit->id} : image modifiée depuis, ignoré.");

                continue;
            }
            try {
                $granit->update(['image' => $entree['ancienne']]);
            } catch (Throwable $e) {
                if ($granit->fresh()?->image !== $entree['ancienne']) {
                    $this->error("Granit #{$granit->id} : {$e->getMessage()}");

                    continue;
                }
            }
            $restaures++;
        }

        $this->info("{$restaures} granit(s) restauré(s).");

        return self::SUCCESS;
    }
}
