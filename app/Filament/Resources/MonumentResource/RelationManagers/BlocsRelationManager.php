<?php

namespace App\Filament\Resources\MonumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BlocsRelationManager extends RelationManager
{
    protected static string $relationship = 'blocs';

    protected static ?string $title = 'Blocs, dimensions et tarifs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nom_bloc')
                    ->label('Nom du bloc (ex: Semelle, Tombale, Stèle)')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference_produit')
                    ->label('Référence produit')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('longueur_standard')
                    ->label('Longueur standard (cm)')
                    ->numeric(),
                Forms\Components\TextInput::make('largeur_standard')
                    ->label('Largeur standard (cm)')
                    ->numeric(),
                Forms\Components\TextInput::make('epaisseur_standard')
                    ->label('Épaisseur standard (cm)')
                    ->numeric(),
                Forms\Components\TextInput::make('poids_kg')
                    ->label('Poids (kg)')
                    ->numeric()
                    ->helperText('Facultatif : utilisé uniquement si aucun poids total n\'est renseigné sur la fiche du monument.'),
                Forms\Components\Textarea::make('commentaire')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('ordre')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom_bloc')
            ->columns([
                Tables\Columns\TextColumn::make('nom_bloc')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reference_produit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('longueur_standard')
                    ->suffix(' cm'),
                Tables\Columns\TextColumn::make('largeur_standard')
                    ->suffix(' cm'),
                Tables\Columns\TextColumn::make('epaisseur_standard')
                    ->label('Hauteur')
                    ->suffix(' cm')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('poids_kg')
                    ->suffix(' kg'),
                Tables\Columns\TextColumn::make('ordre')
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
