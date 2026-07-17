<?php

use App\Models\Client;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\Contact;
use App\Models\User;
use App\Services\Communication\Channels\FakeEmailChannel;
use App\Services\Communication\Channels\FakeFaxChannel;
use App\Services\Communication\CommunicationNotificationService;
use App\Services\Communication\CommunicationRecipientResolver;
use App\Services\Communication\CommunicationSendService;
use App\Services\Communication\CommunicationTemplateRenderService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

test('template rendering replaces allowed variables correctly', function () {
    $client = Client::withoutGlobalScopes()->make([
        'first_name' => 'Khalil',
        'last_name' => 'Ahmad',
        'member_id' => 'M-123',
    ]);

    $service = app(CommunicationTemplateRenderService::class);
    $variables = $service->buildVariables($client);

    $rendered = $service->render('Hello {{ client.first_name }} {{ client.last_name }} ({{ client.member_id }})', $variables, null, false);

    expect($rendered)->toBe('Hello Khalil Ahmad (M-123)');
});

test('unknown variables are handled safely', function () {
    $service = app(CommunicationTemplateRenderService::class);

    $rendered = $service->render('Value: {{ unknown.field }} and {{ client.first_name }}', [
        'client.first_name' => 'Safe',
    ], ['client.first_name'], false);

    expect($rendered)->toBe('Value: {{ unknown.field }} and Safe');
});

test('HTML script input is escaped in rendered output', function () {
    $service = app(CommunicationTemplateRenderService::class);

    $rendered = $service->render('Name: {{ client.first_name }}', [
        'client.first_name' => '<script>alert(1)</script>',
    ]);

    expect($rendered)->not->toContain('<script>')
        ->and($rendered)->toContain('&lt;script&gt;');
});

test('recipient resolver handles manual PCP case coordinator employee and missing recipient cases', function () {
    $org = test()->createOrganization();
    $client = test()->createClient($org->id);
    $employee = test()->createEmployee($org->id, ['email' => 'caregiver@example.com', 'phone' => '5550001111']);

    $pcp = Contact::withoutGlobalScopes()->forceCreate([
        'organization_id' => $org->id,
        'name' => 'Dr. PCP',
        'type' => 'Primary Care Physician',
        'email' => 'pcp@example.com',
        'is_active' => true,
    ]);
    $client->contacts()->attach($pcp->id, ['role' => 'Primary Care Physician']);

    $resolver = app(CommunicationRecipientResolver::class);

    $manualTemplate = CommunicationTemplate::withoutGlobalScopes()->make([
        'recipient_strategy' => CommunicationTemplate::STRATEGY_MANUAL,
        'default_recipient' => 'manual@example.com',
        'channel' => CommunicationTemplate::CHANNEL_EMAIL,
    ]);
    expect($resolver->resolve($manualTemplate)['recipient_email'])->toBe('manual@example.com');

    $pcpTemplate = CommunicationTemplate::withoutGlobalScopes()->make([
        'recipient_strategy' => CommunicationTemplate::STRATEGY_CLIENT_PCP,
        'channel' => CommunicationTemplate::CHANNEL_EMAIL,
    ]);
    expect($resolver->resolve($pcpTemplate, $client)['recipient_email'])->toBe('pcp@example.com');

    $employeeTemplate = CommunicationTemplate::withoutGlobalScopes()->make([
        'recipient_strategy' => CommunicationTemplate::STRATEGY_EMPLOYEE,
        'channel' => CommunicationTemplate::CHANNEL_EMAIL,
    ]);
    expect($resolver->resolve($employeeTemplate, null, $employee)['recipient_email'])->toBe('caregiver@example.com');

    $clientWithoutPcp = test()->createClient($org->id);
    $missingTemplate = CommunicationTemplate::withoutGlobalScopes()->make([
        'recipient_strategy' => CommunicationTemplate::STRATEGY_CLIENT_PCP,
        'channel' => CommunicationTemplate::CHANNEL_EMAIL,
    ]);

    expect(fn () => $resolver->assertResolvable($missingTemplate, $resolver->resolve($missingTemplate, $clientWithoutPcp)))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('channel service records sent and failed status correctly with fake drivers', function () {
    $communication = Communication::withoutGlobalScopes()->make([
        'channel' => Communication::CHANNEL_EMAIL,
        'recipient_email' => 'test@example.com',
        'status' => Communication::STATUS_QUEUED,
    ]);

    $result = (new FakeEmailChannel)->send($communication);
    expect($result->success)->toBeTrue();

    config(['communications.channels.fax' => 'fake_fail']);
    $fax = Communication::withoutGlobalScopes()->make([
        'channel' => Communication::CHANNEL_FAX,
        'recipient_fax' => '5551234567',
    ]);
    $failed = (new FakeFaxChannel)->send($fax);
    expect($failed->success)->toBeFalse();
});

test('notification service creates minimal PHI safe notifications', function () {
    $org = test()->createOrganization();
    $user = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $service = app(CommunicationNotificationService::class);
    $notification = $service->create(
        $user,
        'communication_sent',
        'Communication sent',
        'An email was processed for your review.'
    );

    expect($notification->body)->not->toContain('SSN')
        ->and(strlen($notification->body))->toBeLessThan(120);
});

test('policies allow and deny expected users', function () {
    seedModuleBasics();
    $org = test()->createOrganization();
    $admin = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);
    $employee = test()->createUser(User::ROLE_EMPLOYEE, ['organization_id' => $org->id]);

    expect($admin->can('viewAny', Communication::class))->toBeTrue()
        ->and($employee->can('viewAny', Communication::class))->toBeTrue();

    $role = \App\Models\Role::where('slug', 'employee')->first();
    $role->permissions()->detach(
        \App\Models\Permission::where('slug', 'view_communications')->pluck('id')
    );
    $employee->unsetRelation('roleModel');

    expect($employee->can('viewAny', Communication::class))->toBeFalse();
});

test('send rate limit cap covers a full billing cycle batch without throttling a real cycle', function () {
    $org = test()->createOrganization();
    $actor = test()->createUser(User::ROLE_ADMIN, ['organization_id' => $org->id]);

    $key = 'communication-send:'.$actor->id;
    RateLimiter::clear($key);

    $service = app(CommunicationSendService::class);
    $assertRateLimit = new ReflectionMethod($service, 'assertRateLimit');
    $assertRateLimit->setAccessible(true);

    $maxAttempts = (int) config('communications.send_rate_limit.max_attempts');

    // Regression for the "56 of 66 failed — too many send attempts" bug: a
    // monthly billing cycle submits every eligible claim for one org
    // back-to-back under a single automation actor with no delay between
    // sends, so the cap must clear a realistic batch size.
    $billingCycleBatchSize = 66;
    expect($maxAttempts)->toBeGreaterThan($billingCycleBatchSize);

    for ($i = 0; $i < $billingCycleBatchSize; $i++) {
        $assertRateLimit->invoke($service, $actor);
    }

    // The limiter still protects against runaway/duplicate sends beyond the
    // configured cap — this isn't just the throttle disabled.
    for ($i = $billingCycleBatchSize; $i < $maxAttempts; $i++) {
        $assertRateLimit->invoke($service, $actor);
    }

    expect(fn () => $assertRateLimit->invoke($service, $actor))
        ->toThrow(ValidationException::class, 'Too many send attempts. Please wait before trying again.');

    RateLimiter::clear($key);
});
