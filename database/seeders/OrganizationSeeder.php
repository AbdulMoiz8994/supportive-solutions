<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $this->organization();

        $this->command?->info('Organization seeded.');
    }
}
