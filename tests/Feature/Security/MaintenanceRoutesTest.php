<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('run migrate route is not registered', function () {
    $this->get('/run-migrate')->assertNotFound();
});

test('run seed route is not registered', function () {
    $this->get('/run-seed')->assertNotFound();
});
