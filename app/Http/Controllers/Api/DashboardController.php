<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VisitResource;
use App\Models\Employee;
use App\Models\PayRecord;
use App\Models\Schedule;
use App\Models\SecureMessageParticipant;
use App\Models\VisitTask;
use App\Services\Communication\CommunicationNotificationService;
use App\Services\VisitReportService;
use App\Support\Api\AvatarUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The home screen ("Welcome Back") in one call: greeting, the active/next
 * shift with a live countdown, today's schedule, today's task progress,
 * hours this week, pay year-to-date, and the two unread badges.
 */
class DashboardController extends Controller
{
    use ResolvesCaregiver;

    public function __construct(
        protected CommunicationNotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $caregiver = $this->caregiver();
        $user = $request->user();

        $todays = $this->todaysSchedule($caregiver);
        $active = $this->activeVisit($caregiver);
        $next = $this->nextShift($caregiver);
        $tasks = $this->todaysTaskProgress($caregiver, $todays->pluck('id'));

        return response()->json([
            'data' => [
                'caregiver' => [
                    'id' => $caregiver->id,
                    'name' => $caregiver->name,
                    'first_name' => $caregiver->first_name,
                    'avatar_url' => AvatarUrl::forPhoto($caregiver->profile_photo),
                ],
                'today' => [
                    'date' => today()->toDateString(),
                    'weekday' => today()->format('l'),
                    'label' => today()->format('l, M j'),
                ],
                'active_visit' => $active ? new VisitResource($active) : null,
                'next_shift' => $next ? [
                    'visit' => new VisitResource($next),
                    'starts_in_minutes' => $next->start_at ? max(0, (int) round(now()->diffInMinutes($next->start_at, false))) : null,
                ] : null,
                'today_schedule' => VisitResource::collection($todays),
                'tasks' => [
                    'done' => $tasks['done'],
                    'total' => $tasks['total'],
                    'remaining_hours' => $this->remainingHoursToday($todays),
                ],
                'hours_this_week' => $this->hoursThisWeek($caregiver),
                'pay' => $this->payYearToDate($caregiver),
                'badges' => [
                    'unread_notifications' => $this->notificationService->unreadCount($user),
                    'unread_conversations' => SecureMessageParticipant::query()
                        ->where('user_id', $user->id)
                        ->whereNull('last_read_at')
                        ->count(),
                ],
            ],
        ]);
    }

    private function todaysSchedule(Employee $caregiver)
    {
        return $caregiver->schedules()
            ->with('client')
            ->whereDate('date', today())
            ->scheduleSort('start_at', 'asc')
            ->get();
    }

    private function activeVisit(Employee $caregiver): ?Schedule
    {
        return $caregiver->schedules()
            ->with('client')
            ->whereIn('status', Schedule::inProgressStatuses())
            ->orderByDesc('actual_clock_in')
            ->first();
    }

    private function nextShift(Employee $caregiver): ?Schedule
    {
        return $caregiver->schedules()
            ->with('client')
            ->where('status', Schedule::STATUS_SCHEDULED)
            ->upcoming()
            ->scheduleSort('start_at', 'asc')
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $scheduleIds
     * @return array{done: int, total: int}
     */
    private function todaysTaskProgress(Employee $caregiver, $scheduleIds): array
    {
        if ($scheduleIds->isEmpty()) {
            return ['done' => 0, 'total' => 0];
        }

        $tasks = VisitTask::query()
            ->whereIn('schedule_id', $scheduleIds->all())
            ->get(['is_completed']);

        return [
            'done' => $tasks->where('is_completed', true)->count(),
            'total' => $tasks->count(),
        ];
    }

    /**
     * Scheduled hours still ahead today: visits not yet completed/cancelled.
     */
    private function remainingHoursToday($todays): float
    {
        return round($todays
            ->reject(fn (Schedule $s) => in_array($s->status, [Schedule::STATUS_COMPLETED, Schedule::STATUS_CANCELLED, Schedule::STATUS_NO_SHOW], true))
            ->sum(fn (Schedule $s) => $s->scheduled_hours), 2);
    }

    private function hoursThisWeek(Employee $caregiver): float
    {
        $visitReports = app(VisitReportService::class);

        $hours = $caregiver->schedules()
            ->where('status', Schedule::STATUS_COMPLETED)
            ->whereBetween('start_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->get()
            ->filter(fn (Schedule $schedule) => $visitReports->hasCleanTimeData($schedule))
            ->sum(fn (Schedule $schedule) => (float) ($visitReports->effectiveHours($schedule) ?? 0));

        return round((float) $hours, 2);
    }

    /**
     * @return array{ytd_gross: float, ytd_hours: float, paystub_count: int}
     */
    private function payYearToDate(Employee $caregiver): array
    {
        $records = PayRecord::query()
            ->where('employee_id', $caregiver->id)
            ->where('period_key', 'like', now()->year.'-%')
            ->get(['hours', 'gross']);

        return [
            'ytd_gross' => round((float) $records->sum('gross'), 2),
            'ytd_hours' => round((float) $records->sum('hours'), 2),
            'paystub_count' => $records->count(),
        ];
    }
}
