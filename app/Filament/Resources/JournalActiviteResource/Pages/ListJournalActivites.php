<?php

namespace App\Filament\Resources\JournalActiviteResource\Pages;

use App\Filament\Resources\JournalActiviteResource;
use Filament\Resources\Pages\ListRecords;

class ListJournalActivites extends ListRecords
{
    protected static string $resource = JournalActiviteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Journal en lecture seule — pas de bouton "Créer".
        ];
    }
}
