@php
    $c = $caregiver;
    $all = $c->caregiverNotes->sortByDesc('noted_at');
    $pinned = $all->where('pinned', true);
    $feed = $all->where('pinned', false);
    $authorIcon = fn($n) => $n->author_type === 'human' ? strtoupper(Str::substr($n->author_name, 0, 2)) : '⚙';
    $tagTone = fn($t) => match($t) {
        'Concern' => 'bg-red-50 text-red-600',
        'Reminder' => 'bg-orange-50 text-orange-500',
        'Approval', 'Pay', 'Checks' => 'bg-green-50 text-green-600',
        'Activity' => 'bg-blue-50 text-blue-600',
        default => 'bg-gray-100 text-gray-500',
    };
@endphp

{{-- Composer (working) --}}
<div class="bg-white rounded-[20px] border border-[#e2e8f0] p-5 mb-5">
    <form method="POST" action="{{ route('caregivers.notes.store', $c->id) }}">
        @csrf
        <textarea name="body" rows="2" required placeholder="Add a note about {{ $c->first_name }}… (type @ to mention a teammate)"
            class="w-full px-4 py-3 bg-blue-50/30 border border-[#e2e8f0] rounded-xl text-[13px] text-[#1e293b] outline-none focus:ring-2 focus:ring-blue-500/10 resize-none"></textarea>
        <div class="flex items-center justify-between mt-3">
            <div class="flex items-center gap-2">
                <select name="tag" class="px-3 py-1.5 bg-white border border-[#e2e8f0] rounded-lg text-[11px] font-bold text-[#475569]">
                    @foreach(['General','Reminder','Concern','Activity'] as $t)<option>{{ $t }}</option>@endforeach
                </select>
                <span class="px-3 py-1.5 bg-white border border-[#e2e8f0] rounded-lg text-[11px] font-bold text-[#475569]">@ Mention</span>
                <span class="px-3 py-1.5 bg-white border border-[#e2e8f0] rounded-lg text-[11px] font-bold text-[#475569]">📌 Pin</span>
            </div>
            <button type="submit" class="px-5 py-2 bg-[#2563eb] text-white rounded-lg text-[12px] font-bold shadow-lg shadow-blue-100">Save note</button>
        </div>
    </form>
</div>

{{-- Pinned --}}
@if($pinned->count())
<h3 class="text-[14px] font-bold text-[#1e293b] mb-3">Pinned</h3>
<div class="space-y-3 mb-6">
    @foreach($pinned as $n)
    <div class="bg-white rounded-[16px] border border-[#e2e8f0] p-4">
        <div class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-full {{ $n->author_type==='human' ? 'bg-blue-100 text-blue-700' : 'bg-blue-50 text-blue-500' }} flex items-center justify-center text-[11px] font-bold shrink-0">{{ $authorIcon($n) }}</span>
            <div class="flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[13px] font-bold text-[#1e293b]">{{ $n->author_name }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $n->author_type==='human' ? 'bg-blue-50 text-blue-600' : 'bg-blue-50 text-blue-600' }}">{{ $n->author_role }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tagTone($n->tag) }}">{{ $n->tag }}</span>
                </div>
                <div class="mt-2 bg-amber-50/60 border-l-2 border-amber-300 rounded-lg px-3 py-2.5 text-[12px] text-[#475569]">{{ $n->body }}</div>
                <p class="text-[10px] text-[#94a3b8] mt-1.5">📌 Pinned</p>
            </div>
            <span class="text-[11px] text-[#94a3b8]">{{ $n->noted_at?->format('M j') }}</span>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Feed --}}
<h3 class="text-[14px] font-bold text-[#1e293b] mb-3">All Notes &amp; Activity <span class="text-[#94a3b8] font-semibold">· Newest first</span></h3>
<div class="bg-white rounded-[20px] border border-[#e2e8f0] divide-y divide-[#f1f5f9]">
    @foreach($feed as $n)
    <div class="flex items-start gap-3 p-4">
        <span class="w-9 h-9 rounded-full {{ $n->author_type==='human' ? 'bg-blue-100 text-blue-700' : 'bg-blue-50 text-blue-500' }} flex items-center justify-center text-[11px] font-bold shrink-0">{{ $authorIcon($n) }}</span>
        <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[13px] font-bold text-[#1e293b]">{{ $n->author_name }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-600">{{ $n->author_role }}</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tagTone($n->tag) }}">{{ $n->tag }}</span>
            </div>
            @if($n->tag === 'Concern')
                <div class="mt-2 bg-red-50/60 border-l-2 border-red-300 rounded-lg px-3 py-2.5 text-[12px] text-[#475569]">{{ $n->body }}</div>
            @else
                <p class="text-[12px] text-[#475569] mt-1.5 leading-relaxed">{{ $n->body }}</p>
            @endif
        </div>
        <span class="text-[11px] text-[#94a3b8] shrink-0">{{ $n->noted_at?->format('M j') }}</span>
    </div>
    @endforeach
</div>

