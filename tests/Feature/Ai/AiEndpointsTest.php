<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.model', 'claude-sonnet-4-6');
});

function fakeClaudeJson(array $json, int $status = 200, string $stop = 'end_turn'): void
{
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode($json)]],
        'stop_reason' => $stop,
        'model' => 'claude-sonnet-4-6',
        'usage' => ['input_tokens' => 50, 'output_tokens' => 25],
    ], $status)]);
}

/** A real 1x1 PNG as an UploadedFile (no GD dependency). */
function fakePng(string $name = 'id.png'): UploadedFile
{
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    $path = tempnam(sys_get_temp_dir(), 'aitest').'.png';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/png', null, true);
}

test('scan-id endpoint returns confirmed-needed identity fields', function () {
    fakeClaudeJson([
        'first_name' => 'John', 'last_name' => 'Dutton', 'date_of_birth' => '05/18/1943',
        'address' => '220 Bagley Ave', 'city' => 'Detroit', 'state' => 'MI', 'zip' => '48226',
    ]);

    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_SUPER_ADMIN))
        ->post(route('ai.scan-id'), ['image' => fakePng()])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('result.needs_confirmation', true)
        ->assertJsonPath('result.fields.first_name', 'John')
        ->assertJsonPath('result.fields.state', 'MI');
});

test('scan-id returns 503 when the API key is not configured', function () {
    config()->set('services.anthropic.key', null);

    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_SUPER_ADMIN))
        ->post(route('ai.scan-id'), ['image' => fakePng()])
        ->assertStatus(503)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('kind', 'not_configured');
});

test('scan-id validates that an image is supplied', function () {
    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_SUPER_ADMIN))
        ->postJson(route('ai.scan-id'), [])
        ->assertStatus(422);
});

test('scan-id maps a rate limit to HTTP 429', function () {
    fakeClaudeJson(['error' => 'slow down'], 429);

    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_SUPER_ADMIN))
        ->post(route('ai.scan-id'), ['image' => fakePng()])
        ->assertStatus(429)
        ->assertJsonPath('kind', 'rate_limited');
});

test('recognize-document endpoint accepts text and returns a suggestion', function () {
    fakeClaudeJson([
        'document_type' => 'Prior Authorization', 'summary' => '56 units T1019.',
        'suggested_action' => 'add_care_detail', 'confidence' => 'high',
        'extracted' => ['units' => 56],
    ]);

    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_SUPER_ADMIN))
        ->postJson(route('ai.recognize-document'), ['text' => 'PA letter for 56 units'])
        ->assertOk()
        ->assertJsonPath('result.document_type', 'Prior Authorization')
        ->assertJsonPath('result.needs_review', true);
});

test('client AI summary endpoint returns a structured summary', function () {
    $org = $this->createOrganization();
    $client = $this->createClient($org->id, ['status' => 'Pending']);
    fakeClaudeJson(['summary' => 'Pending client.', 'next_action' => 'Follow up.', 'flags' => ['stuck']]);

    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_SUPER_ADMIN))
        ->postJson(route('ai.client-summary', $client->id))
        ->assertOk()
        ->assertJsonPath('result.next_action', 'Follow up.')
        ->assertJsonPath('result.flags.0', 'stuck');
});

test('caregiver AI summary endpoint returns a structured summary', function () {
    $org = $this->createOrganization();
    $caregiver = $this->createEmployee($org->id);
    fakeClaudeJson(['summary' => 'New caregiver.', 'next_action' => 'CHAMPS association.', 'flags' => []]);

    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_SUPER_ADMIN))
        ->postJson(route('ai.caregiver-summary', $caregiver->id))
        ->assertOk()
        ->assertJsonPath('result.summary', 'New caregiver.');
});

test('AI endpoints are blocked for caregivers (employee role)', function () {
    $this->actingAsWithTwoFactor($this->createUser(User::ROLE_EMPLOYEE))
        ->post(route('ai.scan-id'), ['image' => fakePng()])
        ->assertForbidden();
});
