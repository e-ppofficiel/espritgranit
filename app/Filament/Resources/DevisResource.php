<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevisResource\Pages;
use App\Models\Agence;
use App\Models\Departement;
use App\Models\Devis;
use App\Models\Monument;
use App\Models\Societe;
use App\Services\CoefficientResolver;
use App\Services\PrixCalculator;
use App\Services\TransportCalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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

    protected static function recalculerPrix(Get $get, Set $set): void
    {
        $monumentId = $get('monument_id');
        $granitId = $get('granit_id');
        $departementId = $get('departement_livraison_id');
        $societeId = $get('societe_id');
        $agenceId = $get('agence_id');

        $prixGranit = null;
        $poidsKg = 0;

        if ($monumentId) {
            $monument = Monument::find($monumentId);
            if ($monument) {
                $poidsKg = (float) $monument->blocs()->sum('poids_kg');

                if ($granitId) {
                    $lien = $monument->granits()->where('granit_id', $granitId)->first();
                    if ($lien && $lien->pivot->prix_fob && $lien->pivot->taux_marge !== null) {
                        $prixGranit = PrixCalculator::prixVenteDepart(
                            $monument,
                            (float) $lien->pivot->prix_fob,
                            (float) $lien->pivot->taux_marge
                        );
                    }
                }
            }
        }

        $prixTransport = null;
        if ($departementId && $poidsKg > 0) {
            $departement = Departement::find($departementId);
            if ($departement) {
                $prixTransport = TransportCalculator::calculer($departement->code, $poidsKg);
            }
        }

        $prixTotal = ($prixGranit !== null || $prixTransport !== null)
            ? ($prixGranit ?? 0) + ($prixTransport ?? 0)
            : null;

        $agence = $agenceId ? Agence::find($agenceId) : null;
        $societe = $societeId ? Societe::find($societeId) : null;
        $coefficient = CoefficientResolver::resoudre($agence, $societe);

        $set('prix_granit_base', $prixGranit);
        $set('prix_transport', $prixTransport);
        $set('prix_total', $prixTotal);
        $set('coefficient_applique', $coefficient);
        $set('prix_final', $prixTotal !== null ? round($prixTotal * $coefficient, 2) : null);
    }

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
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculerPrix($get, $set)),

                Forms\Components\Select::make('agence_id')
                    ->label('Agence')
                    ->options(function (Get $get) {
                        $societeId = $get('societe_id');
                        if (! $societeId) {
                            return [];
                        }
                        return Agence::where('societe_id', $societeId)->pluck('nom', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculerPrix($get, $set)),

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
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculerPrix($get, $set)),

                Forms\Components\Select::make('granit_id')
                    ->label('Granit')
                    ->options(function (Get $get) {
                        $monumentId = $get('monument_id');
                        if (! $monumentId) {
                            return [];
                        }
                        $monument = Monument::find($monumentId);
                        return $monument
                            ? $monument->granits()->wherePivotNotNull('prix_fob')->pluck('nom_commercial', 'granits.id')
                            : [];
                    })
                    ->searchable()
                    ->preload()
                    ->helperText('Seuls les granits ayant un prix défini pour ce monument sont proposés.')
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculerPrix($get, $set)),

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
                    ->relationship(
                        name: 'departementLivraison',
                        titleAttribute: 'nom',
                        modifyQueryUsing: fn ($query) => $query->orderBy('code'),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} - {$record->nom}")
                    ->searchable(['code', 'nom'])
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculerPrix($get, $set)),

                Forms\Components\Section::make('Prix')
                    ->schema([
                        Forms\Components\TextInput::make('prix_granit_base')
                            ->label('Prix granit (base)')
                            ->numeric()
                            ->prefix('€')
                            ->helperText('Pré-rempli automatiquement, modifiable si besoin.'),

                        Forms\Components\TextInput::make('prix_transport')
                            ->label('Prix transport')
                            ->numeric()
                            ->prefix('€')
                            ->helperText('Pré-rempli automatiquement, modifiable si besoin.'),

                        Forms\Components\TextInput::make('prix_total')
                            ->label('Prix total (avant coefficient)')
                            ->numeric()
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('coefficient_applique')
                            ->label('Coefficient appliqué')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Résolu automatiquement : agence > société > général. Modifiable si besoin.'),

                        Forms\Components\TextInput::make('prix_final')
                            ->label('Prix final (facturé)')
                            ->numeric()
                            ->prefix('€')
                            ->extraInputAttributes(['class' => 'font-bold']),
                    ])
                    ->columns(2),

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
                Tables\Columns\TextColumn::make('prix_final')
                    ->label('Prix final')
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
                Tables\Actions\Action::make('transformer')
                    ->label('Transformer en commande')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->statut === 'accepte')
                    ->requiresConfirmation()
                    ->modalHeading('Transformer ce devis en commande ?')
                    ->action(function ($record) {
                        $commande = \App\Models\Commande::creerDepuisDevis($record);
                        \Filament\Notifications\Notification::make()
                            ->title('Commande créée : ' . $commande->numero)
                            ->success()
                            ->send();
                        return redirect(\App\Filament\Resources\CommandeResource::getUrl('edit', ['record' => $commande]));
                    }),
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
