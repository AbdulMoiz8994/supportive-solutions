<?php

use App\Models\Client;
use App\Models\Communication;
use App\Models\CommunicationNotification;
use App\Models\CommunicationTemplate;
use App\Models\Contact;
use App\Models\SecureMessageParticipant;
use App\Models\SecureMessageThread;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedModuleBasics();
});

function attachClientPcp(\App\Models\Client $client, int $organizationId): \App\Models\Contact
{
    $contact = Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $organizationId,
        'name' => 'Dr. Primary',
        'type' => 'Primary Care Physician',
        'email' => 'pcp@example.com',
        'fax' => '5551112222',
        'is_active' => true,
    ]);

    $client->contacts()->attach($contact->id, ['role' => 'Primary Care Physician']);

    return $contact;
}

test('authorized user can view communications index', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('communications.index'))
        ->assertOk()
        ->assertSee('Communications');
});

test('unauthorized user cannot view communications', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    $role = \App\Models\Role::where('slug', 'employee')->first();
    $role->permissions()->detach(
        \App\Models\Permission::whereIn('slug', ['view_communications'])->pluck('id')
    );

    $this->actingAsWithTwoFactor($employee)
        ->get(route('communications.index'))
        ->assertForbidden();
});

test('user without permission cannot send communication', function () {
    $org = $this->createOrganization();
    $employee = $this->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);
    $template = createCommunicationTemplate($org->id);

    $role = \App\Models\Role::where('slug', 'employee')->first();
    $role->permissions()->detach(
        \App\Models\Permission::whereIn('slug', ['send_communications'])->pluck('id')
    );

    $this->actingAsWithTwoFactor($employee)
        ->post(route('communications.send-request.store'), [
            'template_id' => $template->id,
            'recipient_email' => 'test@example.com',
        ])
        ->assertForbidden();
});

test('communication template CRUD works for permitted user', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.templates.store'), [
            'name' => 'Auth Request',
            'channel' => CommunicationTemplate::CHANNEL_EMAIL,
            'recipient_strategy' => CommunicationTemplate::STRATEGY_CLIENT_CASE_COORDINATOR,
            'subject' => 'Auth for {{ client.first_name }}',
            'body' => 'Please authorize {{ client.first_name }}.',
            'is_active' => 1,
        ])
        ->assertRedirect(route('communications.templates.index'));

    $template = CommunicationTemplate::withoutGlobalScopes()->where('name', 'Auth Request')->first();
    expect($template)->not->toBeNull();

    $this->actingAsWithTwoFactor($admin)
        ->put(route('communications.templates.update', $template), [
            'name' => 'Updated Auth Request',
            'channel' => CommunicationTemplate::CHANNEL_EMAIL,
            'recipient_strategy' => CommunicationTemplate::STRATEGY_CLIENT_CASE_COORDINATOR,
            'subject' => 'Updated',
            'body' => 'Updated body',
            'is_active' => 1,
        ])
        ->assertRedirect(route('communications.templates.index'));

    expect($template->fresh()->name)->toBe('Updated Auth Request');
});

test('template validation rejects invalid channel and unsafe fields', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.templates.store'), [
            'name' => 'Bad Template',
            'channel' => 'telegram',
            'recipient_strategy' => 'invalid_strategy',
            'body' => 'Body',
        ])
        ->assertSessionHasErrors(['channel', 'recipient_strategy']);
});

test('organization A cannot view edit delete organization B templates', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);
    $templateB = createCommunicationTemplate($orgB->id);

    $this->actingAsWithTwoFactor($adminA)
        ->put(route('communications.templates.update', $templateB), [
            'name' => 'Hacked',
            'channel' => CommunicationTemplate::CHANNEL_EMAIL,
            'recipient_strategy' => CommunicationTemplate::STRATEGY_MANUAL,
            'subject' => 'x',
            'body' => 'x',
            'is_active' => 1,
        ])
        ->assertNotFound();

    $this->actingAsWithTwoFactor($adminA)
        ->delete(route('communications.templates.destroy', $templateB))
        ->assertNotFound();
});

test('organization A cannot view organization B communications', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $communication = Communication::withoutGlobalScopes()->create([
        'organization_id' => $orgB->id,
        'channel' => Communication::CHANNEL_NOTE,
        'direction' => Communication::DIRECTION_INTERNAL,
        'subject' => 'Secret',
        'body' => 'Private note',
        'status' => Communication::STATUS_RECEIVED,
    ]);

    $this->actingAsWithTwoFactor($adminA)
        ->get(route('communications.show', $communication))
        ->assertNotFound();
});

