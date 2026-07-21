<?php

namespace App\Filament\Pages;

use App\Models\ParametreTarif;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GestionTarifs extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.gestion-tarifs';

    protected static ?string $navigationLabel = 'Paramètres tarifs';

    protected static ?string $title = 'Paramètres de tarification';

    public ?array $data = [];

    public function mount(): void
    {
        $parametre = ParametreTarif::courant();

        $this->form->fill([
            'poids_conteneur_kg' => $parametre->poids_conteneur_kg,
            'cout_transport_conteneur' => $parametre->cout_transport_conteneur,
            'cout_transit_conteneur' => $parametre->cout_transit_conteneur,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Paramètres globaux de calcul')
                    ->description('Ces valeurs servent à calculer automatiquement le prix de vente départ de chaque monument, selon son poids.')
                    ->schema([
                        Forms\Components\TextInput::make('poids_conteneur_kg')
                            ->label('Poids du conteneur (kg)')
                            ->numeric()
                            ->required()
                            ->default(20000),
                        Forms\Components\TextInput::make('cout_transport_conteneur')
                            ->label('Coût transport par conteneur (€)')
                            ->helperText('Variable selon la saison, le prix du Brent, l\'offre et la demande — à mettre à jour régulièrement.')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                        Forms\Components\TextInput::make('cout_transit_conteneur')
                            ->label('Coût transit par conteneur (€)')
                            ->helperText('Variable, même logique que le transport.')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $parametre = ParametreTarif::courant();
        $parametre->update($data);

        Notification::make()
            ->title('Paramètres enregistrés')
            ->success()
            ->send();
    }
}
