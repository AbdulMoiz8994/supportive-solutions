<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Seeds demo data for all operational modules.
     */
    public function run(): void
    {
        $this->call([
            DirectoryBaselineSeeder::class,
            ClientSeeder::class,
            EmployeeSeeder::class,
            IntakeSeeder::class,
            CareDetailSeeder::class,
            ClientEmployeeSeeder::class,
            ScheduleSeeder::class,
            BillingSeeder::class,
            ContactSeeder::class,
            MessageSeeder::class,
            MobileChatSeeder::class,
            DocumentSeeder::class,
            ClientRequestSeeder::class,
            RequestTemplateSeeder::class,
            ApiKeySeeder::class,
            ActivityLogSeeder::class,
        ]);

        $this->command?->info('All module demo data seeded.');
    }
}
