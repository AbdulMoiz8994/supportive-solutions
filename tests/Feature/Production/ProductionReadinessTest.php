<?php

use App\Models\Document;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('compliance page includes secure document download links', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $document = $this->createDocument($org->id, $client, [
        'name' => 'Compliance License',
        'verification_status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('compliance'))
        ->assertOk()
        ->assertSee(route('documents.download', $document->id), false)
        ->assertSee('Compliance License', false);
});

test('menu clients path points to client registry with intake entry', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->actingAs($admin);

    $clients = collect(\App\Helpers\MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->firstWhere('name', 'Clients');

    expect($clients)->not->toBeNull()
        ->and($clients['path'])->toBe('/clients');
});

test('menu dashboard entry matches registered route', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $this->actingAs($admin);

    $dashboard = collect(\App\Helpers\MenuHelper::getMenuGroups())
        ->flatMap(fn ($group) => $group['items'])
        ->firstWhere('name', 'Dashboard');

    expect($dashboard)->not->toBeNull()
        ->and($dashboard['path'])->toBe('/dashboard');

    $this->actingAsWithTwoFactor($admin)
        ->get(route('dashboard'))
        ->assertOk();
});

test('audit view route serves clinical audit trail not placeholder', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    \App\Models\ActivityLog::create([
        'organization_id' => $org->id,
        'user_id' => $admin->id,
        'action' => 'Created',
        'subject_type' => User::class,
        'subject_id' => $admin->id,
        'description' => 'Test activity for audit trail',
        'ip_address' => '127.0.0.1',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('audit-view'))
        ->assertOk()
        ->assertSee('System Activity Stream', false)
        ->assertSee('Test activity for audit trail', false)
        ->assertDontSee('Coming Soon — Placeholder Module', false);
});

test('document retention report lists old documents without deleting', function () {
    Setting::create([
        'key' => 'retention.document_retention_days',
        'group' => 'retention',
        'value_payload' => 30,
    ]);

    $org = $this->createOrganization();
    $client = $this->createClient($org->id);

    $oldDocument = Document::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'documentable_type' => 'App\Models\Client',
        'documentable_id' => $client->id,
        'name' => 'Archived Record',
        'path' => 'documents/archived.pdf',
        'verification_status' => 'Verified',
    ]);

    Document::withoutGlobalScopes()
        ->where('id', $oldDocument->id)
        ->update([
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

    Artisan::call('documents:retention-report');

    expect(Artisan::output())->toContain('Archived Record')
        ->and(Document::withoutGlobalScopes()->find($oldDocument->id))->not->toBeNull();
});

test('legacy billing run route is retired', function () {
    Setting::create([
        'key' => 'billing.default_cycle',
        'group' => 'billing',
        'value_payload' => 'weekly',
    ]);

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('billing.run'))
        ->assertNotFound();
});
