<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

/**
 * Seed set for Playwright E2E tests against a real database.
 * Run: php artisan migrate:fresh --seed --seeder=E2eTestSeeder
 */
class E2eTestSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            LookupTableSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            DashboardDemoSeeder::class,
            CaregiverModuleSeeder::class,
            DirectoryBaselineSeeder::class,
            IntakeSeeder::class,
            RequestTemplateSeeder::class,
            DashboardModulesSeeder::class,
            PayrollSeeder::class,
            BillingClaimAuditSeeder::class,
            MessageSeeder::class,
        ]);

        $this->command?->info('E2E test database seeded.');
    }
}
