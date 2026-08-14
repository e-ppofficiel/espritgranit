<?php

namespace App\Filament\Resources\MonumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SemelleDimensionsRelationManager extends RelationManager
{
    protected static string $relationship = 'semelleDimensions';

    protected static ?string $title = 'Dimensions de semelle prédéfinies';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('semelle_type_id')
                    ->label('Type de semelle concerné')
                    ->relationship('semelleType', 'label')
                    ->native(false)
                    ->helperText('Laisser vide si cette dimension est disponible pour tous les types'),
                Forms\Components\TextInput::make('label')
                    ->label('Libellé (ex: 150 x 250)')
                    ->maxLength(255),
                Forms\Components\TextInput::make('longueur')
                    ->label('Longueur (cm)')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('largeur')
                    ->label('Largeur (cm)')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('hauteur')
                    ->label('Hauteur (cm)')
                    ->numeric(),
                Forms\Components\Toggle::make('is_default')
                    ->label('Dimension par défaut'),
                Forms\Components\TextInput::make('ordre_affichage')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('semelleType.label')
                    ->label('Type')
                    ->placeholder('Tous types'),
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('longueur')
                    ->suffix(' cm'),
                Tables\Columns\TextColumn::make('largeur')
                    ->suffix(' cm'),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Défaut')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ordre_affichage')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
