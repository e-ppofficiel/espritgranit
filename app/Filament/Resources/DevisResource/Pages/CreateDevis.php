<?php

namespace App\Filament\Resources\DevisResource\Pages;

use App\Filament\Resources\DevisResource;
use App\Filament\Resources\DevisResource\Concerns\PreparesVisuelConfigurateur;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDevis extends CreateRecord
{
    use PreparesVisuelConfigurateur;

    protected static string $resource = DevisResource::class;
}
