<?php

namespace App\Filament\Resources\DevisResource\Pages;

use App\Filament\Exports\DevisExporter;
use App\Filament\Resources\DevisResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListDevis extends ListRecords
{
    protected static string $resource = DevisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make()
                ->exporter(DevisExporter::class),
        ];
    }
}
