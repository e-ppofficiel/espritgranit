<?php

namespace App\Filament\Agence\Resources\DevisResource\Pages;

use App\Filament\Agence\Resources\DevisResource;
use App\Filament\Resources\DevisResource\Concerns\PreparesVisuelConfigurateur;
use App\Models\Agence;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDevis extends CreateRecord
{
    use PreparesVisuelConfigurateur;

    protected static string $resource = DevisResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        $agenceId = $user->agence_id;
        $societeId = $user->societe_id;

        // Un utilisateur d'agence peut n'avoir que agence_id renseigné sur son compte
        // (le cas du compte "Test Agence") — on retrouve alors la société via l'agence
        // elle-même plutôt que de supposer que societe_id y est toujours dupliqué.
        if (! $societeId && $agenceId) {
            $societeId = Agence::find($agenceId)?->societe_id;
        }

        $data['societe_id'] = $societeId;
        $data['agence_id'] = $agenceId;
        $data['origine'] = 'agence';

        if (empty($data['user_id'])) {
            $data['user_id'] = $user->id;
        }

        return $data;
    }
}