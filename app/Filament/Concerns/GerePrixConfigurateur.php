<?php

namespace App\Filament\Concerns;

use App\Models\ConfigurationPartagee;
use App\Services\ConfigurateurPrix;
use Filament\Facades\Filament;
use Livewire\Attributes\Renderless;

/**
 * Prix en direct du configurateur 3D, commun aux pages admin et agence.
 * La page qui l'utilise déclare simplement `use GerePrixConfigurateur;` (elle possède déjà `public Monument $monument`).
 */
trait GerePrixConfigurateur
{
    /**
     * Appelé par le configurateur après un changement (granit, options, semelle, département, agence).
     * L'état reçu est revalidé comme pour le lien partagé ; le coefficient est toujours déterminé côté serveur
     * (celui de l'agence de l'utilisateur ; un administrateur peut en plus choisir l'agence).
     * Ne renvoie que des montants finaux. En cas de problème : ['ok' => false] (le configurateur reste utilisable).
     */
    #[Renderless]
    public function calculerPrixConfiguration($etat = null, $departementId = null, $agenceId = null): array
    {
        try {
            $utilisateur = Filament::auth()->user();
            $admin = ConfigurateurPrix::estAdmin($utilisateur);
            $etat = ConfigurationPartagee::normaliser($this->monument, $etat);

            $departement = is_numeric($departementId) && (int) $departementId > 0 ? (int) $departementId : null;
            $coefficient = ConfigurateurPrix::coefficientPour($utilisateur, $agenceId);

            return ['ok' => true] + ConfigurateurPrix::calculer($this->monument, $etat, $departement, $coefficient, $admin);
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false];
        }
    }
}
