<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedModuleBasics();
    Storage::fake('local');
});

test('document upload stores file and links to client', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $file = UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('documents.store'), [
            'documentable_type' => 'Client',
            'documentable_id' => $client->id,
            'name' => 'Drivers License',
            'file' => $file,
        ])
        ->assertRedirect();

    $document = Document::latest('id')->first();
    expect($document->documentable_id)->toBe($client->id)
        ->and($document->uploaded_by)->toBe($admin->id);

    Storage::disk('local')->assertExists($document->path);
});

test('document upload rejects invalid subject id', function () {
    $admin = $this->createUser(User::ROLE_ADMIN);
    $file = UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('documents.store'), [
            'documentable_type' => 'Client',
            'documentable_id' => 999999,
            'name' => 'Bad',
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['documentable_id']);
});

test('document upload rejects disallowed file types', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $file = UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('documents.store'), [
            'documentable_type' => 'Client',
            'documentable_id' => $client->id,
            'name' => 'Bad',
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');

    expect(Document::count())->toBe(0);
});

test('document verify updates verification status', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $document = $this->createDocument($org->id, $client, ['verification_status' => 'Pending']);

    $this->actingAsWithTwoFactor($admin)
        ->post(route('documents.verify', $document->id))
        ->assertRedirect();

    expect($document->fresh()->verification_status)->toBe('Verified');
});

test('document download requires authorization for cross org', function () {
    $orgA = $this->createOrganization();
    $orgB = $this->createOrganization(['name' => 'Org B']);
    $client = $this->createClient($orgA->id);
    $adminB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);
    $document = $this->createDocument($orgA->id, $client);

    Storage::disk('local')->put($document->path, 'pdf-content');

    $this->actingAsWithTwoFactor($adminB)
        ->get(route('documents.download', $document->id))
        ->assertForbidden();
});

test('client documents relationship returns morphMany records', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $this->createDocument($org->id, $client, ['name' => 'Morph Doc']);

    expect($client->documents)->toHaveCount(1)
        ->and($client->documents->first()->name)->toBe('Morph Doc');
});
