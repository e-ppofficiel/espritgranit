<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportZoneResource\Pages;
use App\Filament\Resources\TransportZoneResource\RelationManagers;
use App\Models\TransportZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransportZoneResource extends Resource
{
    protected static ?string $model = TransportZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $modelLabel = 'zone de transport';
    protected static ?string $pluralModelLabel = 'zones de transport';
    protected static ?string $navigationLabel = 'Transport';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nom')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('departements_count')
                    ->label('Départements')
                    ->counts('departements'),
                Tables\Columns\TextColumn::make('tarifs_count')
                    ->label('Paliers de prix')
                    ->counts('tarifs'),
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
            RelationManagers\DepartementsRelationManager::class,
            RelationManagers\TarifsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransportZones::route('/'),
            'create' => Pages\CreateTransportZone::route('/create'),
            'edit' => Pages\EditTransportZone::route('/{record}/edit'),
        ];
    }
}
