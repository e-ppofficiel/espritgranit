<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $role = $this->data['role'] ?? null;

        if ($role) {
            // Guard explicite : les rôles existants sont tous enregistrés sur le guard
            // "web" en base, alors que l'authentification passe désormais par les guards
            // "admin"/"agence" séparés — sans précision explicite, syncRoles() ne
            // retrouve pas le bon rôle et échoue.
            $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $this->record->syncRoles([$roleModel]);
        }
    }
}
