<?php

namespace App\Filament\Resources\TransportZoneResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DepartementsRelationManager extends RelationManager
{
    protected static string $relationship = 'departements';

    protected static ?string $title = 'Départements de cette zone';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nom')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('code')
            ->headerActions([
                Tables\Actions\AssociateAction::make()
                    ->label('Ajouter un département')
                    ->recordSelectOptionsQuery(fn ($query) => $query->orderBy('code'))
                    ->recordSelectSearchColumns(['code', 'nom']),
            ])
            ->actions([
                Tables\Actions\DissociateAction::make()
                    ->label('Retirer'),
            ])
            ->bulkActions([
                Tables\Actions\DissociateBulkAction::make(),
            ]);
    }
}
