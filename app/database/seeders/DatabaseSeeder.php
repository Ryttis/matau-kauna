<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            UsersSeeder::class,
            RolesSeeder::class,
            ClassifierSeeder::class,
            InstitutionSeeder::class,
            ProblemSeeder::class,
            ReporterStatisticsDemoSeeder::class,
        ]);
    }
}
