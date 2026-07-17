@extends('layouts.app')

@section('content')
@php
    $queryParams = fn (array $extra = []) => array_merge(
        request()->except('page'),
        array_filter(['period' => $period->format('Y-m')]),
        $extra
    );
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[13px] text-[#64748b]">
                <a href="{{ route('reports.index', ['category' => $definition['category'], 'period' => $period->format('Y-m')]) }}"
                   class="text-[#2563eb] font-semibold hover:underline">‹ Reports</a>
                · {{ $categoryMeta['label'] ?? 'Report' }}
            </p>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight mt-1">{{ $definition['name'] }}</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">{{ $definition['description'] }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($definition['schedule'] !== 'on_demand')
                <a href="{{ route('reports.schedule', ['report' => $report, 'period' => $period->format('Y-m')]) }}"
                   class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                    Scheduled · {{ $definition['schedule_label'] }}
                </a>
            @endif
            <div class="relative inline-block" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                    Export
                </button>
                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute right-0 mt-1 w-40 bg-white border border-[#e2e8f0] rounded-xl shadow-lg z-20 py-1">
                    @foreach(['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)', 'pdf' => 'PDF'] as $fmt => $label)
                        <a href="{{ route('reports.export', array_merge(['report' => $report, 'format' => $fmt], request()->query())) }}"
                           class="block px-4 py-2 text-[12px] text-[#334155] hover:bg-[#f8fafc]">{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <a href="{{ request()->fullUrl() }}"
               class="inline-flex items-center px-4 py-2.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8] transition shadow-sm">
                Refresh
            </a>
        </div>
    </div>

    @if(view()->exists('pages.reports.types.'.$view))
        @include('pages.reports.types.'.$view, compact('data', 'period', 'filters', 'definition', 'report'))
    @elseif(! empty($data['sections']))
        @include('pages.reports.types.standard-report', compact('data', 'period', 'definition', 'report'))
    @else
        @include('pages.reports.types.generic', compact('data', 'period', 'definition'))
    @endif
</div>
@endsection
