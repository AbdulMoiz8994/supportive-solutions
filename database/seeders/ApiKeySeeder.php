<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ApiKeySeeder extends Seeder
{
    public function run(): void
    {
        $keys = [
            ['name' => 'Development Integration', 'key' => 'api_dev_'.Str::random(32), 'active' => true, 'last_used_at' => now()->subDays(3)],
            ['name' => 'Mobile App (Staging)', 'key' => 'api_stg_'.Str::random(32), 'active' => true, 'last_used_at' => now()->subWeek()],
            ['name' => 'Legacy Webhook (Inactive)', 'key' => 'api_legacy_'.Str::random(32), 'active' => false, 'last_used_at' => now()->subMonths(2)],
        ];

        foreach ($keys as $keyData) {
            ApiKey::updateOrCreate(
                ['name' => $keyData['name']],
                $keyData
            );
        }

        $this->command?->info('API keys seeded.');
    }
}
