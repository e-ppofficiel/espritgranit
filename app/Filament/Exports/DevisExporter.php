<?php

namespace App\Filament\Exports;

use App\Models\Devis;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class DevisExporter extends Exporter
{
    protected static ?string $model = Devis::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('numero')->label('Numéro'),
            ExportColumn::make('societe.enseigne')->label('Société'),
            ExportColumn::make('agence.nom')->label('Agence'),
            ExportColumn::make('monument.nom')->label('Monument'),
            ExportColumn::make('granit.nom_commercial')->label('Granit'),
            ExportColumn::make('statut')->label('Statut'),
            ExportColumn::make('prix_granit_base')->label('Prix granit'),
            ExportColumn::make('prix_transport')->label('Prix transport'),
            ExportColumn::make('coefficient_applique')->label('Coefficient'),
            ExportColumn::make('prix_final')->label('Prix final'),
            ExportColumn::make('created_at')->label('Créé le'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $nombre = $export->successful_rows;
        $suffixe = $nombre > 1 ? 's' : '';

        return "Export terminé : {$nombre} devis exporté{$suffixe}."
            . ($export->failed_rows > 0 ? " {$export->failed_rows} ligne(s) en échec." : '');
    }
}
