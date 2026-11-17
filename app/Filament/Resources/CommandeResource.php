<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommandeResource\Pages;
use App\Filament\Resources\CommandeResource\RelationManagers;
use App\Models\Commande;
use App\Models\MonumentSemelleDimension;
use App\Services\SemelleDevis;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommandeResource extends Resource
{
    protected static ?string $model = Commande::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $modelLabel = 'commande';
    protected static ?string $pluralModelLabel = 'commandes';
    protected static ?string $navigationLabel = 'Commandes';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('numero')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Généré automatiquement'),

                Forms\Components\Select::make('devis_id')
                    ->label('Devis d\'origine')
                    ->relationship('devis', 'numero')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('societe_id')
                    ->label('Société')
                    ->relationship('societe', 'enseigne')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('agence_id')
                    ->label('Agence')
                    ->relationship('agence', 'nom')
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('monument_id')
                    ->label('Monument')
                    ->relationship('monument', 'nom')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        // les dimensions de semelle dépendent du monument : on repart d'une commande sans semelle
                        $set('semelle_dimension_id', null);
                        $set('semelle_libelle', null);
                        $set('semelle_reference', null);
                        $set('prix_semelle', null);
                    }),

                Forms\Components\Select::make('granit_id')
                    ->label('Granit')
                    ->relationship('granit', 'nom_commercial')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        // le prix de la semelle dépend du granit : recalculé seulement si une semelle est choisie
                        if ($get('semelle_dimension_id')) {
                            static::remplirSemelle($get, $set);
                        }
                    }),

                Forms\Components\Select::make('semelle_dimension_id')
                    ->label('Semelle')
                    ->options(function (Get $get) {
                        $monumentId = $get('monument_id');
                        if (! $monumentId) {
                            return [];
                        }

                        return MonumentSemelleDimension::with('semelleType')
                            ->where('monument_id', $monumentId)
                            ->orderBy('ordre_affichage')
                            ->orderBy('id')
                            ->get()
                            ->mapWithKeys(fn (MonumentSemelleDimension $d) => [$d->id => $d->libelleSelect()])
                            ->all();
                    })
                    ->placeholder('Sans semelle')
                    ->native(false)
                    ->searchable()
                    ->live()
                    ->dehydrated(false) // simple aide à la saisie : la commande ne stocke que libellé, référence et prix
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?Commande $record) {
                        // à l'édition : retrouver la dimension qui correspond à la semelle déjà enregistrée
                        if (! $record || ! $record->semelle_reference || ! $record->monument_id) {
                            return;
                        }
                        $id = MonumentSemelleDimension::where('monument_id', $record->monument_id)
                            ->where('reference', $record->semelle_reference)
                            ->orderBy('id')
                            ->value('id');
                        if ($id) {
                            $component->state($id);
                        }
                    })
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::remplirSemelle($get, $set))
                    ->helperText(function (Get $get) {
                        $semelle = SemelleDevis::calculer(
                            $get('monument_id') ? (int) $get('monument_id') : null,
                            $get('granit_id') ? (int) $get('granit_id') : null,
                            $get('semelle_dimension_id') ? (int) $get('semelle_dimension_id') : null
                        );

                        return $semelle['avertissement'] ?? 'Facultatif : choisis un monument pour voir ses semelles. Référence et prix se remplissent automatiquement.';
                    }),

                Forms\Components\Hidden::make('semelle_libelle'),

                Forms\Components\TextInput::make('semelle_reference')
                    ->label('Référence de la semelle'),

                Forms\Components\TextInput::make('prix_semelle')
                    ->label('Prix semelle')
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\TextInput::make('commande_par')
                    ->label('Commandé par'),

                Forms\Components\Textarea::make('adresse_livraison')
                    ->columnSpanFull(),

                Forms\Components\Select::make('departement_livraison_id')
                    ->label('Département de livraison')
                    ->relationship(
                        name: 'departementLivraison',
                        titleAttribute: 'nom',
                        modifyQueryUsing: fn ($query) => $query->orderBy('code'),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->nom}")
                    ->searchable(['code', 'nom'])
                    ->preload(),

                Forms\Components\Select::make('statut')
                    ->options([
                        'en_preparation' => 'En préparation',
                        'confirmee' => 'Confirmée',
                        'en_production' => 'En production',
                        'en_transit' => 'En transit',
                        'au_port_arrivee' => 'Au port d\'arrivée',
                        'livree' => 'Livrée',
                        'annulee' => 'Annulée',
                        'archivee' => 'Archivée',
                    ])
                    ->default('en_preparation')
                    ->native(false)
                    ->required(),

                Forms\Components\Select::make('container_id')
                    ->label('Container')
                    ->relationship('container', 'reference')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('prix_transport')
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\TextInput::make('prix_final')
                    ->label('Prix final')
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\DatePicker::make('date_depart_prevue'),
                Forms\Components\DatePicker::make('date_arrivee_prevue'),
                Forms\Components\DatePicker::make('date_arrivee_reelle'),

                Forms\Components\Textarea::make('commentaire')
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('document_cgv')
                    ->label('Document CGV')
                    ->directory('commandes/cgv')
                    ->visibility('public'),
            ]);
    }

    /** Remplit libellé, référence et prix de la semelle d'après la dimension choisie (même calcul que le devis). */
    protected static function remplirSemelle(Get $get, Set $set): void
    {
        $semelle = SemelleDevis::calculer(
            $get('monument_id') ? (int) $get('monument_id') : null,
            $get('granit_id') ? (int) $get('granit_id') : null,
            $get('semelle_dimension_id') ? (int) $get('semelle_dimension_id') : null
        );

        $set('semelle_libelle', $semelle['libelle']);
        $set('semelle_reference', $semelle['reference']);
        $set('prix_semelle', $semelle['prix']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('societe.enseigne')
                    ->label('Société')
                    ->searchable(),
                Tables\Columns\TextColumn::make('monument.nom')
                    ->label('Monument')
                    ->searchable(),
                Tables\Columns\TextColumn::make('semelle_libelle')
                    ->label('Semelle')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'en_preparation' => 'gray',
                        'confirmee' => 'info',
                        'en_production' => 'warning',
                        'en_transit' => 'warning',
                        'au_port_arrivee' => 'info',
                        'livree' => 'success',
                        'annulee' => 'danger',
                        'archivee' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en_preparation' => 'En préparation',
                        'confirmee' => 'Confirmée',
                        'en_production' => 'En production',
                        'en_transit' => 'En transit',
                        'au_port_arrivee' => 'Au port d\'arrivée',
                        'livree' => 'Livrée',
                        'annulee' => 'Annulée',
                        'archivee' => 'Archivée',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('container.reference')
                    ->label('Container')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('prix_final')
                    ->money('EUR')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('date_arrivee_prevue')
                    ->label('Arrivée prévue')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'en_preparation' => 'En préparation',
                        'confirmee' => 'Confirmée',
                        'en_production' => 'En production',
                        'en_transit' => 'En transit',
                        'au_port_arrivee' => 'Au port d\'arrivée',
                        'livree' => 'Livrée',
                        'annulee' => 'Annulée',
                        'archivee' => 'Archivée',
                    ]),
                Tables\Filters\SelectFilter::make('container_id')
                    ->relationship('container', 'reference')
                    ->label('Container'),
            ])
            ->defaultSort('created_at', 'desc')
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
            RelationManagers\PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommandes::route('/'),
            'create' => Pages\CreateCommande::route('/create'),
            'edit' => Pages\EditCommande::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'societe.enseigne', 'agence.nom'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Commande {$record->numero}";
    }
}
