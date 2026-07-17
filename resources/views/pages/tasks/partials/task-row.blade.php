<div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3 cursor-pointer hover:bg-[#f8fbff] transition-colors {{ $task['is_overdue'] ? 'bg-[#fef2f2]/50 hover:bg-[#fef2f2]/70' : '' }}"
     data-testid="task-list-row"
     data-task-id="{{ $task['id'] }}"
     @click="openTaskDrawer({{ $task['id'] }}, $event)"
     role="button"
     tabindex="0"
     @keydown.enter.prevent="openTaskDrawer({{ $task['id'] }})">
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="font-semibold text-[#0f172a] text-[13.5px]">{{ $task['title'] }}</span>
            @if($task['is_overdue'])
                <span class="text-[10px] font-bold uppercase text-[#b91c1c] bg-[#fee2e2] px-1.5 py-0.5 rounded">Overdue</span>
            @endif
            @if(($task['priority_effective'] ?? $task['priority'] ?? '') === 'high')
                <span class="text-[10px] font-bold uppercase text-[#c2410c]">High</span>
            @endif
            @if(!empty($task['priority_elevated']))
                <span class="text-[10px] text-[#94a3b8]" title="Elevated because overdue">(was {{ $task['priority_stored'] ?? $task['priority'] ?? 'medium' }})</span>
            @endif
            @if($task['source'] === 'system')
                <span class="text-[10px] text-[#64748b]">Auto</span>
            @endif
        </div>
        <div class="text-[12px] text-[#64748b] mt-0.5">
            {{ $task['assignee'] }} · Due {{ $task['due_date'] }}
            @if($task['about']) · {{ $task['about'] }} @endif
        </div>
    </div>
    <div class="flex items-center gap-2 shrink-0" @click.stop>
        @if($task['related_url'])
            <a href="{{ $task['related_url'] }}" class="text-[12px] font-semibold text-[#2563eb]">Open record →</a>
        @endif
        <select onchange="Alpine.$data(this.closest('[x-data]')).updateStatus({{ $task['id'] }}, this.value)"
                class="rounded-lg border border-[#e2e8f0] px-2 py-1 text-[12px]">
            @foreach($boardStatuses as $status)
                <option value="{{ $status['key'] }}" @selected(($task['board_status'] ?? $task['status']) === $status['key'])>{{ $status['label'] }}</option>
            @endforeach
        </select>
    </div>
</div>
