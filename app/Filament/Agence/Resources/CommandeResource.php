<?php

namespace App\Filament\Agence\Resources;

use App\Filament\Agence\Resources\CommandeResource\Pages;
use App\Filament\Agence\Resources\CommandeResource\RelationManagers;
use App\Models\Commande;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CommandeResource extends Resource
{
    protected static ?string $model = Commande::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $modelLabel = 'commande';
    protected static ?string $pluralModelLabel = 'mes commandes';
    protected static ?string $navigationLabel = 'Mes commandes';

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

    public static function table(Table $table): Table
    {
        return $table
            // Évite une requête par ligne pour le visuel (devis d'origine + monument).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['devis:id,visuel', 'monument:id,nom,image_principale']))
            ->columns([
                Tables\Columns\ImageColumn::make('visuel_liste')
                    ->label('Visuel')
                    ->getStateUsing(fn (Commande $record): ?string => $record->visuelListe())
                    ->disk('public')
                    ->width(72)
                    ->height(54)
                    ->extraImgAttributes(['style' => 'object-fit: contain; background-color: #f3f4f6; border-radius: 6px;']),
                Tables\Columns\TextColumn::make('numero')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('reference_client')
                    ->label('Réf. client')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reference_interne')
                    ->label('Réf. interne')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reference_usine')
                    ->label('Réf. usine')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('monument.nom')
                    ->label('Monument')
                    ->searchable(),
                Tables\Columns\TextColumn::make('granit.nom_commercial')
                    ->label('Granit'),
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
                    }),
                Tables\Columns\TextColumn::make('date_arrivee_prevue')
                    ->label('Arrivée prévue')
                    ->date('d/m/Y')
                    ->placeholder('—'),
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
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'view' => Pages\ViewCommande::route('/{record}'),
        ];
    }

    /**
     * Recherche globale : numéro et références. getGlobalSearchEloquentQuery() s'appuie sur
     * getEloquentQuery() ci-dessus : une agence ne trouve que ses propres commandes.
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['numero', 'reference_client', 'reference_interne', 'reference_usine', 'monument.nom'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "Commande {$record->numero}";
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Monument' => $record->monument?->nom,
            'Réf. client' => $record->reference_client,
            'Réf. interne' => $record->reference_interne,
            'Réf. usine' => $record->reference_usine,
        ], fn ($valeur) => filled($valeur));
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
