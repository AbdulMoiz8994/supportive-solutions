<?php

namespace App\Services;

use App\Models\CaregiverAssignment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Schedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class VisitSchedulingBoardService
{
    /**
     * @return array{
     *     weekStart: Carbon,
     *     weekEnd: Carbon,
     *     weekLabel: string,
     *     days: list<array{key: string, label: string, short: string, date: string, isToday: bool}>,
     *     caregivers: list<array{
     *         id: int,
     *         name: string,
     *         initials: string,
     *         client_count: int,
     *         days: array<string, list<array<string, mixed>>>
     *     }>,
     *     unassignedVisits: list<array<string, mixed>>,
     *     clientsWithoutVisits: list<array{id: int, name: string}>,
     *     stats: array{caregivers: int, visits: int, unassigned: int}
     * }
     */
    public function build(Carbon $weekStart, ?string $search = null): array
    {
        $weekStart = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $days = collect(CarbonPeriod::create($weekStart, $weekEnd))
            ->map(fn (Carbon $day) => [
                'key' => $day->format('Y-m-d'),
                'label' => $day->format('l'),
                'short' => $day->format('D j'),
                'date' => $day->toDateString(),
                'isToday' => $day->isToday(),
            ])
            ->values()
            ->all();

        $dayKeys = collect($days)->pluck('key')->all();

        $caregivers = Employee::query()
            ->where('status', 'Active')
            ->when(filled($search), function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
                $query->where(function ($q) use ($like) {
                    $q->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $visits = Schedule::query()
            ->with(['client', 'employee'])
            ->where('event_type', Schedule::EVENT_CARE_VISIT)
            ->whereNotIn('status', [Schedule::STATUS_CANCELLED, 'cancelled'])
            ->filterDateRange($weekStart->toDateString(), $weekEnd->toDateString())
            ->scheduleSort('start_at', 'asc')
            ->get();

        $visitsByCaregiverDay = $visits
            ->filter(fn (Schedule $s) => $s->employee_id)
            ->groupBy(fn (Schedule $s) => $s->employee_id.'|'.($s->start_at?->toDateString() ?? $s->date?->toDateString()));

        $caregiverRows = $caregivers->map(function (Employee $caregiver) use ($dayKeys, $visitsByCaregiverDay) {
            $days = [];

            foreach ($dayKeys as $dayKey) {
                $bucketKey = $caregiver->id.'|'.$dayKey;
                $dayVisits = $visitsByCaregiverDay->get($bucketKey, collect());

                $days[$dayKey] = $dayVisits->map(fn (Schedule $visit) => $this->mapVisit($visit))->values()->all();
            }

            $clientCount = CaregiverAssignment::query()
                ->where('employee_id', $caregiver->id)
                ->where('status', 'Active')
                ->whereNull('ended_at')
                ->count();

            return [
                'id' => $caregiver->id,
                'name' => trim($caregiver->first_name.' '.$caregiver->last_name),
                'initials' => strtoupper(mb_substr($caregiver->first_name, 0, 1).mb_substr($caregiver->last_name, 0, 1)),
                'client_count' => $clientCount,
                'days' => $days,
            ];
        })->values()->all();

        $unassignedVisits = $visits
            ->filter(fn (Schedule $s) => ! $s->employee_id)
            ->map(fn (Schedule $visit) => $this->mapVisit($visit))
            ->values()
            ->all();

        $scheduledClientIds = $visits->pluck('client_id')->filter()->unique();

        $clientsWithoutVisits = Client::query()
            ->where('status', 'Active')
            ->whereNotIn('id', $scheduledClientIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(12)
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => trim($client->first_name.' '.$client->last_name),
            ])
            ->values()
            ->all();

        return [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekLabel' => $weekStart->format('M j').' – '.$weekEnd->format('M j, Y'),
            'days' => $days,
            'caregivers' => $caregiverRows,
            'unassignedVisits' => $unassignedVisits,
            'clientsWithoutVisits' => $clientsWithoutVisits,
            'stats' => [
                'caregivers' => count($caregiverRows),
                'visits' => $visits->count(),
                'unassigned' => count($unassignedVisits),
            ],
        ];
    }

    /**
     * Active client assignments for a caregiver (used by quick-schedule modal).
     *
     * @return list<array{id: int, name: string}>
     */
    public function assignedClientsFor(int $employeeId): array
    {
        return CaregiverAssignment::query()
            ->with('client')
            ->where('employee_id', $employeeId)
            ->where('status', 'Active')
            ->whereNull('ended_at')
            ->get()
            ->map(function (CaregiverAssignment $assignment) {
                $client = $assignment->client;

                return [
                    'id' => $client?->id,
                    'name' => $client ? trim($client->first_name.' '.$client->last_name) : 'Unknown client',
                ];
            })
            ->filter(fn (array $row) => $row['id'])
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapVisit(Schedule $visit): array
    {
        $clientName = $visit->client
            ? trim($visit->client->first_name.' '.$visit->client->last_name)
            : 'Client';

        $start = $visit->start_at ?? ($visit->date && $visit->start_time
            ? Carbon::parse($visit->date->format('Y-m-d').' '.$visit->start_time)
            : null);

        $end = $visit->end_at ?? ($visit->date && $visit->end_time
            ? Carbon::parse($visit->date->format('Y-m-d').' '.$visit->end_time)
            : null);

        return [
            'id' => $visit->id,
            'title' => $visit->title,
            'client_id' => $visit->client_id,
            'client_name' => $clientName,
            'employee_id' => $visit->employee_id,
            'employee_name' => $visit->employee
                ? trim($visit->employee->first_name.' '.$visit->employee->last_name)
                : null,
            'date' => $start?->toDateString() ?? $visit->date?->toDateString(),
            'start_time' => $start?->format('g:i A') ?? '',
            'end_time' => $end?->format('g:i A') ?? '',
            'status' => $visit->status,
            'url' => route('schedule.show', $visit->id),
        ];
    }
}
