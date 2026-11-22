<?php

namespace App\Providers;

use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\AssociateAction;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Actions\SelectAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurerTableauxFilament();
    }

    /**
     * Réglages communs à TOUS les tableaux Filament (panels admin et agence), pour qu'ils tiennent
     * dans la largeur de l'écran sans barre de défilement horizontale.
     *
     * Ces configurations s'exécutent AVANT le setUp() de chaque action / colonne : tout ce qu'une
     * ressource précise elle-même (->icon(), ->tooltip(), ->button(), ->wrap(false)…) l'emporte donc.
     */
    private function configurerTableauxFilament(): void
    {
        // 1. Actions de ligne (Voir, Modifier, Supprimer, Accepter, Configurer…) : picto seul,
        //    le libellé s'affiche en infobulle au survol.
        Action::configureUsing(function (Action $action): void {
            // Boutons d'en-tête de tableau (« Nouveau… », « Associer… », exports) : on garde le libellé.
            if (
                $action instanceof CreateAction
                || $action instanceof AttachAction
                || $action instanceof AssociateAction
                || $action instanceof ExportAction
                || $action instanceof ImportAction
                || $action instanceof SelectAction
            ) {
                return;
            }

            $action
                // Icône de secours : écrasée par celle de l'action native (setUp) ou celle de la ressource.
                ->icon('heroicon-o-ellipsis-horizontal-circle')
                ->iconButton()
                ->tooltip(fn (Action $action): string => strip_tags((string) $action->getLabel()));
        });

        // 2. Colonnes de texte : le contenu peut passer à la ligne au lieu d'élargir le tableau.
        TextColumn::configureUsing(fn (TextColumn $column) => $column->wrap());
    }
}
