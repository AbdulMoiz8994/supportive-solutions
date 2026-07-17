<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            LookupTableSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            DummyDataSeeder::class,
            DashboardDemoSeeder::class,
            CaregiverModuleSeeder::class,
            PayrollSeeder::class,
            BillingClaimAuditSeeder::class,
            DashboardModulesSeeder::class,
        ]);
    }
}
