<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GranitResource\Pages;
use App\Models\Granit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GranitResource extends Resource
{
    protected static ?string $model = Granit::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $modelLabel = 'granit';
    protected static ?string $pluralModelLabel = 'granits';
    protected static ?string $navigationLabel = 'Granits';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nom_commercial')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reference_interne')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\FileUpload::make('image')
                    ->label('Image source')
                    ->image()
                    ->imageEditor()
                    ->directory('granits')
                    ->visibility('public')
                    ->helperText('Cette image sert de texture haute qualité dans le configurateur 3D. Une vignette compressée est générée automatiquement pour l\'affichage des listes, sans toucher à cette image source.'),
                Forms\Components\TextInput::make('pays')
                    ->maxLength(255),
                Forms\Components\Toggle::make('actif')
                    ->default(true),
                Forms\Components\TextInput::make('ordre_affichage')
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('restrictions')
                    ->columnSpanFull(),
                Forms\Components\View::make('filament.forms.components.granit-apercu-loupe')
                    ->viewData(fn (?\Illuminate\Database\Eloquent\Model $record) => ['granitRecord' => $record])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_vignette')
                    ->label('Image')
                    ->getStateUsing(fn (Granit $record) => $record->image_vignette ?? $record->image)
                    ->square(),
                Tables\Columns\TextColumn::make('nom_commercial')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_interne')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pays')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('actif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ordre_affichage')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListGranits::route('/'),
            'create' => Pages\CreateGranit::route('/create'),
            'edit' => Pages\EditGranit::route('/{record}/edit'),
        ];
    }
}
