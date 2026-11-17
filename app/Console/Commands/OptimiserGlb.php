<?php

namespace App\Console\Commands;

use App\Models\Monument;
use App\Services\GlbOptimiseur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimiserGlb extends Command
{
    protected $signature = 'glb:optimiser
                            {monument? : ID du monument à traiter (sans argument : tous les monuments qui ont un fichier 3D)}
                            {--a-blanc : Ne modifie rien : indique seulement ce qui serait fait}
                            {--restaurer : Remet le fichier d\'origine (.original.glb) à la place de la version optimisée}';

    protected $description = 'Optimise les .glb des monuments déjà enregistrés (textures embarquées retirées, UV à l\'échelle réelle). L\'original est conservé.';

    public function handle(): int
    {
        if ($probleme = GlbOptimiseur::indisponibilite()) {
            $this->error($probleme);

            return self::FAILURE;
        }

        $id = $this->argument('monument');
        $requete = Monument::whereNotNull('fichier_3d')->orderBy('id');
        if ($id) {
            $requete->where('id', $id);
        }
        $monuments = $requete->get(['id', 'nom', 'fichier_3d', 'actif']);

        if ($monuments->isEmpty()) {
            $this->warn($id ? "Monument #{$id} introuvable ou sans fichier 3D." : 'Aucun monument n\'a de fichier 3D.');

            return self::FAILURE;
        }

        $restaurer = (bool) $this->option('restaurer');
        $aBlanc = (bool) $this->option('a-blanc');

        if (! $aBlanc && ! $this->option('no-interaction')) {
            $this->warn($restaurer
                ? 'Les fichiers d\'origine vont être remis à la place des versions optimisées.'
                : 'Le rendu 3D de ces monuments va changer (textures embarquées retirées : le site applique la texture du granit choisi). Les originaux sont conservés (.original.glb) ; « --restaurer » les remet.');
            if (! $this->confirm("Traiter {$monuments->count()} monument(s) ?", false)) {
                $this->line('Annulé : rien n\'a été modifié.');

                return self::SUCCESS;
            }
        }

        $lignes = [];
        $echecs = 0;
        $optimises = 0;
        $fichiersVus = [];

        foreach ($monuments as $monument) {
            $chemin = Storage::disk('public')->path($monument->fichier_3d);
            $etiquette = $monument->nom.($monument->actif ? '' : ' (inactif)');

            // Plusieurs monuments peuvent partager le même fichier : on ne le traite qu'une fois.
            if (isset($fichiersVus[$chemin])) {
                $lignes[] = [$monument->id, $etiquette, 'même fichier que #'.$fichiersVus[$chemin], ''];
                continue;
            }
            $fichiersVus[$chemin] = $monument->id;

            if ($restaurer) {
                $existe = is_file(GlbOptimiseur::cheminOriginal($chemin));
                $ok = $existe && ($aBlanc || GlbOptimiseur::restaurer($chemin));
                $lignes[] = [$monument->id, $etiquette, ! $existe ? 'pas d\'original conservé' : ($aBlanc ? 'original disponible' : ($ok ? 'original remis' : 'ÉCHEC')), ''];
                continue;
            }

            $r = GlbOptimiseur::optimiser($chemin, $aBlanc);
            $statut = match ($r['statut']) {
                GlbOptimiseur::OPTIMISE => 'optimisé',
                GlbOptimiseur::DEJA => 'déjà optimisé',
                GlbOptimiseur::A_FAIRE => 'à optimiser',
                GlbOptimiseur::INDISPONIBLE => 'indisponible',
                default => 'ÉCHEC',
            };
            if ($r['statut'] === GlbOptimiseur::OPTIMISE) {
                $optimises++;
            }
            if (in_array($r['statut'], [GlbOptimiseur::ECHEC, GlbOptimiseur::INDISPONIBLE], true)) {
                $echecs++;
            }

            $detail = $r['message'];
            if ($r['avertissements']) {
                $detail .= ' ⚠ '.count($r['avertissements']).' avertissement(s) : '.mb_substr($r['avertissements'][0], 0, 90);
            }
            $lignes[] = [$monument->id, $etiquette, $statut, $detail];
        }

        $this->table(['ID', 'Monument', 'Résultat', 'Détail'], $lignes);

        if ($aBlanc) {
            $this->line('(--a-blanc) Aucun fichier n\'a été modifié.');
        } elseif (! $restaurer) {
            $this->info("{$optimises} fichier(s) optimisé(s), {$echecs} échec(s). Rechargez la page du configurateur avec Ctrl+F5 si un ancien rendu reste affiché.");
        }

        return $echecs > 0 ? self::FAILURE : self::SUCCESS;
    }
}
