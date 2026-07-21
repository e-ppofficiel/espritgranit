<?php

namespace App\Filament\Resources\TransportZoneResource\Pages;

use App\Filament\Resources\TransportZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportZones extends ListRecords
{
    protected static string $resource = TransportZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
