<?php

namespace App\Filament\Exports;

use App\Models\Monument;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MonumentExporter extends Exporter
{
    protected static ?string $model = Monument::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_interne')->label('Référence'),
            ExportColumn::make('nom')->label('Nom'),
            ExportColumn::make('categorie')->label('Catégorie'),
            ExportColumn::make('poids_total_kg')->label('Poids total (kg)'),
            ExportColumn::make('actif')->label('Actif'),
            ExportColumn::make('created_at')->label('Créé le'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $nombre = $export->successful_rows;
        $suffixe = $nombre > 1 ? 's' : '';

        return "Export terminé : {$nombre} monument{$suffixe} exporté{$suffixe}."
            . ($export->failed_rows > 0 ? " {$export->failed_rows} ligne(s) en échec." : '');
    }
}
