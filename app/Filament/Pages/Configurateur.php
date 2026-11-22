<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\GereConfigurationPartagee;
use App\Filament\Concerns\GerePrixConfigurateur;
use App\Models\Granit;
use App\Models\Monument;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;

class Configurateur extends Page
{
    use GereConfigurationPartagee;
    use GerePrixConfigurateur;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string $view = 'filament.pages.configurateur';

    protected static bool $shouldRegisterNavigation = false;

    public Monument $monument;

    public $granits;

    public $semelleTypes;

    public $semelleDimensions;

    /** État relu depuis le code ?c=… de l'adresse (revalidé côté serveur) ; verrouillé : non modifiable depuis le navigateur. */
    #[Locked]
    public ?array $etatInitial = null;

    public static function getSlug(): string
    {
        return 'configurateur/{monument}';
    }

    public function mount(Monument $monument): void
    {
        $this->monument = $monument;
        $this->granits = Granit::where('actif', true)->orderBy('ordre_affichage')->get();
        $this->semelleTypes = $monument->semelleTypes()
            ->where('actif', true)
            ->orderBy('ordre_affichage')
            ->get();
        $this->semelleDimensions = $monument->semelleDimensions;
        $this->chargerConfigurationPartagee();
    }

    public function getTitle(): string
    {
        return 'Configurateur — ' . $this->monument->nom;
    }

    /**
     * Seuls les rôles d'administration voient les curseurs de réglage du granit (et peuvent les enregistrer).
     * Même liste de rôles que User::canAccessPanel() pour le panel admin.
     */
    public function peutReglerGranits(): bool
    {
        $utilisateur = Filament::auth()->user();

        return $utilisateur
            && method_exists($utilisateur, 'hasAnyRole')
            && $utilisateur->hasAnyRole(['super_admin', 'admin_esprit_granit', 'gestionnaire']);
    }

    /**
     * Enregistre les réglages de rendu d'un granit (appelé par les curseurs du configurateur).
     * Une valeur null = « valeur standard » (colonne vidée). Renvoie ['ok' => bool, 'message' => ...].
     */
    #[Renderless]
    public function enregistrerReglagesGranit(int $granitId, $echantillon = null, $brillance = null, $luminosite = null): array
    {
        abort_unless($this->peutReglerGranits(), 403);

        $regles = [
            'echantillon_cm' => [$echantillon, 5, 200, 'Taille de l\'échantillon'],
            'brillance' => [$brillance, 0, 100, 'Brillance'],
            'luminosite' => [$luminosite, 50, 150, 'Luminosité'],
        ];

        $valeurs = [];
        foreach ($regles as $colonne => [$valeur, $min, $max, $libelle]) {
            if ($valeur === null || $valeur === '') {
                $valeurs[$colonne] = null;
                continue;
            }
            if (! is_numeric($valeur) || $valeur < $min || $valeur > $max) {
                return ['ok' => false, 'message' => "{$libelle} : une valeur entre {$min} et {$max} est attendue."];
            }
            $valeurs[$colonne] = (int) round((float) $valeur);
        }

        $granit = Granit::find($granitId);
        if (! $granit) {
            return ['ok' => false, 'message' => 'Granit introuvable.'];
        }

        try {
            $granit->update($valeurs);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'Enregistrement impossible (la migration des colonnes de rendu est-elle passée ?).'];
        }

        return ['ok' => true, 'granit' => $granit->nom_commercial];
    }
}
