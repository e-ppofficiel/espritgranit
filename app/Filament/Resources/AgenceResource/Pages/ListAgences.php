<?php

namespace App\Filament\Resources\AgenceResource\Pages;

use App\Filament\Exports\AdherentExporter;
use App\Filament\Resources\AgenceResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListAgences extends ListRecords
{
    protected static string $resource = AgenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make()
                ->label('Exporter (adhérents)')
                ->exporter(AdherentExporter::class),
        ];
    }
}
