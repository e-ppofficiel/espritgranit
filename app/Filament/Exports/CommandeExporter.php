<?php

namespace App\Filament\Exports;

use App\Models\Commande;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CommandeExporter extends Exporter
{
    protected static ?string $model = Commande::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('numero')->label('Numéro'),
            ExportColumn::make('reference_client')->label('Référence client'),
            ExportColumn::make('reference_interne')->label('Référence interne'),
            ExportColumn::make('reference_usine')->label('Référence usine'),
            ExportColumn::make('societe.enseigne')->label('Société'),
            ExportColumn::make('agence.nom')->label('Agence'),
            ExportColumn::make('monument.nom')->label('Monument'),
            ExportColumn::make('granit.nom_commercial')->label('Granit'),
            ExportColumn::make('statut')->label('Statut'),
            ExportColumn::make('container.reference')->label('Container'),
            ExportColumn::make('prix_transport')->label('Prix transport'),
            ExportColumn::make('prix_final')->label('Prix final'),
            ExportColumn::make('date_depart_prevue')->label('Départ prévu'),
            ExportColumn::make('date_arrivee_prevue')->label('Arrivée prévue'),
            ExportColumn::make('date_arrivee_reelle')->label('Arrivée réelle'),
            ExportColumn::make('created_at')->label('Créée le'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $nombre = $export->successful_rows;
        $suffixe = $nombre > 1 ? 's' : '';

        return "Export terminé : {$nombre} commande{$suffixe} exportée{$suffixe}."
            . ($export->failed_rows > 0 ? " {$export->failed_rows} ligne(s) en échec." : '');
    }
}
