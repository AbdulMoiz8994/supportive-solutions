<?php

namespace App\Http\Controllers;

use App\Events\ScheduleEventCancelled;
use App\Events\ScheduleEventCreated;
use App\Events\ScheduleEventUpdated;
use App\Http\Requests\Schedule\CancelScheduleRequest;
use App\Http\Requests\Schedule\ClockInRequest;
use App\Http\Requests\Schedule\ClockOutRequest;
use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\User;
use App\Services\CaregiverAssignmentService;
use App\Services\ScheduleCalendarService;
use App\Services\ScheduleClockService;
use App\Services\VisitSchedulingBoardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleController extends Controller
{
    public function __construct(
        protected ScheduleClockService $clockService,
        protected ScheduleCalendarService $calendarService,
        protected CaregiverAssignmentService $assignmentService,
        protected VisitSchedulingBoardService $visitBoard,
    ) {}

    public function board(Request $request)
    {
        $this->authorize('viewAny', Schedule::class);

        $user = auth()->user();

        if ($user->role === User::ROLE_EMPLOYEE) {
            return redirect()->route('schedule.index');
        }

        $weekParam = $request->query('week');
        $weekStart = filled($weekParam)
            ? Carbon::parse($weekParam)->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $boardData = $this->visitBoard->build($weekStart, $request->query('search'));
        $canManage = $user->can('create', Schedule::class);

        $assignmentMap = [];
        $allClients = [];
        if ($canManage) {
            foreach ($boardData['caregivers'] as $row) {
                $assignmentMap[$row['id']] = $this->visitBoard->assignedClientsFor($row['id']);
            }

            $allClients = Client::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Client $c) => [
                    'id' => $c->id,
                    'name' => trim($c->first_name.' '.$c->last_name),
                ])
                ->values()
                ->all();
        }

        return view('pages.schedule.board', array_merge($boardData, [
            'canManage' => $canManage,
            'assignmentMap' => $assignmentMap,
            'allClients' => $allClients,
            'filters' => $request->only(['search', 'week']),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
        ]), [
            'title' => 'Visit Scheduling',
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Schedule::class);

        $user = auth()->user();

        if ($user->role === User::ROLE_EMPLOYEE) {
            $employee = $this->clockService->resolveEmployeeForUser($user);
            $query = Schedule::with(['client', 'employee']);

            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                $query->whereRaw('1 = 0');
            }

            $schedules = $query->whereDate('date', today())->get();

            return view('pages.schedule.caregiver-index', compact('schedules'), ['title' => 'My Visits']);
        }

        $filters = $this->resolveFilters($request);
        $canManage = $user->can('create', Schedule::class);

        if ($filters['view'] === 'list') {
            return $this->listView($filters, $canManage);
        }

        $pageData = $this->calendarService->buildPageData($filters, $canManage);

        return view('pages.schedule.index', $pageData, [
            'title' => $filters['view'] === 'agenda' ? 'Calendar — Agenda' : 'Calendar',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Schedule::class);

        $filters = $this->resolveFilters($request);
        $pageData = $this->calendarService->buildPageData($filters, false);
        $filename = 'calendar-export-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($pageData) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Title', 'Category', 'Subtitle', 'Needs You', 'Automation']);

            foreach ($pageData['events'] as $event) {
                fputcsv($handle, [
                    $event['date'],
                    $event['title'],
                    $pageData['categories'][$event['category']]['label'] ?? $event['category'],
                    $event['subtitle'],
                    $event['needs_you'] ? 'Yes' : 'No',
                    $event['automation'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function ical(Request $request): Response
    {
        $this->authorize('viewAny', Schedule::class);

        $filters = $this->resolveFilters($request);
        $pageData = $this->calendarService->buildPageData($filters, false);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BeydounTech//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:BeydounTech Calendar',
        ];

        foreach ($pageData['events'] as $event) {
            $start = Carbon::parse($event['date'].' '.($event['start_time'] ?? '09:00:00'));
            $end = $start->copy()->addHour();
            $uid = $event['id'].'@beydountech';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid;
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$start->format('Ymd\THis');
            $lines[] = 'DTEND:'.$end->format('Ymd\THis');
            $lines[] = 'SUMMARY:'.addcslashes($event['title'], ",\\;");
            if ($event['subtitle']) {
                $lines[] = 'DESCRIPTION:'.addcslashes($event['subtitle'], ",\\;");
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="beydountech-calendar.ics"',
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Schedule::class);

        return view('pages.schedule.create', array_merge($this->formOptions(), [
            'preselectedClientId' => $request->query('client_id'),
            'preselectedEmployeeId' => $request->query('employee_id'),
        ]), [
            'title' => 'Add event',
        ]);
    }

    public function store(StoreScheduleRequest $request)
    {
        $attributes = $this->scheduleAttributes($request->validated());
        $attributes['organization_id'] = $this->resolveOrganizationId($attributes);

        $schedule = Schedule::forceCreate($attributes);

        $assignedCaregiver = $this->assignmentService->confirmFromCareVisit($schedule);

        ScheduleEventCreated::dispatch($schedule);

        $message = $assignedCaregiver
            ? 'Schedule event created and caregiver assignment confirmed on the client record.'
            : 'Schedule event created.';

        if ($request->input('redirect_to') === 'board') {
            $week = $schedule->start_at?->toDateString() ?? $schedule->date?->toDateString();

            return redirect()
                ->route('schedule.board', array_filter(['week' => $week ? Carbon::parse($week)->startOfWeek(Carbon::MONDAY)->toDateString() : null]))
                ->with('success', $message);
        }

        return redirect()->route('schedule.show', $schedule->id)->with('success', $message);
    }

    public function moveVisit(Request $request, $id)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $start = Carbon::parse($date.' '.$schedule->start_time);
        $end = Carbon::parse($date.' '.$schedule->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $conflict = Schedule::conflictFor(
            (int) $validated['employee_id'],
            $schedule->client_id,
            $start,
            $end,
            $schedule->id,
        );

        if ($conflict === 'caregiver') {
            return response()->json(['message' => 'This caregiver already has an overlapping visit at that time.'], 422);
        }

        if ($conflict === 'client') {
            return response()->json(['message' => 'This client already has an overlapping visit scheduled at that time.'], 422);
        }

        $schedule->update([
            'employee_id' => $validated['employee_id'],
            'date' => $date,
        ]);

        $assignedCaregiver = $this->assignmentService->confirmFromCareVisit($schedule->fresh());

        ScheduleEventUpdated::dispatch($schedule->fresh());

        return response()->json([
            'message' => $assignedCaregiver
                ? 'Visit rescheduled and caregiver assignment confirmed.'
                : 'Visit rescheduled.',
        ]);
    }

    public function show($id)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('view', $schedule);

        $schedule->load(['client', 'employee', 'creator']);

        return view('pages.schedule.show', compact('schedule'), ['title' => 'Schedule Event']);
    }

    public function edit($id)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $schedule);

        return view('pages.schedule.edit', array_merge($this->formOptions(), compact('schedule')), [
            'title' => 'Edit Schedule Event',
        ]);
    }

    public function update(UpdateScheduleRequest $request, $id)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);
        $schedule->update($this->scheduleAttributes($request->validated()));

        $assignedCaregiver = $this->assignmentService->confirmFromCareVisit($schedule->fresh());

        ScheduleEventUpdated::dispatch($schedule->fresh());

        $message = $assignedCaregiver
            ? 'Schedule event updated and caregiver assignment confirmed on the client record.'
            : 'Schedule event updated.';

        return redirect()->route('schedule.show', $schedule->id)->with('success', $message);
    }

    public function destroy($id)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('delete', $schedule);
        $schedule->delete();

        return redirect()->route('schedule.index')->with('success', 'Schedule event removed.');
    }

    public function cancel($id, CancelScheduleRequest $request)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);
        $schedule->update(['status' => Schedule::STATUS_CANCELLED]);

        ScheduleEventCancelled::dispatch($schedule->fresh());

        return redirect()->route('schedule.show', $schedule->id)->with('success', 'Schedule event cancelled.');
    }

    public function clockIn($id, ClockInRequest $request)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);

        try {
            $this->clockService->clockIn(
                $schedule,
                $request->input('lat'),
                $request->input('lng')
            );
        } catch (ValidationException $exception) {
            return redirect()->back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', 'Clock-in successful. Stay safe!');
    }

    public function clockOut($id, ClockOutRequest $request)
    {
        $schedule = Schedule::withoutGlobalScopes()->findOrFail($id);

        try {
            $schedule = $this->clockService->clockOut(
                $schedule,
                $request->input('note'),
                $request->input('lat'),
                $request->input('lng')
            );
        } catch (ValidationException $exception) {
            return redirect()->back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first());
        }

        return redirect()->route('schedule.index')->with('success', "Clock-out complete. Total Hours: {$schedule->total_hours}");
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFilters(Request $request): array
    {
        $view = $request->query('view', 'month');
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $from = $request->query('from');
        $to = $request->query('to');

        if ($view === 'month' && (! filled($from) || ! filled($to))) {
            $monthStart = Carbon::createFromDate($year, $month, 1);
            $from = $monthStart->copy()->startOfMonth()->toDateString();
            $to = $monthStart->copy()->endOfMonth()->toDateString();
        }

        if ($view === 'list' && (! filled($from) || ! filled($to))) {
            $from = today()->startOfMonth()->toDateString();
            $to = today()->endOfMonth()->toDateString();
        }

        return [
            'search' => $request->query('search'),
            'event_type' => $request->query('event_type'),
            'status' => $request->query('status'),
            'category' => $request->query('category', 'all'),
            'from' => $from,
            'to' => $to,
            'sort' => $request->query('sort', 'start_at'),
            'direction' => $request->query('direction', 'asc'),
            'view' => $view,
            'month' => $month,
            'year' => $year,
            'day' => $request->query('day', now()->day),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function listView(array $filters, bool $canManage)
    {
        $schedules = Schedule::query()
            ->with(['client', 'employee'])
            ->scheduleSearch($filters['search'])
            ->filterEventType($filters['event_type'])
            ->filterStatus($filters['status'])
            ->filterDateRange($filters['from'], $filters['to'])
            ->scheduleSort($filters['sort'], $filters['direction'])
            ->paginate(15)
            ->withQueryString();

        $eventTypes = Schedule::eventTypes();
        $statuses = collect([
            Schedule::STATUS_SCHEDULED,
            Schedule::STATUS_COMPLETED,
            Schedule::STATUS_CANCELLED,
            Schedule::STATUS_NO_SHOW,
            Schedule::STATUS_CLOCKED_IN,
            Schedule::STATUS_MISSED,
        ]);
        $hasFilters = collect($filters)->except(['sort', 'direction', 'view', 'day'])
            ->filter(fn ($value) => filled($value) && $value !== 'all')
            ->isNotEmpty();

        return view('pages.schedule.list', compact('schedules', 'filters', 'eventTypes', 'statuses', 'hasFilters', 'canManage'), [
            'title' => 'Calendar',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'clients' => Client::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'employees' => Employee::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'eventTypes' => Schedule::eventTypes(),
            'statuses' => [
                Schedule::STATUS_SCHEDULED,
                Schedule::STATUS_COMPLETED,
                Schedule::STATUS_CANCELLED,
                Schedule::STATUS_NO_SHOW,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function scheduleAttributes(array $validated): array
    {
        $attributes = collect($validated)->only([
            'title',
            'description',
            'event_type',
            'client_id',
            'employee_id',
            'date',
            'start_time',
            'end_time',
            'timezone',
            'address',
            'all_day',
            'status',
        ])->all();

        if (! array_key_exists('status', $attributes)) {
            $attributes['status'] = Schedule::STATUS_SCHEDULED;
        } else {
            $attributes['status'] = Schedule::normalizeStatus((string) $attributes['status']);
        }

        $attributes['start_time'] = Carbon::parse($attributes['start_time'])->format('H:i:s');
        $attributes['end_time'] = Carbon::parse($attributes['end_time'])->format('H:i:s');
        $attributes['created_by'] = auth()->id();

        $locationId = session('selected_location_id');
        if ($locationId) {
            $attributes['location_id'] = $locationId;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveOrganizationId(array $attributes): int
    {
        $user = auth()->user();

        if ($user?->organization_id) {
            return (int) $user->organization_id;
        }

        if (! empty($attributes['client_id'])) {
            $clientOrganizationId = Client::withoutGlobalScopes()
                ->whereKey($attributes['client_id'])
                ->value('organization_id');

            if ($clientOrganizationId) {
                return (int) $clientOrganizationId;
            }
        }

        if (! empty($attributes['employee_id'])) {
            $employeeOrganizationId = Employee::withoutGlobalScopes()
                ->whereKey($attributes['employee_id'])
                ->value('organization_id');

            if ($employeeOrganizationId) {
                return (int) $employeeOrganizationId;
            }
        }

        return (int) (\App\Models\Organization::query()->value('id') ?? 1);
    }
}
