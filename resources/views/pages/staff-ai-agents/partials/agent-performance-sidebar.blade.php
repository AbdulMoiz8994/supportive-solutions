@props(['agent'])

<div class="rounded-xl border border-[#e2e8f0] bg-white p-4 mb-4">
    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#64748b] mb-2.5">Performance (May)</h4>
    <div class="space-y-0 divide-y divide-[#f1f5f9]">
        @foreach([
            ['Tasks', number_format($agent['tasks_may'] ?? 0)],
            ['Auto-handled', ($agent['auto_handled_pct'] ?? 0).'%'],
            ['Escalated to you', (string) ($agent['escalated'] ?? 0)],
        ] as [$label, $value])
            <div class="flex items-center justify-between py-2 text-[12.5px]">
                <span class="text-[#64748b]">{{ $label }}</span>
                <span class="font-semibold text-[#0f172a] tabular-nums">{{ $value }}</span>
            </div>
        @endforeach
        <div class="flex items-center justify-between py-2 text-[12.5px]">
            <span class="text-[#64748b]">Miss-rate</span>
            <x-ui.pill variant="green" size="sm">{{ $agent['miss_rate_pct'] ?? 0 }}%</x-ui.pill>
        </div>
        <div class="flex items-center justify-between py-2 text-[12.5px]">
            <span class="text-[#64748b]">Registry status</span>
            <x-ui.pill :variant="($agent['is_enabled'] ?? true) ? 'green' : 'gray'" size="sm">{{ $agent['status_label'] }}</x-ui.pill>
        </div>
    </div>
</div>

@if(!empty($agent['connected_systems']))
<div class="rounded-xl border border-[#e2e8f0] bg-white p-4 mb-4">
    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#64748b] mb-2.5">Connected systems</h4>
    @foreach($agent['connected_systems'] as $system)
        <div class="flex items-center justify-between py-2 text-[12.5px]">
            <span class="text-[#334155]">{{ $system['name'] }}</span>
            <x-ui.pill variant="green" size="sm">{{ $system['status'] }}</x-ui.pill>
        </div>
    @endforeach
</div>
@endif

@if(!empty($agent['linked']))
<div class="rounded-xl border border-[#e2e8f0] bg-white p-4">
    <h4 class="text-[12px] font-bold uppercase tracking-wide text-[#64748b] mb-2.5">Linked</h4>
    @foreach($agent['linked'] as $link)
        @php
            $href = isset($link['route']) ? route($link['route'], $link['params'] ?? []) : url($link['url'] ?? '#');
        @endphp
        <div class="flex items-center justify-between py-2 text-[12.5px]">
            <span class="text-[#334155]">{{ $link['label'] }}</span>
            <a href="{{ $href }}" class="text-[#2563eb] font-semibold text-[12px] hover:underline">Open ›</a>
        </div>
    @endforeach
</div>
@endif
