<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\CaregiverAssignment;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Resolves the caregiver (Employee) behind the authenticated mobile user
 * and exposes the set of clients that caregiver is allowed to act on.
 */
trait ResolvesCaregiver
{
    protected function caregiver(): Employee
    {
        $employee = auth()->user()?->employee;

        abort_unless($employee, 403, 'This account is not linked to a caregiver profile.');

        return $employee;
    }

    /**
     * Client IDs this caregiver is currently assigned to — the union of the
     * caregiver-module assignments and the legacy client_employee pivot.
     *
     * @return Collection<int, int>
     */
    protected function assignedClientIds(Employee $caregiver): Collection
    {
        $assignmentIds = CaregiverAssignment::query()
            ->where('employee_id', $caregiver->id)
            ->whereNull('ended_at')
            ->pluck('client_id');

        $pivotIds = $caregiver->clients()->pluck('clients.id');

        return $assignmentIds->merge($pivotIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
