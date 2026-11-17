<?php

namespace App\Filament\Resources\MonumentResource\RelationManagers;

use App\Models\MonumentSemelleDimension;
use App\Models\MonumentSemellePrix;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class SemellePrixRelationManager extends RelationManager
{
    protected static string $relationship = 'semellePrix';

    protected static ?string $title = 'Prix des semelles';

    /** Références des dimensions de semelle de ce monument (une même référence peut figurer sur plusieurs lignes). */
    protected function references(): array
    {
        return MonumentSemelleDimension::where('monument_id', $this->getOwnerRecord()->getKey())
            ->whereNotNull('reference')
            ->distinct()
            ->orderBy('reference')
            ->pluck('reference', 'reference')
            ->all();
    }

    /** Combinaisons référence × granit (granits qui ont un prix sur ce monument) sans prix de semelle. */
    protected function descriptionManquants(): ?string
    {
        $monument = $this->getOwnerRecord();
        $references = array_keys($this->references());
        if ($references === []) {
            return 'Aucune dimension de semelle sur ce monument : ajoutez-en dans l\'onglet « Dimensions de semelle prédéfinies ».';
        }

        $granitIds = $monument->granits()->pluck('granits.id')->all();
        $existants = MonumentSemellePrix::where('monument_id', $monument->getKey())
            ->get(['granit_id', 'reference'])
            ->map(fn ($ligne) => $ligne->granit_id.'|'.$ligne->reference)
            ->flip();

        $manquants = 0;
        foreach ($references as $reference) {
            foreach ($granitIds as $granitId) {
                if (! isset($existants[$granitId.'|'.$reference])) {
                    $manquants++;
                }
            }
        }

        return $manquants > 0
            ? "{$manquants} prix manquant(s) sur ".count($references).' référence(s) × '.count($granitIds).' granit(s) : une semelle sans prix n\'est pas chiffrée dans les devis.'
            : null;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('reference')
                    ->label('Référence de la semelle')
                    ->options(fn () => $this->references())
                    ->searchable()
                    ->required()
                    ->live(),
                Forms\Components\Select::make('granit_id')
                    ->label('Granit')
                    ->options(fn () => $this->getOwnerRecord()->granits()->orderBy('nom_commercial')->pluck('nom_commercial', 'granits.id'))
                    ->searchable()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->where('monument_id', $this->getOwnerRecord()->getKey())
                            ->where('reference', $get('reference'))
                    )
                    ->validationMessages(['unique' => 'Cette référence a déjà un prix pour ce granit.']),
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
                Forms\Components\TextInput::make('poids_kg')
                    ->label('Poids de la semelle (kg)')
                    ->numeric()
                    ->suffix('kg')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->description(fn () => $this->descriptionManquants())
            ->defaultSort('reference')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('granit.nom_commercial')
                    ->label('Granit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('prix_fob')
                    ->label('Prix FOB')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('taux_marge')
                    ->label('Marge')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 0).' %' : '—'),
                Tables\Columns\TextColumn::make('poids_kg')
                    ->label('Poids')
                    ->suffix(' kg'),
                Tables\Columns\TextColumn::make('prix_calcule')
                    ->label('Prix vente départ (calculé)')
                    ->state(function (MonumentSemellePrix $record) {
                        $prix = $record->prixVenteDepart();

                        return $prix ? number_format($prix, 0).' €' : 'Poids manquant';
                    })
                    ->weight('bold'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reference')
                    ->label('Référence')
                    ->options(fn () => $this->references()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajouter un prix de semelle'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
