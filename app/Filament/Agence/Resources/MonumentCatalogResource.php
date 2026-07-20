<?php

namespace App\Filament\Agence\Resources;

use App\Filament\Agence\Resources\MonumentCatalogResource\Pages;
use App\Models\Monument;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonumentCatalogResource extends Resource
{
    protected static ?string $model = Monument::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'monument';
    protected static ?string $pluralModelLabel = 'catalogue';
    protected static ?string $navigationLabel = 'Catalogue';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('actif', true)
            ->orderBy('ordre_affichage');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_principale')
                    ->square()
                    ->size(80),
                Tables\Columns\TextColumn::make('nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('reference_interne')
                    ->label('Référence')
                    ->searchable(),
                Tables\Columns\TextColumn::make('categorie')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')
                    ->options([
                        'monuments' => 'Monuments',
                        'semelles' => 'Semelles',
                        'plinthes' => 'Plinthes',
                        'accessoires' => 'Accessoires',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Configurer')
                    ->icon('heroicon-o-cube-transparent'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMonumentCatalogs::route('/'),
        ];
    }
}
