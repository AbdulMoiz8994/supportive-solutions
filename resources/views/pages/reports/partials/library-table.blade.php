@props(['title', 'reports', 'category', 'total' => 0, 'viewAll' => true, 'viewAllActive' => false, 'lastRuns' => [], 'queryParams' => fn () => []])

@php
    $pills = ['green' => 'bg-[#d1fae5] text-[#065f46]', 'grey' => 'bg-[#e2e8f0] text-[#475569]'];
    $iconBg = ['financial' => 'bg-[#dbeafe]', 'operational' => 'bg-[#d1fae5]', 'compliance' => 'bg-[#ede9fe]', 'caregiver_hr' => 'bg-[#fce7f3]', 'ai_performance' => 'bg-[#e0e7ff]'];
    $buildQuery = is_callable($queryParams) ? $queryParams : fn ($extra = []) => $extra;
@endphp

<div class="rounded-xl border border-[#e2e8f0] bg-white overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-[#f1f5f9]">
        <div>
            <h3 class="text-[14px] font-semibold text-[#0f172a]">{{ $title }}</h3>
            <p class="text-[12px] text-[#94a3b8] mt-0.5">Open to view · scheduled to your inbox</p>
        </div>
        <span class="px-2.5 py-1 rounded-full text-[11.5px] font-semibold bg-[#dbeafe] text-[#1e40af]">{{ $total }} reports</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-[#94a3b8] bg-[#fcfdfe] border-b border-[#e2e8f0]">
                    <th class="px-4 py-2.5 font-semibold">Report</th>
                    <th class="px-4 py-2.5 font-semibold">What it shows</th>
                    <th class="px-4 py-2.5 font-semibold">Schedule</th>
                    <th class="px-4 py-2.5 font-semibold">Last run</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $item)
                    @php
                        $schedClass = \App\Support\ReportPresenter::scheduleBadgeClass($item['schedule']);
                        $pill = $pills[$schedClass] ?? $pills['grey'];
                        $lastRun = $lastRuns[$item['slug']] ?? '—';
                    @endphp
                    <tr class="border-b border-[#f1f5f9] hover:bg-[#f8fafc] cursor-pointer" onclick="window.location='{{ route('reports.show', $item['slug']) }}'">
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $iconBg[$item['category']] ?? 'bg-[#f1f5f9]' }} text-sm mr-2 align-middle">{{ $item['icon'] }}</span>
                            <span class="font-semibold text-[#0f172a]">{{ $item['name'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-[13px] text-[#334155]">{{ $item['description'] }}</td>
                        <td class="px-4 py-2.5">
                            <span class="px-2 py-0.5 rounded-full text-[11.5px] font-semibold {{ $pill }}">{{ $item['schedule_label'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-[13px] text-[#334155]">{{ $lastRun }}</td>
                        <td class="px-4 py-2.5 text-[12px] font-semibold text-[#2563eb]">Open ›</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-[#94a3b8] text-sm">No reports in this category.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($viewAll && $total > count($reports))
        <div class="px-4 py-3 border-t border-[#f1f5f9] text-right">
            <a href="{{ route('reports.index', $buildQuery(['category' => $category, 'view_all' => 1])) }}"
               class="text-[12px] font-semibold text-[#2563eb] hover:underline">
                View all {{ $total }} {{ strtolower(config('reports.categories.'.$category.'.label', 'reports')) }} reports ›
            </a>
        </div>
    @endif
</div>
