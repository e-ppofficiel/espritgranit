<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CommandeResource;
use App\Filament\Resources\DemandeAdhesionResource;
use App\Filament\Resources\DevisResource;
use App\Models\Commande;
use App\Models\DemandeAdhesion;
use App\Models\Devis;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TableauDeBordStats extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $demandesEnAttente = DemandeAdhesion::where('statut', 'en_attente')->count();
        $devisRecus = Devis::count();
        $devisEnCours = Devis::whereIn('statut', ['brouillon', 'envoye', 'en_attente'])->count();
        $commandesEnCours = Commande::whereNotIn('statut', ['livree', 'annulee', 'archivee'])->count();

        $urlDemandesEnAttente = DemandeAdhesionResource::getUrl('index') . '?' . http_build_query([
            'tableFilters' => ['statut' => ['value' => 'en_attente']],
        ]);

        return [
            Stat::make('Demandes d\'adhésion en attente', $demandesEnAttente)
                ->description('Cliquer pour voir la liste')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color($demandesEnAttente > 0 ? 'warning' : 'success')
                ->url($urlDemandesEnAttente),

            Stat::make('Devis reçus', $devisRecus)
                ->description('Total, tous statuts')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('gray')
                ->url(DevisResource::getUrl('index')),

            Stat::make('Devis en cours', $devisEnCours)
                ->description('Brouillon, envoyé ou en attente')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('warning')
                ->url(DevisResource::getUrl('index')),

            Stat::make('Commandes en cours', $commandesEnCours)
                ->description('Hors livrées, annulées, archivées')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('info')
                ->url(CommandeResource::getUrl('index')),
        ];
    }
}
