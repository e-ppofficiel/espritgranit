<?php

namespace App\Filament\Resources\SemelleTypeResource\Pages;

use App\Filament\Resources\SemelleTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSemelleTypes extends ListRecords
{
    protected static string $resource = SemelleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
