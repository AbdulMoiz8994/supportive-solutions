<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => $defaultPassword,
                'role' => 'Super Administrator',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Manager',
                'password' => $defaultPassword,
                'role' => 'Administrator',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        User::firstOrCreate(
            ['email' => 'operations@example.com'],
            [
                'name' => 'Office Operations',
                'password' => $defaultPassword,
                'role' => 'Operations Staff',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        User::firstOrCreate(
            ['email' => 'employee@example.com'],
            [
                'name' => 'Test Employee',
                'password' => $defaultPassword,
                'role' => 'Employee',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );
    }
}
