<?php

namespace App\Services\Communication;

use App\Models\Client;
use App\Models\Communication;
use App\Models\Employee;
use App\Support\CommunicationPresenter;
use Illuminate\Support\Collection;

class CommunicationProfileService
{
    /**
     * Communications for a client profile tab: direct client rows + assigned caregivers.
     *
     * @return Collection<int, CommunicationPresenter>
     */
    public function presentersForClient(Client $client, int $limit = 50): Collection
    {
        $employeeIds = $client->relationLoaded('employees')
            ? $client->employees->pluck('id')
            : $client->employees()->pluck('employees.id');

        $communications = Communication::query()
            ->where('organization_id', $client->organization_id)
            ->where(function ($query) use ($client, $employeeIds) {
                $query->where(function ($q) use ($client) {
                    $q->where('related_type', Client::class)
                        ->where('related_id', $client->id);
                })->orWhere(function ($q) use ($client) {
                    $q->where('recipient_type', Client::class)
                        ->where('recipient_id', $client->id);
                });

                if ($employeeIds->isNotEmpty()) {
                    $query->orWhere(function ($q) use ($employeeIds) {
                        $q->where('related_type', Employee::class)
                            ->whereIn('related_id', $employeeIds);
                    })->orWhere(function ($q) use ($employeeIds) {
                        $q->where('recipient_type', Employee::class)
                            ->whereIn('recipient_id', $employeeIds);
                    });
                }
            })
            ->with(['sender'])
            ->latest()
            ->limit($limit)
            ->get();

        return $communications->map(fn (Communication $c) => CommunicationPresenter::make($c));
    }

    /**
     * @return Collection<int, CommunicationPresenter>
     */
    public function presentersForEmployee(Employee $employee, int $limit = 50): Collection
    {
        return Communication::query()
            ->where('organization_id', $employee->organization_id)
            ->where(function ($query) use ($employee) {
                $query->where(function ($q) use ($employee) {
                    $q->where('related_type', Employee::class)
                        ->where('related_id', $employee->id);
                })->orWhere(function ($q) use ($employee) {
                    $q->where('recipient_type', Employee::class)
                        ->where('recipient_id', $employee->id);
                });
            })
            ->with(['sender'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Communication $c) => CommunicationPresenter::make($c));
    }
}
