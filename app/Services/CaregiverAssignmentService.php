<?php

namespace App\Services;

use App\Models\CaregiverAssignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Schedule;

class CaregiverAssignmentService
{
    /**
     * Assign a caregiver to a client, ending any other active assignment.
     */
    public function assignToClient(
        Client $client,
        int $employeeId,
        ?string $relationship = null,
        bool $liveIn = false,
    ): CaregiverAssignment {
        $caregiver = Employee::withoutGlobalScopes()->findOrFail($employeeId);

        CaregiverAssignment::query()
            ->where('client_id', $client->id)
            ->where('status', 'Active')
            ->whereNull('ended_at')
            ->update([
                'status' => 'Ended',
                'ended_at' => now()->toDateString(),
            ]);

        $assignment = CaregiverAssignment::create([
            'organization_id' => $client->organization_id,
            'employee_id'     => $employeeId,
            'client_id'       => $client->id,
            'relationship'    => $relationship,
            'live_in'         => $liveIn,
            'evv_status'      => $liveIn ? 'Exempt (live-in)' : 'Active',
            'status'          => 'Active',
            'assigned_since'  => now(),
        ]);

        $client->employees()->syncWithoutDetaching([$employeeId]);

        return $assignment;
    }

    /**
     * When a care visit is scheduled for an unassigned client, confirm the caregiver assignment.
     */
    public function confirmFromCareVisit(Schedule $schedule): bool
    {
        if ($schedule->event_type !== Schedule::EVENT_CARE_VISIT) {
            return false;
        }

        if (! $schedule->client_id || ! $schedule->employee_id) {
            return false;
        }

        $client = Client::withoutGlobalScopes()->find($schedule->client_id);

        if (! $client) {
            return false;
        }

        $activeAssignment = CaregiverAssignment::query()
            ->where('client_id', $client->id)
            ->where('status', 'Active')
            ->whereNull('ended_at')
            ->first();

        if ($activeAssignment) {
            if ((int) $activeAssignment->employee_id === (int) $schedule->employee_id) {
                $client->employees()->syncWithoutDetaching([$schedule->employee_id]);

                return false;
            }

            return false;
        }

        $caregiver = Employee::withoutGlobalScopes()->find($schedule->employee_id);
        $liveIn = (bool) ($caregiver?->live_in ?? false);

        $this->assignToClient($client, (int) $schedule->employee_id, null, $liveIn);

        return true;
    }
}
