<?php

namespace App\Filament\Resources\MonumentResource\Pages;

use App\Filament\Exports\MonumentExporter;
use App\Filament\Resources\MonumentResource;
use App\Filament\Resources\MonumentResource\Widgets\MonumentSliderWidget;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMonuments extends ListRecords
{
    protected static string $resource = MonumentResource::class;

    public function getTitle(): string
    {
        return $this->filtreSteleArt() ? 'Monuments Stèle Art' : 'Monuments';
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->filtreSteleArt()) {
            $query->where('reference_interne', 'like', 'ST-%');
        } else {
            // Les monuments Stèle Art (référence ST-…) ne sont visibles que dans « Monuments Stèle Art ».
            // Les références vides (NULL) restent affichées.
            $query->where(function (Builder $q) {
                $q->whereNull('reference_interne')
                    ->orWhere('reference_interne', 'not like', 'ST-%');
            });
        }

        return $query;
    }

    protected function filtreSteleArt(): bool
    {
        return request()->boolean('stele_art');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make()
                ->exporter(MonumentExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MonumentSliderWidget::make(['steleArt' => $this->filtreSteleArt()]),
        ];
    }
}
