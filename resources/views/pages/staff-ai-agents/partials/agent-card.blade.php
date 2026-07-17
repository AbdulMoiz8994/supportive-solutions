@props(['agent'])

@php
    $autonomyClass = match($agent['autonomy'] ?? '') {
        'autonomous', 'autonomous_flag' => 'bg-[#d1fae5] text-[#065f46]',
        default => 'bg-[#fef3c7] text-[#92400e]',
    };
    $missClass = ($agent['miss_rate_pct'] ?? 0) >= 1.5 ? 'text-[#b45309]' : 'text-[#047857]';
    $autoClass = ($agent['auto_handled_pct'] ?? 0) >= 95 ? 'text-[#047857]' : 'text-[#0f172a]';
@endphp

<a href="{{ route('staff.agents.show', $agent['slug']) }}"
   class="block bg-white border border-[#e2e8f0] rounded-xl p-4 hover:border-[#bfdbfe] transition group">
    <div class="flex items-start gap-3 mb-3">
        <div class="w-11 h-11 rounded-[11px] {{ $agent['icon_bg'] }} flex items-center justify-center text-xl shrink-0">
            {{ $agent['icon'] }}
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="text-[14.5px] font-semibold text-[#0f172a] leading-tight">{{ $agent['name'] }}</h3>
            <p class="text-[11.5px] text-[#94a3b8] mt-0.5">{{ $agent['role'] }}</p>
        </div>
        <span class="shrink-0 text-[10.5px] font-bold px-2 py-1 rounded-full {{ $autonomyClass }}">
            {{ $agent['autonomy_label'] }}
        </span>
    </div>

    <div class="grid grid-cols-3 gap-2">
        <div class="bg-[#f8fafc] border border-[#f1f5f9] rounded-lg px-2.5 py-2">
            <div class="text-[10.5px] text-[#94a3b8]">Tasks (May)</div>
            <div class="text-[14px] font-bold text-[#0f172a] mt-0.5">{{ number_format($agent['tasks_may']) }}</div>
        </div>
        <div class="bg-[#f8fafc] border border-[#f1f5f9] rounded-lg px-2.5 py-2">
            <div class="text-[10.5px] text-[#94a3b8]">Auto-handled</div>
            <div class="text-[14px] font-bold mt-0.5 {{ $autoClass }}">{{ $agent['auto_handled_pct'] }}%</div>
        </div>
        <div class="bg-[#f8fafc] border border-[#f1f5f9] rounded-lg px-2.5 py-2">
            <div class="text-[10.5px] text-[#94a3b8]">Miss-rate</div>
            <div class="text-[14px] font-bold mt-0.5 {{ $missClass }}">{{ $agent['miss_rate_pct'] }}%</div>
        </div>
    </div>

    <div class="flex items-center justify-between mt-3 pt-2.5 border-t border-[#f1f5f9] text-[11.5px] text-[#64748b]">
        @if($agent['on_watch'] ?? false)
            <x-ui.pill variant="amber" size="xs">On watch</x-ui.pill>
        @elseif($agent['paused'] ?? false)
            <x-ui.pill variant="gray" size="xs">Paused</x-ui.pill>
        @elseif(!($agent['is_enabled'] ?? true))
            <x-ui.pill variant="gray" size="xs">Disabled</x-ui.pill>
        @else
            <span>{{ $agent['last_run'] }}</span>
        @endif
        <span class="text-[#2563eb] font-semibold group-hover:underline">Configure ›</span>
    </div>
</a>
