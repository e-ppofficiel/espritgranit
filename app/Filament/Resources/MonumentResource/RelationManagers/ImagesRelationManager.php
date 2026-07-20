<?php

namespace App\Filament\Resources\MonumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Images complémentaires';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('chemin')
                    ->label('Image')
                    ->image()
                    ->imageEditor()
                    ->directory('monuments/images-complementaires')
                    ->visibility('public')
                    ->required(),
                Forms\Components\TextInput::make('ordre')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('chemin')
            ->columns([
                Tables\Columns\ImageColumn::make('chemin')
                    ->label('Image')
                    ->square(),
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
