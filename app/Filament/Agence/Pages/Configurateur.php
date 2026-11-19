<?php

namespace App\Filament\Agence\Pages;

use App\Filament\Concerns\GereConfigurationPartagee;
use App\Models\Granit;
use App\Models\Monument;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class Configurateur extends Page
{
    use GereConfigurationPartagee;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string $view = 'filament.agence.pages.configurateur';

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
}
