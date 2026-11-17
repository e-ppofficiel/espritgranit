<?php

namespace App\Filament\Agence\Resources;

use App\Filament\Agence\Resources\DevisResource\Pages;
use App\Models\Agence;
use App\Models\Departement;
use App\Models\Devis;
use App\Models\Monument;
use App\Models\MonumentSemelleDimension;
use App\Models\Societe;
use App\Services\CoefficientResolver;
use App\Services\PrixCalculator;
use App\Services\SemelleDevis;
use App\Services\TransportCalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component as LivewireComponent;

class DevisResource extends Resource
{
    protected static ?string $model = Devis::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'devis';
    protected static ?string $pluralModelLabel = 'mes devis';
    protected static ?string $navigationLabel = 'Mes devis';

    /**
     * Même mécanique de scoping que CommandeResource : priorité à l'agence de l'utilisateur,
     * repli sur toute la société s'il n'a pas d'agence propre (responsable de société),
     * aucun accès sinon.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        $query = parent::getEloquentQuery();

        if ($user->agence_id) {
            return $query->where('agence_id', $user->agence_id);
        }

        if ($user->societe_id) {
            return $query->where('societe_id', $user->societe_id);
        }

        return $query->whereRaw('1 = 0');
    }

    protected static function recalculerPrix(Get $get, Set $set): void
    {
        $monumentId = $get('monument_id');
        $granitId = $get('granit_id');
        $departementId = $get('departement_livraison_id');
        $soubassementNoir = (bool) $get('soubassement_noir');

        $prixGranit = null;
        $poidsKg = 0;
        $monument = null;

        if ($monumentId) {
            $monument = Monument::find($monumentId);
            if ($monument) {
                $poidsKg = $monument->poidsKg();

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

                if ($prixGranit !== null && $soubassementNoir && $monument->soubassement_noir_supplement_valeur) {
                    $prixGranit += $monument->soubassement_noir_supplement_type === 'pourcentage'
                        ? round($prixGranit * ((float) $monument->soubassement_noir_supplement_valeur / 100), 2)
                        : (float) $monument->soubassement_noir_supplement_valeur;
                }
            }
        }

        // Semelle choisie (facultative) : son prix s'ajoute et son poids compte dans le transport.
        $semelle = SemelleDevis::calculer(
            $monumentId ? (int) $monumentId : null,
            $granitId ? (int) $granitId : null,
            $get('semelle_dimension_id') ? (int) $get('semelle_dimension_id') : null
        );
        $poidsKg += $semelle['poids_kg'];

        $prixTransport = null;
        if ($departementId && $poidsKg > 0) {
            $departement = Departement::find($departementId);
            if ($departement) {
                $prixTransport = TransportCalculator::calculer($departement->code, $poidsKg);
            }
        }

        $prixTotal = ($prixGranit !== null || $semelle['prix'] !== null || $prixTransport !== null)
            ? ($prixGranit ?? 0) + ($semelle['prix'] ?? 0) + ($prixTransport ?? 0)
            : null;

        $user = Auth::user();
        $agence = $user->agence_id ? Agence::find($user->agence_id) : null;
        $societe = $user->societe_id ? Societe::find($user->societe_id) : null;
        $coefficient = CoefficientResolver::resoudre($agence, $societe);

        $set('prix_granit_base', $prixGranit);
        if ($get('semelle_dimension_id') && $semelle['dimension_id'] === null) {
            $set('semelle_dimension_id', null); // la dimension ne correspond plus au monument choisi
        }
        $set('semelle_type_id', $semelle['type_id']);
        $set('semelle_reference', $semelle['reference']);
        $set('semelle_libelle', $semelle['libelle']);
        $set('prix_semelle', $semelle['prix']);
        $set('prix_transport', $prixTransport);
        $set('prix_total', $prixTotal);
        $set('coefficient_applique', $coefficient);
        $set('prix_final', $prixTotal !== null ? round($prixTotal * $coefficient, 2) : null);
    }

    protected static function notifierApercu3D(Get $get, ?LivewireComponent $livewire): void
    {
        $livewire?->dispatch(
            'devis-configurateur-change',
            monumentId: $get('monument_id'),
            granitId: $get('granit_id'),
            soubassementNoir: (bool) $get('soubassement_noir'),
        );
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

                Forms\Components\Select::make('user_id')
                    ->label('Contact')
                    ->relationship('user', 'name', fn (Builder $query) => $query->where('agence_id', Auth::user()->agence_id ?? -1))
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('monument_id')
                    ->label('Monument')
                    ->relationship('monument', 'nom')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?LivewireComponent $livewire) {
                        static::recalculerPrix($get, $set);
                        static::notifierApercu3D($get, $livewire);
                    }),

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
                    ->afterStateUpdated(function (Get $get, Set $set, ?LivewireComponent $livewire) {
                        static::recalculerPrix($get, $set);
                        static::notifierApercu3D($get, $livewire);
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
                    ->helperText(function (Get $get) {
                        $semelle = SemelleDevis::calculer(
                            $get('monument_id') ? (int) $get('monument_id') : null,
                            $get('granit_id') ? (int) $get('granit_id') : null,
                            $get('semelle_dimension_id') ? (int) $get('semelle_dimension_id') : null
                        );

                        return $semelle['avertissement'] ?? 'Facultatif : le prix de la semelle s\'ajoute au prix du monument.';
                    })
                    ->afterStateUpdated(fn (Get $get, Set $set) => static::recalculerPrix($get, $set)),

                Forms\Components\Hidden::make('semelle_type_id'),
                Forms\Components\Hidden::make('semelle_reference'),
                Forms\Components\Hidden::make('semelle_libelle'),

                Forms\Components\View::make('filament.forms.components.devis-visuel-capture')
                    ->viewData(fn (Get $get) => [
                        'monumentIdInitial' => $get('monument_id'),
                        'granitIdInitial' => $get('granit_id'),
                        'soubassementNoirInitial' => (bool) $get('soubassement_noir'),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Select::make('statut')
                    ->options([
                        'brouillon' => 'Brouillon',
                        'envoye' => 'Envoyé',
                        'en_attente' => 'En attente',
                        'accepte' => 'Accepté',
                        'refuse' => 'Refusé',
                        'expire' => 'Expiré',
                        // 'transforme_commande' volontairement absent : cette transition ne se
                        // fait que côté admin, via le bouton dédié qui crée la commande réelle.
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
                        Forms\Components\Toggle::make('soubassement_noir')
                            ->label('Soubassement noir')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?LivewireComponent $livewire) {
                                static::recalculerPrix($get, $set);
                                static::notifierApercu3D($get, $livewire);
                            })
                            ->helperText(function (Get $get) {
                                $monumentId = $get('monument_id');
                                if (! $monumentId) {
                                    return null;
                                }
                                $monument = Monument::find($monumentId);
                                if (! $monument || ! $monument->soubassement_noir_supplement_valeur) {
                                    return 'Aucun supplément configuré pour ce monument.';
                                }
                                return $monument->soubassement_noir_supplement_type === 'pourcentage'
                                    ? "Supplément : +{$monument->soubassement_noir_supplement_valeur}% sur le prix granit."
                                    : "Supplément : +{$monument->soubassement_noir_supplement_valeur}€ sur le prix granit.";
                            })
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('prix_granit_base')
                            ->label('Prix granit (base)')
                            ->numeric()
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Calculé automatiquement.'),

                        Forms\Components\TextInput::make('prix_semelle')
                            ->label('Prix semelle')
                            ->numeric()
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Calculé automatiquement.'),

                        Forms\Components\TextInput::make('prix_transport')
                            ->label('Prix transport')
                            ->numeric()
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Calculé automatiquement.'),

                        Forms\Components\TextInput::make('prix_total')
                            ->label('Prix total (avant coefficient)')
                            ->numeric()
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('coefficient_applique')
                            ->label('Coefficient appliqué')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('prix_final')
                            ->label('Prix final')
                            ->numeric()
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated()
                            ->extraInputAttributes(['class' => 'font-bold']),
                    ])
                    ->columns(2),

                Forms\Components\Textarea::make('commentaire')
                    ->label('Commentaire ou demande particulière')
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('piece_jointe')
                    ->directory('devis/pieces-jointes')
                    ->visibility('public'),

                Forms\Components\FileUpload::make('visuel')
                    ->label('Visuel du monument configuré')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->directory('devis/visuels')
                    ->visibility('public')
                    ->extraAlpineAttributes([
                        'x-on:devis-visuel-captures-ready.window' => '
                            const pond = FilePond.find($el.querySelector(".filepond--root"));
                            if (pond) {
                                $event.detail.forEach((fichier) => pond.addFile(fichier));
                            } else {
                                console.error("[devis-visuel] instance FilePond introuvable sur ce champ");
                            }
                        ',
                    ]),
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
                Tables\Columns\TextColumn::make('agence.nom')
                    ->label('Agence')
                    ->toggleable()
                    ->searchable(),
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
            ->defaultSort('created_at', 'desc')
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
                // Utile principalement pour un "responsable de société", qui voit les devis
                // de toutes les agences de sa société — sans effet pour un utilisateur
                // d'agence classique, déjà scopé à la sienne.
                Tables\Filters\SelectFilter::make('agence_id')
                    ->relationship('agence', 'nom')
                    ->label('Agence')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('monument_id')
                    ->relationship('monument', 'nom')
                    ->label('Modèle')
                    ->searchable(),
                Tables\Filters\Filter::make('periode')
                    ->form([
                        Forms\Components\DatePicker::make('du')->label('Du'),
                        Forms\Components\DatePicker::make('au')->label('Au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['du'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['au'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicateurs = [];
                        if ($data['du'] ?? null) {
                            $indicateurs['du'] = 'Du ' . \Carbon\Carbon::parse($data['du'])->format('d/m/Y');
                        }
                        if ($data['au'] ?? null) {
                            $indicateurs['au'] = 'Au ' . \Carbon\Carbon::parse($data['au'])->format('d/m/Y');
                        }
                        return $indicateurs;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('telechargerPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (Devis $record) => route('devis.telecharger-pdf', $record))
                    ->openUrlInNewTab(),
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
