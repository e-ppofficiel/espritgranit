<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DemandeAdhesionResource\Pages;
use App\Models\DemandeAdhesion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DemandeAdhesionResource extends Resource
{
    protected static ?string $model = DemandeAdhesion::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $modelLabel = 'demande d\'adhésion';
    protected static ?string $pluralModelLabel = 'Demandes d\'adhésion';
    protected static ?string $navigationLabel = 'Demandes d\'adhésion';

    public static function getNavigationBadge(): ?string
    {
        $enAttente = static::getModel()::where('statut', 'en_attente')->count();
        return $enAttente > 0 ? (string) $enAttente : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Société')
                    ->schema([
                        Forms\Components\TextInput::make('enseigne')->disabled(),
                        Forms\Components\TextInput::make('raison_sociale')->disabled(),
                        Forms\Components\TextInput::make('siret')->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Adresse')
                    ->schema([
                        Forms\Components\TextInput::make('adresse')->disabled(),
                        Forms\Components\TextInput::make('code_postal')->disabled(),
                        Forms\Components\TextInput::make('commune')->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Adresse de livraison')
                    ->schema([
                        Forms\Components\TextInput::make('adresse_livraison')->disabled(),
                        Forms\Components\TextInput::make('code_postal_livraison')->disabled(),
                        Forms\Components\TextInput::make('commune_livraison')->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Contact')
                    ->schema([
                        Forms\Components\TextInput::make('nom_contact')->disabled(),
                        Forms\Components\TextInput::make('email')->disabled(),
                        Forms\Components\TextInput::make('telephone')->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Traitement')
                    ->schema([
                        Forms\Components\TextInput::make('statut')
                            ->disabled()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'en_attente' => 'En attente',
                                'acceptee' => 'Acceptée',
                                'refusee' => 'Refusée',
                                default => $state,
                            }),
                        Forms\Components\Textarea::make('motif_refus')
                            ->disabled()
                            ->visible(fn ($record) => $record && $record->statut === 'refusee')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('enseigne')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nom_contact')
                    ->label('Contact')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telephone'),
                Tables\Columns\TextColumn::make('statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'en_attente' => 'warning',
                        'acceptee' => 'success',
                        'refusee' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en_attente' => 'En attente',
                        'acceptee' => 'Acceptée',
                        'refusee' => 'Refusée',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reçue le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('statut')
                    ->options([
                        'en_attente' => 'En attente',
                        'acceptee' => 'Acceptée',
                        'refusee' => 'Refusée',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('accepter')
                    ->label('Accepter')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (DemandeAdhesion $record) => $record->statut === 'en_attente')
                    ->requiresConfirmation()
                    ->modalHeading('Accepter cette demande ?')
                    ->modalDescription('Un compte Société, Agence et Utilisateur sera créé automatiquement, avec un mot de passe temporaire à transmettre au client.')
                    ->action(function (DemandeAdhesion $record) {
                        $resultat = $record->accepter();

                        Notification::make()
                            ->title('Demande acceptée')
                            ->body("Compte créé pour {$record->email}. Mot de passe temporaire : {$resultat['motDePasseTemporaire']} — à transmettre au client par un canal sécurisé (pas d'envoi automatique pour l'instant).")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\Action::make('refuser')
                    ->label('Refuser')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (DemandeAdhesion $record) => $record->statut === 'en_attente')
                    ->form([
                        Forms\Components\Textarea::make('motif')
                            ->label('Motif du refus (optionnel)')
                            ->rows(3),
                    ])
                    ->action(function (DemandeAdhesion $record, array $data) {
                        $record->refuser($data['motif'] ?? null);

                        Notification::make()
                            ->title('Demande refusée')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemandeAdhesions::route('/'),
            'view' => Pages\ViewDemandeAdhesion::route('/{record}'),
        ];
    }
}
