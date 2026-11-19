<?php

namespace App\Filament\Concerns;

use App\Models\ConfigurationPartagee;
use Livewire\Attributes\Renderless;

/**
 * Enregistrement / relecture de l'état du configurateur 3D, commun aux pages admin et agence.
 * La page qui l'utilise déclare `#[Locked] public ?array $etatInitial = null;` et appelle
 * `$this->chargerConfigurationPartagee()` à la fin de mount().
 */
trait GereConfigurationPartagee
{
    /** Relit l'état désigné par ?c=… (revalidé côté serveur) ; un code absent ou invalide est simplement ignoré. */
    protected function chargerConfigurationPartagee(): void
    {
        try {
            $this->etatInitial = ConfigurationPartagee::lire($this->monument, request()->query('c'));
        } catch (\Throwable $e) {
            // Table absente (migration pas encore passée) ou autre souci : le configurateur s'ouvre normalement.
            report($e);
            $this->etatInitial = null;
        }
    }

    /**
     * Appelé par le configurateur après un changement. Renvoie le code court à placer dans l'adresse (?c=…),
     * ou null si l'enregistrement est impossible (le configurateur reste alors utilisable sans lien).
     */
    #[Renderless]
    public function enregistrerConfiguration($etat = null): ?string
    {
        try {
            return ConfigurationPartagee::enregistrer(
                $this->monument,
                $etat,
                \Filament\Facades\Filament::auth()->id()
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
