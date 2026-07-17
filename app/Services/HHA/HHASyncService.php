<?php

namespace App\Services\HHA;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Services\VisitReportService;
use Carbon\CarbonInterface;

/**
 * Pushes local EVV visits and caregiver records to HHAeXchange when connected.
 */
class HHASyncService
{
    public function __construct(
        protected HHAExchangeClient $client,
        protected VisitReportService $visitReports,
    ) {}

    /**
     * @return array{synced: int, skipped: int, failed: int}
     */
    public function syncPendingVisits(?int $organizationId = null, int $limit = 50): array
    {
        $counts = ['synced' => 0, 'skipped' => 0, 'failed' => 0];

        if (! $this->client->isConnected()) {
            return $counts;
        }

        $schedules = Schedule::withoutGlobalScopes()
            ->with(['client', 'employee', 'organization'])
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', Schedule::STATUS_COMPLETED)
            ->whereNotNull('actual_clock_in')
            ->whereNotNull('actual_clock_out')
            ->latest('actual_clock_out')
            ->limit($limit * 3)
            ->get()
            ->filter(fn (Schedule $schedule) => $this->shouldSyncVisit($schedule))
            ->take($limit);

        foreach ($schedules as $schedule) {
            $result = $this->syncVisit($schedule);

            if ($result === 'synced') {
                $counts['synced']++;
            } elseif ($result === 'failed') {
                $counts['failed']++;
            } else {
                $counts['skipped']++;
            }
        }

        return $counts;
    }

    /**
     * @return array{synced: int, skipped: int, failed: int}
     */
    public function syncCaregivers(?int $organizationId = null, int $limit = 50): array
    {
        $counts = ['synced' => 0, 'skipped' => 0, 'failed' => 0];

        if (! $this->client->isConnected()) {
            return $counts;
        }

        $employees = Employee::withoutGlobalScopes()
            ->with('organization')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('position', 'Caregiver')
            ->where(function ($q) {
                $q->whereNull('evv_exempt')->orWhere('evv_exempt', false);
            })
            ->orderBy('id')
            ->limit($limit * 2)
            ->get()
            ->filter(fn (Employee $employee) => ! $this->caregiverAlreadySynced($employee))
            ->take($limit);

        foreach ($employees as $employee) {
            $result = $this->syncCaregiver($employee);

            if ($result === 'synced') {
                $counts['synced']++;
            } elseif ($result === 'failed') {
                $counts['failed']++;
            } else {
                $counts['skipped']++;
            }
        }

        return $counts;
    }

    /** @return 'synced'|'skipped'|'failed' */
    public function syncVisit(Schedule $schedule): string
    {
        if (! $this->client->isConnected()) {
            return 'skipped';
        }

        if (! $this->shouldSyncVisit($schedule)) {
            return 'skipped';
        }

        $schedule->loadMissing(['client', 'employee', 'organization']);

        if ($schedule->employee) {
            $this->syncCaregiver($schedule->employee);
        }

        $payload = $this->buildVisitBatchPayload([$this->buildVisitPayload($schedule)]);
        $result = $this->client->exportVisit($payload);

        $metadata = $schedule->metadata ?? [];
        $metadata['hha_export'] = [
            'status' => $result['success'] ? 'synced' : 'failed',
            'external_id' => $result['external_id'],
            'transaction_id' => $result['transaction_id'] ?? null,
            'evvmsid' => $metadata['hha_export']['evvmsid'] ?? null,
            'message' => $result['message'],
            'http_status' => $result['http_status'] ?? null,
            'synced_at' => now()->toIso8601String(),
        ];

        if ($result['success'] && ! empty($result['transaction_id'])) {
            $tx = $this->client->getTransaction((string) $result['transaction_id']);
            if ($tx['success'] && ! empty($tx['evvmsid'])) {
                $metadata['hha_export']['evvmsid'] = $tx['evvmsid'];
            }
        }

        $schedule->forceFill(['metadata' => $metadata])->saveQuietly();

        return $result['success'] ? 'synced' : 'failed';
    }

    /** @return 'synced'|'skipped'|'failed' */
    public function syncCaregiver(Employee $employee, ?array $overrides = null): string
    {
        if (! $this->client->isConnected() || $employee->evv_exempt) {
            return 'skipped';
        }

        if ($overrides === null && $this->caregiverAlreadySynced($employee)) {
            return 'skipped';
        }

        $employee->loadMissing('organization');
        $payload = $this->buildCaregiverPayload($employee, $overrides ?? []);
        $result = $this->client->syncCaregiver($payload);

        $metadata = $employee->metadata ?? [];
        if (! is_array($metadata)) {
            $metadata = [];
        }

        $metadata['hha_caregiver'] = [
            'status' => $result['success'] ? 'synced' : 'failed',
            'external_id' => $result['external_id'],
            'transaction_id' => $result['transaction_id'] ?? null,
            'message' => $result['message'],
            'http_status' => $result['http_status'] ?? null,
            'synced_at' => now()->toIso8601String(),
        ];

        $employee->forceFill(['metadata' => $metadata])->saveQuietly();

        return $result['success'] ? 'synced' : 'failed';
    }

