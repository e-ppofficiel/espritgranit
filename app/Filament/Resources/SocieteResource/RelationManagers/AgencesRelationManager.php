<?php

namespace App\Filament\Resources\SocieteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AgencesRelationManager extends RelationManager
{
    protected static string $relationship = 'agences';

    protected static ?string $title = 'Agences';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nom')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('adresse')
                    ->maxLength(255),
                Forms\Components\TextInput::make('adresse_livraison')
                    ->maxLength(255),
                Forms\Components\TextInput::make('contact')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('telephone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('coefficient_vente')
                    ->numeric()
                    ->step(0.01)
                    ->helperText('Laisser vide pour utiliser le coefficient de la société'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom')
            ->columns([
                Tables\Columns\TextColumn::make('nom')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telephone'),
                Tables\Columns\TextColumn::make('coefficient_vente')
                    ->numeric(),
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
