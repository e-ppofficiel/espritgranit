<?php

namespace App\Filament\Exports;

use App\Models\Agence;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AdherentExporter extends Exporter
{
    protected static ?string $model = Agence::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('societe.enseigne')->label('Enseigne (société)'),
            ExportColumn::make('societe.raison_sociale')->label('Raison sociale'),
            ExportColumn::make('societe.siret')->label('SIRET'),
            ExportColumn::make('societe.statut_compte')->label('Statut compte société'),
            ExportColumn::make('societe.coefficient_vente')->label('Coefficient société'),
            ExportColumn::make('nom')->label('Nom agence'),
            ExportColumn::make('adresse')->label('Adresse'),
            ExportColumn::make('code_postal')->label('Code postal'),
            ExportColumn::make('commune')->label('Commune'),
            ExportColumn::make('contact')->label('Contact'),
            ExportColumn::make('email')->label('E-mail'),
            ExportColumn::make('telephone')->label('Téléphone'),
            ExportColumn::make('coefficient_vente')->label('Coefficient agence'),
            ExportColumn::make('created_at')->label('Créée le'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $nombre = $export->successful_rows;
        $suffixe = $nombre > 1 ? 's' : '';

        return "Export terminé : {$nombre} adhérent{$suffixe} exporté{$suffixe}."
            . ($export->failed_rows > 0 ? " {$export->failed_rows} ligne(s) en échec." : '');
    }
}
