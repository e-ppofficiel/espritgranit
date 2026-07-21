<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartementSeeder::class,
            TestDataSeeder::class,
            ParametreTarifSeeder::class,
        ]);

        \App\Models\User::firstOrCreate(
            ['email' => 'contact@e-ppo.fr'],
            [
                'name' => 'hippolyte',
                'password' => bcrypt('Granit@2026/*Cd'),
            ]
        )->assignRole('super_admin');
    }
}
