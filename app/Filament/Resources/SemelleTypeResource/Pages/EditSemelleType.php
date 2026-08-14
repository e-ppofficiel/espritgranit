<?php

namespace App\Filament\Resources\SemelleTypeResource\Pages;

use App\Filament\Resources\SemelleTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSemelleType extends EditRecord
{
    protected static string $resource = SemelleTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
