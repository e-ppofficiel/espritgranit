<?php

namespace App\Services;

use App\Models\Monument;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Optimise un .glb avec tools/eg-glb (textures embarquées retirées, UV à l'échelle réelle).
 *
 * - Appelé automatiquement quand le fichier 3D d'un monument est envoyé (voir Monument::booted)
 *   et par la commande « php artisan glb:optimiser » pour les monuments déjà en base.
 * - Ne casse jamais l'enregistrement : en cas de problème (Node absent, fichier illisible…),
 *   le fichier d'origine est conservé tel quel et un avertissement est affiché.
 * - L'original est gardé à côté : <nom>.original.glb (jamais écrasé s'il existe déjà).
 */
class GlbOptimiseur
{
    public const OPTIMISE = 'optimise';
    public const DEJA = 'deja';
    public const A_FAIRE = 'a_faire';
    public const ECHEC = 'echec';
    public const INDISPONIBLE = 'indisponible';

    /** Évite de retraiter deux fois le même fichier dans une même requête. */
    protected static array $traites = [];

    public static function activee(): bool
    {
        return (bool) config('configurateur.glb.optimisation_auto', true);
    }

    public static function cheminScript(): string
    {
        return base_path('tools/eg-glb/eg-glb.mjs');
    }

    public static function cheminOriginal(string $chemin): string
    {
        return preg_replace('/\.glb$/i', '', $chemin).'.original.glb';
    }

    /** Chemin de l'exécutable Node, ou null s'il est introuvable. */
    public static function trouverNode(): ?string
    {
        $configure = config('configurateur.glb.node');
        if ($configure) {
            return is_file($configure) && is_executable($configure) ? $configure : null;
        }

        foreach (['/usr/bin/node', '/usr/local/bin/node', '/opt/node/bin/node'] as $candidat) {
            if (is_file($candidat) && is_executable($candidat)) {
                return $candidat;
            }
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dossier) {
            $candidat = rtrim($dossier, '/').'/node';
            if ($dossier !== '' && is_file($candidat) && is_executable($candidat)) {
                return $candidat;
            }
        }

        return null;
    }

    /** Message d'indisponibilité (Node, script, dépendances), ou null si tout est prêt. */
    public static function indisponibilite(): ?string
    {
        if (! function_exists('proc_open')) {
            return 'proc_open est désactivé dans la configuration PHP.';
        }
        if (! static::trouverNode()) {
            return 'Node.js est introuvable pour l\'utilisateur du site (installer Node 20 ou plus, ou renseigner EG_NODE_BIN dans .env).';
        }
        if (! is_file(static::cheminScript())) {
            return 'tools/eg-glb/eg-glb.mjs est absent du site.';
        }
        if (! is_dir(base_path('tools/eg-glb/node_modules'))) {
            return 'Dépendances manquantes : lancer « cd tools/eg-glb && npm install ».';
        }

        return null;
    }

    /**
     * Optimise le .glb situé à $chemin (chemin absolu), en place.
     *
     * @return array{statut: string, message: string, avant: ?int, apres: ?int, avertissements: array<int, string>, textures: ?string}
     */
    public static function optimiser(string $chemin, bool $aBlanc = false): array
    {
        $resultat = ['statut' => self::ECHEC, 'message' => '', 'avant' => null, 'apres' => null, 'avertissements' => [], 'textures' => null];

        if (! is_file($chemin)) {
            $resultat['message'] = 'Fichier introuvable : '.basename($chemin);

            return $resultat;
        }

        if ($probleme = static::indisponibilite()) {
            $resultat['statut'] = self::INDISPONIBLE;
            $resultat['message'] = $probleme;

            return $resultat;
        }

        $resultat['avant'] = filesize($chemin) ?: null;
        $tmp = preg_replace('/\.glb$/i', '', $chemin).'.optimise.tmp.glb';
        @unlink($tmp);

        $commande = [static::trouverNode(), static::cheminScript(), 'optimiser', $chemin, '--sortie', $tmp];
        if ($aBlanc) {
            $commande[] = '--a-blanc';
        }

        [$code, $sortie, $erreur] = static::executer($commande, (int) config('configurateur.glb.delai_secondes', 300));

        foreach (preg_split('/\R/', $sortie) as $ligne) {
            $ligne = trim($ligne);
            if (str_starts_with($ligne, '⚠')) {
                $resultat['avertissements'][] = preg_replace('/^⚠\s*/u', '', $ligne);
            } elseif (str_starts_with($ligne, 'Textures embarquées retirées')) {
                $resultat['textures'] = $ligne;
            }
        }

        if ($code !== 0) {
            @unlink($tmp);
            $resultat['message'] = static::messageErreur($erreur, $sortie) ?: "eg-glb a échoué (code {$code}).";

            return $resultat;
        }

        if (str_contains($sortie, 'déjà optimisé')) {
            @unlink($tmp);
            $resultat['statut'] = self::DEJA;
            $resultat['message'] = 'Fichier déjà optimisé : rien à faire.';

            return $resultat;
        }

        if ($aBlanc) {
            $resultat['statut'] = self::A_FAIRE;
            $resultat['message'] = $resultat['textures'] ?: 'À optimiser.';

            return $resultat;
        }

        if (! is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            $resultat['message'] = 'eg-glb n\'a produit aucun fichier.';

            return $resultat;
        }

        try {
            $json = GlbInspector::lireJson($tmp);
        } catch (Throwable $e) {
            @unlink($tmp);
            $resultat['message'] = 'Le fichier optimisé est illisible : '.$e->getMessage();

            return $resultat;
        }

        if (empty($json['extras']['eg_uv_metres']) && empty($json['asset']['extras']['eg_uv_metres'])) {
            @unlink($tmp);
            $resultat['message'] = 'Le fichier optimisé ne porte pas le repère « UV en mètres » : original conservé.';

            return $resultat;
        }

        $original = static::cheminOriginal($chemin);
        if (! is_file($original) && ! @copy($chemin, $original)) {
            @unlink($tmp);
            $resultat['message'] = 'Impossible de sauvegarder l\'original : optimisation annulée.';

            return $resultat;
        }

        if (! @rename($tmp, $chemin)) {
            @unlink($tmp);
            $resultat['message'] = 'Impossible de remplacer le fichier par la version optimisée : original conservé.';

            return $resultat;
        }

        clearstatcache(true, $chemin);
        $resultat['apres'] = filesize($chemin) ?: null;
        $resultat['statut'] = self::OPTIMISE;
        $resultat['message'] = 'Fichier 3D optimisé'
            .($resultat['avant'] && $resultat['apres'] ? ' : '.static::taille($resultat['avant']).' → '.static::taille($resultat['apres']) : '').'.';

        return $resultat;
    }

