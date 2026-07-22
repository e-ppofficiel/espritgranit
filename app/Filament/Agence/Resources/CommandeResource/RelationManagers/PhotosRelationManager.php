<?php

namespace App\Filament\Agence\Resources\CommandeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Photos de contrôle';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('chemin')
            ->modifyQueryUsing(fn ($query) => $query->where('visible_client', true))
            ->columns([
                Tables\Columns\ImageColumn::make('chemin')
                    ->label('Photo')
                    ->square()
                    ->size(120),
                Tables\Columns\TextColumn::make('ordre')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('agrandir')
                    ->label('Voir en grand')
                    ->icon('heroicon-o-magnifying-glass-plus')
                    ->url(fn ($record) => Storage::url($record->chemin))
                    ->openUrlInNewTab(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}