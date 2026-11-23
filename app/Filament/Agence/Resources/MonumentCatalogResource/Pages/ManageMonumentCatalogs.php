<?php

namespace App\Filament\Agence\Resources\MonumentCatalogResource\Pages;

use App\Filament\Agence\Resources\MonumentCatalogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageMonumentCatalogs extends ManageRecords
{
    protected static string $resource = MonumentCatalogResource::class;

    /** Pas de bouton « Créer » : le catalogue est en lecture seule pour les agences. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
