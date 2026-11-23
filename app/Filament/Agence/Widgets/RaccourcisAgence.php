<?php

namespace App\Filament\Agence\Widgets;

use App\Filament\Agence\Resources\DevisResource;
use App\Filament\Agence\Resources\MonumentCatalogResource;
use Filament\Widgets\Widget;

/**
 * Tableau de bord agence : accès directs aux trois actions les plus fréquentes.
 */
class RaccourcisAgence extends Widget
{
    protected static string $view = 'filament.agence.widgets.raccourcis';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'urlNouveauDevis' => DevisResource::getUrl('create'),
            'urlCatalogue' => MonumentCatalogResource::getUrl('index'),
            'urlCatalogueSteleArt' => MonumentCatalogResource::getUrl('index', ['stele_art' => 1]),
        ];
    }
}
