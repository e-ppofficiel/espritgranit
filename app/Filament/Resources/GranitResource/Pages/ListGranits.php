<?php

namespace App\Filament\Resources\GranitResource\Pages;

use App\Filament\Exports\GranitExporter;
use App\Filament\Resources\GranitResource;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListGranits extends ListRecords
{
    protected static string $resource = GranitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make()
                ->exporter(GranitExporter::class),
        ];
    }
}
