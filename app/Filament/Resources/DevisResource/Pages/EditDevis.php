<?php

namespace App\Filament\Resources\DevisResource\Pages;

use App\Filament\Resources\DevisResource;
use App\Filament\Resources\DevisResource\Concerns\PreparesVisuelConfigurateur;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevis extends EditRecord
{
    use PreparesVisuelConfigurateur;

    protected static string $resource = DevisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
