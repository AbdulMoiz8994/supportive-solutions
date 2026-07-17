<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use App\Services\HHA\HHASyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleClockService
{
    public function __construct(protected HHASyncService $hhaSync) {}
    public function resolveEmployeeForUser(User $user): ?Employee
    {
        return Employee::where('user_id', $user->id)->first();
    }

    public function userOwnsSchedule(User $user, Schedule $schedule): bool
    {
        if (! $user->isEmployee()) {
            return true;
        }

        $employee = $this->resolveEmployeeForUser($user);

        return $employee && (int) $schedule->employee_id === (int) $employee->id;
    }

    public function assertCanClock(User $user, Schedule $schedule): void
    {
        if (! $this->userOwnsSchedule($user, $schedule)) {
            abort(403, 'You are not authorized to modify this schedule.');
        }
    }

    public function clockIn(Schedule $schedule, ?float $latitude = null, ?float $longitude = null): Schedule
    {
        if ($schedule->date->isFuture()) {
            throw ValidationException::withMessages([
                'schedule' => 'Cannot clock in for a future shift.',
            ]);
        }

        if ($schedule->actual_clock_in !== null || $schedule->status === Schedule::STATUS_CLOCKED_IN) {
            throw ValidationException::withMessages([
                'schedule' => 'This shift has already been clocked in.',
            ]);
        }

        if ($schedule->status === Schedule::STATUS_COMPLETED || $schedule->actual_clock_out !== null) {
            throw ValidationException::withMessages([
                'schedule' => 'This shift is already completed.',
            ]);
        }

        $metadata = $this->withClientHomeCoordinates($schedule);

        $schedule->update([
            'actual_clock_in' => now(),
            'status' => Schedule::STATUS_CLOCKED_IN,
            'clock_in_latitude' => $latitude,
            'clock_in_longitude' => $longitude,
            'metadata' => $metadata,
        ]);

        return $schedule->fresh();
    }

    public function clockOut(Schedule $schedule, ?string $note = null, ?float $latitude = null, ?float $longitude = null): Schedule
    {
        if ($schedule->actual_clock_in === null || $schedule->status !== Schedule::STATUS_CLOCKED_IN) {
            throw ValidationException::withMessages([
                'schedule' => 'You must clock in before clocking out.',
            ]);
        }

        if ($schedule->actual_clock_out !== null || $schedule->status === Schedule::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'schedule' => 'This shift has already been clocked out.',
            ]);
        }

        if ($schedule->date->isFuture()) {
            throw ValidationException::withMessages([
                'schedule' => 'Cannot clock out for a future shift.',
            ]);
        }

        return DB::transaction(function () use ($schedule, $note, $latitude, $longitude) {
            $clockOut = now();
            $totalHours = $this->calculateWorkedHours($schedule->actual_clock_in, $clockOut);

            // Abnormal durations (e.g. a forgotten clock-out closed days later)
            // must not auto-verify: they stay "Needs review" and are excluded
            // from billing/payroll until an office time correction is approved.
            $durationOk = $totalHours <= VisitReportService::maxVisitHours();

            $metadata = $schedule->metadata ?? [];

            if (! $durationOk) {
                $metadata['duration_flag'] = [
                    'hours' => $totalHours,
                    'max_hours' => VisitReportService::maxVisitHours(),
                    'flagged_at' => $clockOut->toIso8601String(),
                ];
            }

            $schedule->update([
                'actual_clock_out' => $clockOut,
                'total_hours' => $totalHours,
                'status' => Schedule::STATUS_COMPLETED,
                'evv_status' => $durationOk, // refined below after location checks
                'metadata' => $metadata,
                'visit_notes' => ['note' => $note ?? ''],
                'clock_out_latitude' => $latitude,
                'clock_out_longitude' => $longitude,
            ]);

            $fresh = $schedule->fresh(['client', 'employee']);
            $visitReports = app(VisitReportService::class);
            $evvClean = $durationOk
                && $visitReports->hasCleanTimeData($fresh)
                && $visitReports->locationMatches($fresh) === true;

            if ((bool) $fresh->evv_status !== $evvClean) {
                $fresh->update(['evv_status' => $evvClean]);
                $fresh = $fresh->fresh(['client', 'employee']);
            }

            // Clean complete visits become billable and reduce auth units.
            $visitReports->markBillableIfClean($fresh);
            $fresh = $fresh->fresh(['client', 'employee']);

            if (! $visitReports->isBillable($fresh)
                && $visitReports->resolveReportStatus($fresh) === VisitReportService::STATUS_NEEDS_REVIEW
            ) {
                app(EvvWorkflowQueueService::class)->syncNeedsReview($fresh, 'clock-out flagged for review');
            }

            if ($evvClean && $visitReports->isBillable($fresh)) {
                try {
                    $this->hhaSync->syncVisit($fresh);
                } catch (\Throwable) {
                    // EVV export is best-effort — local clock-out must always succeed.
                }
            }

            return $fresh;
        });
    }

    public function calculateWorkedHours(\DateTimeInterface $clockIn, \DateTimeInterface $clockOut): float
    {
        $start = \Carbon\Carbon::parse($clockIn);
        $end = \Carbon\Carbon::parse($clockOut);

        if ($end->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    /**
     * Stamp the client's home GPS onto the visit so location match works
     * even when the schedule was created without seed metadata.
     */
    public function withClientHomeCoordinates(Schedule $schedule): array
    {
        $metadata = $schedule->metadata ?? [];

        if (isset($metadata['client_home_lat'], $metadata['client_home_lng'])) {
            return $metadata;
        }

        $client = $schedule->relationLoaded('client')
            ? $schedule->client
            : $schedule->client()->first();

        if ($client?->home_latitude !== null && $client?->home_longitude !== null) {
            $metadata['client_home_lat'] = (float) $client->home_latitude;
            $metadata['client_home_lng'] = (float) $client->home_longitude;
        }

        return $metadata;
    }
}
