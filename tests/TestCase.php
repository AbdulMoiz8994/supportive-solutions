<?php

namespace Tests;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\Concerns\InteractsWithTime;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithTime;

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->ensureViteManifestExists();
        $this->configureAvailityForTests();
    }

    protected function configureAvailityForTests(): void
    {
        config([
            'services.availity.env'           => 'demo',
            'services.availity.demo_key'      => 'test-demo-key',
            'services.availity.demo_secret'   => 'test-demo-secret',
            'services.availity.prod_key'      => 'test-prod-key',
            'services.availity.prod_secret'   => 'test-prod-secret',
            'services.availity.token_url'     => 'https://api.availity.com/v1/token',
            'services.availity.api_base_url'  => 'https://api.availity.com/availity/v1',
            'services.availity.base_url_demo' => 'https://api.availity.com/availity/v1',
            'services.availity.base_url_prod' => 'https://api.availity.com/availity/v1',
            'services.availity.request_type_code' => 'PRE_DETERMINATION',
            'services.availity.default_payer_id' => 'BCBSF',
        ]);
    }

    protected function ensureViteManifestExists(): void
    {
        $manifestPath = public_path('build/manifest.json');

        if (file_exists($manifestPath)) {
            return;
        }

        if (! is_dir(dirname($manifestPath))) {
            mkdir(dirname($manifestPath), 0755, true);
        }

        file_put_contents($manifestPath, json_encode([
            'resources/css/app.css' => [
                'file' => 'assets/app.css',
                'src' => 'resources/css/app.css',
                'isEntry' => true,
            ],
            'resources/js/app.js' => [
                'file' => 'assets/app.js',
                'src' => 'resources/js/app.js',
                'isEntry' => true,
            ],
        ]));
    }

    protected function createOrganization(array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Test Organization',
            'address' => '123 Test St',
            'status' => 'Active',
        ], $attributes));
    }

    protected function createUser(string $role, array $attributes = []): User
    {
        $organization = $attributes['organization_id'] ?? null;

        if ($role !== User::ROLE_SUPER_ADMIN && ! $organization) {
            $organization = $this->createOrganization()->id;
        }

        return User::create(array_merge([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'organization_id' => $organization,
            'is_active' => true,
        ], $attributes));
    }

    protected function actingAsWithTwoFactor(User $user): self
    {
        return $this->actingAs($user)->withSession(['2fa_verified' => true]);
    }

    protected function createClient(int $organizationId, array $attributes = []): Client
    {
        return Client::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId,
            'first_name' => 'Test',
            'last_name' => 'Client',
            'status' => 'Active',
        ], $attributes));
    }

    protected function createEmployee(int $organizationId, array $attributes = []): Employee
    {
        return Employee::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId,
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'status' => 'Active',
        ], $attributes));
    }

    protected function createSchedule(int $organizationId, int $clientId, int $employeeId, array $attributes = []): Schedule
    {
        $date = $attributes['date'] ?? today()->toDateString();
        $startTime = $attributes['start_time'] ?? '08:00:00';
        $endTime = $attributes['end_time'] ?? '12:00:00';

        return Schedule::withoutGlobalScopes()->forceCreate(array_merge([
            'organization_id' => $organizationId,
            'client_id' => $clientId,
            'employee_id' => $employeeId,
            'title' => $attributes['title'] ?? 'Test Schedule Event',
            'event_type' => Schedule::EVENT_CARE_VISIT,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_at' => $date.' '.$startTime,
            'end_at' => $date.' '.$endTime,
            'timezone' => config('app.timezone', 'UTC'),
            'status' => Schedule::STATUS_SCHEDULED,
        ], $attributes));
    }

    protected function createContact(int $organizationId, array $attributes = []): Contact
    {
        return Contact::withoutGlobalScopes()->forceCreate(array_merge([
            'organization_id' => $organizationId,
            'name' => 'Test Contact',
            'type' => Contact::TYPE_OTHER,
            'is_active' => true,
        ], $attributes));
    }

    protected function createDocument(int $organizationId, Model $documentable, array $attributes = []): Document
    {
        return Document::create(array_merge([
            'organization_id' => $organizationId,
            'documentable_type' => $documentable->getMorphClass(),
            'documentable_id' => $documentable->getKey(),
            'name' => 'Test Document',
            'path' => 'documents/test.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'original_filename' => 'test.pdf',
            'verification_status' => 'Pending',
        ], $attributes));
    }
}
