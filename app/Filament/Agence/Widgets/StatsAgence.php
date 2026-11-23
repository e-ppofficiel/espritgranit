<?php

namespace App\Filament\Agence\Widgets;

use App\Filament\Agence\Resources\CommandeResource;
use App\Filament\Agence\Resources\DevisResource;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Tableau de bord agence : compteurs cliquables.
 *
 * Le périmètre (agence de l'utilisateur, à défaut sa société) est celui des ressources
 * « Mes devis » / « Mes commandes » : on réutilise leur getEloquentQuery() pour ne jamais
 * dupliquer (ni contourner) la règle de scoping.
 */
class StatsAgence extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $devisEnCours = DevisResource::getEloquentQuery()
            ->whereIn('statut', ['brouillon', 'envoye', 'en_attente'])
            ->count();

        $devisAcceptes = DevisResource::getEloquentQuery()
            ->where('statut', 'accepte')
            ->count();

        $commandesEnCours = CommandeResource::getEloquentQuery()
            ->whereNotIn('statut', ['livree', 'annulee', 'archivee'])
            ->count();

        $commandesLivrees = CommandeResource::getEloquentQuery()
            ->where('statut', 'livree')
            ->where('date_arrivee_reelle', '>=', now()->subYear()->toDateString())
            ->count();

        return [
            Stat::make('Devis en cours', $devisEnCours)
                ->description('Brouillon, envoyé ou en attente')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('warning')
                ->url(DevisResource::getUrl('index')),

            Stat::make('Devis acceptés', $devisAcceptes)
                ->description('En attente de transformation en commande')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color($devisAcceptes > 0 ? 'success' : 'gray')
                ->url(DevisResource::getUrl('index') . '?' . http_build_query([
                    'tableFilters' => ['statut' => ['value' => 'accepte']],
                ])),

            Stat::make('Commandes en cours', $commandesEnCours)
                ->description('Hors livrées, annulées, archivées')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('info')
                ->url(CommandeResource::getUrl('index')),

            Stat::make('Commandes livrées', $commandesLivrees)
                ->description('Sur les 12 derniers mois')
                ->descriptionIcon('heroicon-m-arrow-right')
                ->color('gray')
                ->url(CommandeResource::getUrl('index') . '?' . http_build_query([
                    'tableFilters' => ['statut' => ['value' => 'livree']],
                ])),
        ];
    }
}
