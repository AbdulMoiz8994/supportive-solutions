<?php

use App\Models\Client;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\Contact;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedModuleBasics();
    Storage::fake('local');
});

test('compose efax validates recipient and attachment requirements', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.compose.efax.store'), [])
        ->assertSessionHasErrors(['recipient_fax', 'attachment']);
});

test('compose efax sends and logs when ringcentral is configured', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $contact = Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $org->id,
        'name' => 'Fax Contact',
        'type' => Contact::TYPE_PCP,
        'fax' => '5551234567',
        'is_active' => true,
    ]);
    stubRingCentralCredentials();

    $pdf = UploadedFile::fake()->create('referral.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.compose.efax.store'), [
            'contact_id' => $contact->id,
            'cover_note' => 'PA renewal attached',
            'attachment' => $pdf,
        ])
        ->assertRedirect(route('communications.index'))
        ->assertSessionHas('success');

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->channel)->toBe(Communication::CHANNEL_FAX)
        ->and($communication->direction)->toBe(Communication::DIRECTION_OUTBOUND);
});

test('super admin compose efax resolves organization from contact', function () {
    $org = $this->createOrganization();
    $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);
    $contact = Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $org->id,
        'name' => 'Dr. Nadia Khalil',
        'type' => Contact::TYPE_PCP,
        'fax' => '5559876543',
        'is_active' => true,
    ]);
    stubRingCentralCredentials();

    $pdf = UploadedFile::fake()->create('referral.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($superAdmin)
        ->post(route('communications.compose.efax.store'), [
            'contact_id' => $contact->id,
            'cover_note' => 'Outbound eFax',
            'attachment' => $pdf,
        ])
        ->assertRedirect(route('communications.index'))
        ->assertSessionHas('success');

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->organization_id)->toBe($org->id)
        ->and($communication->sender_id)->toBe($superAdmin->id)
        ->and($communication->recipient_id)->toBe($contact->id);
});

test('super admin compose efax with raw fax number uses default organization', function () {
    $org = $this->createOrganization();
    $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);
    stubRingCentralCredentials();

    $pdf = UploadedFile::fake()->create('referral.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($superAdmin)
        ->post(route('communications.compose.efax.store'), [
            'recipient_fax' => '5551112222',
            'cover_note' => 'Manual fax',
            'attachment' => $pdf,
        ])
        ->assertRedirect(route('communications.index'))
        ->assertSessionHas('success');

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->organization_id)->toBe($org->id);
});

test('super admin compose message resolves organization from contact recipient', function () {
    $org = $this->createOrganization();
    $superAdmin = $this->createUser(User::ROLE_SUPER_ADMIN, ['organization_id' => null]);
    $contact = Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $org->id,
        'name' => 'Directory Contact',
        'type' => Contact::TYPE_PCP,
        'phone' => '5554443333',
        'is_active' => true,
    ]);
    stubRingCentralCredentials();

    $this->actingAsWithTwoFactor($superAdmin)
        ->post(route('communications.compose.message.store'), [
            'channel' => Communication::CHANNEL_SMS,
            'language' => 'en',
            'recipient_type' => 'contact',
            'recipient_id' => $contact->id,
            'body' => 'Test message body',
        ])
        ->assertRedirect(route('communications.index'))
        ->assertSessionHas('success');

    $communication = Communication::withoutGlobalScopes()->first();
    expect($communication->organization_id)->toBe($org->id)
        ->and($communication->channel)->toBe(Communication::CHANNEL_SMS)
        ->and($communication->recipient_id)->toBe($contact->id);
});

test('send request preview returns rendered template json', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Preview', 'last_name' => 'Client']);
    $template = createCommunicationTemplate($org->id, [
        'subject' => 'Auth for {{ client.first_name }}',
        'body' => 'Dear coordinator, {{ client.first_name }} {{ client.last_name }} needs review.',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('communications.send-request.preview'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
        ])
        ->assertOk()
        ->assertJsonFragment(['subject' => 'Auth for Preview'])
        ->assertJsonFragment(['body' => 'Dear coordinator, Preview Client needs review.']);
});

test('client profile communication request stores outbound log', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $template = createCommunicationTemplate($org->id);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.client-send', $client->id), [
            'template_id' => $template->id,
            'recipient_email' => 'coordinator@example.com',
        ])
        ->assertRedirect();

    expect(Communication::withoutGlobalScopes()->where('related_id', $client->id)->count())->toBe(1);
});

test('client documents json endpoint returns documents for compose modal', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id);
    $this->createDocument($org->id, $client, [
        'name' => 'PA Letter',
        'original_filename' => 'PA Letter.pdf',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->getJson(route('communications.client-documents', $client))
        ->assertOk()
        ->assertJsonFragment(['name' => 'PA Letter.pdf']);
});

test('notifications mark all read clears unread notifications', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    \App\Models\CommunicationNotification::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'user_id' => $admin->id,
        'type' => \App\Models\CommunicationNotification::TYPE_COMMUNICATION_SENT,
        'title' => 'Unread',
        'body' => 'Test',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('communications.notifications.read-all'))
        ->assertRedirect();

    expect(\App\Models\CommunicationNotification::where('user_id', $admin->id)->whereNull('read_at')->count())->toBe(0);
});

test('communication show page loads for authorized user', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $communication = Communication::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'channel' => Communication::CHANNEL_EMAIL,
        'direction' => Communication::DIRECTION_OUTBOUND,
        'subject' => 'Show Test',
        'body' => 'Body content',
        'status' => Communication::STATUS_SENT,
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('communications.show', $communication))
        ->assertOk()
        ->assertSee('Show Test');
});

test('send request create page loads with client context', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $client = $this->createClient($org->id, ['first_name' => 'Send', 'last_name' => 'Request']);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('communications.send-request.create', ['client_id' => $client->id]))
        ->assertOk()
        ->assertSee('Send Request');
});
