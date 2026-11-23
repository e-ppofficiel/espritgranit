<?php

namespace App\Filament\Agence\Widgets;

use App\Filament\Agence\Resources\CommandeResource;
use App\Models\Commande;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Tableau de bord agence : commandes en cours, avec leur avancement et la date d'arrivée prévue.
 * Périmètre : celui de « Mes commandes » (CommandeResource::getEloquentQuery()).
 */
class CommandesEnCoursAgence extends BaseWidget
{
    protected static ?string $heading = 'Mes commandes en cours';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static array $libellesStatut = [
        'en_preparation' => 'En préparation',
        'confirmee' => 'Confirmée',
        'en_production' => 'En production',
        'en_transit' => 'En transit',
        'au_port_arrivee' => "Au port d'arrivée",
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CommandeResource::getEloquentQuery()
                    ->whereNotIn('statut', ['livree', 'annulee', 'archivee'])
                    ->with(['monument', 'granit', 'container'])
                    ->latest()
            )
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Aucune commande en cours')
            ->recordUrl(fn (Commande $record): string => CommandeResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Commande')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('monument.nom')
                    ->label('Monument'),
                Tables\Columns\TextColumn::make('granit.nom_commercial')
                    ->label('Granit'),
                Tables\Columns\TextColumn::make('statut')
                    ->label('Avancement')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'en_production', 'en_transit' => 'warning',
                        'confirmee', 'au_port_arrivee' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => static::$libellesStatut[$state] ?? $state),
                Tables\Columns\TextColumn::make('container.reference')
                    ->label('Container')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('arrivee_prevue')
                    ->label('Arrivée prévue')
                    // Date propre à la commande, à défaut celle du container.
                    ->state(fn (Commande $record) => $record->date_arrivee_prevue ?? $record->container?->date_arrivee_prevue)
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ]);
    }
}
