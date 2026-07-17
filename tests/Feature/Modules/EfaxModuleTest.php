<?php

use App\Models\Communication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedModuleBasics();
    Storage::fake('local');
});

test('guest cannot access efax composer', function () {
    $this->get(route('efax.compose'))->assertRedirect(route('signin'));
    $this->post(route('efax.send'), [])->assertRedirect(route('signin'));
});

test('efax compose redirects to communications efax modal', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('efax.compose'))
        ->assertRedirect(route('communications.index', ['compose' => 'efax']));
});

test('communications index opens efax modal from compose query', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('communications.index', ['compose' => 'efax']))
        ->assertOk()
        ->assertSee('New eFax')
        ->assertSee('x-init="init()"', false);
});

test('legacy efax post validates required recipient and attachment', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('efax.send'), ['to' => ''])
        ->assertSessionHasErrors(['to']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('efax.send'), [
            'to' => '313-555-0100',
        ])
        ->assertSessionHasErrors(['attachment']);
});

test('legacy efax post sends through communications pipeline when configured', function () {
    config(['communications.channels.fax' => 'fake']);
    $admin = $this->createUser(User::ROLE_ADMIN);
    stubRingCentralCredentials();
    $file = UploadedFile::fake()->create('referral.pdf', 50, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('efax.send'), [
            'to' => '313-555-0100',
            'subject' => 'PA Letter',
            'message' => 'Please see attached.',
            'attachment' => $file,
        ])
        ->assertRedirect(route('communications.index'))
        ->assertSessionHas('success');

    expect(Communication::withoutGlobalScopes()->count())->toBe(1);
});

test('legacy efax post rejects oversized attachment', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);
    $file = UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('efax.send'), [
            'to' => '313-555-0100',
            'attachment' => $file,
        ])
        ->assertSessionHasErrors(['attachment']);
});

test('dashboard send efax link targets unified communications composer', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('communications.index', ['compose' => 'efax']), false)
        ->assertDontSee(route('messages.index'), false);
});
