<?php

namespace App\Filament\Resources\DemandeAdhesionResource\Pages;

use App\Filament\Resources\DemandeAdhesionResource;
use Filament\Resources\Pages\ListRecords;

class ListDemandeAdhesions extends ListRecords
{
    protected static string $resource = DemandeAdhesionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pas de bouton "Créer" — les demandes arrivent uniquement via le formulaire public.
        ];
    }
}
