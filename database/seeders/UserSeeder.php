<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use SeedsOrganizationContext;

    /**
     * Seeds demo accounts for local/staging only.
     *
     * Production: change every seeded password immediately after first deploy, or
     * deactivate/delete demo users. See SERVER_SETUP_GUIDE.md § Production Security.
     */
    public function run(): void
    {
        $org = $this->organization();
        $location = $this->location();

        User::updateOrCreate(
            ['email' => 'super@beydountech.com'],
            [
                'name' => 'System Super Admin',
                'password' => Hash::make('super123'),
                'role' => User::ROLE_SUPER_ADMIN,
                'organization_id' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@beydountech.com'],
            [
                'name' => 'Agency Admin',
                'password' => Hash::make('admin123'),
                'role' => User::ROLE_ADMIN,
                'organization_id' => $org->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@beydountech.com'],
            [
                'name' => 'Office Coordinator',
                'password' => Hash::make('staff123'),
                'role' => User::ROLE_STAFF,
                'organization_id' => $org->id,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'caregiver@beydountech.com'],
            [
                'name' => 'Test Caregiver',
                'password' => Hash::make('care123'),
                'role' => User::ROLE_EMPLOYEE,
                'organization_id' => $org->id,
                'is_active' => true,
            ]
        );

        // Second caregiver login — lets the mobile team test two-sided chat and
        // click-to-call from two accounts. Linked to an employee in EmployeeSeeder.
        User::updateOrCreate(
            ['email' => 'caregiver2@beydountech.com'],
            [
                'name' => 'Second Caregiver',
                'password' => Hash::make('care123'),
                'role' => User::ROLE_EMPLOYEE,
                'organization_id' => $org->id,
                'is_active' => true,
            ]
        );

        User::whereNotNull('id')->each(function (User $user) use ($location) {
            if ($user->role !== User::ROLE_SUPER_ADMIN) {
                $user->locations()->syncWithoutDetaching([$location->id]);
            }
        });

        $this->command?->info('Users seeded (demo credentials — not for production use).');
    }
}