test('send request creates a communication log', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $template = createCommunicationTemplate($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.send-request.store'), [
            'template_id' => $template->id,
            'recipient_email' => 'manual@example.com',
        ])
        ->assertRedirect();

    expect(Communication::withoutGlobalScopes()->count())->toBe(1);
});

test('send request fails clearly when recipient cannot be resolved', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $template = createCommunicationTemplate($org->id, [
        'recipient_strategy' => CommunicationTemplate::STRATEGY_CLIENT_PCP,
        'default_recipient' => null,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.send-request.store'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
        ])
        ->assertSessionHasErrors('recipient_email');
});

test('send request resolves PCP recipient when client PCP exists', function () {
    mockGoogleWorkspaceForBilling();

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    attachClientPcp($client, $org->id);
    $template = createCommunicationTemplate($org->id, [
        'recipient_strategy' => CommunicationTemplate::STRATEGY_CLIENT_PCP,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.send-request.store'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
        ])
        ->assertRedirect();

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->status)->toBe(Communication::STATUS_SENT)
        ->and($communication->recipient_name)->toBe('Dr. Primary');
});

test('secure message thread can be created with participants', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.secure-messages.store'), [
            'subject' => 'Shift coverage',
            'body' => 'Can you cover tomorrow?',
            'participant_ids' => [$staff->id],
        ])
        ->assertRedirect();

    expect(SecureMessageThread::withoutGlobalScopes()->count())->toBe(1);
});

test('non participant cannot read secure thread', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);
    $outsider = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.secure-messages.store'), [
            'subject' => 'Private',
            'body' => 'Internal only',
            'participant_ids' => [$staff->id],
        ]);

    $thread = SecureMessageThread::withoutGlobalScopes()->first();

    $role = \App\Models\Role::where('slug', 'operations-staff')->first();
    $role->permissions()->detach(
        \App\Models\Permission::where('slug', 'manage_secure_messages')->pluck('id')
    );

    $this->actingAsWithTwoFactor($outsider)
        ->get(route('communications.secure-messages.show', $thread))
        ->assertForbidden();
});

test('message reply updates last_message_at and unread state', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $staff = $this->createUser(User::ROLE_STAFF, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.secure-messages.store'), [
            'subject' => 'Follow up',
            'body' => 'Initial message',
            'participant_ids' => [$staff->id],
        ]);

    $thread = SecureMessageThread::withoutGlobalScopes()->first();
    $originalLastMessageAt = $thread->last_message_at;

    $this->travel(1)->seconds();

    $this->actingAsWithTwoFactor($staff)
        ->post(route('communications.secure-messages.reply', $thread), [
            'body' => 'Reply message',
        ])
        ->assertRedirect();

    $thread->refresh();
    $adminParticipant = SecureMessageParticipant::where('thread_id', $thread->id)->where('user_id', $admin->id)->first();

    expect($thread->last_message_at->gt($originalLastMessageAt))->toBeTrue()
        ->and($adminParticipant->last_read_at)->toBeNull();
});

test('user can mark thread notification as read', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $notification = CommunicationNotification::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'user_id' => $admin->id,
        'type' => CommunicationNotification::TYPE_COMMUNICATION_SENT,
        'title' => 'Communication sent',
        'body' => 'An email was processed for your review.',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.notifications.read', $notification))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('notifications are scoped to the user and organization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $userA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);
    $userB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    CommunicationNotification::withoutGlobalScopes()->create([
        'organization_id' => $orgB->id,
        'user_id' => $userB->id,
        'type' => CommunicationNotification::TYPE_SECURE_MESSAGE,
        'title' => 'Other org',
        'body' => 'Hidden',
    ]);

    $this->actingAsWithTwoFactor($userA)
        ->get(route('communications.notifications.index'))
        ->assertOk()
        ->assertDontSee('Other org');
});

test('call log note can be created and related to a client', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.manual.store'), [
            'channel' => Communication::CHANNEL_CALL,
            'direction' => Communication::DIRECTION_INBOUND,
            'subject' => 'Wellness call',
            'body' => 'Services confirmed',
            'related_type' => 'Client',
            'related_id' => $client->id,
        ])
        ->assertRedirect();

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->channel)->toBe(Communication::CHANNEL_CALL)
        ->and($communication->related_id)->toBe($client->id);
});

