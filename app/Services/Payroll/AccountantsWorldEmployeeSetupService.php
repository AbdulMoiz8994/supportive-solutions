<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AccountantsWorldEmployeeSetupService
{
    public function __construct(
        protected AccountantsWorldClient $client,
        protected AccountantsWorldErrorFormatter $errorFormatter,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{success: bool, message: string, employee_id: ?string}
     */
    public function createFromForm(Employee $employee, array $validated): array
    {
        $payload = $this->payloadFromValidated($validated);

        return $this->attemptSetup($employee, $payload);
    }

    /**
     * @return array{success: bool, message: string, employee_id: ?string}
     */
    public function retry(Employee $employee): array
    {
        $payload = $employee->aw_setup_payload;

        if (! is_array($payload) || $payload === []) {
            return [
                'success' => false,
                'message' => 'No saved setup data for this caregiver — submit the form again.',
                'employee_id' => null,
            ];
        }

        return $this->attemptSetup($employee, $payload);
    }

    /**
     * @param  array{search?: ?string, context?: ?string, sort?: ?string}  $filters
     */
    public function paginateAwaitingSetup(?int $organizationId, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Employee::query()
            ->when($organizationId, fn ($builder) => $builder->where('organization_id', $organizationId))
            ->awaitingAccountantsWorldSetup();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search) {
                $builder->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['context']) && in_array($filters['context'], [
            AccountantsWorldErrorFormatter::CONTEXT_CREATE,
            AccountantsWorldErrorFormatter::CONTEXT_VERIFY,
            AccountantsWorldErrorFormatter::CONTEXT_LEGACY,
        ], true)) {
            $query->where('aw_setup_error_context', $filters['context']);
        }

        match ($filters['sort'] ?? 'recent') {
            'name' => $query->orderBy('last_name')->orderBy('first_name'),
            'oldest' => $query->orderBy('aw_setup_attempted_at'),
            default => $query->orderByDesc('aw_setup_attempted_at'),
        };

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function listAwaitingSetup(?int $organizationId): Collection
    {
        return Employee::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->awaitingAccountantsWorldSetup()
            ->orderByDesc('aw_setup_attempted_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function listEligibleForSetup(?int $organizationId): Collection
    {
        return Employee::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->where(function ($query) {
                $query->whereNull('aw_setup_status')
                    ->orWhere('aw_setup_status', Employee::AW_SETUP_FAILED);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'aw_setup_status', 'hourly_wage']);
    }

    /**
     * @return array{success: bool, message: string, employee_id: ?string}
     */
    public function verifyAndMarkSynced(Employee $employee, ?string $awEmployeeId = null): array
    {
        $name = trim("{$employee->first_name} {$employee->last_name}");

        if ($awEmployeeId) {
            $result = $this->client->getEmployee($awEmployeeId);
        } elseif (is_array($employee->aw_setup_payload) && ! empty($employee->aw_setup_payload['ssn'])) {
            $result = $this->client->lookupEmployeeBySsn((string) $employee->aw_setup_payload['ssn']);
        } else {
            return [
                'success' => false,
                'message' => "{$name} could not be verified — enter an AW employee ID or re-submit the setup form with SSN.",
                'employee_id' => null,
            ];
        }

        if (! $result['success']) {
            $error = $this->formatFailure($result, AccountantsWorldErrorFormatter::CONTEXT_VERIFY);

            $employee->update([
                'aw_setup_error' => $error,
                'aw_setup_http_status' => $result['raw']['http_status'] ?? null,
                'aw_setup_error_context' => AccountantsWorldErrorFormatter::CONTEXT_VERIFY,
                'aw_setup_attempted_at' => now(),
            ]);

            return [
                'success' => false,
                'message' => "{$name} was not found in AccountantsWorld. {$error}",
                'employee_id' => null,
            ];
        }

        $confirmedId = $result['employee_id'] ?? $awEmployeeId;
        $this->markManuallySynced($employee, $confirmedId);

        $awId = $confirmedId ? " (ID: {$confirmedId})" : '';

        return [
            'success' => true,
            'message' => "{$name} verified in AccountantsWorld and marked synced{$awId}.",
            'employee_id' => $confirmedId,
        ];
    }

    public function markManuallySynced(Employee $employee, ?string $awEmployeeId = null): void
    {
        $employee->update([
            'payroll_system' => 'AccountantsWorld',
            'aw_employee_id' => $awEmployeeId ?: $employee->aw_employee_id,
            'aw_setup_status' => Employee::AW_SETUP_SYNCED,
            'aw_setup_error' => null,
            'aw_setup_attempted_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function payloadFromValidated(array $validated): array
    {
        return [
            'firstName' => $validated['aw_first_name'],
            'lastName' => $validated['aw_last_name'],
            'ssn' => $validated['aw_ssn'],
            'payRate' => $validated['aw_pay_rate'],
            'payType' => $validated['aw_pay_type'],
            'department' => $validated['aw_dept'] ?? 'Caregivers',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string, employee_id: ?string}
     */
    protected function attemptSetup(Employee $employee, array $payload): array
    {
        $result = $this->client->createEmployee($payload);
        $name = trim("{$employee->first_name} {$employee->last_name}");

        if ($result['success']) {
            $employee->update([
                'payroll_system' => 'AccountantsWorld',
                'aw_employee_id' => $result['employee_id'],
                'aw_setup_status' => Employee::AW_SETUP_SYNCED,
                'aw_setup_error' => null,
                'aw_setup_payload' => $payload,
                'aw_setup_attempted_at' => now(),
            ]);

            $awId = $result['employee_id'] ? " (ID: {$result['employee_id']})" : '';

            return [
                'success' => true,
                'message' => "{$name} created in AccountantsWorld{$awId}.",
                'employee_id' => $result['employee_id'],
            ];
        }

        $error = $this->formatFailure($result, AccountantsWorldErrorFormatter::CONTEXT_CREATE);

        $employee->update([
            'aw_setup_status' => Employee::AW_SETUP_FAILED,
            'aw_setup_error' => $error,
            'aw_setup_http_status' => $result['raw']['http_status'] ?? null,
            'aw_setup_error_context' => AccountantsWorldErrorFormatter::CONTEXT_CREATE,
            'aw_setup_payload' => $payload,
            'aw_setup_attempted_at' => now(),
        ]);

        return [
            'success' => false,
            'message' => "{$name} could not be created in AccountantsWorld. {$error} The setup is listed below — retry when the API is available.",
            'employee_id' => null,
        ];
    }

    /**
     * @param  array{success?: bool, raw?: array<string, mixed>}  $result
     */
    protected function formatFailure(array $result, string $context): string
    {
        return $this->errorFormatter->formatFromApiResult($result, $context);
    }
}
