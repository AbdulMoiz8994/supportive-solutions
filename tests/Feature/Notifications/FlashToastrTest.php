<?php

use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('flash toastr partial renders nothing without messages', function () {
    $html = View::make('components.flash-toastr', [
        'errors' => new ViewErrorBag(),
    ])->render();

    expect($html)->toBe('');
});

test('flash toastr partial renders session flash payloads', function () {
    session()->flash('success', 'Saved successfully.');
    session()->flash('warning', 'Please review.');

    $html = View::make('components.flash-toastr', [
        'errors' => new ViewErrorBag(),
    ])->render();

    expect($html)->toContain('flash-messages-data')
        ->and($html)->toContain('Saved successfully.')
        ->and($html)->toContain('Please review.')
        ->and($html)->toContain('"type":"success"')
        ->and($html)->toContain('"type":"warning"');
});

test('flash toastr partial renders validation errors', function () {
    $bag = new ViewErrorBag();
    $bag->put('default', new MessageBag([
        'email' => ['The email field is required.'],
        'name' => ['The name field is required.'],
    ]));

    $html = View::make('components.flash-toastr', [
        'errors' => $bag,
    ])->render();

    expect($html)->toContain('flash-messages-data')
        ->and($html)->toContain('The email field is required.')
        ->and($html)->toContain('The name field is required.');
});

test('authenticated page includes flash toastr payload after redirect', function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

    $user = $this->createUser(\App\Models\User::ROLE_SUPER_ADMIN);

    $this->actingAsWithTwoFactor($user)
        ->withSession(['success' => 'Settings updated.'])
        ->get(route('settings.global'))
        ->assertOk()
        ->assertSee('flash-messages-data', false)
        ->assertSee('Settings updated.', false);
});
