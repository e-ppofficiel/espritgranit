<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Agence;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'utilisateur';
    protected static ?string $pluralModelLabel = 'utilisateurs';
    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static array $roles = [
        'super_admin' => 'Super administrateur',
        'admin_esprit_granit' => 'Administrateur Esprit Granit',
        'gestionnaire' => 'Gestionnaire',
        'responsable_societe' => 'Responsable de société',
        'utilisateur_agence' => 'Utilisateur agence',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->helperText(fn (string $context): string => $context === 'edit'
                        ? 'Laisser vide pour ne pas modifier le mot de passe actuel.'
                        : ''),

                Forms\Components\Select::make('role')
                    ->label('Rôle')
                    ->options(static::$roles)
                    ->required()
                    ->native(false)
                    ->helperText('Détermine à quel panel (admin ou agence) ce compte peut se connecter.'),

                Forms\Components\Select::make('societe_id')
                    ->label('Société')
                    ->relationship('societe', 'enseigne')
                    ->searchable()
                    ->preload()
                    ->live(),

                Forms\Components\Select::make('agence_id')
                    ->label('Agence')
                    ->options(function (Get $get) {
                        $societeId = $get('societe_id');
                        if (! $societeId) {
                            return [];
                        }
                        return Agence::where('societe_id', $societeId)->pluck('nom', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->helperText('Laisser vide pour un "responsable de société" qui doit voir toutes les agences.'),

                Forms\Components\Toggle::make('actif')
                    ->label('Compte actif')
                    ->default(true)
                    ->helperText('Un compte désactivé ne peut plus se connecter, sans avoir à le supprimer.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rôle')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::$roles[$state] ?? $state),
                Tables\Columns\TextColumn::make('societe.enseigne')
                    ->label('Société')
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('agence.nom')
                    ->label('Agence')
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('actif')
                    ->label('Actif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->options(static::$roles),
                Tables\Filters\SelectFilter::make('societe_id')
                    ->relationship('societe', 'enseigne')
                    ->label('Société')
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('actif')
                    ->label('Statut')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Désactivés uniquement'),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reinitialiserMotDePasse')
                    ->label('Réinit. mot de passe')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Réinitialiser le mot de passe ?')
                    ->modalDescription('Un nouveau mot de passe temporaire sera généré. Pense à le transmettre à l\'utilisateur par un canal sécurisé.')
                    ->action(function (User $record) {
                        $nouveauMotDePasse = Str::password(12);
                        $record->update(['password' => Hash::make($nouveauMotDePasse)]);

                        Notification::make()
                            ->title('Mot de passe réinitialisé')
                            ->body("Nouveau mot de passe pour {$record->email} : {$nouveauMotDePasse}")
                            ->warning()
                            ->persistent()
                            ->send();
                    }),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
