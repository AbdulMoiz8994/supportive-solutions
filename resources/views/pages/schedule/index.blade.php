@extends('layouts.app')

@section('content')
<div class="max-w-full mx-auto pb-12">

    @if (session('success'))
        <div x-data="{show:true}" x-show="show" x-transition class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm font-semibold text-[#067647]">
            <span>{{ session('success') }}</span>
            <button @click="show=false" class="text-[#067647]/60 hover:text-[#067647]">&times;</button>
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between mb-5">
        <div>
            <h1 class="text-[26px] font-extrabold text-[#0f172a] tracking-tight">
                {{ $view === 'agenda' ? 'Calendar — Agenda' : 'Calendar' }}
            </h1>
            <p class="text-[13.5px] text-[#64748b] mt-1">
                @if ($view === 'agenda')
                    Upcoming — next 30 days — {{ $needsYouCount }} {{ Str::plural('item', $needsYouCount) }} flagged for you
                @else
                    {{ $monthLabel }} · {{ $needsYouCount }} {{ Str::plural('item', $needsYouCount) }} need you this month · everything else auto-scheduled
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.btn variant="outline" size="sm" :href="route('schedule.board')">Visit board</x-ui.btn>
            <x-ui.btn variant="outline" size="sm" :href="route('schedule.export', request()->query())">Export</x-ui.btn>
            <x-ui.btn variant="outline" size="sm" :href="route('schedule.ical', request()->query())">Subscribe (iCal)</x-ui.btn>
            @if ($canManage)
                <x-ui.btn variant="primary" size="sm" :href="route('schedule.create')">+ Add event</x-ui.btn>
            @endif
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="rounded-2xl border border-[#e6eef9] bg-white p-4 mb-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $prevMonth = $month == 1 ? 12 : $month - 1;
                    $prevYear = $month == 1 ? $year - 1 : $year;
                    $nextMonth = $month == 12 ? 1 : $month + 1;
                    $nextYear = $month == 12 ? $year + 1 : $year;
                    $queryBase = request()->except(['month', 'year', 'day']);
                @endphp
                <a href="{{ route('schedule.index', array_merge($queryBase, ['month' => $prevMonth, 'year' => $prevYear])) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#e2e8f0] text-[#64748b] hover:border-[#2563eb] hover:text-[#2563eb]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="min-w-[120px] text-center text-[15px] font-bold text-[#0f172a]">{{ $monthLabel }}</span>
                <a href="{{ route('schedule.index', array_merge($queryBase, ['month' => $nextMonth, 'year' => $nextYear])) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#e2e8f0] text-[#64748b] hover:border-[#2563eb] hover:text-[#2563eb]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>

                <div class="hidden sm:block h-6 w-px bg-[#e2e8f0] mx-1"></div>

                @foreach ([
                    ['view' => 'day', 'label' => 'Today', 'params' => ['view' => 'day', 'month' => now()->month, 'year' => now()->year, 'day' => now()->day]],
                    ['view' => 'week', 'label' => 'Week', 'params' => ['view' => 'week']],
                    ['view' => 'month', 'label' => 'Month', 'params' => ['view' => 'month']],
                    ['view' => 'agenda', 'label' => 'Agenda', 'params' => ['view' => 'agenda']],
                ] as $toggle)
                    <a href="{{ route('schedule.index', array_merge($queryBase, $toggle['params'], ['month' => $month, 'year' => $year])) }}"
                       class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold transition-colors {{ $view === $toggle['view'] ? 'bg-[#2563eb] text-white' : 'text-[#64748b] hover:bg-[#eef4ff] hover:text-[#2563eb]' }}">
                        {{ $toggle['label'] }}
                    </a>
                @endforeach
            </div>

            @if ($view === 'month')
                <form method="GET" action="{{ route('schedule.index') }}" class="flex items-center gap-2">
                    @foreach (request()->except('category') as $key => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label class="text-[12px] font-semibold text-[#64748b]">Filter</label>
                    <select name="category" onchange="this.form.submit()"
                            class="rounded-lg border border-[#e2e8f0] bg-white px-3 py-2 text-[13px] font-semibold text-[#0f172a] outline-none focus:border-[#2563eb]">
                        @foreach ($categoryFilters as $option)
                            <option value="{{ $option['key'] }}" @selected(($filters['category'] ?? 'all') === $option['key'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($view === 'agenda')
            <div class="mt-4 flex flex-wrap gap-2 border-t border-[#eef2f9] pt-4">
                @foreach ([
                    ['key' => 'all', 'label' => 'All'],
                    ['key' => 'needs_me', 'label' => 'Needs me'],
                    ['key' => 'payroll', 'label' => 'Payroll'],
                    ['key' => 'compliance', 'label' => 'Compliance'],
                    ['key' => 'billing', 'label' => 'Billing'],
                    ['key' => 'authorizations', 'label' => 'Authorizations'],
                    ['key' => 'background', 'label' => 'Background'],
                ] as $chip)
                    <a href="{{ route('schedule.index', array_merge(request()->query(), ['view' => 'agenda', 'category' => $chip['key']])) }}"
                       class="rounded-full border px-3.5 py-1.5 text-[12px] font-semibold transition-colors {{ ($filters['category'] ?? 'all') === $chip['key'] ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:border-[#94a3b8]' }}">
                        {{ $chip['label'] }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($view === 'month')
            <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl bg-[#f8fbff] border border-[#e6eef9] px-4 py-3">
                @foreach ($categories as $key => $cat)
                    <div class="flex items-center gap-2 text-[11.5px] font-semibold text-[#475569]">
                        <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $cat['dot'] }}"></span>
                        {{ $cat['label'] }}
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Views --}}
    @if ($view === 'month')
        @include('pages.schedule.partials.month')
    @elseif ($view === 'agenda')
        @include('pages.schedule.partials.agenda')
    @elseif ($view === 'week')
        @include('pages.schedule.partials.week')
    @else
        @include('pages.schedule.partials.day')
    @endif
</div>
@endsection
