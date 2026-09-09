<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JournalActiviteResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class JournalActiviteResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $modelLabel = 'entrée du journal';
    protected static ?string $pluralModelLabel = 'Journal d\'activité';
    protected static ?string $navigationLabel = 'Journal d\'activité';

    protected static array $typesLisibles = [
        'App\\Models\\Devis' => 'Devis',
        'App\\Models\\Commande' => 'Commande',
        'App\\Models\\Granit' => 'Granit',
        'App\\Models\\Container' => 'Container',
    ];

    protected static array $evenementsLisibles = [
        'created' => 'Création',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
    ];

    /**
     * Tente de retrouver une référence lisible du sujet concerné (numéro de devis,
     * nom commercial du granit...), avec repli sur l'identifiant brut si l'enregistrement
     * a depuis été supprimé ou si le modèle n'a pas de champ de référence évident.
     */
    protected static function referenceSujet(Activity $record): string
    {
        $sujet = $record->subject;

        if (! $sujet) {
            return "#{$record->subject_id} (supprimé depuis)";
        }

        return $sujet->numero
            ?? $sujet->nom_commercial
            ?? $sujet->reference
            ?? $sujet->nom
            ?? "#{$record->subject_id}";
    }

    /**
     * Convertit toute valeur non-scalaire (tableau, JSON) en texte affichable, pour que
     * KeyValueEntry ne plante jamais — y compris sur d'anciennes entrées déjà enregistrées
     * avant qu'on ne restreigne les champs suivis à des valeurs simples.
     */
    protected static function aplatirProprietes(mixed $proprietes): array
    {
        return collect($proprietes)
            ->map(fn ($valeur) => is_array($valeur) || is_object($valeur)
                ? json_encode($valeur, JSON_UNESCAPED_UNICODE)
                : (string) ($valeur ?? '—'))
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->placeholder('Système')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => static::$evenementsLisibles[$state] ?? ($state ?? '—')),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Sur')
                    ->formatStateUsing(fn (?string $state): string => static::$typesLisibles[$state] ?? $state),
                Tables\Columns\TextColumn::make('id')
                    ->label('Référence')
                    ->formatStateUsing(fn (Activity $record): string => static::referenceSujet($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('subject_type')
                    ->label('Type')
                    ->options(static::$typesLisibles),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Action')
                    ->options(static::$evenementsLisibles),
                Tables\Filters\SelectFilter::make('causer_id')
                    ->label('Utilisateur')
                    ->options(fn () => User::pluck('name', 'id'))
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
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Détail')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Date')
                            ->dateTime('d/m/Y H:i'),
                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Utilisateur')
                            ->placeholder('Système'),
                        Infolists\Components\TextEntry::make('event')
                            ->label('Action')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => static::$evenementsLisibles[$state] ?? ($state ?? '—')),
                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Type')
                            ->formatStateUsing(fn (?string $state): string => static::$typesLisibles[$state] ?? $state),
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Référence')
                            ->state(fn (Activity $record): string => static::referenceSujet($record)),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Modifications')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('avant')
                            ->label('Avant')
                            ->state(fn (Activity $record) => static::aplatirProprietes($record->properties['old'] ?? [])),
                        Infolists\Components\KeyValueEntry::make('apres')
                            ->label('Après')
                            ->state(fn (Activity $record) => static::aplatirProprietes($record->properties['attributes'] ?? [])),
                    ])
                    ->columns(2)
                    ->visible(fn (Activity $record): bool => $record->event === 'updated'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournalActivites::route('/'),
            'view' => Pages\ViewJournalActivite::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
