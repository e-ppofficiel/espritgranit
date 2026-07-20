<?php

namespace App\Filament\Agence\Resources\MonumentCatalogResource\Pages;

use App\Filament\Agence\Resources\MonumentCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageMonumentCatalogs extends ManageRecords
{
    protected static string $resource = MonumentCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
