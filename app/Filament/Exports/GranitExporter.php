<?php

namespace App\Filament\Exports;

use App\Models\Granit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GranitExporter extends Exporter
{
    protected static ?string $model = Granit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('nom_commercial')->label('Nom commercial'),
            ExportColumn::make('reference_interne')->label('Référence'),
            ExportColumn::make('pays')->label('Pays'),
            ExportColumn::make('actif')->label('Actif'),
            ExportColumn::make('created_at')->label('Créé le'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $nombre = $export->successful_rows;
        $suffixe = $nombre > 1 ? 's' : '';

        return "Export terminé : {$nombre} granit{$suffixe} exporté{$suffixe}."
            . ($export->failed_rows > 0 ? " {$export->failed_rows} ligne(s) en échec." : '');
    }
}
