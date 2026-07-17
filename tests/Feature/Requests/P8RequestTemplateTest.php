<?php

use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Contact;
use App\Models\RequestTemplate;
use App\Models\User;
use App\Services\ClientRequestDeliveryService;
use App\Services\RequestTemplateVariableService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createRequestTemplate(int $organizationId, array $attributes = []): RequestTemplate
{
    return RequestTemplate::withoutGlobalScopes()->create(array_merge([
        'organization_id' => $organizationId,
        'name' => 'Test Template',
        'delivery_method' => RequestTemplate::DELIVERY_EMAIL,
        'recipient_type' => RequestTemplate::RECIPIENT_CUSTOM,
        'default_recipient_email' => 'recipient@example.com',
        'subject' => 'Subject for {{ client_name }}',
        'body' => 'Hello {{ client_name }}',
        'is_active' => true,
    ], $attributes));
}

function attachCaseCoordinator(Client $client, int $organizationId): Contact
{
    $contact = Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $organizationId,
        'name' => 'Jane Coordinator',
        'type' => 'Case Coordinator',
        'email' => 'coordinator@example.com',
        'fax' => '5551234567',
        'is_active' => true,
    ]);

    $client->contacts()->attach($contact->id, ['role' => 'Case Coordinator']);

    return $contact;
}

test('admin can create request template', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('request-templates.store'), [
            'name' => 'Authorization Request',
            'delivery_method' => RequestTemplate::DELIVERY_EMAIL,
            'recipient_type' => RequestTemplate::RECIPIENT_CASE_COORDINATOR,
            'subject' => 'Auth for {{ client_name }}',
            'body' => 'Please send authorization for {{ client_name }}.',
            'is_active' => 1,
        ])
        ->assertRedirect(route('request-templates.index'));

    expect(RequestTemplate::withoutGlobalScopes()->where('name', 'Authorization Request')->exists())->toBeTrue();
});

test('email template requires subject on create', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('request-templates.store'), [
            'name' => 'Missing Subject',
            'delivery_method' => RequestTemplate::DELIVERY_EMAIL,
            'recipient_type' => RequestTemplate::RECIPIENT_CUSTOM,
            'body' => 'Body only',
        ])
        ->assertSessionHasErrors(['subject']);
});

test('admin can update and deactivate request template', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $template = createRequestTemplate($org->id, ['name' => 'Original Name']);

    $this->actingAsWithTwoFactor($admin)
        ->put(route('request-templates.update', $template->id), [
            'name' => 'Updated Name',
            'delivery_method' => RequestTemplate::DELIVERY_MANUAL,
            'recipient_type' => RequestTemplate::RECIPIENT_OTHER,
            'body' => 'Updated body content',
            'is_active' => 1,
        ])
        ->assertRedirect(route('request-templates.index'));

    $template->refresh();
    expect($template->name)->toBe('Updated Name')
        ->and($template->delivery_method)->toBe(RequestTemplate::DELIVERY_MANUAL);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('request-templates.toggle', $template->id))
        ->assertRedirect(route('request-templates.index'));

    expect($template->fresh()->is_active)->toBeFalse();
});

test('operations staff cannot manage request templates', function () {
    $org = $this->createOrganization();
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($staff)
        ->get(route('request-templates.index'))
        ->assertForbidden();
});

test('employee cannot send client requests', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $template = createRequestTemplate($org->id);
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($employee)
        ->post(route('requests.store', $client->id), [
            'request_template_id' => $template->id,
        ])
        ->assertForbidden();
});

test('operations staff with permission can send client request', function () {
    Mail::fake();

    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['first_name' => 'Alex', 'last_name' => 'Rivera', 'member_id' => 'M12345']);
    $template = createRequestTemplate($org->id, [
        'name' => 'Staff Email Template',
        'recipient_type' => RequestTemplate::RECIPIENT_CUSTOM,
        'default_recipient_email' => 'staff-send@example.com',
        'subject' => 'Request for {{ client_name }}',
        'body' => 'Member {{ member_id }}',
    ]);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($staff)
        ->post(route('requests.store', $client->id), [
            'request_template_id' => $template->id,
        ])
        ->assertRedirect();

    $log = ClientRequest::withoutGlobalScopes()->where('client_id', $client->id)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(ClientRequest::STATUS_SENT)
        ->and($log->subject)->toBe('Request for Alex Rivera')
        ->and($log->body_snapshot)->toBe('Member M12345');

    Mail::assertSent(\App\Mail\ClientRequestMail::class);
});

