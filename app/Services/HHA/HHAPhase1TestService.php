<?php

namespace App\Services\HHA;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use Carbon\Carbon;
use RuntimeException;

/**
 * Runs HHAeXchange Third-Party EVV API Interface Phase 1 test scenarios.
 */
class HHAPhase1TestService
{
    public function __construct(
        protected HHAExchangeClient $client,
        protected HHASyncService $sync,
    ) {}

    /**
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    public function run(string $scenario, array $options = []): array
    {
        $scenario = strtoupper(trim($scenario));

        return match ($scenario) {
            '1-001' => $this->testAuthenticate(),
            '2-001' => $this->testCreateCaregiver($options),
            '3-001' => $this->testUpdateCaregiver($options),
            '4-001' => $this->testBatchVisits($options),
            '4-002' => $this->testTransactionStatus($options, '4-001'),
            '5-001' => $this->testSingleVisit($options),
            '5-002' => $this->testTransactionStatus($options, '5-001'),
            '6-001' => $this->testMissedVisit($options),
            '7-001' => $this->testEditVisit($options),
            '8-001' => $this->testDeleteVisit($options),
            '9-001' => $this->testMissingRequiredField($options),
            '10-001' => $this->testInvalidHireDate($options),
            default => [
                'success' => false,
                'scenario' => $scenario,
                'message' => 'Unknown scenario. Use 1-001 through 10-001.',
                'details' => [],
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function supportedScenarios(): array
    {
        return [
            '1-001', '2-001', '3-001', '4-001', '4-002',
            '5-001', '5-002', '6-001', '7-001', '8-001',
            '9-001', '10-001',
        ];
    }

    /**
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testAuthenticate(): array
    {
        config(['hha.attestation_status' => 'approved']);
        $this->client->forgetCachedToken();

        try {
            $token = $this->client->accessToken();

            return [
                'success' => $token !== '',
                'scenario' => '1-001',
                'message' => $token !== ''
                    ? 'Authenticate passed — access token acquired (HTTP 200).'
                    : 'Authenticate failed — empty access token.',
                'details' => [
                    'token_url' => $this->client->tokenUrl(),
                    'scope' => config('hha.scope'),
                    'token_preview' => $token !== '' ? substr($token, 0, 12).'…' : null,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'scenario' => '1-001',
                'message' => 'Authenticate failed: '.$e->getMessage(),
                'details' => ['token_url' => $this->client->tokenUrl()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testCreateCaregiver(array $options): array
    {
        $employee = $this->resolveEmployee($options);
        $payload = $this->sync->buildCaregiverPayload($employee, [
            'type' => 'Both',
            'hireDate' => '2020-10-01',
        ]);

        $result = $this->client->syncCaregiver($payload);

        return [
            'success' => $result['success'] && (int) ($result['http_status'] ?? 0) === 200,
            'scenario' => '2-001',
            'message' => $result['success']
                ? 'Create Caregiver passed — HTTP '.($result['http_status'] ?? '200').', transaction '.$result['transaction_id']
                : 'Create Caregiver failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'transaction_id' => $result['transaction_id'],
                'http_status' => $result['http_status'],
                'payload' => $payload,
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testUpdateCaregiver(array $options): array
    {
        $employee = $this->resolveEmployee($options);
        $payload = $this->sync->buildCaregiverPayload($employee, [
            'type' => 'Both',
            'hireDate' => '2020-10-07',
        ]);

        $result = $this->client->syncCaregiver($payload);

        return [
            'success' => $result['success'] && (int) ($result['http_status'] ?? 0) === 200,
            'scenario' => '3-001',
            'message' => $result['success']
                ? 'Update Caregiver passed — hireDate 2020-10-07, transaction '.$result['transaction_id']
                : 'Update Caregiver failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'transaction_id' => $result['transaction_id'],
                'http_status' => $result['http_status'],
                'payload' => $payload,
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testBatchVisits(array $options): array
    {
        $schedule = $this->resolveSchedule($options);
        $visits = [];

        for ($i = 1; $i <= 5; $i++) {
            $visits[] = $this->sync->buildVisitPayload($schedule, [
                'externalVisitId' => (string) $schedule->id.'-batch-'.$i,
                'scheduleStartTime' => Carbon::parse('2020-10-0'.$i.'T09:00:00')->format('Y-m-d\TH:i:s.00'),
                'scheduleEndTime' => Carbon::parse('2020-10-0'.$i.'T10:00:00')->format('Y-m-d\TH:i:s.00'),
                'visitStartDateTime' => Carbon::parse('2020-10-0'.$i.'T09:00:00')->format('Y-m-d\TH:i:s.00'),
                'visitEndDateTime' => Carbon::parse('2020-10-0'.$i.'T10:00:00')->format('Y-m-d\TH:i:s.00'),
                'evv' => [
                    'clockIn' => [
                        'callDateTime' => Carbon::parse('2020-10-0'.$i.'T09:00:00')->format('Y-m-d\TH:i:s.00'),
                        'callType' => 'Mobile',
                        'callLatitude' => 42.3314,
                        'callLongitude' => -83.0458,
                    ],
                    'clockOut' => [
                        'callDateTime' => Carbon::parse('2020-10-0'.$i.'T10:00:00')->format('Y-m-d\TH:i:s.00'),
                        'callType' => 'Mobile',
                        'callLatitude' => 42.3314,
                        'callLongitude' => -83.0458,
                    ],
                ],
            ]);
        }

        $payload = $this->sync->buildVisitBatchPayload($visits);
        $result = $this->client->exportVisit($payload);

        return [
            'success' => $result['success'] && (int) ($result['http_status'] ?? 0) === 202,
            'scenario' => '4-001',
            'message' => $result['success']
                ? 'Create Visits in Batch passed — HTTP '.($result['http_status'] ?? '202').', transaction '.$result['transaction_id']
                : 'Batch visits failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'transaction_id' => $result['transaction_id'],
                'http_status' => $result['http_status'],
                'visit_count' => 5,
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testSingleVisit(array $options): array
    {
        $schedule = $this->resolveSchedule($options);
        $payload = $this->sync->buildVisitBatchPayload([
            $this->sync->buildVisitPayload($schedule, [
                'externalVisitId' => (string) $schedule->id.'-single',
            ]),
        ]);
        $result = $this->client->exportVisit($payload);

        return [
            'success' => $result['success'] && (int) ($result['http_status'] ?? 0) === 202,
            'scenario' => '5-001',
            'message' => $result['success']
                ? 'Create Single Visit passed — transaction '.$result['transaction_id']
                : 'Single visit failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'transaction_id' => $result['transaction_id'],
                'http_status' => $result['http_status'],
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testTransactionStatus(array $options, string $sourceScenario): array
    {
        $transactionId = (string) ($options['transaction_id'] ?? '');

        if ($transactionId === '') {
            return [
                'success' => false,
                'scenario' => $sourceScenario === '4-001' ? '4-002' : '5-002',
                'message' => 'Provide --transaction-id= from '.$sourceScenario.'.',
                'details' => [],
            ];
        }

        $result = $this->client->getTransaction($transactionId);
        $scenario = $sourceScenario === '4-001' ? '4-002' : '5-002';

        return [
            'success' => $result['success'] && (int) ($result['http_status'] ?? 0) === 200,
            'scenario' => $scenario,
            'message' => $result['success']
                ? 'Transaction status passed — EVVMSID '.($result['evvmsid'] ?? 'pending')
                : 'Transaction status failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'transaction_id' => $transactionId,
                'evvmsid' => $result['evvmsid'],
                'http_status' => $result['http_status'],
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testMissedVisit(array $options): array
    {
        $schedule = $this->resolveSchedule($options);
        $reason = (string) ($options['reason_code'] ?? '1');
        $action = (string) ($options['action_code'] ?? '1');
        $visit = $this->sync->buildMissedVisitPayload($schedule, $reason, $action, [
            'externalVisitId' => (string) $schedule->id.'-missed',
        ]);
        $result = $this->client->exportVisit($this->sync->buildVisitBatchPayload([$visit]));

        return [
            'success' => $result['success'] && (int) ($result['http_status'] ?? 0) === 202,
            'scenario' => '6-001',
            'message' => $result['success']
                ? 'Missed Visit passed — transaction '.$result['transaction_id']
                : 'Missed visit failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'transaction_id' => $result['transaction_id'],
                'http_status' => $result['http_status'],
                'reason_code' => $reason,
                'action_code' => $action,
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testEditVisit(array $options): array
    {
        $evvmsid = (string) ($options['evvmsid'] ?? '');
        if ($evvmsid === '') {
            return [
                'success' => false,
                'scenario' => '7-001',
                'message' => 'Provide --evvmsid= from Test 5-002.',
                'details' => [],
            ];
        }

        $schedule = $this->resolveSchedule($options);
        $payload = $this->sync->buildVisitPayload($schedule, [
            'edited' => true,
            'reasonCode' => (string) ($options['reason_code'] ?? '1'),
            'actionCode' => (string) ($options['action_code'] ?? '1'),
        ]);
        $result = $this->client->updateVisit($evvmsid, $payload);

        return [
            'success' => $result['success'] && in_array((int) ($result['http_status'] ?? 0), [200, 202], true),
            'scenario' => '7-001',
            'message' => $result['success']
                ? 'Edit Confirmed Visit passed — transaction '.$result['transaction_id']
                : 'Edit visit failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'evvmsid' => $evvmsid,
                'transaction_id' => $result['transaction_id'],
                'http_status' => $result['http_status'],
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testDeleteVisit(array $options): array
    {
        $evvmsid = (string) ($options['evvmsid'] ?? '');
        if ($evvmsid === '') {
            return [
                'success' => false,
                'scenario' => '8-001',
                'message' => 'Provide --evvmsid= from Test 5-002.',
                'details' => [],
            ];
        }

        $result = $this->client->deleteVisit($evvmsid);

        return [
            'success' => $result['success'] && in_array((int) ($result['http_status'] ?? 0), [200, 202], true),
            'scenario' => '8-001',
            'message' => $result['success']
                ? 'Delete Visit passed — transaction '.$result['transaction_id']
                : 'Delete visit failed: '.($result['message'] ?? 'unknown'),
            'details' => [
                'evvmsid' => $evvmsid,
                'transaction_id' => $result['transaction_id'],
                'http_status' => $result['http_status'],
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testMissingRequiredField(array $options): array
    {
        $employee = $this->resolveEmployee($options);
        $payload = $this->sync->buildCaregiverPayload($employee, [
            'type' => 'Both',
            'hireDate' => '2020-10-01',
        ]);
        unset($payload['providerTaxId']);

        $result = $this->client->postCaregiverRaw($payload);
        $status = (int) ($result['http_status'] ?? 0);

        return [
            'success' => $status === 400,
            'scenario' => '9-001',
            'message' => $status === 400
                ? 'Missing Required Field passed — HTTP 400, transaction '.($result['transaction_id'] ?? 'n/a')
                : 'Expected HTTP 400, got '.$status.': '.($result['message'] ?? ''),
            'details' => [
                'http_status' => $status,
                'transaction_id' => $result['transaction_id'],
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, scenario: string, message: string, details: array<string, mixed>}
     */
    protected function testInvalidHireDate(array $options): array
    {
        $employee = $this->resolveEmployee($options);
        $payload = $this->sync->buildCaregiverPayload($employee, [
            'type' => 'Both',
            'hireDate' => '01-10-2020', // DD-MM-YYYY invalid per Test 10-001
        ]);

        $result = $this->client->postCaregiverRaw($payload);
        $status = (int) ($result['http_status'] ?? 0);

        return [
            'success' => $status === 400,
            'scenario' => '10-001',
            'message' => $status === 400
                ? 'Invalid Type Attribute passed — HTTP 400, transaction '.($result['transaction_id'] ?? 'n/a')
                : 'Expected HTTP 400, got '.$status.': '.($result['message'] ?? ''),
            'details' => [
                'http_status' => $status,
                'transaction_id' => $result['transaction_id'],
                'raw' => $result['raw'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveEmployee(array $options): Employee
    {
        if (! empty($options['employee_id'])) {
            $employee = Employee::withoutGlobalScopes()->with('organization')->find($options['employee_id']);
            if ($employee) {
                return $employee;
            }
        }

        $employee = Employee::withoutGlobalScopes()
            ->with('organization')
            ->where('position', 'Caregiver')
            ->orderBy('id')
            ->first();

        if ($employee) {
            return $employee;
        }

        $org = Organization::query()->first();
        if (! $org) {
            throw new RuntimeException('No organization found. Seed an org or pass --employee-id=.');
        }

        return Employee::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'first_name' => 'Phase1',
            'last_name' => 'Caregiver',
            'position' => 'Caregiver',
            'email' => 'phase1.caregiver@example.com',
            'phone' => '3135550100',
            'date_of_birth' => '1985-09-19',
            'hire_date' => '2020-10-01',
            'gender' => 'Other',
            'status' => 'Active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveSchedule(array $options): Schedule
    {
        if (! empty($options['schedule_id'])) {
            $schedule = Schedule::withoutGlobalScopes()
                ->with(['client', 'employee', 'organization'])
                ->find($options['schedule_id']);
            if ($schedule) {
                return $schedule;
            }
        }

        $schedule = Schedule::withoutGlobalScopes()
            ->with(['client', 'employee', 'organization'])
            ->whereNotNull('actual_clock_in')
            ->whereNotNull('actual_clock_out')
            ->latest('id')
            ->first();

        if ($schedule) {
            return $schedule;
        }

        throw new RuntimeException(
            'No completed schedule found. Pass --schedule-id= or create a clocked-in/out visit first. HHAX test member/payer data must be in the vault.'
        );
    }
}
