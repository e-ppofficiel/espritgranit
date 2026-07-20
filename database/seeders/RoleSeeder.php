<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin_esprit_granit', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'gestionnaire', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'responsable_societe', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'utilisateur_agence', 'guard_name' => 'web']);
    }
}
