<?php

namespace App\Filament\Resources\ContainerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CommandesRelationManager extends RelationManager
{
    protected static string $relationship = 'commandes';

    protected static ?string $title = 'Commandes rattachées';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('societe.enseigne')
                    ->label('Société'),
                Tables\Columns\TextColumn::make('monument.nom')
                    ->label('Monument'),
                Tables\Columns\TextColumn::make('statut')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('voir')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => \App\Filament\Resources\CommandeResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\DissociateAction::make()
                    ->label('Retirer du container'),
            ]);
    }
}
