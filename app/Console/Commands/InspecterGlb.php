<?php

namespace App\Console\Commands;

use App\Models\Monument;
use App\Services\GlbInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InspecterGlb extends Command
{
    protected $signature = 'glb:inspecter
                            {monument? : ID du monument dont on inspecte le .glb (sans argument : tous les monuments)}
                            {--fichier= : Inspecter un fichier précis (chemin absolu, ou relatif au disque public)}
                            {--json : Afficher le résultat en JSON brut}';

    protected $description = 'Vérifie les matériaux d\'un .glb par rapport à la nomenclature du configurateur 3D (GRANIT, BICOLOR, SEMELLE-B/P/R)';

    public function handle(): int
    {
        if ($fichier = $this->option('fichier')) {
            return $this->detail($this->resoudreChemin($fichier), $fichier);
        }

        if ($id = $this->argument('monument')) {
            $monument = Monument::find($id);

            if (! $monument) {
                $this->error("Monument #{$id} introuvable.");

                return self::FAILURE;
            }

            if (! $monument->fichier_3d) {
                $this->error("Le monument #{$id} (« {$monument->nom} ») n'a pas de fichier 3D.");

                return self::FAILURE;
            }

            $this->info("Monument #{$monument->id} — {$monument->nom}");

            return $this->detail(
                Storage::disk('public')->path($monument->fichier_3d),
                $monument->fichier_3d,
                $this->typesDuMonument($monument)
            );
        }

        return $this->vueEnsemble();
    }

    /**
     * Types de semelle rattachés au monument, tels que le configurateur les affiche en boutons
     * (le code en base sert de préfixe pour retrouver les matériaux).
     *
     * @return array<int, array{code: ?string, label: ?string}>
     */
    protected function typesDuMonument(Monument $monument): array
    {
        return $monument->semelleTypes
            ->map(fn ($type) => ['code' => $type->code, 'label' => $type->label])
            ->all();
    }

    protected function resoudreChemin(string $fichier): string
    {
        if (is_file($fichier)) {
            return $fichier;
        }

        return Storage::disk('public')->path(ltrim($fichier, '/'));
    }

    /**
     * Un monument par ligne : nombre de matériaux reconnus par famille et verdict.
     */
    protected function vueEnsemble(): int
    {
        $monuments = Monument::whereNotNull('fichier_3d')->with('semelleTypes')->orderBy('id')->get(['id', 'nom', 'fichier_3d']);

        if ($monuments->isEmpty()) {
            $this->warn('Aucun monument n\'a de fichier 3D.');

            return self::SUCCESS;
        }

        $lignes = [];
        $jsonTout = [];

        foreach ($monuments as $monument) {
            try {
                $r = GlbInspector::analyser(Storage::disk('public')->path($monument->fichier_3d));
            } catch (RuntimeException $e) {
                $lignes[] = [$monument->id, $monument->nom, '—', '—', '—', '—', '—', '—', 'ILLISIBLE : ' . $e->getMessage()];
                continue;
            }

            $f = $r['familles'];
            $types = GlbInspector::verifierTypesSemelle($r, $this->typesDuMonument($monument));
            $r['types_semelle'] = $types['types'];
            $r['problemes'] = array_merge($r['problemes'], $types['problemes']);
            $erreurs = count(array_filter($r['problemes'], fn (array $p) => $p['niveau'] === 'erreur'));
            $sansMateriau = count(array_filter($types['types'], fn (array $t) => $t['materiaux'] === 0));
            $jsonTout[] = ['monument_id' => $monument->id, 'nom' => $monument->nom] + $r;

            $lignes[] = [
                $monument->id,
                $monument->nom,
                $f['GRANIT']['materiaux'],
                $f['BICOLOR']['materiaux'],
                $f['SEMELLE-B']['materiaux'],
                $f['SEMELLE-P']['materiaux'],
                $f['SEMELLE-R']['materiaux'],
                $types['types'] === [] ? '—' : ($sansMateriau === 0 ? 'OK' : "{$sansMateriau} sans matériau"),
                $erreurs === 0 ? 'OK' : "{$erreurs} erreur(s)",
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($jsonTout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['ID', 'Monument', 'GRANIT', 'BICOLOR', 'SEMELLE-B', 'SEMELLE-P', 'SEMELLE-R', 'Boutons de type', 'Verdict'], $lignes);
        $this->comment('Nombre de matériaux reconnus par famille. « Boutons de type » : les codes des types de semelle en base retrouvent-ils des matériaux ? Détail : php artisan glb:inspecter <ID>');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{code: ?string, label: ?string}>|null  $types  Types de semelle du monument (null : pas de contrôle)
     */
    protected function detail(string $chemin, string $libelle, ?array $types = null): int
    {
        try {
            $r = GlbInspector::analyser($chemin);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($types !== null) {
            $verif = GlbInspector::verifierTypesSemelle($r, $types);
            $r['types_semelle'] = $verif['types'];
            $r['problemes'] = array_merge($r['problemes'], $verif['problemes']);
            $r['conforme'] = count(array_filter($r['problemes'], fn (array $p) => $p['niveau'] === 'erreur')) === 0;
        }

        if ($this->option('json')) {
            $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $r['conforme'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line("Fichier        : {$libelle}");
        $this->line('Taille         : ' . number_format($r['taille_octets'] / 1048576, 2, ',', ' ') . ' Mo');
        $this->line('Généré par     : ' . ($r['generateur'] ?? 'inconnu') . ' (glTF ' . ($r['version_gltf'] ?? '?') . ')');
        $this->line("Matériaux      : {$r['nb_materiaux']}");
        $this->line('Textures       : ' . $r['nb_images'] . ' image(s) embarquée(s), ' . number_format($r['octets_images'] / 1048576, 2, ',', ' ') . ' Mo');
        $this->line('UV en mètres   : ' . ($r['uv_metres'] ? 'oui (fichier optimisé)' : 'non (voir tools/eg-glb)'));
        $this->newLine();

        // Plusieurs matériaux peuvent porter exactement le même nom (KeyShot en exporte parfois
        // une dizaine) : on les regroupe pour garder un rapport lisible.
        $groupes = [];
        foreach ($r['materiaux'] as $m) {
            $cle = (string) ($m['nom'] ?? '');

            if (! isset($groupes[$cle])) {
                $groupes[$cle] = ['nom' => $m['nom'], 'famille' => $m['famille'], 'alerte' => $m['alerte'], 'nombre' => 0, 'primitives' => 0, 'pieces' => []];
            }

            $groupes[$cle]['nombre']++;
            $groupes[$cle]['primitives'] += $m['primitives'];
            $groupes[$cle]['pieces'] = array_values(array_unique(array_merge($groupes[$cle]['pieces'], $m['pieces'])));
        }

        $lignes = [];
        foreach ($groupes as $g) {
            $affichees = array_slice($g['pieces'], 0, 3);
            $texte = implode(', ', $affichees) . (count($g['pieces']) > 3 ? ' +' . (count($g['pieces']) - 3) : '');

            $lignes[] = [
                '« ' . ($g['nom'] ?? '(sans nom)') . ' »',
                $g['nombre'],
                $g['famille'] ?? ($g['alerte'] ? 'NON RECONNU' : '—'),
                $g['primitives'],
                $texte !== '' ? $texte : '(aucune pièce)',
            ];
        }
        $this->table(['Nom du matériau', 'Nombre', 'Famille reconnue', 'Primitives', 'Pièces qui le portent'], $lignes);

        $this->newLine();
        $resume = [];
        foreach ($r['familles'] as $famille => $infos) {
            $resume[] = [
                $famille,
                $infos['materiaux'] > 0 ? 'oui' : 'MANQUANT',
                $infos['materiaux'],
                implode(', ', array_slice($infos['pieces'], 0, 4)) ?: '—',
            ];
        }
        $this->table(['Famille', 'Présente', 'Matériaux', 'Pièces'], $resume);

        if (! empty($r['types_semelle'])) {
            $this->newLine();
            $this->line('Boutons « Type de semelle » du configurateur (codes lus en base) :');
            $this->table(
                ['Bouton', 'Code en base', 'Matériaux SEMELLE… qui commencent par ce code'],
                array_map(fn (array $t) => [$t['label'], $t['code'] !== '' ? $t['code'] : '(vide)', $t['materiaux'] > 0 ? $t['materiaux'] : '0  ← sans effet'], $r['types_semelle'])
            );
        }

        $this->newLine();
        if ($r['problemes'] === []) {
            $this->info('Aucun problème détecté : le fichier respecte la nomenclature.');

            return self::SUCCESS;
        }

        foreach ($r['problemes'] as $p) {
            $p['niveau'] === 'erreur' ? $this->error("ERREUR  {$p['message']}") : $this->warn("ALERTE  {$p['message']}");
        }

        return $r['conforme'] ? self::SUCCESS : self::FAILURE;
    }
}
