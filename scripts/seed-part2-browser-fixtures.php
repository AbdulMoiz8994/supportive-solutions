<?php

/**
 * Seed deterministic Part 2 browser regression fixtures.
 * Usage: php scripts/seed-part2-browser-fixtures.php
 */

use App\Models\AiAgent;
use App\Models\Client;
use App\Models\Employee;
use App\Models\FormTemplate;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Services\AiAgentRegistryService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$admin = User::where('email', 'admin@beydountech.com')->firstOrFail();
$orgId = (int) $admin->organization_id;

app(AiAgentRegistryService::class)->ensureCatalog($orgId);

$client = Client::withoutGlobalScopes()->updateOrCreate(
    ['organization_id' => $orgId, 'first_name' => 'Browser', 'last_name' => 'Part2Client'],
    [
        'status' => 'Active',
        'email' => 'browser.part2@example.com',
        'home_latitude' => 42.3314,
        'home_longitude' => -83.0458,
        'mco_name' => 'DHS Browser Plan',
        'address' => '100 Browser St',
    ]
);

$caregiver = Employee::withoutGlobalScopes()->updateOrCreate(
    ['organization_id' => $orgId, 'first_name' => 'Browser', 'last_name' => 'Part2Care'],
    ['status' => 'Active', 'email' => 'browser.care@example.com']
);

$mismatch = Schedule::withoutGlobalScopes()
    ->where('organization_id', $orgId)
    ->where('title', 'Part2 Location Mismatch Visit')
    ->first();

$payload = [
    'organization_id' => $orgId,
    'client_id' => $client->id,
    'employee_id' => $caregiver->id,
    'title' => 'Part2 Location Mismatch Visit',
    'status' => Schedule::STATUS_COMPLETED,
    'event_type' => Schedule::EVENT_CARE_VISIT,
    'date' => today()->toDateString(),
    'start_time' => '09:00:00',
    'end_time' => '10:00:00',
    'start_at' => today()->setTime(9, 0),
    'end_at' => today()->setTime(10, 0),
    'actual_clock_in' => today()->setTime(9, 0),
    'actual_clock_out' => today()->setTime(10, 0),
    'total_hours' => 1,
    'clock_in_latitude' => 42.3314,
    'clock_in_longitude' => -83.0458,
    'clock_out_latitude' => 43.5,
    'clock_out_longitude' => -84.5,
    'timezone' => config('app.timezone', 'UTC'),
    'metadata' => [
        'client_home_lat' => 42.3314,
        'client_home_lng' => -83.0458,
        'browser_fixture' => 'location_mismatch',
        'location_overrides' => [],
        'billable' => false,
        'units_deducted' => null,
    ],
    'evv_status' => false,
];

if ($mismatch) {
    $mismatch->forceFill($payload)->save();
} else {
    $mismatch = Schedule::withoutGlobalScopes()->forceCreate($payload);
}

Task::withoutGlobalScopes()->updateOrCreate(
    [
        'organization_id' => $orgId,
        'title' => 'Part2 Overdue Browser Task',
    ],
    [
        'status' => Task::STATUS_TODO,
        'priority' => Task::PRIORITY_MEDIUM,
        'due_date' => today()->subDays(2),
        'assignee_type' => Task::ASSIGNEE_USER,
        'assignee_user_id' => $admin->id,
        'client_id' => $client->id,
        'source' => Task::SOURCE_MANUAL,
    ]
);

FormTemplate::withoutGlobalScopes()->updateOrCreate(
    ['organization_id' => $orgId, 'slug' => 'part2-browser-consent'],
    [
        'name' => 'Part2 Browser Consent',
        'target_type' => FormTemplate::TARGET_CLIENT,
        'fields' => [
            ['key' => 'full_name', 'label' => 'Name', 'readonly' => true],
            ['key' => 'notes', 'label' => 'Notes'],
        ],
        'requires_signature' => true,
        'is_active' => true,
        'is_compliance_required' => true,
    ]
);

$formsAgent = AiAgent::withoutGlobalScopes()
    ->where('organization_id', $orgId)
    ->where('slug', 'forms')
    ->first();
if ($formsAgent) {
    $formsAgent->update(['is_enabled' => true, 'is_paused' => false]);
}

$documentAgent = AiAgent::withoutGlobalScopes()
    ->where('organization_id', $orgId)
    ->where('slug', 'document')
    ->first();
if ($documentAgent) {
    $documentAgent->update(['is_enabled' => true, 'is_paused' => false]);
}

echo json_encode([
    'ok' => true,
    'org_id' => $orgId,
    'client_id' => $client->id,
    'schedule_id' => $mismatch->id,
    'caregiver' => trim($caregiver->first_name.' '.$caregiver->last_name),
    'client' => trim($client->first_name.' '.$client->last_name),
], JSON_PRETTY_PRINT).PHP_EOL;

$fixturePath = storage_path('app/part2-browser-fixtures.json');
file_put_contents($fixturePath, json_encode([
    'ok' => true,
    'org_id' => $orgId,
    'client_id' => $client->id,
    'schedule_id' => $mismatch->id,
    'caregiver' => trim($caregiver->first_name.' '.$caregiver->last_name),
    'client' => trim($client->first_name.' '.$client->last_name),
], JSON_PRETTY_PRINT));
echo "Wrote {$fixturePath}\n";
