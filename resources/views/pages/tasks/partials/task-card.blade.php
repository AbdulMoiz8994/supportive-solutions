<div class="rounded-xl border border-[#e2e8f0] bg-white p-3 {{ $task['is_overdue'] ? 'border-[#fecaca]' : '' }}">
    <div class="flex items-center gap-1.5 flex-wrap">
        <div class="text-[13px] font-semibold text-[#0f172a] leading-snug">{{ $task['title'] }}</div>
        @if($task['is_overdue'])
            <span class="text-[10px] font-bold uppercase text-[#b91c1c] bg-[#fee2e2] px-1.5 py-0.5 rounded">Overdue</span>
        @endif
        @if(($task['priority_effective'] ?? $task['priority'] ?? '') === 'high')
            <span class="text-[10px] font-bold uppercase text-[#c2410c]">High</span>
        @endif
    </div>
    <div class="text-[11px] text-[#64748b] mt-1">{{ $task['assignee'] }}</div>
    <div class="text-[11px] mt-1 {{ $task['is_overdue'] ? 'text-[#b91c1c] font-semibold' : 'text-[#94a3b8]' }}">Due {{ $task['due_date'] }}</div>
    @if($task['related_url'])
        <a href="{{ $task['related_url'] }}" class="text-[11px] font-semibold text-[#2563eb] mt-2 inline-block">Open →</a>
    @endif
</div>