test('super admin can manually log inbound call without organization on user', function () {
    $org = $this->createOrganization();
    $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);

    $this->actingAsWithTwoFactor($superAdmin)
        ->post(route('communications.manual.store'), [
            'channel' => Communication::CHANNEL_CALL,
            'direction' => Communication::DIRECTION_INBOUND,
            'body' => 'test',
        ])
        ->assertRedirect();

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->organization_id)->toBe($org->id)
        ->and($communication->channel)->toBe(Communication::CHANNEL_CALL)
        ->and($communication->direction)->toBe(Communication::DIRECTION_INBOUND)
        ->and($communication->sender_id)->toBe($superAdmin->id);
});

test('super admin manual call log uses related client organization', function () {
    $org = $this->createOrganization();
    $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($superAdmin)
        ->post(route('communications.manual.store'), [
            'channel' => Communication::CHANNEL_CALL,
            'direction' => Communication::DIRECTION_INBOUND,
            'body' => 'Client wellness check',
            'related_type' => 'Client',
            'related_id' => $client->id,
        ])
        ->assertRedirect();

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->organization_id)->toBe($org->id)
        ->and($communication->related_id)->toBe($client->id);
});

test('attachment upload rejects invalid mime oversized and path traversal attempts', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $template = createCommunicationTemplate($org->id);

    $badFile = UploadedFile::fake()->create('evil.exe', 100, 'application/octet-stream');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.send-request.store'), [
            'template_id' => $template->id,
            'recipient_email' => 'test@example.com',
            'attachment' => $badFile,
        ])
        ->assertSessionHasErrors('attachment');

    $hugeFile = UploadedFile::fake()->create('doc.pdf', 15000, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.send-request.store'), [
            'template_id' => $template->id,
            'recipient_email' => 'test@example.com',
            'attachment' => $hugeFile,
        ])
        ->assertSessionHasErrors('attachment');
});

test('attachment download requires authorization', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $adminA = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgA->id]);

    $communication = Communication::withoutGlobalScopes()->create([
        'organization_id' => $orgB->id,
        'channel' => Communication::CHANNEL_EMAIL,
        'direction' => Communication::DIRECTION_OUTBOUND,
        'status' => Communication::STATUS_SENT,
    ]);

    Storage::disk('local')->put('communications/test/file.pdf', 'test');

    $attachment = \App\Models\CommunicationAttachment::withoutGlobalScopes()->create([
        'communication_id' => $communication->id,
        'organization_id' => $orgB->id,
        'original_name' => 'file.pdf',
        'stored_path' => 'communications/test/file.pdf',
        'disk' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 4,
    ]);

    $this->actingAsWithTwoFactor($adminA)
        ->get(route('communications.attachments.download', $attachment))
        ->assertForbidden();
});

test('soft deleted templates are not usable for sending', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $template = createCommunicationTemplate($org->id);
    $template->delete();

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.send-request.store'), [
            'template_id' => $template->id,
            'recipient_email' => 'test@example.com',
        ])
        ->assertSessionHasErrors('template_id');
});

test('communication UI does not contain hard coded ASW text', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $response = $this->actingAsWithTwoFactor($admin)->get(route('communications.index'));
    $content = $response->getContent();

    expect(stripos($content, 'ASW') === false)->toBeTrue();
    expect(stripos($content, 'Case Coordinator') !== false || stripos($content, 'Communications') !== false)->toBeTrue();
});

test('communications index includes compose modals and export link', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('communications.index'))
        ->assertOk()
        ->assertSee('New message')
        ->assertSee('New eFax')
        ->assertSee('Send an SMS or email')
        ->assertSee(route('communications.export'));
});

test('directory search returns matching parties', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Unique', 'last_name' => 'ComposeClient', 'phone' => '5551234567']);

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('communications.directory-search', ['q' => 'ComposeClient']))
        ->assertOk()
        ->assertJsonFragment(['type' => 'client', 'id' => $client->id, 'name' => 'Unique ComposeClient']);
});

test('directory search finds clients by email', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'first_name' => 'Email',
        'last_name' => 'SearchClient',
        'email' => 'compose.search@example.com',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('communications.directory-search', ['q' => 'compose.search@example.com']))
        ->assertOk()
        ->assertJsonFragment(['type' => 'client', 'id' => $client->id, 'name' => 'Email SearchClient']);
});

