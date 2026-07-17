<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the live dashboard for an admin', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    $admin = User::where('email', 'admin@beydountech.com')->firstOrFail();

    $this->actingAs($admin)
        ->withSession(['2fa_verified' => true])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Needs your approval', false)
        ->assertSee('AI fleet health', false)
        ->assertSee('Recent Activity', false);
});
