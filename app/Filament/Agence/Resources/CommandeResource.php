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
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('monument.nom')
                    ->label('Monument')
                    ->searchable(),
                Tables\Columns\TextColumn::make('granit.nom_commercial')
                    ->label('Granit'),
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

    public static function canCreate(): bool
    {
        return false;
    }
}
