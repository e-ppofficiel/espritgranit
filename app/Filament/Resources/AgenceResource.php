<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgenceResource\Pages;
use App\Filament\Resources\AgenceResource\RelationManagers;
use App\Models\Agence;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AgenceResource extends Resource
{
    protected static ?string $model = Agence::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('societe_id')
                    ->label('Société')
                    ->relationship('societe', 'enseigne')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('nom')
                    ->label('Nom de l\'agence')
                    ->required()
                    ->maxLength(255),

                Forms\Components\FileUpload::make('logo')
                    ->label('Logo de l\'agence')
                    ->helperText('Affiché dans l\'en-tête du PDF des devis. PNG, JPG ou WebP, 2 Mo maximum (fond transparent accepté).')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('agences/logos')
                    ->visibility('public'),

                Forms\Components\Section::make('Adresse de l\'agence')
                    ->schema([
                        Forms\Components\TextInput::make('adresse')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('code_postal')
                            ->label('Code postal')
                            ->maxLength(10)
                            ->default(null),
                        Forms\Components\TextInput::make('commune')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('contact')
                            ->label('Contact agence')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('telephone')
                            ->label('Téléphone agence')
                            ->tel()
                            ->maxLength(255)
                            ->default(null),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Adresse de livraison')
                    ->schema([
                        Forms\Components\TextInput::make('adresse_livraison')
                            ->label('Adresse')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('code_postal_livraison')
                            ->label('Code postal')
                            ->maxLength(10)
                            ->default(null),
                        Forms\Components\TextInput::make('commune_livraison')
                            ->label('Commune')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('contact_livraison')
                            ->label('Contact livraison')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('telephone_livraison')
                            ->label('Téléphone livraison')
                            ->tel()
                            ->maxLength(255)
                            ->default(null),
                    ])
                    ->columns(2),

                Forms\Components\TextInput::make('coefficient_vente')
                    ->label('Coefficient de vente spécifique')
                    ->numeric()
                    ->step(0.01)
                    ->helperText('Laisser vide pour utiliser le coefficient de la société, ou à défaut le coefficient général.')
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->height(32)
                    ->extraImgAttributes(['style' => 'object-fit: contain; max-width: 80px;'])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('societe.enseigne')
                    ->label('Société')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nom')
                    ->searchable(),
                Tables\Columns\TextColumn::make('adresse')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('code_postal')
                    ->label('CP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('commune')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('adresse_livraison')
                    ->label('Adresse livraison')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contact')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('telephone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('contact_livraison')
                    ->label('Contact livraison')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('telephone_livraison')
                    ->label('Tél. livraison')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('coefficient_vente')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('societe_id')
                    ->label('Société')
                    ->relationship('societe', 'enseigne')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgences::route('/'),
            'create' => Pages\CreateAgence::route('/create'),
            'edit' => Pages\EditAgence::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nom', 'societe.enseigne'];
    }
}
