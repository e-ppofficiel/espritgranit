<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class DernieresActivitesWidget extends BaseWidget
{
    protected static ?string $heading = 'Dernières activités';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

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

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->latest())
            ->paginated([8])
            ->defaultPaginationPageOption(8)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Utilisateur')
                    ->placeholder('Système'),
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
            ]);
    }
}