    /**
     * @param  list<array<string, mixed>>  $visits
     * @return array{visits: list<array<string, mixed>>}
     */
    public function buildVisitBatchPayload(array $visits): array
    {
        return ['visits' => array_values($visits)];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildVisitPayload(Schedule $schedule, array $overrides = []): array
    {
        $client = $schedule->client;
        $employee = $schedule->employee;
        $organization = $schedule->organization;
        $defaults = config('hha.defaults', []);
        $timezone = (string) ($defaults['timezone'] ?? 'US/Eastern');
        $callType = (string) ($defaults['call_type'] ?? 'Mobile');

        $clockIn = $schedule->actual_clock_in;
        $clockOut = $schedule->actual_clock_out;
        $scheduledStart = $schedule->start_time
            ? $this->combineDateTime($schedule->date, $schedule->start_time)
            : $clockIn;
        $scheduledEnd = $schedule->end_time
            ? $this->combineDateTime($schedule->date, $schedule->end_time)
            : $clockOut;

        $memberId = $client?->member_id ?: (string) ($client?->id ?? '');
        $caregiverExternalId = $employee?->champs_provider_id ?: (string) ($employee?->id ?? $schedule->employee_id);
        $address = $this->parseAddress($client?->address);

        $payload = [
            'providerTaxId' => $this->providerTaxId($organization),
            'office' => [
                'qualifier' => 'NPI',
                'identifier' => $this->officeNpi($organization),
            ],
            'member' => array_filter([
                'qualifier' => 'MedicaidID',
                'identifier' => (string) $memberId,
                'admissionId' => data_get($client?->metadata ?? [], 'admission_id'),
            ], fn ($value) => filled($value)),
            'caregiver' => [
                'qualifier' => 'ExternalID',
                'identifier' => (string) $caregiverExternalId,
            ],
            'payerId' => (string) (config('hha.payer_id') ?: ''),
            'externalVisitId' => (string) $schedule->id,
            'procedureCode' => (string) (
                data_get($schedule->metadata ?? [], 'procedure_code')
                ?: data_get($client?->metadata ?? [], 'procedure_code')
                ?: 'T1019'
            ),
            'timezone' => $timezone,
            'scheduleStartTime' => $this->formatApiDateTime($scheduledStart),
            'scheduleEndTime' => $this->formatApiDateTime($scheduledEnd),
            'visitStartDateTime' => $this->formatApiDateTime($clockIn),
            'visitEndDateTime' => $this->formatApiDateTime($clockOut),
            'timesheetRequired' => true,
            'timesheetApproved' => true,
            'evv' => [
                'clockIn' => array_filter([
                    'callDateTime' => $this->formatApiDateTime($clockIn),
                    'callType' => $callType,
                    'callLatitude' => $schedule->clock_in_latitude !== null ? (float) $schedule->clock_in_latitude : null,
                    'callLongitude' => $schedule->clock_in_longitude !== null ? (float) $schedule->clock_in_longitude : null,
                    'serviceAddress' => $address ?: null,
                ], fn ($value) => $value !== null && $value !== ''),
                'clockOut' => array_filter([
                    'callDateTime' => $this->formatApiDateTime($clockOut),
                    'callType' => $callType,
                    'callLatitude' => $schedule->clock_out_latitude !== null ? (float) $schedule->clock_out_latitude : null,
                    'callLongitude' => $schedule->clock_out_longitude !== null ? (float) $schedule->clock_out_longitude : null,
                    'serviceAddress' => $address ?: null,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
            'missedVisit' => [
                'missed' => false,
                'reasonCode' => '',
                'actionCode' => '',
                'notes' => '',
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildCaregiverPayload(Employee $employee, array $overrides = []): array
    {
        $employee->loadMissing('organization');
        $defaults = config('hha.defaults', []);
        $gender = $this->normalizeGender($employee->gender);

        $payload = [
            'providerTaxId' => $this->providerTaxId($employee->organization),
            'qualifier' => 'ExternalID',
            'externalID' => (string) ($employee->champs_provider_id ?: $employee->id),
            'ssn' => (string) ($defaults['ssn'] ?? '999999999'),
            'dateOfBirth' => $employee->date_of_birth?->format('Y-m-d') ?: '1980-01-01',
            'lastName' => (string) $employee->last_name,
            'firstName' => (string) $employee->first_name,
            'gender' => $gender,
            'email' => $employee->email ?: null,
            'phoneNumber' => $this->digitsOnly($employee->phone, 10),
            'type' => (string) ($defaults['caregiver_type'] ?? 'Both'),
            'professionalLicenseNumber' => (string) (
                $employee->champs_provider_id
                ?: ($defaults['professional_license_number'] ?? '999999999999')
            ),
            'hireDate' => $employee->hire_date?->format('Y-m-d')
                ?: (string) ($defaults['hire_date'] ?? '1900-01-02'),
        ];

        $address = $this->parseAddress($employee->address ?? null);
        if ($address !== []) {
            $payload['address'] = $address;
        }

        return array_replace_recursive(array_filter($payload, fn ($value) => $value !== null && $value !== ''), $overrides);
    }

    /**
     * Build a missed-visit payload for Phase 1 Test 6-001.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function buildMissedVisitPayload(Schedule $schedule, string $reasonCode, string $actionCode, array $overrides = []): array
    {
        $visit = $this->buildVisitPayload($schedule, $overrides);
        $visit['missedVisit'] = [
            'missed' => true,
            'reasonCode' => $reasonCode,
            'actionCode' => $actionCode,
            'notes' => $overrides['missedVisit']['notes'] ?? 'Phase 1 missed visit test',
        ];
        $visit['evv'] = [
            'clockIn' => null,
            'clockOut' => null,
        ];

        return $visit;
    }

    protected function shouldSyncVisit(Schedule $schedule): bool
    {
        if (($schedule->metadata['hha_export']['status'] ?? null) === 'synced') {
            return false;
        }

        if (! $schedule->evv_status || ! $this->visitReports->hasCleanTimeData($schedule)) {
            return false;
        }

        return $schedule->actual_clock_in !== null && $schedule->actual_clock_out !== null;
    }

    protected function caregiverAlreadySynced(Employee $employee): bool
    {
        $metadata = $employee->metadata ?? [];

        return is_array($metadata) && (($metadata['hha_caregiver']['status'] ?? null) === 'synced');
    }

    protected function providerTaxId(?Organization $organization): string
    {
        $fromConfig = preg_replace('/\D/', '', (string) config('hha.provider_tax_id', '')) ?: '';
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return preg_replace('/\D/', '', (string) ($organization?->tax_id_ein ?? '')) ?: '';
    }

    protected function officeNpi(?Organization $organization): string
    {
        $fromConfig = (string) (config('hha.office_npi') ?: '');
        if ($fromConfig !== '') {
            return $fromConfig;
        }

        return (string) ($organization?->agency_npi ?? '');
    }

    protected function normalizeGender(?string $gender): string
    {
        $value = strtolower(trim((string) $gender));

        return match (true) {
            in_array($value, ['m', 'male'], true) => 'Male',
            in_array($value, ['f', 'female'], true) => 'Female',
            default => (string) (config('hha.defaults.gender') ?: 'Other'),
        };
    }

    protected function digitsOnly(?string $value, ?int $length = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?: '';

        if ($digits === '') {
            return null;
        }

        if ($length !== null && strlen($digits) > $length) {
            return substr($digits, -$length);
        }

        return $digits;
    }

    /**
     * @return array<string, string>
     */
    protected function parseAddress(?string $address): array
    {
        if (! filled($address)) {
            return [];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', (string) $address))));

        if ($parts === []) {
            return ['addressLine1' => (string) $address];
        }

        $result = ['addressLine1' => $parts[0]];

        if (isset($parts[1])) {
            $result['city'] = $parts[1];
        }

        if (isset($parts[2])) {
            $stateZip = preg_split('/\s+/', trim($parts[2])) ?: [];
            if (! empty($stateZip[0])) {
                $result['state'] = strtoupper(substr($stateZip[0], 0, 2));
            }
            if (! empty($stateZip[1])) {
                $result['zipcode'] = preg_replace('/\D/', '', $stateZip[1]) ?: $stateZip[1];
            }
        }

        return $result;
    }

    protected function combineDateTime(mixed $date, mixed $time): ?CarbonInterface
    {
        if (! $date || ! $time) {
            return null;
        }

        try {
            $datePart = $date instanceof CarbonInterface ? $date->format('Y-m-d') : (string) $date;
            $timePart = $time instanceof CarbonInterface ? $time->format('H:i:s') : (string) $time;

            return \Carbon\Carbon::parse($datePart.' '.$timePart);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function formatApiDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $carbon = $value instanceof CarbonInterface
                ? $value
                : \Carbon\Carbon::parse($value);

            return $carbon->format('Y-m-d\TH:i:s.00');
        } catch (\Throwable) {
            return null;
        }
    }
}
