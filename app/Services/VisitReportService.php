<?php

namespace App\Services;

use App\Models\CareDetail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Models\VisitTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitReportService
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_MISSED = 'missed';

    /** Miles within which clock-in counts as at the client's home. */
    private const LOCATION_MATCH_MILES = 0.5;

    /** Hard ceiling for a single visit's duration (config: hha.max_visit_hours). */
    public static function maxVisitHours(): float
    {
        return (float) config('hha.max_visit_hours', 16);
    }

    public function pageData(?int $orgId, Request $request): array
    {
        $filters = $this->parseFilters($request);
        $viewer = $request->user();
        $visits = $this->fetchVisits($orgId, $filters);
        $rows = $visits->map(fn (Schedule $s) => $this->serializeRow($s, $viewer));

        return [
            'title' => 'Visit Reports',
            'filters' => $filters,
            'counters' => $this->buildCounters($orgId, $filters),
            'rows' => $rows,
            'caregivers' => $this->assigneeOptions($orgId, 'employee'),
            'clients' => $this->assigneeOptions($orgId, 'client'),
            'statusOptions' => $this->statusOptions(),
            'can_manage_visit_reports' => $viewer instanceof User
                && ($viewer->isSuperAdmin() || $viewer->hasPermission('manage_visit_reports')),
            'csrfToken' => csrf_token(),
        ];
    }

    public function detail(?int $orgId, int $scheduleId, ?User $viewer = null): ?array
    {
        $schedule = $this->baseQuery($orgId)
            ->with(['client', 'employee', 'visitTasks'])
            ->find($scheduleId);

        if (! $schedule) {
            return null;
        }

        return $this->serializeDetail($schedule, $viewer);
    }

    public function proposeTimeCorrection(
        ?int $orgId,
        int $scheduleId,
        User $user,
        string $field,
        string $proposedTime,
        string $reason,
    ): array {
        $schedule = $this->baseQuery($orgId)->findOrFail($scheduleId);

        if (! in_array($field, ['actual_clock_in', 'actual_clock_out'], true)) {
            throw new \InvalidArgumentException('Invalid time field.');
        }

        $original = $schedule->{$field}?->toIso8601String();
        $metadata = $this->metadataPreservingAudits($schedule);
        $corrections = $metadata['time_corrections'] ?? [];

        // Never silently overwrite EVV — seal the first captured time once.
        $originalEvv = $metadata['original_evv'] ?? [];
        if ($original !== null && empty($originalEvv[$field])) {
            $originalEvv[$field] = $original;
            $metadata['original_evv'] = $originalEvv;
        }

        $corrections[] = [
            'field' => $field,
            'original' => $original,
            'proposed' => Carbon::parse($proposedTime)->toIso8601String(),
            'reason' => $reason,
            'by_user_id' => $user->id,
            'by_user_name' => $user->name,
            'created_at' => now()->toIso8601String(),
            'approved' => false,
        ];

        $metadata['time_corrections'] = $corrections;
        $metadata['pending_review'] = true;
        $schedule->update(['metadata' => $metadata]);

        $fresh = $schedule->fresh(['client', 'employee']);
        app(EvvWorkflowQueueService::class)->syncNeedsReview($fresh, 'time correction awaiting approval');

        return $this->serializeDetail($fresh, $user);
    }

    public function approveTimeCorrection(?int $orgId, int $scheduleId, User $user): array
    {
        $schedule = $this->baseQuery($orgId)->findOrFail($scheduleId);
        $metadata = $this->metadataPreservingAudits($schedule);
        $corrections = $metadata['time_corrections'] ?? [];
        $pending = collect($corrections)->firstWhere('approved', false);

        if (! $pending) {
            throw new \RuntimeException('No pending time correction to approve.');
        }

        $field = $pending['field'];
        $proposed = Carbon::parse($pending['proposed']);

        $updates = [
            'metadata' => array_merge($metadata, [
                'time_corrections' => collect($corrections)->map(function (array $c) use ($user, $pending) {
                    if ($c === $pending) {
                        $c['approved'] = true;
                        $c['approved_by'] = $user->id;
                        $c['approved_at'] = now()->toIso8601String();
                    }

                    return $c;
                })->all(),
                'pending_review' => false,
            ]),
        ];

        $updates[$field] = $proposed;

        if ($field === 'actual_clock_out' && $schedule->actual_clock_in) {
            $updates['total_hours'] = app(ScheduleClockService::class)
                ->calculateWorkedHours($schedule->actual_clock_in, $proposed);
            $updates['status'] = Schedule::STATUS_COMPLETED;

            // Forgotten clock-outs rarely have out GPS — inherit clock-in / home
            // so a human-approved correction can still clear location review.
            if ($schedule->clock_out_latitude === null) {
                $home = $this->clientHomeCoordinates($schedule);
                $updates['clock_out_latitude'] = $schedule->clock_in_latitude ?? $home['lat'] ?? null;
                $updates['clock_out_longitude'] = $schedule->clock_in_longitude ?? $home['lng'] ?? null;
            }
        }

        if ($field === 'actual_clock_in' && $schedule->actual_clock_out) {
            $updates['total_hours'] = app(ScheduleClockService::class)
                ->calculateWorkedHours($proposed, $schedule->actual_clock_out);
        }

        $schedule->update($updates);
        $fresh = $schedule->fresh(['client', 'employee']);
        $fresh->update([
            'evv_status' => $this->hasCleanTimeData($fresh) && $this->locationMatches($fresh) === true,
        ]);
        $this->markBillableIfClean($fresh->fresh());
        $resolved = $fresh->fresh(['client', 'employee']);

        if ($this->isBillable($resolved) || $this->resolveReportStatus($resolved) === self::STATUS_COMPLETE) {
            app(EvvWorkflowQueueService::class)->resolveForVisit($resolved);
        }

        return $this->serializeDetail($resolved, $user);
    }

    public function markMissed(?int $orgId, int $scheduleId): array
    {
        $schedule = $this->baseQuery($orgId)->findOrFail($scheduleId);

        $schedule->update(['status' => Schedule::STATUS_MISSED]);

        app(TaskService::class)->createFromMissedVisit($schedule);

        $fresh = $schedule->fresh(['client', 'employee']);
        app(EvvWorkflowQueueService::class)->syncMissedVisit($fresh);

        return $this->serializeDetail($fresh);
    }

    /**
     * Human clears a location mismatch without mutating GPS columns.
     * Original pins stay on the schedule; audit trail is append-only.
     */
    public function approveLocationOverride(
        ?int $orgId,
        int $scheduleId,
        User $user,
        string $reason,
    ): array {
        $schedule = $this->baseQuery($orgId)->findOrFail($scheduleId);

        if ($this->hasApprovedLocationOverride($schedule)) {
            throw new \RuntimeException('Location mismatch already approved for this visit.');
        }

        if ($this->rawLocationMatches($schedule) !== false) {
            throw new \RuntimeException('This visit does not have a location mismatch to approve.');
        }

        $home = $this->clientHomeCoordinates($schedule);
        $metadata = $this->metadataPreservingAudits($schedule);
        $sealed = $this->sealedLocationOverrides($metadata);

        $entry = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'location_mismatch_approval',
            'approval_kind' => 'mismatch_clearance',
            'schedule_id' => $schedule->id,
            'original_clock_in' => $schedule->clock_in_latitude !== null
                ? ['lat' => (float) $schedule->clock_in_latitude, 'lng' => (float) $schedule->clock_in_longitude]
                : null,
            'original_clock_out' => $schedule->clock_out_latitude !== null
                ? ['lat' => (float) $schedule->clock_out_latitude, 'lng' => (float) $schedule->clock_out_longitude]
                : null,
            'home' => $home,
            'override_location' => null,
            'override_location_note' => 'No replacement GPS — original EVV pins retained; mismatch cleared by human approval.',
            'reason' => $reason,
            'by_user_id' => $user->id,
            'by_user_name' => $user->name,
            'approved' => true,
            'approved_by' => $user->id,
            'approved_at' => now()->toIso8601String(),
            'immutable' => true,
        ];
        $entry['entry_hash'] = $this->hashLocationOverrideEntry($entry);

        $metadata['location_overrides'] = array_values([...$sealed, $entry]);
        $metadata['pending_review'] = $this->hasPendingTimeCorrection($schedule);

        $schedule->update(['metadata' => $metadata]);
        $fresh = $schedule->fresh(['client', 'employee']);
        $fresh->update([
            'evv_status' => $this->hasCleanTimeData($fresh) && $this->locationMatches($fresh) === true,
        ]);
        $this->markBillableIfClean($fresh->fresh());
        $resolved = $fresh->fresh(['client', 'employee']);

        if ($this->isBillable($resolved) || $this->resolveReportStatus($resolved) === self::STATUS_COMPLETE) {
            app(EvvWorkflowQueueService::class)->resolveForVisit($resolved);
        }

        return $this->serializeDetail($resolved, $user);
    }

    /**
     * Reject silent mutation of sealed location override audit entries.
     * Used by tests and any future metadata rewrite paths.
     *
     * @param  list<array<string, mixed>>  $proposed
     */
    public function assertLocationOverridesImmutable(Schedule $schedule, array $proposed): void
    {
        $sealed = $this->sealedLocationOverrides($schedule->metadata ?? []);

        if (count($proposed) < count($sealed)) {
            throw new \RuntimeException('Approved location override audit entries cannot be removed.');
        }

        foreach ($sealed as $original) {
            $candidate = collect($proposed)->first(function ($entry) use ($original) {
                if (! is_array($entry)) {
                    return false;
                }

                if (! empty($original['id']) && ($entry['id'] ?? null) === $original['id']) {
                    return true;
                }

                return ($entry['entry_hash'] ?? null) === ($original['entry_hash'] ?? null);
            });

            if (! is_array($candidate)
                || $this->hashLocationOverrideEntry($candidate) !== ($original['entry_hash'] ?? null)
            ) {
                throw new \RuntimeException('Approved location override audit entries are immutable.');
            }
        }
    }

    public function resolveReportStatus(Schedule $schedule): string
    {
        $status = Schedule::normalizeStatus((string) $schedule->status);

        if (in_array($status, [Schedule::STATUS_MISSED, Schedule::STATUS_NO_SHOW], true)) {
            return self::STATUS_MISSED;
        }

        if ($status === Schedule::STATUS_SCHEDULED) {
            return self::STATUS_SCHEDULED;
        }

        if ($this->hasPendingTimeCorrection($schedule)) {
            return self::STATUS_NEEDS_REVIEW;
        }

        $clockedIn = $schedule->actual_clock_in !== null;
        $clockedOut = $schedule->actual_clock_out !== null;

        if ($clockedIn && ! $clockedOut) {
            // Far clock-in GPS while still open → Needs review immediately.
            if ($this->locationMatches($schedule) === false) {
                return self::STATUS_NEEDS_REVIEW;
            }

            if ($this->visitWindowPassed($schedule)) {
                return self::STATUS_NEEDS_REVIEW;
            }

            return self::STATUS_IN_PROGRESS;
        }

        if ($status === Schedule::STATUS_COMPLETED || ($clockedIn && $clockedOut)) {
            if ($this->needsReview($schedule)) {
                return self::STATUS_NEEDS_REVIEW;
            }

            return self::STATUS_COMPLETE;
        }

        if (in_array($status, Schedule::inProgressStatuses(), true)) {
            return self::STATUS_IN_PROGRESS;
        }

        return self::STATUS_SCHEDULED;
    }

    public function locationMatches(Schedule $schedule): ?bool
    {
        if ($this->hasApprovedLocationOverride($schedule)) {
            return true;
        }

        return $this->rawLocationMatches($schedule);
    }

    /**
     * GPS match against client home, ignoring human location overrides.
     */
    public function rawLocationMatches(Schedule $schedule): ?bool
    {
        $home = $this->clientHomeCoordinates($schedule);

        if (! $home) {
            // Cannot verify against a home address. Incomplete completed visits
            // must not pass; open visits stay undecided until home is set.
            if ($schedule->actual_clock_out) {
                return false;
            }

            return null;
        }

        $checks = [];

        if ($schedule->clock_in_latitude !== null && $schedule->clock_in_longitude !== null) {
            $checks[] = $this->haversineMiles(
                (float) $schedule->clock_in_latitude,
                (float) $schedule->clock_in_longitude,
                $home['lat'],
                $home['lng'],
            ) <= self::LOCATION_MATCH_MILES;
        }

        if ($schedule->actual_clock_out) {
            if ($schedule->clock_out_latitude === null || $schedule->clock_out_longitude === null) {
                // Completed visit without clock-out GPS cannot prove location.
                $checks[] = false;
            } else {
                $checks[] = $this->haversineMiles(
                    (float) $schedule->clock_out_latitude,
                    (float) $schedule->clock_out_longitude,
                    $home['lat'],
                    $home['lng'],
                ) <= self::LOCATION_MATCH_MILES;
            }
        }

        if ($checks === []) {
            return null;
        }

        // Any far or missing required pin flags a location mismatch.
        return ! in_array(false, $checks, true);
    }

    public function hasApprovedLocationOverride(Schedule $schedule): bool
    {
        $overrides = data_get($schedule->metadata, 'location_overrides', []);

        return collect($overrides)->contains(fn (array $o) => ! empty($o['approved']));
    }

    public function isBillable(Schedule $schedule): bool
    {
        return $this->resolveReportStatus($schedule) === self::STATUS_COMPLETE
            && $this->locationMatches($schedule) === true
            && ! $this->hasPendingTimeCorrection($schedule);
    }

    public function unitsForVisit(Schedule $schedule): int
    {
        $hours = (float) ($schedule->total_hours ?? 0);

        if ($hours <= 0 && $schedule->actual_clock_in && $schedule->actual_clock_out) {
            $hours = app(ScheduleClockService::class)
                ->calculateWorkedHours($schedule->actual_clock_in, $schedule->actual_clock_out);
        }

        return max(1, (int) round($hours * 4));
    }

    private function baseQuery(?int $orgId)
    {
        return Schedule::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('event_type', Schedule::EVENT_CARE_VISIT);
    }

    private function fetchVisits(?int $orgId, array $filters, bool $forCounters = false): Collection
    {
        $query = $this->baseQuery($orgId)
            ->with(['client', 'employee'])
            ->filterDateRange($filters['date_from'], $filters['date_to']);

        if ($filters['employee_id'] ?? null) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if ($filters['client_id'] ?? null) {
            $query->where('client_id', $filters['client_id']);
        }

        // Counters and status-filtered tables share the same population so
        // clicking a counter box yields a matching row count.
        $statusFiltered = (bool) ($filters['report_status'] ?? null);
        $fetchLimit = ($forCounters || $statusFiltered) ? 5000 : 500;

        $rows = $query->orderByDesc(DB::raw('COALESCE(start_at, date)'))
            ->limit($fetchLimit)
            ->get();

        if ($statusFiltered) {
            $rows = $rows->filter(
                fn (Schedule $s) => $this->resolveReportStatus($s) === $filters['report_status']
            )->values();
        }

        if ($forCounters || $statusFiltered) {
            return $rows;
        }

        return $rows->take(500)->values();
    }

    private function parseFilters(Request $request): array
    {
        $preset = $request->input('date_preset', 'this_week');
        [$from, $to] = $this->datePresetRange($preset, $request);

        return [
            'date_preset' => $preset,
            'date_from' => $from,
            'date_to' => $to,
            'employee_id' => $request->integer('employee_id') ?: null,
            'client_id' => $request->integer('client_id') ?: null,
            'report_status' => $request->input('report_status') ?: null,
        ];
    }

    private function datePresetRange(string $preset, Request $request): array
    {
        return match ($preset) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'this_week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'custom' => [
                $request->input('date_from', now()->startOfWeek()->toDateString()),
                $request->input('date_to', now()->endOfWeek()->toDateString()),
            ],
            default => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
        };
    }

    private function buildCounters(?int $orgId, array $filters): array
    {
        $visits = $this->fetchVisits($orgId, array_merge($filters, ['report_status' => null]), forCounters: true);

        $counts = [
            'today' => 0,
            'complete' => 0,
            'in_progress' => 0,
            'missed' => 0,
            'needs_review' => 0,
        ];

        foreach ($visits as $visit) {
            $reportStatus = $this->resolveReportStatus($visit);
            $visitDate = $visit->start_at?->toDateString() ?? $visit->date?->toDateString();

            if ($visitDate === today()->toDateString()) {
                $counts['today']++;
            }

            match ($reportStatus) {
                self::STATUS_COMPLETE => $counts['complete']++,
                self::STATUS_IN_PROGRESS => $counts['in_progress']++,
                self::STATUS_MISSED => $counts['missed']++,
                self::STATUS_NEEDS_REVIEW => $counts['needs_review']++,
                default => null,
            };
        }

        return [
            [
                'key' => 'today',
                'label' => 'Visits today',
                'value' => $counts['today'],
                'filter' => null,
                'date_preset' => 'today',
            ],
            ['key' => 'complete', 'label' => 'Completed', 'value' => $counts['complete'], 'filter' => self::STATUS_COMPLETE],
            ['key' => 'in_progress', 'label' => 'In progress', 'value' => $counts['in_progress'], 'filter' => self::STATUS_IN_PROGRESS],
            ['key' => 'missed', 'label' => 'Missed', 'value' => $counts['missed'], 'filter' => self::STATUS_MISSED],
            ['key' => 'needs_review', 'label' => 'Needs review', 'value' => $counts['needs_review'], 'filter' => self::STATUS_NEEDS_REVIEW],
        ];
    }

    private function serializeRow(Schedule $schedule, ?User $viewer = null): array
    {
        $reportStatus = $this->resolveReportStatus($schedule);
        $locationMatch = $this->locationMatches($schedule);
        $rawLocationMatch = $this->rawLocationMatches($schedule);
        $locationOverridden = $this->hasApprovedLocationOverride($schedule);
        $canManage = $this->viewerCanManage($viewer);

        $locationLabel = match (true) {
            $locationMatch === null => '—',
            $locationOverridden => 'Yes (Approved Override)',
            $locationMatch => 'Yes',
            default => 'No',
        };

        return [
            'id' => $schedule->id,
            'caregiver' => $schedule->employee
                ? trim($schedule->employee->first_name.' '.$schedule->employee->last_name)
                : '—',
            'client' => $schedule->client
                ? trim($schedule->client->first_name.' '.$schedule->client->last_name)
                : '—',
            'date' => $schedule->start_at?->format('M j, Y') ?? $schedule->date?->format('M j, Y') ?? '—',
            'scheduled_time' => $this->formatTimeRange($schedule),
            'clock_in' => $schedule->actual_clock_in?->format('g:i A') ?? '—',
            'clock_out' => $schedule->actual_clock_out?->format('g:i A') ?? '—',
            'duration' => $this->formatDuration($schedule),
            'location_match' => $locationLabel,
            'location_match_bool' => $locationMatch,
            'location_overridden' => $locationOverridden,
            'status' => $reportStatus,
            'status_label' => $this->statusLabel($reportStatus),
            'billable' => $this->isBillable($schedule),
            'can_fix' => $canManage && in_array($reportStatus, [self::STATUS_NEEDS_REVIEW], true),
            'can_approve_location' => $canManage
                && $reportStatus === self::STATUS_NEEDS_REVIEW
                && $rawLocationMatch === false
                && ! $locationOverridden,
        ];
    }

    private function serializeDetail(Schedule $schedule, ?User $viewer = null): array
    {
        $row = $this->serializeRow($schedule, $viewer);
        $metadata = $schedule->metadata ?? [];
        $home = $this->clientHomeCoordinates($schedule);
        $note = data_get($schedule->visit_notes, 'note')
            ?: data_get($schedule->visit_notes, 'caregiver_note');

        $careTasks = $schedule->relationLoaded('visitTasks')
            ? $schedule->visitTasks
            : $schedule->visitTasks()->orderBy('sort_order')->get();

        $careTaskPayload = $careTasks->isNotEmpty()
            ? $careTasks->map(fn (VisitTask $task) => [
                'label' => $task->label,
                'completed' => (bool) $task->is_completed,
                'category' => $task->category,
            ])->values()->all()
            : collect(data_get($schedule->visit_notes, 'tasks', []))->map(function ($task) {
                if (is_string($task)) {
                    return ['label' => $task, 'completed' => false, 'category' => null];
                }

                return [
                    'label' => data_get($task, 'label', data_get($task, 'name', 'Task')),
                    'completed' => (bool) data_get($task, 'completed', data_get($task, 'is_completed', false)),
                    'category' => data_get($task, 'category'),
                ];
            })->values()->all();

        return array_merge($row, [
            'address' => $schedule->address ?? $schedule->client?->address,
            'visit_notes' => $note,
            'care_tasks' => $careTaskPayload,
            'clock_in_coords' => $schedule->clock_in_latitude
                ? ['lat' => (float) $schedule->clock_in_latitude, 'lng' => (float) $schedule->clock_in_longitude]
                : null,
            'clock_out_coords' => $schedule->clock_out_latitude
                ? ['lat' => (float) $schedule->clock_out_latitude, 'lng' => (float) $schedule->clock_out_longitude]
                : null,
            'home_coords' => $home,
            'map_points' => array_values(array_filter([
                $home ? ['label' => 'Client home', 'lat' => $home['lat'], 'lng' => $home['lng'], 'tone' => 'home'] : null,
                $schedule->clock_in_latitude
                    ? ['label' => 'Clock in', 'lat' => (float) $schedule->clock_in_latitude, 'lng' => (float) $schedule->clock_in_longitude, 'tone' => 'in']
                    : null,
                $schedule->clock_out_latitude
                    ? ['label' => 'Clock out', 'lat' => (float) $schedule->clock_out_latitude, 'lng' => (float) $schedule->clock_out_longitude, 'tone' => 'out']
                    : null,
            ])),
            'time_corrections' => $metadata['time_corrections'] ?? [],
            'original_evv' => $metadata['original_evv'] ?? null,
            'location_overrides' => $this->sealedLocationOverrides($metadata),
            'units' => $this->unitsForVisit($schedule),
            'scheduled_hours' => $schedule->scheduled_hours,
            'remaining_auth_units' => $schedule->client_id
                ? $this->remainingAuthorizationUnits($schedule->client_id)
                : null,
        ]);
    }

    private function viewerCanManage(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        return $viewer->isSuperAdmin() || $viewer->hasPermission('manage_visit_reports');
    }

    /**
     * Start from current metadata but always restore sealed location override audits.
     *
     * @return array<string, mixed>
     */
    private function metadataPreservingAudits(Schedule $schedule): array
    {
        $metadata = is_array($schedule->metadata) ? $schedule->metadata : [];
        $metadata['location_overrides'] = $this->sealedLocationOverrides($metadata);

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<array<string, mixed>>
     */
    private function sealedLocationOverrides(array $metadata): array
    {
        $overrides = $metadata['location_overrides'] ?? [];
        if (! is_array($overrides)) {
            return [];
        }

        return collect($overrides)
            ->filter(fn ($entry) => is_array($entry) && ! empty($entry['approved']))
            ->map(function (array $entry) {
                if (empty($entry['entry_hash'])) {
                    $entry['immutable'] = true;
                    $entry['entry_hash'] = $this->hashLocationOverrideEntry($entry);
                }

                return $entry;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function hashLocationOverrideEntry(array $entry): string
    {
        $payload = [
            'id' => $entry['id'] ?? null,
            'schedule_id' => $entry['schedule_id'] ?? null,
            'original_clock_in' => $entry['original_clock_in'] ?? null,
            'original_clock_out' => $entry['original_clock_out'] ?? null,
            'home' => $entry['home'] ?? null,
            'reason' => $entry['reason'] ?? null,
            'by_user_id' => $entry['by_user_id'] ?? null,
            'approved_by' => $entry['approved_by'] ?? null,
            'approved_at' => $entry['approved_at'] ?? null,
        ];

        return hash('sha256', json_encode($payload));
    }

    private function needsReview(Schedule $schedule): bool
    {
        if (! $this->hasCleanTimeData($schedule)) {
            return true;
        }

        return $schedule->status === Schedule::STATUS_COMPLETED && ! $schedule->evv_status;
    }

    /**
     * True when the visit's time data is trustworthy: no pending correction,
     * no missing clock-out, on-site clock-in, a duration under the hard cap
     * and (when a schedule exists) within 25% of the scheduled hours.
     * This is the shared gate that keeps abnormal visits (e.g. 30-hour
     * "visits" from a missing clock-out) out of billing and payroll sums.
     */
    public function hasCleanTimeData(Schedule $schedule): bool
    {
        if ($this->hasPendingTimeCorrection($schedule)) {
            return false;
        }

        if ($schedule->actual_clock_in && ! $schedule->actual_clock_out) {
            return false;
        }

        if ($this->locationMatches($schedule) === false) {
            return false;
        }

        $actual = $this->effectiveHours($schedule);

        if ($actual !== null && $actual > self::maxVisitHours()) {
            return false;
        }

        if ($schedule->actual_clock_in && $schedule->actual_clock_out && $actual !== null) {
            $scheduled = $schedule->scheduled_hours;

            if ($scheduled > 0 && (abs($actual - $scheduled) / $scheduled) > 0.25) {
                return false;
            }
        }

        return true;
    }

    /** Worked hours: stored total_hours, or computed from the clock pair. */
    public function effectiveHours(Schedule $schedule): ?float
    {
        if ($schedule->total_hours !== null && (float) $schedule->total_hours > 0) {
            return (float) $schedule->total_hours;
        }

        if ($schedule->actual_clock_in && $schedule->actual_clock_out) {
            return app(ScheduleClockService::class)
                ->calculateWorkedHours($schedule->actual_clock_in, $schedule->actual_clock_out);
        }

        return $schedule->total_hours !== null ? (float) $schedule->total_hours : null;
    }

    /**
     * Single source of truth for billable/payable hours: the sum of completed
     * visits whose time data passes {@see hasCleanTimeData()}. Used by claim
     * generation (billing) and the payroll EVV hours resolver so flagged
     * visits can never inflate money.
     */
    public function payableHours(
        ?int $organizationId,
        string $periodStart,
        string $periodEnd,
        ?int $clientId = null,
        ?int $employeeId = null,
    ): float {
        $visits = Schedule::withoutGlobalScopes()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereIn('status', [Schedule::STATUS_COMPLETED, 'Verified', 'completed'])
            ->get();

        return round($visits
            ->filter(fn (Schedule $s) => $this->hasCleanTimeData($s))
            ->sum(fn (Schedule $s) => (float) ($this->effectiveHours($s) ?? 0)), 2);
    }

    public function hasPendingTimeCorrection(Schedule $schedule): bool
    {
        $corrections = data_get($schedule->metadata, 'time_corrections', []);

        return collect($corrections)->contains(fn (array $c) => empty($c['approved']));
    }

    private function visitWindowPassed(Schedule $schedule): bool
    {
        $end = $schedule->end_at ?? ($schedule->date && $schedule->end_time
            ? Carbon::parse($schedule->date->format('Y-m-d').' '.$schedule->end_time)
            : null);

        return $end && now()->greaterThan($end->copy()->addMinutes(30));
    }

    public function markBillableIfClean(Schedule $schedule): void
    {
        if (! $this->isBillable($schedule)) {
            return;
        }

        $metadata = $this->metadataPreservingAudits($schedule);

        if (! empty($metadata['billable']) && ! empty($metadata['units_deducted'])) {
            app(EvvWorkflowQueueService::class)->resolveForVisit($schedule);

            return;
        }

        $metadata['billable'] = true;
        $metadata['billable_at'] = now()->toIso8601String();
        $metadata['units'] = $this->unitsForVisit($schedule);

        $schedule->update(['metadata' => $metadata, 'evv_status' => true]);

        if ($schedule->client_id) {
            $this->deductAuthorizationUnits($schedule->fresh());
        }

        app(EvvWorkflowQueueService::class)->resolveForVisit($schedule->fresh() ?? $schedule);
    }

    private function deductAuthorizationUnits(Schedule $schedule): void
    {
        $metadata = $this->metadataPreservingAudits($schedule);

        if (! empty($metadata['units_deducted'])) {
            return;
        }

        $units = $this->unitsForVisit($schedule);

        $auth = CareDetail::query()
            ->where('client_id', $schedule->client_id)
            ->where('status', 'Active')
            ->orderByDesc('end_date')
            ->first();

        if (! $auth) {
            // Leave units_deducted unset so a later run can retry once an auth exists.
            return;
        }

        $auth->increment('units_used', $units);

        $metadata['units_deducted'] = $units;
        $metadata['authorization_deducted_at'] = now()->toIso8601String();
        $schedule->update(['metadata' => $metadata]);
    }

    public function remainingAuthorizationUnits(int $clientId): ?int
    {
        $auth = CareDetail::query()
            ->where('client_id', $clientId)
            ->where('status', 'Active')
            ->orderByDesc('end_date')
            ->first();

        if (! $auth || ! $auth->total_units) {
            return null;
        }

        $used = (int) ($auth->units_used ?? 0);

        if ($used <= 0) {
            $used = (int) Schedule::query()
                ->where('client_id', $clientId)
                ->where('event_type', Schedule::EVENT_CARE_VISIT)
                ->where('metadata->billable', true)
                ->get()
                ->sum(fn (Schedule $s) => (int) data_get($s->metadata, 'units', $this->unitsForVisit($s)));
        }

        return max(0, (int) $auth->total_units - $used);
    }

    private function clientHomeCoordinates(Schedule $schedule): ?array
    {
        $scheduleMeta = $schedule->metadata ?? [];

        if (isset($scheduleMeta['client_home_lat'], $scheduleMeta['client_home_lng'])) {
            return [
                'lat' => (float) $scheduleMeta['client_home_lat'],
                'lng' => (float) $scheduleMeta['client_home_lng'],
            ];
        }

        $client = $schedule->relationLoaded('client')
            ? $schedule->client
            : $schedule->client()->first();

        if ($client?->home_latitude !== null && $client?->home_longitude !== null) {
            return [
                'lat' => (float) $client->home_latitude,
                'lng' => (float) $client->home_longitude,
            ];
        }

        return null;
    }

    private function haversineMiles(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 3959;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function formatTimeRange(Schedule $schedule): string
    {
        if ($schedule->start_at && $schedule->end_at) {
            return $schedule->start_at->format('g:i A').' – '.$schedule->end_at->format('g:i A');
        }

        if ($schedule->start_time && $schedule->end_time) {
            return Carbon::parse($schedule->start_time)->format('g:i A')
                .' – '.Carbon::parse($schedule->end_time)->format('g:i A');
        }

        return '—';
    }

    private function formatDuration(Schedule $schedule): string
    {
        $hours = $schedule->total_hours;

        if ($hours === null && $schedule->actual_clock_in && $schedule->actual_clock_out) {
            $hours = app(ScheduleClockService::class)
                ->calculateWorkedHours($schedule->actual_clock_in, $schedule->actual_clock_out);
        }

        if ($hours === null || $hours <= 0) {
            return '—';
        }

        $h = floor($hours);
        $m = round(($hours - $h) * 60);

        if ($h > 0 && $m > 0) {
            return "{$h}h {$m}m";
        }

        if ($h > 0) {
            return "{$h}h";
        }

        return "{$m}m";
    }

    private function assigneeOptions(?int $orgId, string $type): array
    {
        if ($type === 'employee') {
            return Employee::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => trim($e->first_name.' '.$e->last_name),
                ])
                ->all();
        }

        return Client::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Client $c) => [
                'id' => $c->id,
                'name' => trim($c->first_name.' '.$c->last_name),
            ])
            ->all();
    }

    private function statusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All statuses'],
            ['value' => self::STATUS_SCHEDULED, 'label' => 'Scheduled'],
            ['value' => self::STATUS_IN_PROGRESS, 'label' => 'In progress'],
            ['value' => self::STATUS_COMPLETE, 'label' => 'Complete'],
            ['value' => self::STATUS_NEEDS_REVIEW, 'label' => 'Needs review'],
            ['value' => self::STATUS_MISSED, 'label' => 'Missed'],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_COMPLETE => 'Complete',
            self::STATUS_NEEDS_REVIEW => 'Needs review',
            self::STATUS_MISSED => 'Missed',
            default => ucfirst($status),
        };
    }
}
