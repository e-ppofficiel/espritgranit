<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FondResource\Pages;
use App\Models\Fond;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Fonds du configurateur 3D : couleurs de fond et ambiances (panoramas 360° dont les reflets apparaissent sur la pierre).
 * Ce que l'on crée, ordonne, active ou désactive ici est ce que proposent les onglets « Fond » du configurateur (admin et agences).
 */
class FondResource extends Resource
{
    protected static ?string $model = Fond::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $modelLabel = 'fond';
    protected static ?string $pluralModelLabel = 'fonds';
    protected static ?string $navigationLabel = 'Fonds du configurateur';

    /** Taille maximale d'un envoi (Ko) : celle que Livewire accepte par défaut (12 Mo). */
    private const TAILLE_MAX_KO = 12288;

    private const EXTENSIONS_PANORAMA = ['hdr', 'jpg', 'jpeg', 'png', 'webp'];
    private const EXTENSIONS_IMAGE = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Nom de fichier enregistré : nom d'origine nettoyé + suffixe aléatoire, et SURTOUT l'extension d'origine contrôlée
     * (le configurateur reconnaît un panorama HDR à son extension « .hdr » ; toute extension hors liste est refusée).
     */
    private static function nomFichier(array $extensionsAutorisees, string $champ): \Closure
    {
        // Le paramètre doit s'appeler $file : c'est le nom sous lequel Filament fournit le fichier envoyé.
        return static function (TemporaryUploadedFile $file) use ($extensionsAutorisees, $champ): string {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($extension, $extensionsAutorisees, true)) {
                throw ValidationException::withMessages([
                    'data.' . $champ => 'Format non accepté : utilisez un fichier .' . implode(', .', $extensionsAutorisees) . '.',
                ]);
            }
            $base = Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'fond';

            return $base . '-' . Str::lower(Str::random(6)) . '.' . $extension;
        };
    }

    public static function form(Form $form): Form
    {
        $ambiance = static fn (Get $get): bool => $get('type') === 'ambiance';
        $couleur = static fn (Get $get): bool => $get('type') === 'couleur';

        return $form->schema([
            Forms\Components\Section::make('Fond')->schema([
                Forms\Components\Radio::make('type')
                    ->label('Type de fond')
                    ->options([
                        'couleur' => 'Couleur unie (fond du viewer)',
                        'ambiance' => 'Ambiance : panorama 360° (reflets sur la pierre, décor possible)',
                    ])
                    ->default('ambiance')
                    ->required()
                    ->live()
                    // Le type ne change plus une fois le fond créé (les liens partagés en dépendent).
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                Forms\Components\TextInput::make('nom')
                    ->label('Nom affiché')
                    ->required()
                    ->maxLength(60)
                    ->helperText('Texte sous la vignette dans le configurateur.'),
                Forms\Components\ColorPicker::make('couleur')
                    ->label('Couleur')
                    ->visible($couleur)
                    ->required($couleur)
                    ->helperText('Un fond clair passe automatiquement les textes du viewer en sombre.'),
            ])->columns(1),

            Forms\Components\Section::make('Panorama')
                ->visible($ambiance)
                ->schema([
                    Forms\Components\FileUpload::make('fichier')
                        ->label('Fichier du panorama')
                        ->disk('public')
                        ->directory('fonds')
                        ->visibility('public')
                        ->maxSize(self::TAILLE_MAX_KO)
                        ->required($ambiance)
                        ->getUploadedFileNameForStorageUsing(self::nomFichier(self::EXTENSIONS_PANORAMA, 'fichier'))
                        ->helperText('Format équirectangulaire 2:1 : .hdr (idéal : 1536×768 environ 4 Mo), .jpg ou .png. 12 Mo maximum.'),
                    Forms\Components\TextInput::make('exposition')
                        ->label('Exposition (luminosité de la scène)')
                        ->numeric()
                        ->minValue(0.1)
                        ->maxValue(4)
                        ->step(0.05)
                        ->default(1.5)
                        ->required($ambiance)
                        ->helperText('Plus élevé = pierre plus lumineuse. Repères : 1,5 en studio, entre 0,9 et 1,6 pour les paysages.'),
                    Forms\Components\FileUpload::make('vignette')
                        ->label('Vignette (facultative)')
                        ->disk('public')
                        ->directory('fonds/vignettes')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->maxSize(self::TAILLE_MAX_KO)
                        ->getUploadedFileNameForStorageUsing(self::nomFichier(self::EXTENSIONS_IMAGE, 'vignette'))
                        ->helperText('Laissée vide, la vignette est générée automatiquement à partir du panorama.'),
                ])->columns(1),

            Forms\Components\Section::make('Décor 360°')
                ->visible($ambiance)
                ->description('L\'interrupteur « Afficher en décor » du configurateur affiche le paysage en arrière-plan.')
                ->schema([
                    Forms\Components\Toggle::make('visible_decor')
                        ->label('Proposer cette ambiance en décor')
                        ->default(true)
                        ->helperText('À désactiver pour un éclairage sans paysage (studio, ciel seul) : l\'ambiance reste proposée pour ses reflets.'),
                    Forms\Components\FileUpload::make('decor_fichier')
                        ->label('Image de décor plus nette (facultative)')
                        ->disk('public')
                        ->directory('fonds/decors')
                        ->visibility('public')
                        ->maxSize(self::TAILLE_MAX_KO)
                        ->getUploadedFileNameForStorageUsing(self::nomFichier(self::EXTENSIONS_PANORAMA, 'decor_fichier'))
                        ->helperText('JPG 4096×2048 conseillé. Sans image dédiée, le panorama ci-dessus sert de décor.'),
                ])->columns(1),

            Forms\Components\Section::make('Affichage')->schema([
                Forms\Components\Toggle::make('actif')
                    ->label('Actif (proposé dans le configurateur)')
                    ->default(true),
                Forms\Components\TextInput::make('ordre')
                    ->label('Ordre d\'affichage')
                    ->numeric()
                    ->default(fn (): int => ((int) Fond::max('ordre')) + 10)
                    ->helperText('Plus petit = affiché en premier. On peut aussi glisser-déposer les lignes dans la liste.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordre')
            ->reorderable('ordre')
            ->columns([
                Tables\Columns\ImageColumn::make('vignette')
                    ->label('Aperçu')
                    ->disk('public')
                    ->width(96)
                    ->height(48),
                Tables\Columns\ColorColumn::make('couleur')
                    ->label('Couleur'),
                Tables\Columns\TextColumn::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'couleur' ? 'Couleur' : 'Ambiance')
                    ->color(fn (string $state): string => $state === 'couleur' ? 'gray' : 'info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('exposition')
                    ->label('Exposition')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state, Fond $record): ?string => $record->type === 'ambiance' ? number_format((float) $state, 2, ',', '') : null),
                Tables\Columns\IconColumn::make('visible_decor')
                    ->label('En décor')
                    ->boolean()
                    ->getStateUsing(fn (Fond $record): ?bool => $record->type === 'ambiance' ? (bool) $record->visible_decor : null),
                Tables\Columns\ToggleColumn::make('actif')
                    ->label('Actif'),
                Tables\Columns\TextColumn::make('ordre')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(['couleur' => 'Couleurs', 'ambiance' => 'Ambiances']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('Les liens partagés qui utilisaient ce fond reviendront au fond par défaut. Pour le masquer sans le perdre, désactivez-le plutôt.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFonds::route('/'),
            'create' => Pages\CreateFond::route('/create'),
            'edit' => Pages\EditFond::route('/{record}/edit'),
        ];
    }
}
