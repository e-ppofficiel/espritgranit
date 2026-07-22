<?php

namespace App\Filament\Resources\CommandeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Photos de contrôle';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('chemin')
                    ->label('Photo')
                    ->image()
                    ->imageEditor()
                    ->directory('commandes/photos-controle')
                    ->visibility('public')
                    ->required(),
                Forms\Components\Toggle::make('visible_client')
                    ->label('Visible par le client')
                    ->default(true),
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
                    ->label('Photo')
                    ->square()
                    ->size(120),
                Tables\Columns\IconColumn::make('visible_client')
                    ->label('Visible client')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ordre')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('agrandir')
                    ->label('Voir en grand')
                    ->icon('heroicon-o-magnifying-glass-plus')
                    ->url(fn ($record) => Storage::url($record->chemin))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
