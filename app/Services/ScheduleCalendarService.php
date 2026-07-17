<?php

namespace App\Services;

use App\Models\BackgroundCheck;
use App\Models\Client;
use App\Models\Schedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ScheduleCalendarService
{
    public const VISIBLE_EVENTS_PER_DAY = 3;

    public const CATEGORY_PAYROLL = 'payroll';

    public const CATEGORY_COMPLIANCE = 'compliance';

    public const CATEGORY_BILLING = 'billing';

    public const CATEGORY_AUTHORIZATIONS = 'authorizations';

    public const CATEGORY_BACKGROUND = 'background';

    public const CATEGORY_DEADLINE = 'deadline';

    /**
     * @return array<string, array{label: string, dot: string, bg: string, text: string, bar: string, chip: string}>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_PAYROLL => [
                'label' => 'Payroll',
                'dot' => '#2563eb',
                'bg' => '#eff4ff',
                'text' => '#2563eb',
                'bar' => '#2563eb',
                'chip' => 'bg-[#eff4ff] text-[#2563eb] border-[#dbe6ff]',
            ],
            self::CATEGORY_COMPLIANCE => [
                'label' => 'Compliance / Wellness',
                'dot' => '#16a34a',
                'bg' => '#ecfdf3',
                'text' => '#067647',
                'bar' => '#16a34a',
                'chip' => 'bg-[#ecfdf3] text-[#067647] border-[#d1fadf]',
            ],
            self::CATEGORY_BILLING => [
                'label' => 'Billing / Batch',
                'dot' => '#0891b2',
                'bg' => '#ecfeff',
                'text' => '#0e7490',
                'bar' => '#0891b2',
                'chip' => 'bg-[#ecfeff] text-[#0e7490] border-[#cffafe]',
            ],
            self::CATEGORY_AUTHORIZATIONS => [
                'label' => 'Authorizations (PA / Time-Task)',
                'dot' => '#7c3aed',
                'bg' => '#f5f3ff',
                'text' => '#6d28d9',
                'bar' => '#7c3aed',
                'chip' => 'bg-[#f5f3ff] text-[#6d28d9] border-[#ddd6fe]',
            ],
            self::CATEGORY_BACKGROUND => [
                'label' => 'Background checks',
                'dot' => '#ea580c',
                'bg' => '#fff7ed',
                'text' => '#c2410c',
                'bar' => '#ea580c',
                'chip' => 'bg-[#fff7ed] text-[#c2410c] border-[#fed7aa]',
            ],
            self::CATEGORY_DEADLINE => [
                'label' => 'Deadline — needs you',
                'dot' => '#dc2626',
                'bg' => '#fef2f2',
                'text' => '#dc2626',
                'bar' => '#dc2626',
                'chip' => 'bg-[#fef2f2] text-[#dc2626] border-[#fecaca]',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildPageData(array $filters, bool $canManage): array
    {
        $view = $filters['view'] ?? 'month';
        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);

        [$rangeStart, $rangeEnd] = $this->resolveRange($filters, $view, $month, $year);

        $events = $this->collectEvents($rangeStart, $rangeEnd, $filters);
        $needsYouCount = $events->where('needs_you', true)->count();
        $monthLabel = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $calendarDays = [];
        if ($view === 'month') {
            $calendarDays = $this->buildMonthGrid($year, $month, $events);
        }

        $agendaGroups = [];
        if ($view === 'agenda') {
            $agendaGroups = $this->buildAgendaGroups($events);
        }

        $weekDays = [];
        if ($view === 'week') {
            $weekDays = $this->buildWeekGrid($rangeStart, $events);
        }

        return [
            'filters' => $filters,
            'view' => $view,
            'month' => $month,
            'year' => $year,
            'monthLabel' => $monthLabel,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'events' => $events,
            'calendarDays' => $calendarDays,
            'agendaGroups' => $agendaGroups,
            'weekDays' => $weekDays,
            'categories' => self::categories(),
            'categoryFilters' => $this->categoryFilterOptions(),
            'needsYouCount' => $needsYouCount,
            'totalCount' => $events->count(),
            'canManage' => $canManage,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collectEvents(Carbon $from, Carbon $to, array $filters): Collection
    {
        $events = $this->loadScheduleEvents($from, $to, $filters)
            ->merge($this->generateSystemEvents($from, $to))
            ->merge($this->loadBackgroundCheckEvents($from, $to))
            ->merge($this->loadAuthorizationEvents($from, $to))
            // De-duplicate (A6): the same deadline can surface both as a seeded
            // schedule row and a generated system event (e.g. "SAM + OIG batch",
            // "45-day determination — X"), or via duplicate background-check
            // rows. Real schedule rows win over generated duplicates; genuine
            // repeat visits (same title, different times) are preserved.
            ->groupBy(fn (array $event) => strtolower(trim((string) $event['title'])).'|'.$event['date'])
            ->flatMap(function (Collection $group) {
                if ($group->count() === 1) {
                    return $group;
                }

                $scheduleBacked = $group->filter(fn (array $event) => ! empty($event['schedule_id']));
                $kept = $scheduleBacked->isNotEmpty() ? $scheduleBacked : $group;

                return $kept->unique(fn (array $event) => $this->deduplicationKey($event));
            })
            ->values();

        if (filled($filters['search'] ?? null)) {
            $search = strtolower((string) $filters['search']);
            $events = $events->filter(function (array $event) use ($search) {
                $haystack = strtolower(implode(' ', array_filter([
                    $event['title'],
                    $event['subtitle'],
                    $event['person_name'] ?? '',
                ])));

                return str_contains($haystack, $search);
            });
        }

        if (filled($filters['category'] ?? null) && ($filters['category'] ?? '') !== 'all') {
            $events = $events->filter(fn (array $event) => $event['category'] === $filters['category']);
        }

        if (($filters['category'] ?? '') === 'needs_me') {
            $events = $events->filter(fn (array $event) => $event['needs_you']);
        }

        return $events->sortBy([
            ['date', 'asc'],
            ['sort_order', 'asc'],
            ['title', 'asc'],
        ])->values();
    }

    public function mapSchedule(Schedule $schedule): array
    {
        $date = ($schedule->start_at ?? $schedule->date)?->toDateString();
        $category = $this->resolveCategoryForSchedule($schedule);
        $clientName = $schedule->client
            ? trim($schedule->client->first_name.' '.$schedule->client->last_name)
            : null;

        $subtitle = $schedule->description;
        if ($clientName && ! str_contains(strtolower((string) $schedule->title), strtolower($clientName))) {
            $subtitle = $subtitle ?: $clientName;
        }

        return [
            'id' => 'schedule-'.$schedule->id,
            'schedule_id' => $schedule->id,
            'title' => $schedule->title,
            'subtitle' => $subtitle,
            'date' => $date,
            'start_time' => $schedule->start_time,
            'category' => $category,
            'needs_you' => $this->scheduleNeedsYou($schedule, $category),
            'automation' => data_get($schedule->metadata, 'automation', 'manual'),
            'url' => route('schedule.show', $schedule->id),
            'action_label' => $this->scheduleNeedsYou($schedule, $category) ? 'Review' : 'Open',
            'person_name' => $clientName,
            'is_system' => false,
            'sort_order' => 2,
            'icon' => $this->iconForCategory($category),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function loadScheduleEvents(Carbon $from, Carbon $to, array $filters): Collection
    {
        return collect(
            Schedule::query()
                ->with(['client', 'employee'])
                ->scheduleSearch($filters['search'] ?? null)
                ->filterEventType($filters['event_type'] ?? null)
                ->filterStatus($filters['status'] ?? null)
                ->filterDateRange($from->toDateString(), $to->toDateString())
                ->get()
        )->map(fn (Schedule $schedule) => $this->mapSchedule($schedule));
    }

    private function generateSystemEvents(Carbon $from, Carbon $to): Collection
    {
        $events = collect();
        $period = CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->endOfMonth());

        foreach ($period as $monthStart) {
            $month = $monthStart->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            if ($monthEnd->lt($from) || $month->gt($to)) {
                continue;
            }

            $firstOfMonth = $month->copy();
            $payrollTuesday = $month->copy()->startOfMonth();
            while ($payrollTuesday->dayOfWeek !== Carbon::TUESDAY) {
                $payrollTuesday->addDay();
            }
            $payFriday = $payrollTuesday->copy()->next(Carbon::FRIDAY);
            $lateFormDay = $month->copy()->day(min(12, $month->daysInMonth));
            $graceDay = $month->copy()->day(min(4, $month->daysInMonth));

            $events->push($this->systemEvent(
                id: 'billing-sam-'.$firstOfMonth->format('Y-m'),
                title: 'SAM + OIG batch',
                subtitle: 'Monthly exclusion screening',
                date: $firstOfMonth,
                category: self::CATEGORY_BILLING,
                automation: 'auto',
            ));

            $events->push($this->systemEvent(
                id: 'billing-invoices-'.$firstOfMonth->format('Y-m'),
                title: 'Invoices generate',
                subtitle: 'Billing cycle opens',
                date: $firstOfMonth,
                category: self::CATEGORY_BILLING,
                automation: 'auto',
            ));

            $events->push($this->systemEvent(
                id: 'compliance-forms-'.$firstOfMonth->format('Y-m'),
                title: 'Compliance forms due',
                subtitle: 'Monthly wellness packet',
                date: $firstOfMonth,
                category: self::CATEGORY_COMPLIANCE,
                automation: 'auto',
            ));

            if ($payrollTuesday->between($from, $to)) {
                $events->push($this->systemEvent(
                    id: 'payroll-batch-'.$payrollTuesday->format('Y-m-d'),
                    title: 'Payroll batch built in AccountantsWorld',
                    subtitle: '132 caregivers — pays '.$payFriday->format('D M j'),
                    date: $payrollTuesday,
                    category: self::CATEGORY_PAYROLL,
                    automation: 'auto',
                ));
            }

            if ($payFriday->between($from, $to)) {
                $events->push($this->systemEvent(
                    id: 'payroll-pay-'.$payFriday->format('Y-m-d'),
                    title: 'Pay day (deposit)',
                    subtitle: 'Direct deposit run',
                    date: $payFriday,
                    category: self::CATEGORY_PAYROLL,
                    automation: 'auto',
                ));
            }

            if ($graceDay->between($from, $to)) {
                $events->push($this->systemEvent(
                    id: 'compliance-grace-'.$graceDay->format('Y-m-d'),
                    title: 'Grace window clears',
                    subtitle: 'Late forms move to next pay run',
                    date: $graceDay,
                    category: self::CATEGORY_COMPLIANCE,
                    automation: 'auto',
                ));
            }

            if ($lateFormDay->between($from, $to)) {
                $events->push($this->systemEvent(
                    id: 'payroll-late-'.$lateFormDay->format('Y-m-d'),
                    title: 'Late-form pay run',
                    subtitle: 'Second payroll batch',
                    date: $lateFormDay,
                    category: self::CATEGORY_PAYROLL,
                    automation: 'auto',
                ));
            }
        }

        return $events->filter(function (array $event) use ($from, $to) {
            $date = Carbon::parse($event['date']);

            return $date->between($from, $to);
        })->values();
    }

    private function loadBackgroundCheckEvents(Carbon $from, Carbon $to): Collection
    {
        return BackgroundCheck::query()
            ->with('employee')
            ->where('type', 'ICHAT')
            ->whereNotNull('next_due')
            ->whereDate('next_due', '>=', $from->toDateString())
            ->whereDate('next_due', '<=', $to->toDateString())
            ->get()
            ->map(function (BackgroundCheck $check) {
                $employee = $check->employee;
                $name = $employee ? trim($employee->first_name.' '.$employee->last_name) : 'Caregiver';

                return $this->systemEvent(
                    id: 'background-ichat-'.$check->id,
                    title: 'ICHAT due — '.$name,
                    subtitle: 'Annual background renewal',
                    date: Carbon::parse($check->next_due),
                    category: self::CATEGORY_BACKGROUND,
                    automation: 'manual',
                    needsYou: true,
                    url: $employee ? route('caregivers.show', $employee->id) : null,
                    personName: $name,
                );
            });
    }

    private function loadAuthorizationEvents(Carbon $from, Carbon $to): Collection
    {
        return Client::query()
            ->with('careDetails')
            ->get()
            ->map(function (Client $client) {
                $auth = $client->currentAuthorization();
                if (! $auth?->end_date) {
                    return null;
                }

                $endDate = Carbon::parse($auth->end_date);
                $name = trim($client->first_name.' '.$client->last_name);
                $days = now()->startOfDay()->diffInDays($endDate, false);

                if ($days > 45) {
                    return null;
                }

                $needsYou = $days <= 30;
                $category = $days <= 14 ? self::CATEGORY_DEADLINE : self::CATEGORY_AUTHORIZATIONS;
                $title = $days <= 14
                    ? '45-day determination — '.$name
                    : 'PA renewal due — '.$name;

                return $this->systemEvent(
                    id: 'auth-'.$client->id.'-'.$endDate->format('Y-m-d'),
                    title: $title,
                    subtitle: 'Authorization ends '.$endDate->format('M j, Y'),
                    date: $endDate,
                    category: $category,
                    automation: $needsYou ? 'manual' : 'auto',
                    needsYou: $needsYou,
                    url: route('clients.show', $client->id).'?tab=authorization',
                    personName: $name,
                );
            })
            ->filter()
            ->filter(function (array $event) use ($from, $to) {
                $date = Carbon::parse($event['date']);

                return $date->between($from, $to);
            })
            ->values();
    }

    private function systemEvent(
        string $id,
        string $title,
        ?string $subtitle,
        Carbon $date,
        string $category,
        string $automation = 'auto',
        bool $needsYou = false,
        ?string $url = null,
        ?string $personName = null,
    ): array {
        return [
            'id' => $id,
            'schedule_id' => null,
            'title' => $title,
            'subtitle' => $subtitle,
            'date' => $date->toDateString(),
            'start_time' => null,
            'category' => $category,
            'needs_you' => $needsYou,
            'automation' => $automation,
            'url' => $url,
            'action_label' => $needsYou ? 'Review' : 'Open',
            'person_name' => $personName,
            'is_system' => true,
            'sort_order' => 1,
            'icon' => $this->iconForCategory($category),
        ];
    }

    private function resolveCategoryForSchedule(Schedule $schedule): string
    {
        $fromMetadata = data_get($schedule->metadata, 'category');
        if ($fromMetadata && isset(self::categories()[$fromMetadata])) {
            return $fromMetadata;
        }

        return match ($schedule->event_type) {
            Schedule::EVENT_INTAKE => self::CATEGORY_AUTHORIZATIONS,
            Schedule::EVENT_REASSESSMENT => self::CATEGORY_DEADLINE,
            Schedule::EVENT_FOLLOW_UP => self::CATEGORY_COMPLIANCE,
            Schedule::EVENT_CARE_VISIT => self::CATEGORY_COMPLIANCE,
            Schedule::EVENT_INTERNAL => self::CATEGORY_BILLING,
            default => self::CATEGORY_COMPLIANCE,
        };
    }

    private function scheduleNeedsYou(Schedule $schedule, string $category): bool
    {
        if (data_get($schedule->metadata, 'needs_you') === true) {
            return true;
        }

        if (in_array($category, [self::CATEGORY_DEADLINE, self::CATEGORY_BACKGROUND], true)) {
            return true;
        }

        return in_array($schedule->event_type, [Schedule::EVENT_INTAKE, Schedule::EVENT_REASSESSMENT], true)
            && $schedule->status === Schedule::STATUS_SCHEDULED;
    }

    private function iconForCategory(string $category): string
    {
        return match ($category) {
            self::CATEGORY_PAYROLL => 'payroll',
            self::CATEGORY_BILLING => 'billing',
            self::CATEGORY_AUTHORIZATIONS => 'authorization',
            self::CATEGORY_BACKGROUND => 'background',
            self::CATEGORY_DEADLINE => 'deadline',
            default => 'compliance',
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $filters, string $view, int $month, int $year): array
    {
        if ($view === 'agenda') {
            $start = today();
            $end = today()->copy()->addDays(30);

            return [$start, $end];
        }

        if ($view === 'week') {
            $focus = Carbon::createFromDate($year, $month, min((int) ($filters['day'] ?? now()->day), Carbon::createFromDate($year, $month, 1)->daysInMonth));

            return [$focus->copy()->startOfWeek(Carbon::SUNDAY), $focus->copy()->endOfWeek(Carbon::SATURDAY)];
        }

        if ($view === 'day') {
            $day = Carbon::createFromDate($year, $month, (int) ($filters['day'] ?? now()->day));

            return [$day->copy()->startOfDay(), $day->copy()->endOfDay()];
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        if (filled($filters['from'] ?? null) && filled($filters['to'] ?? null)) {
            $start = Carbon::parse($filters['from']);
            $end = Carbon::parse($filters['to']);
        }

        return [$start, $end];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildMonthGrid(int $year, int $month, Collection $events): array
    {
        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $date = $startOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $days = [];

        for ($i = 0; $i < 42; $i++) {
            $currentDate = $date->toDateString();
            $dayEvents = $events->filter(fn (array $event) => $event['date'] === $currentDate)->values();
            ['events' => $visible, 'overflow' => $overflow] = $this->capDayEvents($dayEvents);

            $days[] = [
                'date' => $currentDate,
                'day' => $date->day,
                'isCurrentMonth' => $date->month === $month,
                'isToday' => $currentDate === today()->toDateString(),
                'events' => $visible,
                'overflow' => $overflow,
                'all_events' => $dayEvents,
            ];

            $date->addDay();
        }

        return $days;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAgendaGroups(Collection $events): array
    {
        return $events->groupBy('date')->map(function (Collection $group, string $date) {
            $carbon = Carbon::parse($date);

            return [
                'date' => $date,
                'label' => $carbon->format('D, M j'),
                'is_today' => $date === today()->toDateString(),
                'events' => $group->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildWeekGrid(Carbon $start, Collection $events): array
    {
        $days = [];
        $date = $start->copy();

        for ($i = 0; $i < 7; $i++) {
            $currentDate = $date->toDateString();
            $dayEvents = $events->filter(fn (array $event) => $event['date'] === $currentDate)->values();
            ['events' => $visible, 'overflow' => $overflow] = $this->capDayEvents($dayEvents);

            $days[] = [
                'date' => $currentDate,
                'label' => $date->format('D'),
                'day' => $date->day,
                'is_today' => $currentDate === today()->toDateString(),
                'events' => $visible->all(),
                'overflow' => $overflow,
            ];
            $date->addDay();
        }

        return $days;
    }

    /**
     * @return array{events: Collection, overflow: int}
     */
    private function capDayEvents(Collection $dayEvents): array
    {
        $visible = $dayEvents->take(self::VISIBLE_EVENTS_PER_DAY);

        return [
            'events' => $visible,
            'overflow' => max(0, $dayEvents->count() - self::VISIBLE_EVENTS_PER_DAY),
        ];
    }

    private function deduplicationKey(array $event): string
    {
        if ($this->eventIsDayUnique($event)) {
            return 'day-unique';
        }

        return $event['start_time'] ?? '';
    }

    private function eventIsDayUnique(array $event): bool
    {
        if (! empty($event['is_system'])) {
            return true;
        }

        if (in_array($event['category'], [
            self::CATEGORY_DEADLINE,
            self::CATEGORY_AUTHORIZATIONS,
            self::CATEGORY_BACKGROUND,
        ], true)) {
            return true;
        }

        $title = strtolower(trim((string) $event['title']));

        return str_starts_with($title, '45-day determination')
            || str_starts_with($title, 'pa renewal due')
            || str_starts_with($title, 'ichat due');
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function categoryFilterOptions(): array
    {
        return collect(self::categories())
            ->map(fn (array $config, string $key) => ['key' => $key, 'label' => $config['label']])
            ->prepend(['key' => 'all', 'label' => 'All categories'])
            ->push(['key' => 'needs_me', 'label' => 'Needs me'])
            ->values()
            ->all();
    }
}
