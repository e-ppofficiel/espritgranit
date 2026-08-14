<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SemelleTypeResource\Pages;
use App\Models\SemelleType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SemelleTypeResource extends Resource
{
    protected static ?string $model = SemelleType::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'type de semelle';
    protected static ?string $pluralModelLabel = 'types de semelle';
    protected static ?string $navigationLabel = 'Types de semelle';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Code (préfixe matériau .glb)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Doit correspondre exactement au préfixe des matériaux dans le fichier .glb, ex: SEMELLE-B'),
                Forms\Components\TextInput::make('label')
                    ->label('Libellé affiché')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('ordre_affichage')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('actif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordre_affichage')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('actif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ordre_affichage')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListSemelleTypes::route('/'),
            'create' => Pages\CreateSemelleType::route('/create'),
            'edit' => Pages\EditSemelleType::route('/{record}/edit'),
        ];
    }
}
