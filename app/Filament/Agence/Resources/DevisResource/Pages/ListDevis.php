<?php

namespace App\Filament\Agence\Resources\DevisResource\Pages;

use App\Filament\Agence\Resources\DevisResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDevis extends ListRecords
{
    protected static string $resource = DevisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nouveau devis'),
        ];
    }
}
