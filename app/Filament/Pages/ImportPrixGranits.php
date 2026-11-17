<?php

namespace App\Filament\Pages;

use App\Models\Granit;
use App\Models\Monument;
use App\Models\MonumentSemelleDimension;
use App\Models\MonumentSemellePrix;
use App\Models\Tag;
use App\Services\ImportPrixGranitsParser;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportPrixGranits extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string $view = 'filament.pages.import-prix-granits';

    protected static ?string $navigationLabel = 'Import prix granits';

    protected static ?string $title = 'Import de prix par copier-coller';

    public ?array $data = [];

    public bool $analyseEffectuee = false;

    public function mount(): void
    {
        $this->form->fill([
            'mode' => 'granit',
            'monument_id' => null,
            'semelle_reference' => null,
            'texte_colle' => '',
            'lignes' => [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('1. Sélection du monument et collage')
                    ->schema([
                        Forms\Components\Radio::make('mode')
                            ->label('Type d\'import')
                            ->options([
                                'granit' => 'Prix du monument (un prix par granit)',
                                'semelle' => 'Prix d\'une semelle (un prix par granit)',
                            ])
                            ->default('granit')
                            ->inline()
                            ->live()
                            ->disabled(fn () => $this->analyseEffectuee)
                            ->dehydrated(),

                        Forms\Components\Select::make('monument_id')
                            ->label('Monument')
                            ->options(Monument::orderBy('nom')->pluck('nom', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn () => $this->analyseEffectuee)
                            ->dehydrated(),

                        Forms\Components\Select::make('semelle_reference')
                            ->label('Référence de la semelle')
                            ->options(function (Forms\Get $get) {
                                if (! $get('monument_id')) {
                                    return [];
                                }

                                return MonumentSemelleDimension::where('monument_id', $get('monument_id'))
                                    ->whereNotNull('reference')
                                    ->distinct()
                                    ->orderBy('reference')
                                    ->pluck('reference', 'reference')
                                    ->all();
                            })
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('mode') === 'semelle')
                            ->required(fn (Forms\Get $get) => $get('mode') === 'semelle')
                            ->helperText('Les références viennent des dimensions de semelle du monument. Le prix collé sera enregistré pour cette référence.')
                            ->disabled(fn () => $this->analyseEffectuee)
                            ->dehydrated(),

                        Forms\Components\Textarea::make('texte_colle')
                            ->label('Lignes collées depuis Excel')
                            ->rows(8)
                            ->helperText('Colle directement les lignes copiées depuis le tableau Excel (une ligne par granit).')
                            ->disabled(fn () => $this->analyseEffectuee)
                            ->dehydrated(),
                    ]),

                Forms\Components\Section::make('2. Vérification avant import')
                    ->description('Les lignes en rouge ne seront pas importées tant que le granit n\'est pas rattaché manuellement.')
                    ->visible(fn () => $this->analyseEffectuee)
                    ->schema([
                        Forms\Components\Repeater::make('lignes')
                            ->label('')
                            ->schema([
                                Forms\Components\Placeholder::make('apercu')
                                    ->label('Ligne collée')
                                    ->content(fn (Forms\Get $get) => $get('ligne_brute'))
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('granit_id')
                                    ->label('Granit à mettre à jour')
                                    ->options(Granit::orderBy('nom_commercial')->pluck('nom_commercial', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->helperText(fn (Forms\Get $get) => $get('erreur')),

                                Forms\Components\TextInput::make('prix_fob')
                                    ->label('Prix FOB')
                                    ->numeric()
                                    ->prefix('€')
                                    ->required(),

                                Forms\Components\TextInput::make('taux_marge')
                                    ->label('Taux de marge')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->suffix('(ex: 0.35 = 35%)')
                                    ->required(),

                                Forms\Components\Hidden::make('nom_detecte'),
                                Forms\Components\Hidden::make('ligne_brute'),
                                Forms\Components\TextInput::make('poids_semelle')
                                    ->label('Poids de la semelle (kg)')
                                    ->numeric()
                                    ->suffix('kg')
                                    ->visible(fn (Forms\Get $get) => $get('../../mode') === 'semelle'),
                                Forms\Components\Hidden::make('poids_detecte'),
                                Forms\Components\Hidden::make('categorie_detectee'),
                                Forms\Components\Hidden::make('pays_detecte'),
                                Forms\Components\Hidden::make('erreur'),
                            ])
                            ->columns(3)
                            ->deletable(true)
                            ->addable(false)
                            ->itemLabel(fn (array $state): ?string => $state['nom_detecte'] ?? 'Ligne non reconnue'),
                    ]),
            ])
            ->statePath('data');
    }

    public function analyser(): void
    {
        $etat = $this->form->getState();

        if (empty($etat['monument_id'])) {
            Notification::make()->title('Sélectionne un monument avant d\'analyser.')->danger()->send();
            return;
        }

        if (trim((string) $etat['texte_colle']) === '') {
            Notification::make()->title('Colle des lignes avant d\'analyser.')->danger()->send();
            return;
        }

        $mode = $etat['mode'] ?? 'granit';
        if ($mode === 'semelle' && empty($etat['semelle_reference'])) {
            Notification::make()->title('Sélectionne la référence de la semelle avant d\'analyser.')->danger()->send();
            return;
        }

        $lignes = ImportPrixGranitsParser::parse($etat['texte_colle'], $mode === 'semelle' ? 'semelle' : 'monument');

        if (empty($lignes)) {
            Notification::make()->title('Aucune ligne exploitable détectée.')->danger()->send();
            return;
        }

        $this->analyseEffectuee = true;

        $this->form->fill([
            'mode' => $mode,
            'monument_id' => $etat['monument_id'],
            'semelle_reference' => $etat['semelle_reference'] ?? null,
            'texte_colle' => $etat['texte_colle'],
            'lignes' => $lignes,
        ]);

        $nonReconnus = collect($lignes)->whereNull('granit_id')->count();
        Notification::make()
            ->title(count($lignes) . ' ligne(s) analysée(s)' . ($nonReconnus ? ", {$nonReconnus} à rattacher manuellement" : ''))
            ->warning($nonReconnus > 0)
            ->success($nonReconnus === 0)
            ->send();
    }

    public function reinitialiser(): void
    {
        $this->analyseEffectuee = false;
        $this->form->fill([
            'mode' => 'granit',
            'monument_id' => null,
            'semelle_reference' => null,
            'texte_colle' => '',
            'lignes' => [],
        ]);
    }

    public function validerImport(): void
    {
        $etat = $this->form->getState();
        $monument = Monument::find($etat['monument_id']);

        if (! $monument) {
            Notification::make()->title('Monument introuvable.')->danger()->send();
            return;
        }

        if (($etat['mode'] ?? 'granit') === 'semelle') {
            $this->validerImportSemelle($monument, (string) ($etat['semelle_reference'] ?? ''), $etat['lignes'] ?? []);
            return;
        }

        $reussies = 0;
        $poidsAAppliquer = null;
        $tagsAAttacher = [];

        foreach ($etat['lignes'] as $ligne) {
            if (empty($ligne['granit_id']) || $ligne['prix_fob'] === null || $ligne['taux_marge'] === null) {
                continue;
            }

            $monument->granits()->syncWithoutDetaching([
                $ligne['granit_id'] => [
                    'prix_fob' => $ligne['prix_fob'],
                    'taux_marge' => $ligne['taux_marge'],
                ],
            ]);
            $reussies++;

            // Bonus : poids et catégorie détectés (uniquement si pas déjà renseignés, pour ne rien écraser)
            if ($poidsAAppliquer === null && ! empty($ligne['poids_detecte'])) {
                $poidsAAppliquer = $ligne['poids_detecte'];
            }
            if (! empty($ligne['categorie_detectee'])) {
                $tagsAAttacher[] = $ligne['categorie_detectee'];
            }
        }

        if ($poidsAAppliquer !== null && $monument->poids_total_kg === null) {
            $monument->update(['poids_total_kg' => $poidsAAppliquer]);
        }

        foreach (array_unique($tagsAAttacher) as $nomTag) {
            $tag = Tag::firstOrCreate(['nom' => $nomTag]);
            $monument->tags()->syncWithoutDetaching([$tag->id]);
        }

        Notification::make()
            ->title("{$reussies} tarif(s) importé(s) sur « {$monument->nom} »")
            ->success()
            ->send();

        $this->reinitialiser();
    }

    /** Enregistre les prix d'une semelle (une référence) pour les granits collés, sans toucher au prix du monument. */
    protected function validerImportSemelle(Monument $monument, string $reference, array $lignes): void
    {
        if ($reference === '') {
            Notification::make()->title('Référence de semelle manquante.')->danger()->send();
            return;
        }

        $reussies = 0;
        foreach ($lignes as $ligne) {
            if (empty($ligne['granit_id']) || $ligne['prix_fob'] === null || $ligne['taux_marge'] === null) {
                continue;
            }

            $valeurs = [
                'prix_fob' => $ligne['prix_fob'],
                'taux_marge' => $ligne['taux_marge'],
            ];
            // Un poids vide ne remplace pas un poids déjà enregistré.
            if (isset($ligne['poids_semelle']) && $ligne['poids_semelle'] !== '') {
                $valeurs['poids_kg'] = $ligne['poids_semelle'];
            }

            MonumentSemellePrix::updateOrCreate(
                ['monument_id' => $monument->id, 'granit_id' => $ligne['granit_id'], 'reference' => $reference],
                $valeurs
            );
            $reussies++;
        }

        Notification::make()
            ->title("{$reussies} prix de la semelle {$reference} importé(s) sur « {$monument->nom} »")
            ->success()
            ->send();

        $this->reinitialiser();
    }
}
