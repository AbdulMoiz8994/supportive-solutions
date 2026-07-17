<?php

use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('admin can open staff create form from staff tab', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.index', ['tab' => 'staff']))
        ->assertOk()
        ->assertSee(route('staff.create'), false)
        ->assertSee('Add Staff');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('staff.create'))
        ->assertOk()
        ->assertSee('New Staff Enrollment');
});
