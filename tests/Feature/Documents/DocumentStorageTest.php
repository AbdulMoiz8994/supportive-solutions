<?php

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStorageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

test('document upload stores a real file on disk', function () {
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

    expect($document)->not->toBeNull()
        ->and($document->path)->not->toBeEmpty()
        ->and($document->disk)->toBe(DocumentStorageService::DISK)
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->original_filename)->toBe('license.pdf')
        ->and($document->uploaded_by)->toBe($admin->id);

    Storage::disk('local')->assertExists($document->path);
});

test('document upload rejects invalid files', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('documents.store'), [
            'documentable_type' => 'Client',
            'documentable_id' => $client->id,
            'name' => 'Bad File',
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');

    expect(Document::count())->toBe(0);
});

test('document upload rejects invalid subject id with validation error', function () {
    $org = $this->createOrganization();
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $file = UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('documents.store'), [
            'documentable_type' => 'Client',
            'documentable_id' => 999999,
            'name' => 'Invalid Subject',
            'file' => $file,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['documentable_id']);

    expect(Document::count())->toBe(0);
});

test('document upload accepts caregiver subject value', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id, ['position' => 'Caregiver']);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $file = UploadedFile::fake()->create('caregiver-id.pdf', 100, 'application/pdf');

    $this->actingAsWithTwoFactor($admin)
        ->post(route('documents.store'), [
            'documentable_type' => 'Employee',
            'documentable_id' => $caregiver->id,
            'name' => 'Caregiver Credential',
            'file' => $file,
        ])
        ->assertRedirect();

    $document = Document::latest('id')->first();

    expect($document)->not->toBeNull()
        ->and($document->documentable_type)->toBe($caregiver->getMorphClass())
        ->and($document->documentable_id)->toBe($caregiver->id);
});

test('cross organization document upload is blocked', function () {
    $orgA = $this->createOrganization(['name' => 'Org A']);
    $orgB = $this->createOrganization(['name' => 'Org B']);

    $clientInOrgA = $this->createClient($orgA->id);
    $adminInOrgB = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $orgB->id]);

    $file = UploadedFile::fake()->create('id-card.pdf', 50, 'application/pdf');

    $this->actingAsWithTwoFactor($adminInOrgB)
        ->post(route('documents.store'), [
            'documentable_type' => 'Client',
            'documentable_id' => $clientInOrgA->id,
            'name' => 'Cross Org Upload',
            'file' => $file,
        ])
        ->assertForbidden();

    expect(Document::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('documents');
});

test('signature upload stores persistent png file', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id);
    $admin = $this->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $payload = 'data:image/png;base64,'.base64_encode($png);

    $this->actingAsWithTwoFactor($admin)
        ->postJson(route('documents.signature.store'), [
            'signature' => $payload,
            'documentable_type' => 'Client',
            'documentable_id' => $client->id,
            'document_name' => 'Signed Clinical Assessment',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $document = Document::latest('id')->first();

    expect($document->is_signed)->toBeTrue()
        ->and($document->mime_type)->toBe('image/png')
        ->and($document->path)->toContain('signatures/');

    Storage::disk('local')->assertExists($document->path);
});
