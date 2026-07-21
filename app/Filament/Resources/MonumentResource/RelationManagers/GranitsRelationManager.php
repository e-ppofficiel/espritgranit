<?php

namespace App\Filament\Resources\MonumentResource\RelationManagers;

use App\Services\PrixCalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class GranitsRelationManager extends RelationManager
{
    protected static string $relationship = 'granits';

    protected static ?string $title = 'Prix par granit';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('prix_fob')
                    ->label('Prix FOB usine (€)')
                    ->numeric()
                    ->prefix('€')
                    ->required(),
                Forms\Components\TextInput::make('taux_marge')
                    ->label('Taux de marge (ex: 0,40 pour 40%)')
                    ->numeric()
                    ->step(0.01)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom_commercial')
            ->columns([
                Tables\Columns\TextColumn::make('nom_commercial')
                    ->label('Granit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.prix_fob')
                    ->label('Prix FOB')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('pivot.taux_marge')
                    ->label('Marge')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 0) . ' %' : '—'),
                Tables\Columns\TextColumn::make('prix_calcule')
                    ->label('Prix vente départ (calculé)')
                    ->state(function ($record) {
                        if (! $record->pivot->prix_fob || $record->pivot->taux_marge === null) {
                            return null;
                        }
                        $prix = PrixCalculator::prixVenteDepart(
                            $this->getOwnerRecord(),
                            (float) $record->pivot->prix_fob,
                            (float) $record->pivot->taux_marge
                        );
                        return $prix ? number_format($prix, 0) . ' €' : 'Poids manquant';
                    })
                    ->weight('bold'),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Ajouter un granit')
                    ->recordSelectSearchColumns(['nom_commercial'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('prix_fob')
                            ->label('Prix FOB usine (€)')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        Forms\Components\TextInput::make('taux_marge')
                            ->label('Taux de marge (ex: 0,40)')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make()
                    ->label('Retirer'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make(),
            ]);
    }
}
