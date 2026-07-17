<?php

use App\Mail\OTPMail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Mail::fake();
});

test('guest deep link to caregivers stores intended url and redirects to signin', function () {
    $this->get('/caregivers')
        ->assertRedirect(route('signin'));

    expect(session('url.intended'))->toContain('/caregivers');
});

test('authenticated guest flow login and two factor lands on deep linked caregivers page', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, [
        'organization_id' => $org->id,
        'email' => 'deeplink@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->get('/caregivers')->assertRedirect(route('signin'));

    $otp = '482910';
    $admin->update([
        'two_factor_code' => Hash::make($otp),
        'two_factor_expires_at' => now()->addMinutes(10),
    ]);

    $this->post(route('signin.store'), [
        'email' => 'deeplink@example.com',
        'password' => 'password',
    ])->assertRedirect(route('two-factor.choice'));

    $this->post(route('two-factor.verify.post'), ['otp' => $otp])
        ->assertRedirect('/caregivers');
});

test('profile page remains accessible for authenticated user without logging out', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee($admin->name);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('caregivers'))
        ->assertOk();
});