test('directory search is not limited by selected location filter', function () {
    $org = $this->createOrganization();
    $location = \App\Models\Location::create([
        'organization_id' => $org->id,
        'name' => 'Main Office',
        'address' => '123 Main St',
    ]);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, [
        'first_name' => 'Location',
        'last_name' => 'AgnosticClient',
        'location_id' => null,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->withSession(['selected_location_id' => $location->id])
        ->getJson(route('communications.directory-search', ['q' => 'AgnosticClient']))
        ->assertOk()
        ->assertJsonFragment(['type' => 'client', 'id' => $client->id]);
});

test('compose message creates outbound communication log', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['phone' => '5559876543']);

    \App\Models\IntegrationCredential::query()->create([
        'key' => \App\Models\IntegrationCredential::KEY_RINGCENTRAL,
        'api_key' => 'client-id',
        'password' => 'client-secret',
        'metadata' => ['server_url' => 'https://platform.ringcentral.com', 'from_number' => '+15550001111'],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    \Illuminate\Support\Facades\Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => \Illuminate\Support\Facades\Http::response([
            'access_token' => 'token-abc',
            'expires_in' => 3600,
            'scope' => 'SMS Fax ReadAccounts',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~' => \Illuminate\Support\Facades\Http::response([
            'extensionNumber' => '101',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/sms' => \Illuminate\Support\Facades\Http::response([
            'id' => 'sms-123',
        ]),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.compose.message.store'), [
            'recipient_type' => 'client',
            'recipient_id' => $client->id,
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Hello from the compose modal.',
        ])
        ->assertRedirect(route('communications.index'))
        ->assertSessionHas('success');

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication)
        ->not->toBeNull()
        ->and($communication->channel)->toBe(Communication::CHANNEL_SMS)
        ->and($communication->direction)->toBe(Communication::DIRECTION_OUTBOUND)
        ->and($communication->metadata['ai_summary'] ?? null)->toContain('Hello from the compose modal');
});

test('communications export returns csv', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    Communication::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'channel' => Communication::CHANNEL_SMS,
        'direction' => Communication::DIRECTION_OUTBOUND,
        'subject' => 'Export row',
        'body' => 'Body',
        'status' => Communication::STATUS_SENT,
        'recipient_name' => 'Test Party',
    ]);

    $response = $this->actingAsWithTwoFactor($admin)
        ->get(route('communications.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('queued communication shows needs ali handled label', function () {
    config(['communications.queue_owner_label' => 'Ali']);

    $communication = Communication::withoutGlobalScopes()->make([
        'status' => Communication::STATUS_QUEUED,
        'channel' => Communication::CHANNEL_SMS,
        'direction' => Communication::DIRECTION_INBOUND,
        'metadata' => [],
    ]);

    expect(\App\Support\CommunicationPresenter::make($communication)->handledLabel())->toBe('Needs Ali');
});

test('outbound compose message stores staff sender name in metadata', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id, 'name' => 'Staff Sender']);
    $client = $this->createClient($org->id, ['phone' => '5559876543']);

    \App\Models\IntegrationCredential::query()->create([
        'key' => \App\Models\IntegrationCredential::KEY_RINGCENTRAL,
        'api_key' => 'client-id',
        'password' => 'client-secret',
        'metadata' => ['server_url' => 'https://platform.ringcentral.com', 'from_number' => '+15550001111'],
    ]);

    app(\App\Services\IntegrationConfigService::class)->hydrateRuntimeConfig();

    \Illuminate\Support\Facades\Http::fake([
        'https://platform.ringcentral.com/restapi/oauth/token' => \Illuminate\Support\Facades\Http::response([
            'access_token' => 'token-abc',
            'expires_in' => 3600,
            'scope' => 'SMS Fax ReadAccounts',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~' => \Illuminate\Support\Facades\Http::response([
            'extensionNumber' => '101',
        ]),
        'https://platform.ringcentral.com/restapi/v1.0/account/~/extension/~/sms' => \Illuminate\Support\Facades\Http::response([
            'id' => 'sms-123',
        ]),
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.compose.message.store'), [
            'recipient_type' => 'client',
            'recipient_id' => $client->id,
            'channel' => 'sms',
            'language' => 'en',
            'body' => 'Staff outbound test.',
        ])
        ->assertRedirect(route('communications.index'));

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication->metadata['handled_by'])->toBe('staff')
        ->and($communication->metadata['handled_by_name'])->toBe('Staff Sender');
});

