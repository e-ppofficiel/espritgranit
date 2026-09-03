<?php

namespace App\Filament\Agence\Resources\DevisResource\Pages;

use App\Filament\Agence\Resources\DevisResource;
use App\Filament\Resources\DevisResource\Concerns\PreparesVisuelConfigurateur;
use Filament\Resources\Pages\EditRecord;

class EditDevis extends EditRecord
{
    use PreparesVisuelConfigurateur;

    protected static string $resource = DevisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pas de suppression côté agence — un devis créé reste tracé.
        ];
    }
}
