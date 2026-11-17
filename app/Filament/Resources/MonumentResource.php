<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonumentResource\Pages;
use App\Filament\Resources\MonumentResource\RelationManagers;
use App\Models\Monument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

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
                Forms\Components\Select::make('tags')
                    ->label('Mots-clés')
                    ->relationship('tags', 'nom')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nom')
                            ->label('Nouveau mot-clé')
                            ->required()
                            ->unique(table: 'tags', column: 'nom'),
                    ])
                    ->helperText('Tape un mot-clé existant pour le sélectionner (ex: moderne, épuré, double tombe), ou un nouveau mot pour le créer directement.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('poids_total_kg')
                    ->label('Poids total du monument (kg)')
                    ->numeric()
                    ->step(0.01)
                    ->suffix('kg')
                    ->helperText('Utilisé pour le calcul du prix granit et du transport. Si vide, le système se rabat sur la somme des poids par bloc (ancien fonctionnement).'),
                Forms\Components\TextInput::make('ordre_affichage')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('actif')
                    ->default(true),

                Forms\Components\Section::make('Option "Soubassement noir"')
                    ->description('Laisser vide si cette option n\'est pas proposée pour ce monument.')
                    ->schema([
                        Forms\Components\Select::make('soubassement_noir_supplement_type')
                            ->label('Type de supplément')
                            ->options([
                                'fixe' => 'Montant fixe (€)',
                                'pourcentage' => 'Pourcentage du prix granit',
                            ])
                            ->native(false)
                            ->live(),
                        Forms\Components\TextInput::make('soubassement_noir_supplement_valeur')
                            ->label('Valeur du supplément')
                            ->numeric()
                            ->step(0.01)
                            ->suffix(fn (Forms\Get $get) => $get('soubassement_noir_supplement_type') === 'pourcentage' ? '%' : '€')
                            ->visible(fn (Forms\Get $get) => filled($get('soubassement_noir_supplement_type'))),
                    ])
                    ->columns(2),
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
                Tables\Columns\TextColumn::make('tags.nom')
                    ->label('Mots-clés')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
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
                    // Suppression sécurisée : un monument présent dans un devis ou une commande ne peut pas être
                    // supprimé (clés étrangères en RESTRICT). Il est conservé, et signalé dans la notification.
                    Tables\Actions\BulkAction::make('supprimer_securise')
                        ->label('Supprimer')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-trash')
                        ->modalHeading('Supprimer les monuments sélectionnés')
                        ->modalDescription('Seuls les monuments qui ne figurent dans aucun devis ni aucune commande seront supprimés. Les autres seront conservés : utilisez « Désactiver » pour les retirer du catalogue.')
                        ->modalSubmitActionLabel('Supprimer')
                        ->action(function (Collection $records): void {
                            $utilises = Monument::idsUtilises($records->modelKeys());
                            $supprimes = 0;
                            $conserves = [];

                            foreach ($records as $monument) {
                                if (in_array((int) $monument->getKey(), $utilises, true)) {
                                    $conserves[] = $monument->reference_interne ?: $monument->nom;
                                    continue;
                                }

                                try {
                                    $monument->delete();
                                    $supprimes++;
                                } catch (QueryException $e) {
                                    // Autre contrainte non prévue : on ne bloque pas le lot, on conserve ce monument.
                                    report($e);
                                    $conserves[] = $monument->reference_interne ?: $monument->nom;
                                }
                            }

                            $notification = Notification::make();

                            if ($conserves === []) {
                                $notification
                                    ->title($supprimes . ' monument(s) supprimé(s)')
                                    ->success();
                            } else {
                                $liste = implode(', ', array_slice($conserves, 0, 15)) . (count($conserves) > 15 ? '…' : '');
                                $notification
                                    ->title($supprimes . ' supprimé(s), ' . count($conserves) . ' conservé(s)')
                                    ->body('Conservés car utilisés dans des devis ou des commandes : ' . e($liste) . '. Sélectionnez-les puis utilisez « Désactiver » pour les retirer du catalogue.')
                                    ->persistent();

                                $supprimes > 0 ? $notification->warning() : $notification->danger();
                            }

                            $notification->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('desactiver')
                        ->label('Désactiver')
                        ->icon('heroicon-m-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Désactiver les monuments sélectionnés')
                        ->modalDescription('Les monuments désactivés ne sont pas supprimés : les devis et commandes existants sont conservés. Vous pourrez les réactiver depuis leur fiche.')
                        ->modalSubmitActionLabel('Désactiver')
                        ->action(function (Collection $records): void {
                            $nombre = Monument::whereIn('id', $records->modelKeys())
                                ->where('actif', true)
                                ->update(['actif' => false]);

                            Notification::make()
                                ->title($nombre . ' monument(s) désactivé(s)')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
