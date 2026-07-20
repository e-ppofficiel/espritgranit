<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SocieteResource\Pages;
use App\Filament\Resources\SocieteResource\RelationManagers;
use App\Models\Societe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SocieteResource extends Resource
{
    protected static ?string $model = Societe::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $modelLabel = 'société';
    protected static ?string $pluralModelLabel = 'sociétés';
    protected static ?string $navigationLabel = 'Sociétés';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('enseigne')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('raison_sociale')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('siret')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('adresse_siege')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('telephone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('contact_principal')
                    ->maxLength(255),
                Forms\Components\Select::make('statut_compte')
                    ->options([
                        'en_attente' => 'En attente',
                        'actif' => 'Actif',
                        'suspendu' => 'Suspendu',
                        'refuse' => 'Refusé',
                    ])
                    ->default('en_attente')
                    ->native(false),
                Forms\Components\TextInput::make('coefficient_vente')
                    ->numeric()
                    ->step(0.01),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('enseigne')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('raison_sociale')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siret')
                    ->searchable(),
                Tables\Columns\TextColumn::make('statut_compte')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'actif' => 'success',
                        'en_attente' => 'warning',
                        'suspendu' => 'danger',
                        'refuse' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('coefficient_vente')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut_compte')
                    ->options([
                        'en_attente' => 'En attente',
                        'actif' => 'Actif',
                        'suspendu' => 'Suspendu',
                        'refuse' => 'Refusé',
                    ]),
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
            RelationManagers\AgencesRelationManager::class,
        ];
    }

    public static function getPages(): array
        {
            return [
                'index' => Pages\ListSocietes::route('/'),
                'create' => Pages\CreateSociete::route('/create'),
                'edit' => Pages\EditSociete::route('/{record}/edit'),
            ];
        }
}
