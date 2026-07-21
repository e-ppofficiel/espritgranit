<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevisResource\Pages;
use App\Models\Devis;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DevisResource extends Resource
{
    protected static ?string $model = Devis::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'devis';
    protected static ?string $pluralModelLabel = 'devis';
    protected static ?string $navigationLabel = 'Devis';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('numero')
                    ->label('Numéro')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Généré automatiquement à la création'),

                Forms\Components\Select::make('societe_id')
                    ->label('Société')
                    ->relationship('societe', 'enseigne')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Forms\Components\Select::make('agence_id')
                    ->label('Agence')
                    ->options(function (Forms\Get $get) {
                        $societeId = $get('societe_id');
                        if (! $societeId) {
                            return [];
                        }
                        return \App\Models\Agence::where('societe_id', $societeId)->pluck('nom', 'id');
                    })
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('user_id')
                    ->label('Contact')
                    ->relationship('user', 'name')
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

                Forms\Components\Select::make('origine')
                    ->options([
                        'agence' => 'Agence',
                        'admin' => 'Administrateur',
                    ])
                    ->default('admin')
                    ->native(false)
                    ->required(),

                Forms\Components\Select::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'envoye' => 'Envoyé',
                        'en_attente' => 'En attente',
                        'accepte' => 'Accepté',
                        'refuse' => 'Refusé',
                        'expire' => 'Expiré',
                        'transforme_commande' => 'Transformé en commande',
                    ])
                    ->default('brouillon')
                    ->native(false)
                    ->required(),

                Forms\Components\Select::make('departement_livraison_id')
                    ->label('Département de livraison')
                    ->relationship('departementLivraison', 'nom')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('prix_granit_base')
                    ->label('Prix granit (base)')
                    ->numeric()
                    ->prefix('€')
                    ->helperText('Calcul automatique à venir'),

                Forms\Components\TextInput::make('prix_transport')
                    ->label('Prix transport')
                    ->numeric()
                    ->prefix('€')
                    ->helperText('Calcul automatique à venir'),

                Forms\Components\TextInput::make('prix_total')
                    ->label('Prix total')
                    ->numeric()
                    ->prefix('€'),

                Forms\Components\Textarea::make('commentaire')
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('piece_jointe')
                    ->directory('devis/pieces-jointes')
                    ->visibility('public'),

                Forms\Components\FileUpload::make('visuel')
                    ->label('Visuel du monument configuré')
                    ->image()
                    ->directory('devis/visuels')
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
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('agence.nom')
                    ->label('Agence')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('monument.nom')
                    ->label('Monument')
                    ->searchable(),
                Tables\Columns\TextColumn::make('granit.nom_commercial')
                    ->label('Granit')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'brouillon' => 'gray',
                        'envoye' => 'info',
                        'en_attente' => 'warning',
                        'accepte' => 'success',
                        'refuse' => 'danger',
                        'expire' => 'danger',
                        'transforme_commande' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'brouillon' => 'Brouillon',
                        'envoye' => 'Envoyé',
                        'en_attente' => 'En attente',
                        'accepte' => 'Accepté',
                        'refuse' => 'Refusé',
                        'expire' => 'Expiré',
                        'transforme_commande' => 'Commande',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('prix_total')
                    ->money('EUR')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'envoye' => 'Envoyé',
                        'en_attente' => 'En attente',
                        'accepte' => 'Accepté',
                        'refuse' => 'Refusé',
                        'expire' => 'Expiré',
                        'transforme_commande' => 'Transformé en commande',
                    ]),
                Tables\Filters\SelectFilter::make('societe_id')
                    ->relationship('societe', 'enseigne')
                    ->label('Société')
                    ->searchable(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevis::route('/'),
            'create' => Pages\CreateDevis::route('/create'),
            'edit' => Pages\EditDevis::route('/{record}/edit'),
        ];
    }
}
