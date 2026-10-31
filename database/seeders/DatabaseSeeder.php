<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            CoreDataSeeder::class,
        ]);

        if (app()->environment('local') || env('SEED_DEMO', false)) {
            $this->call([
                DemoDataSeeder::class,
                DemoPortalSeeder::class,
            ]);
        }
    }
}
