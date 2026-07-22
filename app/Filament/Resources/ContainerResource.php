<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContainerResource\Pages;
use App\Filament\Resources\ContainerResource\RelationManagers;
use App\Models\Container;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContainerResource extends Resource
{
    protected static ?string $model = Container::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $modelLabel = 'container';
    protected static ?string $pluralModelLabel = 'containers';
    protected static ?string $navigationLabel = 'Containers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('reference')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('statut')
                    ->options([
                        'en_preparation' => 'En préparation',
                        'en_transit' => 'En transit',
                        'au_port_arrivee' => 'Au port d\'arrivée',
                        'livre' => 'Livré',
                    ])
                    ->default('en_preparation')
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('date_depart'),
                Forms\Components\DatePicker::make('date_arrivee_prevue'),
                Forms\Components\DatePicker::make('date_arrivee_reelle'),
                Forms\Components\Textarea::make('informations_complementaires')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'en_preparation' => 'gray',
                        'en_transit' => 'warning',
                        'au_port_arrivee' => 'info',
                        'livre' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en_preparation' => 'En préparation',
                        'en_transit' => 'En transit',
                        'au_port_arrivee' => 'Au port d\'arrivée',
                        'livre' => 'Livré',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('commandes_count')
                    ->label('Commandes')
                    ->counts('commandes'),
                Tables\Columns\TextColumn::make('date_depart')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('date_arrivee_prevue')
                    ->label('Arrivée prévue')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('date_arrivee_reelle')
                    ->label('Arrivée réelle')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'en_preparation' => 'En préparation',
                        'en_transit' => 'En transit',
                        'au_port_arrivee' => 'Au port d\'arrivée',
                        'livre' => 'Livré',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('repercuter')
                    ->label('Répercuter sur les commandes')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Répercuter le statut et les dates ?')
                    ->modalDescription('Cette action va mettre à jour le statut et les dates de toutes les commandes rattachées à ce container. Confirmez-vous ?')
                    ->action(function (Container $record) {
                        $record->repercuterSurCommandes();
                        Notification::make()
                            ->title('Statut répercuté sur ' . $record->commandes()->count() . ' commande(s)')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CommandesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContainers::route('/'),
            'create' => Pages\CreateContainer::route('/create'),
            'edit' => Pages\EditContainer::route('/{record}/edit'),
        ];
    }
}
