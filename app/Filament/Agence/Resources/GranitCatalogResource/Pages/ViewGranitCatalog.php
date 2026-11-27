<?php

namespace App\Filament\Agence\Resources\GranitCatalogResource\Pages;

use App\Filament\Agence\Resources\GranitCatalogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewGranitCatalog extends ViewRecord
{
    protected static string $resource = GranitCatalogResource::class;

    /** Lecture seule : ni « Modifier », ni « Supprimer ». */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
