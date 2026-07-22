<?php

namespace App\Filament\Resources\MonumentResource\Pages;

use App\Filament\Resources\MonumentResource;
use App\Filament\Resources\MonumentResource\Widgets\MonumentSliderWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonuments extends ListRecords
{
    protected static string $resource = MonumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MonumentSliderWidget::class,
        ];
    }
}
