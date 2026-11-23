<?php

namespace App\Filament\Agence\Resources;

use App\Filament\Agence\Pages\Configurateur;
use App\Filament\Agence\Resources\MonumentCatalogResource\Pages;
use App\Models\Monument;
use Filament\Navigation\NavigationItem;
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

    /**
     * Deux entrées de menu pour la même page (même principe que « Monuments » / « Monuments Stèle Art » côté admin) :
     * « Catalogue » (hors références ST-) et « Catalogue Stèle Art » (références ST- uniquement, ?stele_art=1).
     */
    public static function getNavigationItems(): array
    {
        return [
            // Entrée standard, reproduite à l'identique du comportement par défaut de Filament.
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

            // Même page, filtrée sur les références Stèle Art (préfixe ST-).
            NavigationItem::make('Catalogue Stèle Art')
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName() . '.index') && request()->boolean('stele_art'))
                ->sort(static::getNavigationSort())
                ->url(fn (): string => static::getUrl('index', ['stele_art' => 1])),
        ];
    }

    /** Catalogue en lecture seule : l'agence consulte et configure, elle ne crée pas de monument. */
    public static function canCreate(): bool
    {
        return false;
    }

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
                Tables\Columns\TextColumn::make('tags.nom')
                    ->label('Mots-clés')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categorie')
                    ->options([
                        'monuments' => 'Monuments',
                        'semelles' => 'Semelles',
                        'plinthes' => 'Plinthes',
                        'accessoires' => 'Accessoires',
                    ]),
                Tables\Filters\SelectFilter::make('tags')
                    ->label('Mots-clés')
                    ->relationship('tags', 'nom')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ])
            // Un clic sur la ligne ou sur le picto ouvre le configurateur 3D du monument.
            ->recordUrl(fn (Monument $record): string => Configurateur::getUrl(['monument' => $record->id]))
            ->actions([
                Tables\Actions\Action::make('configurer')
                    ->label('Configurer')
                    ->icon('heroicon-o-cube-transparent')
                    ->url(fn (Monument $record): string => Configurateur::getUrl(['monument' => $record->id])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMonumentCatalogs::route('/'),
        ];
    }
}
