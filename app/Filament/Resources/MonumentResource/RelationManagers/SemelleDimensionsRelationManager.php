<?php

namespace App\Filament\Resources\MonumentResource\RelationManagers;

use App\Models\MonumentSemelleDimension;
use App\Models\SemelleType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SemelleDimensionsRelationManager extends RelationManager
{
    protected static string $relationship = 'semelleDimensions';

    protected static ?string $title = 'Dimensions de semelle prédéfinies';

    public function form(Form $form): Form
    {
        // Pré-remplit la référence (code du type + largeur X longueur) tant qu'elle n'a pas été saisie à la main.
        $referenceAuto = function (Get $get, Set $set) {
            if ($get('reference_manuelle')) {
                return;
            }
            if (blank($get('largeur')) || blank($get('longueur'))) {
                return;
            }
            $code = $get('semelle_type_id') ? SemelleType::find($get('semelle_type_id'))?->code : null;
            $set('reference', MonumentSemelleDimension::referenceAuto($code, $get('largeur'), $get('longueur')));
        };

        return $form
            ->schema([
                Forms\Components\Select::make('semelle_type_id')
                    ->label('Type de semelle concerné')
                    ->relationship('semelleType', 'label')
                    ->getOptionLabelFromRecordUsing(fn (SemelleType $type) => $type->libelle_complet)
                    ->native(false)
                    ->live()
                    ->afterStateUpdated($referenceAuto)
                    ->helperText('Laisser vide si cette dimension est disponible pour tous les types'),
                Forms\Components\TextInput::make('longueur')
                    ->label('Longueur (cm)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated($referenceAuto),
                Forms\Components\TextInput::make('largeur')
                    ->label('Largeur (cm)')
                    ->numeric()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated($referenceAuto),
                Forms\Components\TextInput::make('reference')
                    ->label('Référence')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set) => $set('reference_manuelle', true))
                    ->helperText('Calculée automatiquement : code du type + largeur X longueur (ex. SEMELLE-B-140X240). Modifiable. Elle sert à retrouver le prix de la semelle (onglet « Prix des semelles »).'),
                Forms\Components\Hidden::make('reference_manuelle')
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Set $set, $record) {
                        $set('reference_manuelle', $record
                            && filled($record->reference)
                            && $record->reference !== MonumentSemelleDimension::referenceAuto($record->semelleType?->code, $record->largeur, $record->longueur));
                    }),
                Forms\Components\TextInput::make('label')
                    ->label('Libellé (ex: 140 x 240)')
                    ->maxLength(255)
                    ->helperText('Laisser vide : « largeur x longueur » est généré.'),
                Forms\Components\TextInput::make('hauteur')
                    ->label('Hauteur (cm)')
                    ->numeric(),
                Forms\Components\Toggle::make('is_default')
                    ->label('Dimension par défaut')
                    ->helperText('Une seule dimension par défaut par type de semelle : celle-ci est présélectionnée dans le configurateur.'),
                Forms\Components\TextInput::make('ordre_affichage')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type_affiche')
                    ->label('Type')
                    ->state(fn (MonumentSemelleDimension $record) => $record->semelleType?->libelle_complet)
                    ->placeholder('Tous types'),
                Tables\Columns\TextColumn::make('label')
                    ->label('Libellé'),
                Tables\Columns\TextColumn::make('longueur')
                    ->suffix(' cm'),
                Tables\Columns\TextColumn::make('largeur')
                    ->suffix(' cm'),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Défaut')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ordre_affichage')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('generer_standard')
                    ->label('Générer les références standard')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Générer les références standard')
                    ->modalDescription('Crée, pour chaque type de semelle disponible sur ce monument, les 3 tailles standard (130 x 230, 140 x 240 par défaut, 150 x 250). Les dimensions déjà présentes sont conservées.')
                    ->visible(fn () => $this->getOwnerRecord()->semelleTypes()->exists())
                    ->action(function () {
                        $monument = $this->getOwnerRecord();
                        $crees = 0;
                        $presentes = 0;

                        foreach ($monument->semelleTypes()->orderBy('ordre_affichage')->get() as $type) {
                            foreach (MonumentSemelleDimension::TAILLES_STANDARD as $ordre => [$largeur, $longueur, $parDefaut]) {
                                $existe = MonumentSemelleDimension::where('monument_id', $monument->id)
                                    ->where('semelle_type_id', $type->id)
                                    ->where('largeur', $largeur)
                                    ->where('longueur', $longueur)
                                    ->exists();
                                if ($existe) {
                                    $presentes++;
                                    continue;
                                }
                                $defautExistant = MonumentSemelleDimension::where('monument_id', $monument->id)
                                    ->where('semelle_type_id', $type->id)
                                    ->where('is_default', true)
                                    ->exists();

                                MonumentSemelleDimension::create([
                                    'monument_id' => $monument->id,
                                    'semelle_type_id' => $type->id,
                                    'largeur' => $largeur,
                                    'longueur' => $longueur,
                                    'is_default' => $parDefaut && ! $defautExistant,
                                    'ordre_affichage' => $ordre,
                                ]);
                                $crees++;
                            }
                        }

                        Notification::make()
                            ->title("{$crees} dimension(s) créée(s), {$presentes} déjà présente(s)")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