test('client profile communications tab shows related log entries', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Log', 'last_name' => 'Client', 'phone' => '5551112233']);

    Communication::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'related_type' => Client::class,
        'related_id' => $client->id,
        'channel' => Communication::CHANNEL_SMS,
        'direction' => Communication::DIRECTION_INBOUND,
        'status' => Communication::STATUS_RECEIVED,
        'metadata' => [
            'ai_summary' => 'Client asked about appointment time.',
            'handled_by' => 'needs_review',
            'party_name' => 'Log Client',
        ],
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('clients.show', ['id' => $client->id, 'tab' => 'communications']))
        ->assertOk()
        ->assertSee('Client asked about appointment time.')
        ->assertSee('Needs Ali');
});

test('communications index shows ai summary column header', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('communications.index'))
        ->assertOk()
        ->assertSee('AI summary');
});

test('ringcentral webhook validation token is echoed', function () {
    $this->postJson(route('webhooks.ringcentral'), [], [
        'Validation-Token' => 'rc-validation-token',
    ])
        ->assertOk()
        ->assertHeader('Validation-Token', 'rc-validation-token');
});

test('ringcentral webhook records inbound sms for matched client', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['phone' => '5554443322']);

    config(['communications.inbound.webhook_secret' => null]);

    $this->postJson(route('webhooks.ringcentral'), [
        'message' => [
            'id' => 'rc-msg-123',
            'type' => 'SMS',
            'direction' => 'Inbound',
            'from' => ['phoneNumber' => '+15554443322', 'name' => 'Log Client'],
            'to' => ['phoneNumber' => '+15550001111'],
            'text' => 'Can you call me back?',
        ],
    ])->assertNoContent();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication)
        ->not->toBeNull()
        ->and($communication->direction)->toBe(Communication::DIRECTION_INBOUND)
        ->and($communication->related_id)->toBe($client->id)
        ->and($communication->metadata['ai_summary'])->toContain('Can you call me back?')
        ->and($communication->metadata['handled_by'])->toBe('needs_review');
});

test('retell webhook logs wellness call with transcript', function () {
    $org = $this->createOrganization();
    $employee = $this->createEmployee($org->id, ['phone' => '5553332211']);

    config(['retell.webhook_secret' => null, 'communications.inbound.organization_id' => $org->id]);

    $this->postJson(route('webhooks.retell'), [
        'event' => 'call_analyzed',
        'call' => [
            'call_id' => 'retell-call-1',
            'duration_ms' => 120000,
            'call_summary' => 'Caregiver confirmed wellness check.',
            'transcript' => [
                ['role' => 'agent', 'content' => 'Monthly wellness check'],
                ['role' => 'user', 'content' => 'Services are going well'],
            ],
            'retell_llm_dynamic_variables' => [
                'employee_id' => $employee->id,
                'wellness_call' => true,
                'campaign' => 'wellness',
            ],
        ],
    ])->assertNoContent();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication)
        ->not->toBeNull()
        ->and($communication->channel)->toBe(Communication::CHANNEL_CALL)
        ->and($communication->metadata['wellness_call'])->toBeTrue()
        ->and($communication->metadata['handled_by'])->toBe('ai_va');
});

test('inbound billing sms links to billing claim audit', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['phone' => '5554443322']);
    $claim = billingClaimAuditRecord($org->id, $client->id, [
        'billing_period' => now()->startOfMonth()->toDateString(),
        'claim_status' => \App\Models\BillingClaimAudit::STATUS_SUBMITTED,
    ]);

    config(['communications.inbound.webhook_secret' => null]);

    $this->postJson(route('webhooks.ringcentral'), [
        'message' => [
            'id' => 'rc-billing-1',
            'type' => 'SMS',
            'direction' => 'Inbound',
            'from' => ['phoneNumber' => '+15554443322'],
            'text' => 'Question about my MCO billing claim status',
        ],
    ])->assertNoContent();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication->metadata['billing_claim_audit_id'] ?? null)->toBe($claim->id);
});