    /** Remet l'original (.original.glb) à la place du fichier optimisé. */
    public static function restaurer(string $chemin): bool
    {
        $original = static::cheminOriginal($chemin);

        return is_file($original) && @copy($original, $chemin);
    }

    /**
     * Traite le fichier 3D d'un monument et prévient l'utilisateur (notification Filament).
     * Ne lève jamais d'exception : l'enregistrement du monument doit toujours aboutir.
     */
    public static function traiterMonument(Monument $monument): void
    {
        try {
            $chemin = Storage::disk('public')->path($monument->fichier_3d);
            $cle = $chemin.'|'.(is_file($chemin) ? filemtime($chemin) : 0);
            if (isset(static::$traites[$cle])) {
                return;
            }
            static::$traites[$cle] = true;

            @set_time_limit((int) config('configurateur.glb.delai_secondes', 300) + 30);

            $r = static::optimiser($chemin);

            Log::log($r['statut'] === self::ECHEC ? 'warning' : 'info', "Optimisation GLB monument #{$monument->id} : {$r['statut']} — {$r['message']}");

            if (app()->runningInConsole()) {
                return;
            }

            $notification = Notification::make();
            $details = $r['avertissements'] ? implode("\n", array_slice($r['avertissements'], 0, 4)) : null;

            match ($r['statut']) {
                self::OPTIMISE => $notification->title($r['message'])->body($details)->success(),
                self::DEJA => $notification->title($r['message'])->info(),
                default => $notification
                    ->title('Fichier 3D enregistré, mais non optimisé')
                    ->body($r['message'].' Le fichier d\'origine est conservé ; l\'aperçu 3D fonctionne mais sans réglage de grain ni de brillance.')
                    ->warning()
                    ->persistent(),
            };

            $notification->send();
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected static function taille(int $octets): string
    {
        return $octets >= 1048576 ? number_format($octets / 1048576, 1, ',', ' ').' Mo' : number_format($octets / 1024, 0, ',', ' ').' Ko';
    }

    /** Ligne la plus parlante d'un échec : la première « XxxError: … » de la pile Node, sinon la dernière ligne. */
    protected static function messageErreur(string $erreur, string $sortie): string
    {
        foreach (preg_split('/\R/', $erreur) as $ligne) {
            if (preg_match('/^\s*(\w*Error|Erreur)\b.*$/u', $ligne)) {
                return 'Fichier invalide ou illisible ('.mb_substr(trim($ligne), 0, 200).').';
            }
        }

        return static::derniereLigne($erreur ?: $sortie);
    }

    protected static function derniereLigne(string $texte): string
    {
        $lignes = array_values(array_filter(array_map('trim', preg_split('/\R/', $texte))));

        return $lignes ? mb_substr(end($lignes), 0, 300) : '';
    }

    /**
     * Lance une commande sans passer par un shell (pas d'injection possible), avec délai maximal.
     *
     * @param  array<int, string>  $commande
     * @return array{0: int, 1: string, 2: string} [code de sortie, sortie standard, sortie d'erreur]
     */
    protected static function executer(array $commande, int $delai): array
    {
        $process = @proc_open($commande, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($process)) {
            return [1, '', 'Impossible de lancer Node.'];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $sortie = $erreur = '';
        $debut = time();
        $code = null;

        while (true) {
            $lecture = [$pipes[1], $pipes[2]];
            $ecriture = $exception = null;
            @stream_select($lecture, $ecriture, $exception, 0, 200000);
            $sortie .= (string) stream_get_contents($pipes[1]);
            $erreur .= (string) stream_get_contents($pipes[2]);

            $etat = proc_get_status($process);
            if (! $etat['running']) {
                $code = $etat['exitcode'];
                break;
            }
            if (time() - $debut > $delai) {
                proc_terminate($process, 9);
                $erreur .= "\nDélai dépassé ({$delai} s) : optimisation interrompue.";
                $code = 124;
                break;
            }
        }

        $sortie .= (string) stream_get_contents($pipes[1]);
        $erreur .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $retour = proc_close($process);

        // proc_get_status ne donne le code qu'une fois ; proc_close renvoie -1 après coup
        return [(int) ($code ?? $retour), $sortie, $erreur];
    }
}
