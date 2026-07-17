@extends('layouts.app')

@section('content')
@php
    use App\Support\ReportPresenter;

    $queryParams = fn (array $extra = []) => array_merge(
        request()->except('page'),
        array_filter([
            'period' => $period->format('Y-m'),
            'preset' => $preset,
            'category' => $category,
            'search' => $search,
        ]),
        $extra
    );
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[12px] font-semibold text-[#2563eb] mb-1">Insights</p>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Reports</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">
                Agency overview · {{ $overview['range']['label'] }}
                · live from Billing, Payroll, Compliance &amp; agents
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('reports.schedule', ['period' => $period->format('Y-m')]) }}"
               class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                Schedule
            </a>
            <div class="relative inline-block" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                    Export
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute right-0 mt-1 w-36 bg-white border border-[#e2e8f0] rounded-xl shadow-lg z-20 py-1">
                    @foreach(['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)', 'pdf' => 'PDF'] as $fmt => $label)
                        <a href="{{ route('reports.export', ['report' => 'revenue-collections', 'period' => $period->format('Y-m'), 'format' => $fmt]) }}"
                           class="block px-4 py-2 text-[12px] text-[#334155] hover:bg-[#f8fafc]">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('reports.show', 'custom-builder') }}"
               class="inline-flex items-center px-4 py-2.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8] transition shadow-sm">
                + New report
            </a>
        </div>
    </div>

    @include('pages.reports.partials.month-bar', [
        'period' => $period,
        'preset' => $preset,
        'periodOptions' => $periodOptions,
        'prevPeriod' => $prevPeriod,
        'nextPeriod' => $nextPeriod,
        'routeName' => 'reports.index',
        'queryParams' => $queryParams,
    ])

    {{-- Search --}}
    <form method="GET" action="{{ route('reports.index') }}" class="flex gap-2">
        <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
        <input type="hidden" name="preset" value="{{ $preset }}">
        <input type="hidden" name="category" value="{{ $category }}">
        <input type="search" name="search" value="{{ $search }}" placeholder="Search reports…"
               class="flex-1 max-w-lg px-4 py-2 text-[13px] border border-[#e2e8f0] rounded-xl bg-[#f8fafc] focus:outline-none focus:ring-2 focus:ring-[#bfdbfe]">
    </form>

    @include('pages.reports.partials.category-cards', [
        'categories' => $catalog['categories'],
        'active' => $category,
        'queryParams' => $queryParams,
    ])

    @include('pages.reports.partials.kpi-row', ['kpis' => $overview['kpis'], 'cols' => 5])

    <div class="grid grid-cols-1 xl:grid-cols-[1.4fr_1fr] gap-4">
        @include('pages.reports.partials.billed-collected-chart', [
            'trend' => $overview['trend'],
            'footer' => $overview['trend_footer'],
        ])
        @include('pages.reports.partials.program-donut', ['split' => $overview['program_split']])
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @include('pages.reports.partials.aging-panel', [
            'title' => 'AR aging',
            'subtitle' => 'Outstanding '.ReportPresenter::money($overview['aging']['total_outstanding'], true).' across '.$overview['aging']['total_count'].' bills',
            'buckets' => $overview['aging']['buckets'],
            'footnote' => 'Full breakdown in Billing → Aging report.',
            'link' => route('billing-claims-audit.aging', ['period' => $period->format('Y-m')]),
            'linkLabel' => 'Open Billing aging',
        ])
        @include('pages.reports.partials.compliance-panel', [
            'bars' => $overview['compliance_bars'],
            'note' => $overview['compliance_note'],
        ])
    </div>

    @include('pages.reports.partials.library-table', [
        'title' => (config('reports.categories.'.$category.'.label') ?? 'Reports').' reports',
        'reports' => $library,
        'category' => $category,
        'total' => collect($catalog['reports'])->where('category', $category)->count(),
        'viewAll' => $category !== 'custom',
        'viewAllActive' => $viewAll ?? false,
        'lastRuns' => $lastRuns ?? [],
        'queryParams' => $queryParams,
    ])
</div>
@endsection