test('cross organization template access is blocked on send', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $foreignTemplate = createRequestTemplate($orgB->id);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $this->actingAsWithTwoFactor($adminA)
        ->post(route('requests.store', $client->id), [
            'request_template_id' => $foreignTemplate->id,
        ])
        ->assertSessionHasErrors(['request_template_id']);
});

test('admin cannot update template from another organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $template = createRequestTemplate($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $this->actingAsWithTwoFactor($adminB)
        ->put(route('request-templates.update', $template->id), [
            'name' => 'Blocked Update',
            'delivery_method' => RequestTemplate::DELIVERY_EMAIL,
            'recipient_type' => RequestTemplate::RECIPIENT_CUSTOM,
            'subject' => 'Subject',
            'body' => 'Body',
        ])
        ->assertForbidden();
});

test('fax template is logged as manual without fax provider', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    attachCaseCoordinator($client, $org->id);
    $template = createRequestTemplate($org->id, [
        'delivery_method' => RequestTemplate::DELIVERY_FAX,
        'recipient_type' => RequestTemplate::RECIPIENT_CASE_COORDINATOR,
        'default_recipient_email' => null,
        'subject' => null,
        'body' => 'Fax body for {{ client_name }}',
    ]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('requests.store', $client->id), [
            'request_template_id' => $template->id,
        ])
        ->assertRedirect();

    $log = ClientRequest::withoutGlobalScopes()->where('client_id', $client->id)->latest('id')->first();

    expect($log->status)->toBe(ClientRequest::STATUS_MANUAL)
        ->and($log->recipient_fax)->toBe('5551234567')
        ->and($log->notes)->toContain('Fax delivery pending provider integration');
});

test('missing recipient data returns validation error', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $template = createRequestTemplate($org->id, [
        'delivery_method' => RequestTemplate::DELIVERY_EMAIL,
        'recipient_type' => RequestTemplate::RECIPIENT_CASE_COORDINATOR,
        'default_recipient_email' => null,
        'subject' => 'Need docs',
        'body' => 'Please send docs',
    ]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->from(route('clients.show', $client->id))
        ->post(route('requests.store', $client->id), [
            'request_template_id' => $template->id,
        ])
        ->assertRedirect(route('clients.show', $client->id))
        ->assertSessionHasErrors(['recipient_email']);
});

test('client profile renders send request and active templates', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $active = createRequestTemplate($org->id, ['name' => 'Active POC Request', 'is_active' => true]);
    createRequestTemplate($org->id, ['name' => 'Inactive Template', 'is_active' => false]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', $client->id));

    $response->assertOk()
        ->assertSee('Send Request')
        ->assertSee('Active POC Request')
        ->assertDontSee('Inactive Template');
});

test('template variables render safely without executing blade', function () {
    $org = $this->createOrganization(['name' => 'Sunrise Home Care']);
    $client = $this->createClient($org->id, [
        'first_name' => 'Jamie',
        'last_name' => 'Lee',
        'member_id' => 'ABC999',
        'dob' => '1990-01-15',
    ]);
    $client->setRelation('organization', $org);
    attachCaseCoordinator($client, $org->id);

    $service = app(RequestTemplateVariableService::class);
    $rendered = $service->render(
        'Client {{ client_name }} / {{ client_first_name }} {{ client_last_name }} / {{ member_id }} / {{ dob }} / CC: {{ case_coordinator_name }} / {{ agency_name }} / {{ unknown_key }}',
        $client
    );

    expect($rendered)
        ->toContain('Jamie Lee')
        ->toContain('Jamie')
        ->toContain('ABC999')
        ->toContain('1990-01-15')
        ->toContain('Jane Coordinator')
        ->toContain('Sunrise Home Care')
        ->toContain('{{ unknown_key }}');
});

test('delivery service resolves primary care physician recipient', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $pcp = Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $org->id,
        'name' => 'Dr. Patel',
        'type' => Contact::TYPE_PCP,
        'email' => 'pcp@example.com',
        'is_active' => true,
    ]);
    $client->contacts()->attach($pcp->id, ['role' => 'Primary Care Physician']);
    $client->load('contacts');

    $template = createRequestTemplate($org->id, [
        'recipient_type' => RequestTemplate::RECIPIENT_PCP,
        'subject' => 'PCP request for {{ pcp_name }}',
        'body' => 'Please review {{ client_name }}',
    ]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Mail::fake();

    $log = app(ClientRequestDeliveryService::class)->send($client, $template, $admin);

    expect($log->recipient_email)->toBe('pcp@example.com')
        ->and($log->subject)->toBe('PCP request for Dr. Patel')
        ->and($log->status)->toBe(ClientRequest::STATUS_SENT);
});
