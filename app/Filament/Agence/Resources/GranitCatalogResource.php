<?php

namespace App\Filament\Agence\Resources;

use App\Filament\Agence\Resources\GranitCatalogResource\Pages;
use App\Models\Granit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rubrique « Granits » du panel agence : consultation seule.
 *
 * Volontairement séparée de App\Filament\Resources\GranitResource (admin), qui n'est pas modifiée :
 * aucun champ de réglage 3D (échantillon, brillance, luminosité), ni d'ordre d'affichage,
 * ni aucune action de création, d'édition ou de suppression n'existe dans cette ressource.
 */
class GranitCatalogResource extends Resource
{
    protected static ?string $model = Granit::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $modelLabel = 'granit';
    protected static ?string $pluralModelLabel = 'granits';
    protected static ?string $navigationLabel = 'Granits';

    // Après « Catalogue » et « Catalogue Stèle Art » (entrées sans ordre explicite).
    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'nom_commercial';

    /** Les agences ne voient que les granits actifs, dans l'ordre d'affichage du configurateur. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('actif', true)
            ->orderBy('ordre_affichage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Fiche en lecture seule (la page « Voir » désactive déjà tous les champs). */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nom_commercial')
                    ->label('Nom commercial'),
                Forms\Components\TextInput::make('reference_interne')
                    ->label('Référence interne'),
                // Aperçu seul : le champ est désactivé, il n'y a ni dépôt de fichier ni suppression possible.
                Forms\Components\FileUpload::make('image')
                    ->label('Image source')
                    ->image()
                    ->directory('granits')
                    ->visibility('public')
                    ->disabled(),
                Forms\Components\TextInput::make('pays')
                    ->label('Pays'),
                Forms\Components\Textarea::make('restrictions')
                    ->label('Restrictions')
                    ->columnSpanFull(),
                Forms\Components\View::make('filament.forms.components.granit-apercu-loupe')
                    ->viewData(fn (?Model $record) => [
                        'granitRecord' => $record,
                        // Masque les messages de diagnostic réservés à l'administration.
                        'masquerDiagnostic' => true,
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_vignette')
                    ->label('Image')
                    ->getStateUsing(fn (Granit $record) => $record->image_vignette ?? $record->image)
                    ->square(),
                Tables\Columns\TextColumn::make('nom_commercial')
                    ->label('Nom commercial')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_interne')
                    ->label('Référence interne')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pays')
                    ->label('Pays')
                    ->searchable()
                    ->sortable(),
            ])
            // Pas de picto d'action : un clic sur la ligne ouvre la fiche (lecture seule).
            ->recordUrl(fn (Granit $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGranitCatalogs::route('/'),
            'view' => Pages\ViewGranitCatalog::route('/{record}'),
        ];
    }
}
