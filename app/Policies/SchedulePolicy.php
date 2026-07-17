<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;
use App\Policies\Concerns\InteractsWithOrganization;
use App\Services\ScheduleClockService;

class SchedulePolicy
{
    use InteractsWithOrganization;

    public function __construct(
        protected ScheduleClockService $clockService
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'view_calendar');
    }

    public function view(User $user, Schedule $schedule): bool
    {
        if (! $this->sameOrganization($user, $schedule)) {
            return false;
        }

        if ($this->isOfficeTeam($user)) {
            return $this->hasPermission($user, 'view_calendar');
        }

        return $this->clockService->userOwnsSchedule($user, $schedule);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'manage_schedules');
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->hasPermission($user, 'manage_schedules') && $this->sameOrganization($user, $schedule);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function clockIn(User $user, Schedule $schedule): bool
    {
        return $this->sameOrganization($user, $schedule)
            && $this->clockService->userOwnsSchedule($user, $schedule);
    }

    public function clockOut(User $user, Schedule $schedule): bool
    {
        return $this->clockIn($user, $schedule);
    }

    public function viewVisitReports(User $user): bool
    {
        return $this->hasPermission($user, 'view_visit_reports');
    }

    public function manageVisitReports(User $user): bool
    {
        return $this->hasPermission($user, 'manage_visit_reports');
    }

    /**
     * Approve a location mismatch on a care-visit schedule without mutating GPS.
     */
    public function approveLocation(User $user, Schedule $schedule): bool
    {
        return $this->manageVisitReports($user) && $this->sameOrganization($user, $schedule);
    }
}
