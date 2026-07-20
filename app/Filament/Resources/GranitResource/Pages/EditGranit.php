<?php

namespace App\Filament\Resources\GranitResource\Pages;

use App\Filament\Resources\GranitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGranit extends EditRecord
{
    protected static string $resource = GranitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
