<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCaregiver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\VisitResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    use ResolvesCaregiver;

    /**
     * The logged-in caregiver's shifts/visits.
     * Defaults to upcoming when no date range is supplied.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $caregiver = $this->caregiver();

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'upcoming' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $hasRange = $request->filled('from') || $request->filled('to');

        $query = $caregiver->schedules()
            ->with('client')
            ->filterDateRange($request->query('from'), $request->query('to'))
            ->filterStatus($request->query('status'));

        if ($request->boolean('upcoming', ! $hasRange)) {
            $query->upcoming();
        }

        $records = $query
            ->scheduleSort('start_at', 'asc')
            ->paginate($request->integer('per_page', 50));

        return VisitResource::collection($records);
    }

    /**
     * The Week view: visits grouped into the seven days of the week that
     * contains ?date (defaults to today). Weeks start on Monday. Each day
     * carries the chip metadata (weekday, day number) plus its visit list.
     */
    public function week(Request $request): JsonResponse
    {
        $caregiver = $this->caregiver();

        $request->validate(['date' => ['nullable', 'date']]);

        $anchor = $request->filled('date') ? Carbon::parse($request->query('date')) : today();
        $weekStart = $anchor->copy()->startOfWeek();
        $weekEnd = $anchor->copy()->endOfWeek();

        $visits = $caregiver->schedules()
            ->with('client')
            ->filterDateRange($weekStart->toDateString(), $weekEnd->toDateString())
            ->scheduleSort('start_at', 'asc')
            ->get();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $dayVisits = $visits->filter(fn ($s) => optional($s->date)->isSameDay($day)
                || (optional($s->start_at)->isSameDay($day)));

            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->format('l'),
                'weekday_short' => strtoupper($day->format('D')),
                'day_number' => (int) $day->format('j'),
                'is_today' => $day->isToday(),
                'count' => $dayVisits->count(),
                'visits' => VisitResource::collection($dayVisits->values())->toArray($request),
            ];
        }

        return response()->json([
            'data' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'month' => $weekStart->format('F Y'),
                'days' => $days,
            ],
        ]);
    }
}
