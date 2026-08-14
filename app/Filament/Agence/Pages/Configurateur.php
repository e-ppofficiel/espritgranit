<?php

namespace App\Filament\Agence\Pages;

use App\Models\Granit;
use App\Models\Monument;
use Filament\Pages\Page;

class Configurateur extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static string $view = 'filament.agence.pages.configurateur';

    protected static bool $shouldRegisterNavigation = false;

    public Monument $monument;

    public $granits;

    public $semelleTypes;

    public $semelleDimensions;

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
    }

    public function getTitle(): string
    {
        return 'Configurateur — ' . $this->monument->nom;
    }
}
