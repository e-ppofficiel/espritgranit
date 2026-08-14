<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonumentResource\Pages;
use App\Filament\Resources\MonumentResource\RelationManagers;
use App\Models\Monument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MonumentResource extends Resource
{
    protected static ?string $model = Monument::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $modelLabel = 'monument';
    protected static ?string $pluralModelLabel = 'monuments';
    protected static ?string $navigationLabel = 'Monuments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('reference_interne')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('nom')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('categorie')
                    ->options([
                        'monuments' => 'Monuments',
                        'semelles' => 'Semelles',
                        'plinthes' => 'Plinthes',
                        'accessoires' => 'Accessoires',
                    ])
                    ->native(false),
                Forms\Components\FileUpload::make('fichier_3d')
                    ->label('Fichier 3D (.glb)')
                    ->acceptedFileTypes(['model/gltf-binary', 'application/octet-stream', '.glb'])
                    ->directory('monuments/3d')
                    ->visibility('public')
                    ->helperText('Fichier au format .glb uniquement'),
                Forms\Components\FileUpload::make('image_principale')
                    ->image()
                    ->imageEditor()
                    ->directory('monuments/images')
                    ->visibility('public'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
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
            ->columns([
                Tables\Columns\ImageColumn::make('image_principale')
                    ->square(),
                Tables\Columns\TextColumn::make('reference_interne')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('categorie')
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
                Tables\Actions\Action::make('configurer')
                    ->label('Configurer')
                    ->icon('heroicon-o-cube-transparent')
                    ->url(fn ($record) => \App\Filament\Pages\Configurateur::getUrl(['monument' => $record->id])),
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
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\BlocsRelationManager::class,
            RelationManagers\GranitsRelationManager::class,
            RelationManagers\SemelleTypesRelationManager::class,
            RelationManagers\SemelleDimensionsRelationManager::class,
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            // Item par défaut, reproduit à l'identique du comportement standard Filament
            // (voir Filament\Resources\Resource::getNavigationItems()), pour ne rien changer
            // au comportement existant du menu "Monuments".
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.*') && ! request()->boolean('stele_art'))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),

            // Deuxième item : même page, filtrée sur les références Stèle Art (préfixe ST-)
            NavigationItem::make('Monuments Stèle Art')
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.index') && request()->boolean('stele_art'))
                ->sort(static::getNavigationSort())
                ->url(fn (): string => static::getUrl('index', ['stele_art' => 1])),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonuments::route('/'),
            'create' => Pages\CreateMonument::route('/create'),
            'edit' => Pages\EditMonument::route('/{record}/edit'),
        ];
    }
}
