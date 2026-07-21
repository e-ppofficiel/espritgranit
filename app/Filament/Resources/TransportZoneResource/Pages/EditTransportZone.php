<?php

namespace App\Filament\Resources\TransportZoneResource\Pages;

use App\Filament\Resources\TransportZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportZone extends EditRecord
{
    protected static string $resource = TransportZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
