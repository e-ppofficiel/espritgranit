<?php

namespace App\Filament\Resources\TransportZoneResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TarifsRelationManager extends RelationManager
{
    protected static string $relationship = 'tarifs';

    protected static ?string $title = 'Grille tarifaire (par poids)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('poids_min')
                    ->label('Poids minimum (kg)')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('poids_max')
                    ->label('Poids maximum (kg)')
                    ->numeric()
                    ->helperText('Laisser vide pour "au-delà de"'),
                Forms\Components\TextInput::make('prix')
                    ->numeric()
                    ->required()
                    ->prefix('€'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('prix')
            ->columns([
                Tables\Columns\TextColumn::make('poids_min')
                    ->label('De (kg)'),
                Tables\Columns\TextColumn::make('poids_max')
                    ->label('À (kg)')
                    ->placeholder('∞'),
                Tables\Columns\TextColumn::make('prix')
                    ->money('EUR'),
            ])
            ->defaultSort('poids_min')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