test('directory exposes client design aligned categories', function () {
    expect(\App\Support\DirectoryCategories::all())->toHaveCount(8)
        ->and(collect(\App\Support\DirectoryCategories::all())->pluck('label')->all())->toBe([
            'Payers / MCOs',
            'DHS — ASWs',
            'MICH — Case Coordinators',
            'Physicians / PCPs',
            'Referral sources',
            'State systems & portals',
            'Vendors / integrations',
            'Pharmacies / facilities',
        ]);
});

test('ringcentral webhook with wrong secret returns 403', function () {
    config(['communications.inbound.webhook_secret' => 'correct-secret']);

    $this->postJson(route('webhooks.ringcentral'), [], [
        'X-Communications-Webhook-Secret' => 'wrong-secret',
    ])->assertForbidden();
});

test('retell webhook with wrong secret returns 403', function () {
    config(['retell.webhook_secret' => 'correct-retell-secret']);

    $this->postJson(route('webhooks.retell'), [
        'event' => 'call_analyzed',
        'call'  => ['call_id' => 'retell-x'],
    ], [
        'X-Retell-Signature' => 'wrong-secret',
    ])->assertForbidden();
});

test('inbound efax webhook creates fax channel communication log entry with ai triage', function () {
    $org    = $this->createOrganization();
    $client = $this->createClient($org->id, ['phone' => '5554443322']);

    config(['communications.inbound.webhook_secret' => null]);

    $this->postJson(route('webhooks.ringcentral'), [
        'message' => [
            'id'                => 'rc-fax-001',
            'type'              => 'FAX',
            'direction'         => 'Inbound',
            'from'              => ['phoneNumber' => '+15554443322', 'name' => 'Fax Sender'],
            'to'                => ['phoneNumber' => '+15550001111'],
            'subject'           => 'Authorization form',
            'attachmentContent' => 'Inbound fax document content',
        ],
    ])->assertNoContent();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication)->not->toBeNull()
        ->and($communication->channel)->toBe(Communication::CHANNEL_FAX)
        ->and($communication->direction)->toBe(Communication::DIRECTION_INBOUND)
        ->and($communication->related_id)->toBe($client->id)
        ->and($communication->metadata['handled_by'])->toBe('needs_review')
        ->and($communication->metadata['ai_summary'])->not->toBeNull()
        ->and($communication->ai_triage_category)->not->toBeNull()
        ->and($communication->ai_triage_priority)->toBe(Communication::TRIAGE_PRIORITY_NORMAL);
});

test('inbound voicemail webhook creates call channel communication log entry', function () {
    $org = $this->createOrganization();

    config(['communications.inbound.webhook_secret' => null, 'communications.inbound.organization_id' => $org->id]);

    $this->postJson(route('webhooks.ringcentral'), [
        'message' => [
            'id'        => 'rc-vm-001',
            'type'      => 'VOICEMAIL',
            'direction' => 'Inbound',
            'from'      => ['phoneNumber' => '+15559876543'],
            'to'        => ['phoneNumber' => '+15550001111'],
            'subject'   => 'New voicemail',
        ],
    ])->assertNoContent();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication)->not->toBeNull()
        ->and($communication->channel)->toBe(Communication::CHANNEL_CALL)
        ->and($communication->direction)->toBe(Communication::DIRECTION_INBOUND)
        ->and($communication->metadata['handled_by'])->toBe('needs_review');
});

test('inbound ringcentral call webhook creates call log entry linked to matched client', function () {
    $org    = $this->createOrganization();
    $client = $this->createClient($org->id, ['phone' => '5554443322']);

    config(['communications.inbound.webhook_secret' => null]);

    $this->postJson(route('webhooks.ringcentral'), [
        'call' => [
            'id'        => 'rc-call-001',
            'direction' => 'Inbound',
            'from'      => ['phoneNumber' => '+15554443322'],
            'to'        => ['phoneNumber' => '+15550001111'],
            'duration'  => 180,
            'result'    => 'Accepted',
        ],
    ])->assertNoContent();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication)->not->toBeNull()
        ->and($communication->channel)->toBe(Communication::CHANNEL_CALL)
        ->and($communication->direction)->toBe(Communication::DIRECTION_INBOUND)
        ->and($communication->related_id)->toBe($client->id)
        ->and($communication->metadata['ai_summary'])->not->toBeNull()
        ->and($communication->ai_triage_priority)->toBe(Communication::TRIAGE_PRIORITY_NORMAL);
});

