<?php

use App\Models\RequestTemplate;
use App\Models\User;

beforeEach(fn () => seedModuleBasics());

test('guest cannot access request templates', function () {
    $this->get(route('request-templates.index'))->assertRedirect(route('signin'));
});

test('admin can view request templates index without parse errors', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('request-templates.index'))
        ->assertOk()
        ->assertSee('Request Templates');
});

test('admin can create request template', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('request-templates.store'), [
            'name' => 'PA Renewal',
            'delivery_method' => 'email',
            'recipient_type' => 'primary_care_physician',
            'subject' => 'Authorization renewal for {{ client_name }}',
            'body' => 'Please review the attached authorization.',
            'is_active' => '1',
        ])
        ->assertRedirect(route('request-templates.index'));

    $this->assertDatabaseHas('request_templates', [
        'name' => 'PA Renewal',
        'organization_id' => $org->id,
    ]);
});

test('request template store validates required fields', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('request-templates.store'), [])
        ->assertSessionHasErrors(['name', 'delivery_method', 'recipient_type']);
});

test('admin can update and toggle request template', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $template = RequestTemplate::create([
        'organization_id' => $org->id,
        'name' => 'Original',
        'delivery_method' => 'email',
        'recipient_type' => 'pcp',
        'subject' => 'Subject',
        'body' => 'Body',
        'is_active' => true,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('request-templates.update', $template->id), [
            'name' => 'Updated Template',
            'delivery_method' => 'fax',
            'recipient_type' => 'case_coordinator',
            'subject' => 'New subject',
            'body' => 'New body',
            'is_active' => '1',
        ])
        ->assertRedirect(route('request-templates.index'));

    expect($template->fresh()->name)->toBe('Updated Template');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('request-templates.toggle', $template->id))
        ->assertRedirect();

    expect((bool) $template->fresh()->is_active)->toBeFalse();
});
