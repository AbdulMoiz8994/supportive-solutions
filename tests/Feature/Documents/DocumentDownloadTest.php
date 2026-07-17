<?php

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

test('authorized user can download a private document', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $path = 'documents/App\Models\Client/'.$client->id.'/sample.pdf';
    Storage::disk('local')->put($path, 'pdf-content');

    $document = $this->createDocument($org->id, $client, [
        'path' => $path,
        'original_filename' => 'sample.pdf',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('documents.download', $document->id))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('document download returns 404 when file is missing', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $document = $this->createDocument($org->id, $client, [
        'path' => 'documents/missing/file.pdf',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('documents.download', $document->id))
        ->assertNotFound();
});

test('cross organization document download is denied', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $path = 'documents/App\Models\Client/'.$client->id.'/private.pdf';
    Storage::disk('local')->put($path, 'secret');

    $document = $this->createDocument($orgA->id, $client, ['path' => $path]);

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('documents.download', $document->id))
        ->assertForbidden();
});

test('legacy public intake documents can be downloaded via secure route', function () {
    Storage::fake('public');

    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $intake = \App\Models\Intake::withoutGlobalScopes()->create([
        'organization_id' => $org->id,
        'first_name' => 'Lead',
        'last_name' => 'Person',
        'status' => 'New',
    ]);

    $path = 'intake-documents/legacy.pdf';
    Storage::disk('public')->put($path, 'legacy-intake');

    $document = Document::create([
        'organization_id' => $org->id,
        'documentable_type' => 'App\Models\Intake',
        'documentable_id' => $intake->id,
        'name' => 'Legacy Intake Doc',
        'path' => $path,
        'disk' => 'public',
        'verification_status' => 'Pending',
    ]);

    $this->actingAsWithTwoFactor($admin)
        ->get(route('documents.download', $document->id))
        ->assertOk();
});