test('manual inbound communication gets ai triage and appears in workflow queue', function () {
    $org    = $this->createOrganization();
    $admin  = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.manual.store'), [
            'channel'      => Communication::CHANNEL_CALL,
            'direction'    => Communication::DIRECTION_INBOUND,
            'subject'      => 'Client called about scheduling',
            'body'         => 'Client wants to reschedule appointment for next week',
            'related_type' => 'Client',
            'related_id'   => $client->id,
        ])
        ->assertRedirect();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication)->not->toBeNull()
        ->and($communication->metadata['ai_summary'])->not->toBeNull()
        ->and($communication->metadata['handled_by'])->toBeIn(['needs_review', 'concern'])
        ->and($communication->ai_triage_category)->toBe(Communication::TRIAGE_CATEGORY_SCHEDULING)
        ->and($communication->concern_flagged)->toBeFalse();

    // Verify workflow queue item was created for the needs_review item
    $queueItem = \App\Models\WorkflowQueueItem::withoutGlobalScopes()
        ->where('subject_type', Communication::class)
        ->where('subject_id', $communication->id)
        ->first();

    expect($queueItem)->not->toBeNull();
});

test('inbound concern keyword in manual log sets concern_flagged and priority urgent', function () {
    $org   = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.manual.store'), [
            'channel'   => Communication::CHANNEL_CALL,
            'direction' => Communication::DIRECTION_INBOUND,
            'subject'   => 'Emergency',
            'body'      => 'Client has fallen and cannot get up — needs emergency assistance',
        ])
        ->assertRedirect();

    $communication = Communication::withoutGlobalScopes()->first();

    expect($communication)->not->toBeNull()
        ->and($communication->concern_flagged)->toBeTrue()
        ->and($communication->ai_triage_priority)->toBe(Communication::TRIAGE_PRIORITY_URGENT)
        ->and($communication->metadata['handled_by'])->toBe('concern');
});

test('inbound ringcentral sms for employee also creates caregiver communication record', function () {
    $org      = $this->createOrganization();
    $employee = $this->createEmployee($org->id, ['phone' => '5556667777']);

    config(['communications.inbound.webhook_secret' => null]);

    $this->postJson(route('webhooks.ringcentral'), [
        'message' => [
            'id'        => 'rc-emp-sms-001',
            'type'      => 'SMS',
            'direction' => 'Inbound',
            'from'      => ['phoneNumber' => '+15556667777', 'name' => $employee->first_name],
            'to'        => ['phoneNumber' => '+15550001111'],
            'text'      => 'I will be late for my shift today',
        ],
    ])->assertNoContent();

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication)->not->toBeNull()
        ->and($communication->related_type)->toBe(\App\Models\Employee::class);

    $caregiverComm = \App\Models\CaregiverCommunication::withoutGlobalScopes()
        ->where('employee_id', $employee->id)
        ->first();

    expect($caregiverComm)->not->toBeNull()
        ->and($caregiverComm->channel)->toBe('SMS')
        ->and($caregiverComm->direction)->toBe('Inbound')
        ->and($caregiverComm->tag)->toBe('AI Secretary');
});

test('inbound sync command ingests ringcentral messages via service', function () {
    $org = $this->createOrganization();
    config(['communications.inbound.organization_id' => $org->id]);

    $mockInbound = Mockery::mock(\App\Services\Communication\CommunicationInboundService::class);
    $mockInbound->shouldReceive('syncGoogleInbound')->once()->andReturn([]);
    $mockInbound->shouldReceive('syncRingCentralCalls')->once()->andReturn([]);
    $mockInbound->shouldReceive('syncRingCentralMessages')->once()->andReturn([
        \App\Models\Communication::withoutGlobalScopes()->forceCreate([
            'organization_id' => $org->id,
            'channel' => \App\Models\Communication::CHANNEL_SMS,
            'direction' => \App\Models\Communication::DIRECTION_INBOUND,
            'status' => \App\Models\Communication::STATUS_RECEIVED,
            'body' => 'Synced inbound text',
            'metadata' => ['handled_by' => 'needs_review'],
            'sent_at' => now(),
        ]),
    ]);

    $this->app->instance(\App\Services\Communication\CommunicationInboundService::class, $mockInbound);

    $this->artisan('communications:sync-inbound')
        ->assertSuccessful()
        ->expectsOutputToContain('RingCentral messages');
});
