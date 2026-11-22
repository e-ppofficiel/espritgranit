<?php

namespace App\Filament\Resources\FondResource\Pages;

use App\Filament\Resources\FondResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFond extends EditRecord
{
    protected static string $resource = FondResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
