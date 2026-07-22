<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommandeResource\Pages;
use App\Filament\Resources\CommandeResource\RelationManagers;
use App\Models\Commande;
use Filament\Forms;
use Filament\Forms\Form;
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
                    ->required(),

                Forms\Components\Select::make('granit_id')
                    ->label('Granit')
                    ->relationship('granit', 'nom_commercial')
                    ->searchable()
                    ->preload(),

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
}
