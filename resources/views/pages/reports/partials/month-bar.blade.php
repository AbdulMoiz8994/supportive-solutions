@props([
    'period',
    'preset' => 'month',
    'periodOptions' => [],
    'prevPeriod',
    'nextPeriod',
    'routeName' => 'reports.index',
    'queryParams',
    'showProgramFilter' => false,
    'program' => 'all',
])

<div class="flex flex-col gap-2">
    <div class="flex flex-wrap items-center gap-2 bg-white border border-[#e2e8f0] rounded-xl px-3 py-2.5">
        <a href="{{ route($routeName, $queryParams(['period' => $prevPeriod->format('Y-m')])) }}"
           class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1] text-sm">&lsaquo;</a>
        <span class="inline-flex items-center gap-2 px-3 py-1.5 text-[13px] font-bold text-[#1e40af] bg-[#eff6ff] border border-[#bfdbfe] rounded-lg">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            {{ $period->format('M Y') }}
        </span>
        <a href="{{ route($routeName, $queryParams(['period' => $nextPeriod->format('Y-m')])) }}"
           class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1] text-sm">&rsaquo;</a>
        <span class="text-[12px] text-[#64748b] ml-1">Reporting period</span>
        @foreach($periodOptions as $opt)
            <a href="{{ route($routeName, $queryParams(['period' => $period->format('Y-m'), 'preset' => $opt['preset']])) }}"
               class="px-3 py-1 text-[12px] font-semibold rounded-full border transition {{ $preset === $opt['preset'] ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:border-[#cbd5e1]' }}">
                {{ $opt['label'] }}
            </a>
        @endforeach
        @if($showProgramFilter)
            <div class="flex gap-1.5 ml-2">
                @foreach(['all' => 'All programs', 'mich' => 'MICH only', 'dhs' => 'DHS only'] as $val => $label)
                    <a href="{{ request()->fullUrlWithQuery(['program' => $val]) }}"
                       class="px-3 py-1 text-[12px] font-semibold rounded-full border {{ ($program ?? 'all') === $val ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        @endif
        <span class="ml-auto text-[12px] text-[#94a3b8] hidden lg:inline">All charts &amp; reports recompute for the period</span>
    </div>
</div>
