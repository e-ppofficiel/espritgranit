<?php

namespace App\Filament\Agence\Resources\GranitCatalogResource\Pages;

use App\Filament\Agence\Resources\GranitCatalogResource;
use Filament\Resources\Pages\ListRecords;

class ListGranitCatalogs extends ListRecords
{
    protected static string $resource = GranitCatalogResource::class;

    /** Lecture seule : ni « Créer », ni « Exporter ». */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
