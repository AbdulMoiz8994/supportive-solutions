<?php

namespace Database\Seeders;

use App\Models\AiAgent;
use App\Models\Client;
use App\Models\Document;
use App\Models\Employee;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DashboardModulesSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->first();

        if (! $org) {
            return;
        }

        $this->seedFormTemplates($org->id);
        $this->seedTasks($org->id);
        $this->tagExistingVisitsAsCareVisits($org->id);
        $this->seedVisitReportScenarios($org->id);
        $this->seedFormSubmissions($org->id);
    }

    private function seedFormTemplates(int $orgId): void
    {
        $templates = [
            [
                'slug' => 'consent-to-care',
                'name' => 'Consent to Care',
                'description' => 'Client consent for home-care services.',
                'target_type' => FormTemplate::TARGET_CLIENT,
                'is_compliance_required' => true,
                'fields' => [
                    ['key' => 'full_name', 'label' => 'Client name', 'readonly' => true],
                    ['key' => 'dob', 'label' => 'Date of birth', 'readonly' => true],
                    ['key' => 'address', 'label' => 'Address', 'readonly' => true],
                    ['key' => 'consent_acknowledgment', 'label' => 'I consent to receive home care services', 'type' => 'text'],
                ],
            ],
            [
                'slug' => 'plan-of-care',
                'name' => 'Plan of Care',
                'description' => 'Care plan outlining services and goals.',
                'target_type' => FormTemplate::TARGET_CLIENT,
                'is_compliance_required' => true,
                'fields' => [
                    ['key' => 'full_name', 'label' => 'Client name', 'readonly' => true],
                    ['key' => 'program', 'label' => 'Program', 'readonly' => true],
                    ['key' => 'care_goals', 'label' => 'Care goals', 'type' => 'textarea'],
                    ['key' => 'services', 'label' => 'Authorized services', 'type' => 'textarea'],
                ],
            ],
            [
                'slug' => 'intake-form',
                'name' => 'Intake Form',
                'description' => 'New client intake paperwork.',
                'target_type' => FormTemplate::TARGET_CLIENT,
                'fields' => [
                    ['key' => 'full_name', 'label' => 'Client name', 'readonly' => true],
                    ['key' => 'phone', 'label' => 'Phone', 'readonly' => true],
                    ['key' => 'emergency_contact', 'label' => 'Emergency contact'],
                ],
            ],
            [
                'slug' => 'caregiver-agreement',
                'name' => 'Caregiver Agreement',
                'description' => 'Employment and conduct agreement for caregivers.',
                'target_type' => FormTemplate::TARGET_EMPLOYEE,
                'fields' => [
                    ['key' => 'full_name', 'label' => 'Caregiver name', 'readonly' => true],
                    ['key' => 'position', 'label' => 'Position', 'readonly' => true],
                    ['key' => 'agreement_terms', 'label' => 'Terms acknowledged', 'type' => 'text'],
                ],
            ],
            [
                'slug' => 'incident-report',
                'name' => 'Incident Report',
                'description' => 'Document an incident involving a client or caregiver.',
                'target_type' => FormTemplate::TARGET_CLIENT,
                'fields' => [
                    ['key' => 'full_name', 'label' => 'Client name', 'readonly' => true],
                    ['key' => 'incident_date', 'label' => 'Incident date'],
                    ['key' => 'incident_description', 'label' => 'Description', 'type' => 'textarea'],
                ],
            ],
        ];

        foreach ($templates as $template) {
            FormTemplate::query()->updateOrCreate(
                ['organization_id' => $orgId, 'slug' => $template['slug']],
                array_merge($template, [
                    'organization_id' => $orgId,
                    'requires_signature' => true,
                    'is_active' => true,
                ]),
            );
        }
    }

    private function seedTasks(int $orgId): void
    {
        $agent = AiAgent::query()
            ->where('organization_id', $orgId)
            ->where('slug', 'authorizations')
            ->first();

        $staff = User::query()->where('organization_id', $orgId)->where('role', 'Administrator')->first();

        Task::query()->firstOrCreate(
            [
                'organization_id' => $orgId,
                'title' => 'Review weekly visit report exceptions',
                'source' => Task::SOURCE_SYSTEM,
            ],
            [
                'description' => 'Visit/EVV Monitor Agent flagged visits needing human review.',
                'status' => Task::STATUS_TODO,
                'priority' => Task::PRIORITY_MEDIUM,
                'due_date' => today()->addDays(2),
                'assignee_type' => $agent ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
                'assignee_agent_id' => $agent?->id,
            ],
        );

        Task::query()->firstOrCreate(
            [
                'organization_id' => $orgId,
                'title' => 'Renew Anaya MICH authorization',
                'source' => Task::SOURCE_SYSTEM,
            ],
            [
                'description' => 'Authorization expiring soon — start renewal packet.',
                'status' => Task::STATUS_TODO,
                'priority' => Task::PRIORITY_HIGH,
                'due_date' => today()->addDays(3),
                'assignee_type' => $agent ? Task::ASSIGNEE_AGENT : Task::ASSIGNEE_USER,
                'assignee_agent_id' => $agent?->id,
            ],
        );

        Task::query()->firstOrCreate(
            [
                'organization_id' => $orgId,
                'title' => 'Follow up on overdue compliance form',
            ],
            [
                'description' => 'Client has not returned signed consent.',
                'status' => Task::STATUS_TODO,
                'priority' => Task::PRIORITY_HIGH,
                'due_date' => today()->subDays(2),
                'assignee_type' => Task::ASSIGNEE_USER,
                'assignee_user_id' => $staff?->id,
                'source' => Task::SOURCE_MANUAL,
            ],
        );

        Task::query()->firstOrCreate(
            [
                'organization_id' => $orgId,
                'title' => 'Re-open: fix visit report correction',
            ],
            [
                'description' => 'EVV time correction was rejected — needs another attempt.',
                'status' => Task::STATUS_REOPEN,
                'priority' => Task::PRIORITY_MEDIUM,
                'due_date' => today()->addDay(),
                'assignee_type' => Task::ASSIGNEE_USER,
                'assignee_user_id' => $staff?->id,
                'source' => Task::SOURCE_SYSTEM,
            ],
        );
    }

    private function tagExistingVisitsAsCareVisits(int $orgId): void
    {
        Schedule::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->whereNotNull('client_id')
            ->whereNotNull('employee_id')
            ->where(function ($q) {
                $q->whereNull('event_type')
                    ->orWhere('event_type', '')
                    ->orWhere('event_type', Schedule::EVENT_OTHER);
            })
            ->update(['event_type' => Schedule::EVENT_CARE_VISIT]);
    }

    private function seedVisitReportScenarios(int $orgId): void
    {
        $client = Client::withoutGlobalScopes()->where('organization_id', $orgId)->first();
        $caregiver = Employee::withoutGlobalScopes()->where('organization_id', $orgId)->where('position', 'Caregiver')->first();

        if (! $client || ! $caregiver) {
            return;
        }

        $homeLat = 42.3314;
        $homeLng = -83.0458;
        $today = today();

        $scenarios = [
            [
                'title' => 'Demo — In progress visit',
                'status' => Schedule::STATUS_CLOCKED_IN,
                'actual_clock_in' => now()->subMinutes(45),
                'actual_clock_out' => null,
                'clock_in_latitude' => $homeLat,
                'clock_in_longitude' => $homeLng,
                'metadata' => ['client_home_lat' => $homeLat, 'client_home_lng' => $homeLng],
            ],
            [
                'title' => 'Demo — Needs review (no clock-out)',
                'status' => Schedule::STATUS_CLOCKED_IN,
                'actual_clock_in' => now()->subHours(4),
                'actual_clock_out' => null,
                'start_at' => $today->copy()->setTime(8, 0),
                'end_at' => $today->copy()->setTime(10, 0),
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'clock_in_latitude' => $homeLat,
                'clock_in_longitude' => $homeLng,
                'metadata' => ['client_home_lat' => $homeLat, 'client_home_lng' => $homeLng],
            ],
            [
                'title' => 'Demo — Location mismatch',
                'status' => Schedule::STATUS_COMPLETED,
                'actual_clock_in' => $today->copy()->setTime(9, 2),
                'actual_clock_out' => $today->copy()->setTime(11, 0),
                'total_hours' => 1.97,
                'evv_status' => false,
                'clock_in_latitude' => 42.2800,
                'clock_in_longitude' => -83.7500,
                'clock_out_latitude' => 42.2800,
                'clock_out_longitude' => -83.7500,
                'metadata' => ['client_home_lat' => $homeLat, 'client_home_lng' => $homeLng],
            ],
            [
                'title' => 'Demo — Complete billable visit',
                'status' => Schedule::STATUS_COMPLETED,
                'actual_clock_in' => $today->copy()->setTime(13, 0),
                'actual_clock_out' => $today->copy()->setTime(15, 0),
                'total_hours' => 2.0,
                'evv_status' => true,
                'clock_in_latitude' => $homeLat,
                'clock_in_longitude' => $homeLng,
                'clock_out_latitude' => $homeLat,
                'clock_out_longitude' => $homeLng,
                'metadata' => [
                    'client_home_lat' => $homeLat,
                    'client_home_lng' => $homeLng,
                    'billable' => true,
                    'units' => 8,
                ],
            ],
        ];

        foreach ($scenarios as $index => $row) {
            Schedule::withoutGlobalScopes()->updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'client_id' => $client->id,
                    'employee_id' => $caregiver->id,
                    'title' => $row['title'],
                ],
                array_merge([
                    'event_type' => Schedule::EVENT_CARE_VISIT,
                    'date' => $today,
                    'start_time' => '09:00:00',
                    'end_time' => '11:00:00',
                    'start_at' => $today->copy()->setTime(9, 0),
                    'end_at' => $today->copy()->setTime(11, 0),
                    'visit_notes' => ['note' => 'Demo visit for Visit Reports module.'],
                ], $row),
            );
        }
    }

    private function seedFormSubmissions(int $orgId): void
    {
        $client = Client::withoutGlobalScopes()->where('organization_id', $orgId)->first();
        $consent = FormTemplate::query()->where('organization_id', $orgId)->where('slug', 'consent-to-care')->first();
        $intake = FormTemplate::query()->where('organization_id', $orgId)->where('slug', 'intake-form')->first();

        if (! $client || ! $consent) {
            return;
        }

        $signed = FormSubmission::query()->firstOrCreate(
            [
                'organization_id' => $orgId,
                'form_template_id' => $consent->id,
                'subject_type' => Client::class,
                'subject_id' => $client->id,
                'status' => FormSubmission::STATUS_SIGNED,
            ],
            [
                'field_values' => [
                    'full_name' => trim($client->first_name.' '.$client->last_name),
                    'consent_acknowledgment' => 'Yes',
                ],
                'signed_at' => now()->subDays(3),
                'signed_by_name' => trim($client->first_name.' '.$client->last_name),
                'locked_at' => now()->subDays(3),
            ],
        );

        if (! $signed->document_id) {
            $document = Document::create([
                'organization_id' => $orgId,
                'documentable_type' => Client::class,
                'documentable_id' => $client->id,
                'name' => 'Consent to Care (Signed)',
                'path' => 'documents/forms/consent-'.$signed->id.'.pdf',
                'disk' => 'local',
                'mime_type' => 'application/pdf',
                'file_size' => 0,
                'original_filename' => 'consent-signed.pdf',
                'type' => 'form',
                'category' => 'Compliance',
                'verification_status' => 'Verified',
                'is_signed' => true,
                'signed_at' => now()->subDays(3),
            ]);
            $signed->update(['document_id' => $document->id]);
        }

        if ($intake) {
            FormSubmission::query()->firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'form_template_id' => $intake->id,
                    'subject_type' => Client::class,
                    'subject_id' => $client->id,
                    'status' => FormSubmission::STATUS_AWAITING_SIGNATURE,
                ],
                [
                    'field_values' => ['full_name' => trim($client->first_name.' '.$client->last_name)],
                ],
            );
        }
    }
}
