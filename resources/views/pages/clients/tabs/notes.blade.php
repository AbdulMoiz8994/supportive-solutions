{{-- Notes & Activity --}}
@php
    $first = $client->first_name;

    // Author badge tone: AI agents vs humans.
    $feed = [
        ['name' => 'Authorizations Agent', 'atype' => 'ai', 'tag' => 'Reminder', 'tagTone' => 'amber', 'tone' => 'pin', 'pinned' => true, 'kind' => 'notes',
         'when' => 'Jan 30', 'body' => '<b>Live-In Exemption renews Nov 2026.</b> Agent will refile the exemption ~30 days before expiry; no action needed now.', 'links' => ['Unpin']],
        ['name' => 'Ali Beydoun', 'atype' => 'human', 'arole' => 'Owner', 'tag' => 'General', 'tagTone' => 'gray', 'tone' => 'pin', 'pinned' => true, 'kind' => 'notes',
         'when' => 'Feb 2', 'body' => 'Family prefers son <b>Yousef</b> as the sole point of contact; all calls/texts in <b>Arabic</b>. Maria does not use the phone herself.', 'links' => ['Unpin', 'Edit']],
        ['name' => 'Ali Beydoun', 'atype' => 'ai', 'tag' => 'Activity', 'tagTone' => 'gray', 'tone' => 'none', 'pinned' => false, 'kind' => 'activity',
         'when' => 'May 17 · 9:02 AM', 'body' => 'Aetna PA renewal notice received → renewal task created (PA expires Jun 14). Filed to Documents › Authorizations.', 'links' => ['Go to Authorization']],
        ['name' => 'Billing Agent', 'atype' => 'ai', 'tag' => 'Payment', 'tagTone' => 'green', 'tone' => 'none', 'pinned' => false, 'kind' => 'activity',
         'when' => 'May 12', 'body' => 'April claim paid $3,240 — EOB received from Aetna, matched and marked Paid. No balance due.', 'links' => ['View EOB']],
        ['name' => 'Billing Agent', 'atype' => 'ai', 'tag' => 'Billing', 'tagTone' => 'blue', 'tone' => 'none', 'pinned' => false, 'kind' => 'activity',
         'when' => 'May 1', 'body' => 'April claim submitted to Availity — 108 hrs (3 hospital days excluded) at $30/hr = $3,240.', 'links' => ['View claim PDF']],
        ['name' => 'AI Secretary', 'atype' => 'ai', 'tag' => 'Reminder', 'tagTone' => 'red', 'tone' => 'concern', 'pinned' => false, 'kind' => 'notes',
         'when' => 'Apr 30', 'body' => 'April wellness call done. Client recovering after the Apr 10–12 hospital stay — <b>recommend confirming follow-up care and watching May attendance.</b> Routed to task queue.', 'links' => ['Recording', 'Open task']],
        ['name' => 'Ali Beydoun', 'atype' => 'human', 'arole' => 'Owner', 'tag' => 'General', 'tagTone' => 'gray', 'tone' => 'none', 'pinned' => false, 'kind' => 'notes',
         'when' => 'Jan 30', 'body' => 'Spoke with Yousef — Maria is back home and doing well as of today. Resume normal schedule.', 'links' => []],
        ['name' => 'AI Secretary', 'atype' => 'ai', 'tag' => 'Activity', 'tagTone' => 'amber', 'tone' => 'none', 'pinned' => false, 'kind' => 'activity',
         'when' => 'Apr 10', 'body' => '<b>Hospitalization logged</b> — caregiver reported Maria admitted Apr 10. Flagged for billing exclusion; discharge follow-up task created.', 'links' => []],
        ['name' => 'Compliance Agent', 'atype' => 'ai', 'tag' => 'Activity', 'tagTone' => 'green', 'tone' => 'none', 'pinned' => false, 'kind' => 'activity',
         'when' => 'Feb–Mar', 'body' => 'February &amp; March compliance forms received and verified; invoices generated and paid.', 'links' => []],
        ['name' => 'Ali Beydoun', 'atype' => 'human', 'arole' => 'Owner', 'tag' => 'Approval', 'tagTone' => 'green', 'tone' => 'none', 'pinned' => false, 'kind' => 'notes',
         'when' => 'Feb 1', 'body' => '<b>Client activated</b> — reviewed intake + PA and approved to activate. Services live.', 'links' => []],
        ['name' => 'Authorizations Agent', 'atype' => 'ai', 'tag' => 'Activity', 'tagTone' => 'gray', 'tone' => 'none', 'pinned' => false, 'kind' => 'activity',
         'when' => 'Jan 28', 'body' => 'Prior Authorization PA-2026-0042 received from Aetna (valid through Jun 14). Caregiver linked; background checks cleared.', 'links' => []],
        ['name' => 'Intake', 'atype' => 'human', 'arole' => 'R. Saleh', 'tag' => 'Activity', 'tagTone' => 'gray', 'tone' => 'none', 'pinned' => false, 'kind' => 'activity',
         'when' => 'Jan 9', 'body' => 'Client chart created · eligibility verified (dual → MICH) · status set to Pending Application.', 'links' => []],
    ];
    $pinned = array_filter($feed, fn ($e) => $e['pinned']);

    $authorAvatar = function ($e) {
        if ($e['atype'] === 'ai') {
            return '<span class="w-9 h-9 rounded-full bg-[#dbe7fa] text-[#2563eb] flex items-center justify-center shrink-0"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="7" width="16" height="12" rx="2"/><path d="M9 7V4h6v3M9 13h.01M15 13h.01"/></svg></span>';
        }
        $init = strtoupper(mb_substr($e['name'], 0, 1).mb_substr(explode(' ', $e['name'].' ')[1], 0, 1));
        return '<span class="w-9 h-9 rounded-full bg-[#c9d8ee] text-[#334155] text-sm font-bold flex items-center justify-center shrink-0">'.$init.'</span>';
    };
@endphp

<div x-show="activeTab === 'notes'" x-cloak class="space-y-4"
     x-data="{ filter: 'all', show(kind, author, pinned) { return this.filter === 'all' || this.filter === kind || this.filter === author || (this.filter === 'pinned' && pinned); } }">

    {{-- Composer --}}
    <div class="rounded-2xl border border-card-border bg-card p-5" x-data="{ text: '', pin: false }">
        <textarea x-model="text" rows="2" placeholder="Add a note about {{ $first }}… (type @ to mention a teammate)" class="w-full px-0 py-1 bg-transparent text-sm text-[#0f172a] outline-none placeholder-[#94a3b8] resize-none"></textarea>
        <div class="flex items-center justify-between gap-3 mt-3 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <select class="appearance-none text-sm font-semibold text-[#475569] bg-white border border-card-border rounded-[8px] pl-7 pr-7 py-1.5 outline-none focus:border-[#2563eb] cursor-pointer">
                        <option>General</option><option>Reminder</option><option>Concern</option><option>Approval</option>
                    </select>
                    <svg class="w-3.5 h-3.5 text-[#2563eb] absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1"/></svg>
                    <svg class="w-3 h-3 text-[#94a3b8] absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <button type="button" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#475569] bg-white border border-card-border rounded-[8px] px-2.5 py-1.5 hover:border-[#94a3b8]">
                    <svg class="w-3.5 h-3.5 text-[#2563eb]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/></svg>Mention
                </button>
                <button type="button" @click="pin = !pin" :class="pin ? 'border-[#2563eb] text-[#2563eb] bg-[#eff4ff]' : 'border-card-border text-[#475569] bg-white hover:border-[#94a3b8]'" class="inline-flex items-center gap-1.5 text-sm font-semibold rounded-[8px] px-2.5 py-1.5 border">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5M9 10.76V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v6.76a2 2 0 0 0 .59 1.41l1.7 1.7A1 1 0 0 1 17.59 16H6.41a1 1 0 0 1-.7-1.71l1.7-1.7A2 2 0 0 0 8 11.18"/></svg>Pin
                </button>
                <button type="button" class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#475569] bg-white border border-card-border rounded-[8px] px-2.5 py-1.5 hover:border-[#94a3b8]">
                    <svg class="w-3.5 h-3.5 text-[#2563eb]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66L9.64 16.2a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>Attach
                </button>
            </div>
            <x-ui.btn variant="primary" size="sm" x-on:click="text = ''; pin = false">Save note</x-ui.btn>
        </div>
    </div>

    {{-- Filter chips --}}
    <div class="flex items-center gap-2 flex-wrap">
        @foreach([['all','All'],['notes','Notes'],['activity','Activity'],['pinned','Pinned'],['ai','AI'],['human','Human']] as [$f,$label])
            <button type="button" @click="filter = '{{ $f }}'"
                :class="filter === '{{ $f }}' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-card-border hover:border-[#94a3b8]'"
                class="text-sm font-semibold rounded-full px-3.5 py-1.5 border transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- Date filter --}}
    <div class="rounded-2xl border border-card-border bg-card px-5 py-3 flex items-center gap-3 flex-wrap">
        <span class="text-xs font-bold text-[#94a3b8] uppercase tracking-wider">Filter by date</span>
        <button type="button" class="inline-flex items-center gap-2 text-sm font-semibold text-[#475569] bg-white border border-card-border rounded-[8px] px-3 py-1.5 hover:border-[#94a3b8]">
            <svg class="w-3.5 h-3.5 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>From: May 1, 2026
            <svg class="w-3 h-3 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <button type="button" class="inline-flex items-center gap-2 text-sm font-semibold text-[#475569] bg-white border border-card-border rounded-[8px] px-3 py-1.5 hover:border-[#94a3b8]">
            <svg class="w-3.5 h-3.5 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>To: May 31, 2026
            <svg class="w-3 h-3 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <button type="button" class="text-sm font-semibold text-[#475569] bg-white border border-card-border rounded-[8px] px-3 py-1.5 hover:border-[#94a3b8]">Clear</button>
        <x-ui.btn variant="primary" size="sm">Apply</x-ui.btn>
        <div class="ml-auto flex items-center gap-3 text-sm font-semibold text-[#2563eb]">
            <button type="button" class="hover:text-[#1d4ed8]">Today</button>
            <button type="button" class="hover:text-[#1d4ed8]">This month</button>
            <button type="button" class="hover:text-[#1d4ed8]">All time</button>
        </div>
    </div>

    {{-- Pinned --}}
    <x-ui.panel title="Pinned">
        <div class="space-y-4">
            @foreach($pinned as $e)
                <div class="flex items-start gap-3">
                    {!! $authorAvatar($e) !!}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-bold text-[#0f172a]">{{ $e['name'] }}</span>
                            @if($e['atype'] === 'ai')<x-ui.pill variant="indigo" size="xs">AI</x-ui.pill>@else<x-ui.pill variant="blue" size="xs">{{ $e['arole'] ?? 'Human' }}</x-ui.pill>@endif
                            <x-ui.pill :variant="$e['tagTone']" size="xs">{{ $e['tag'] }}</x-ui.pill>
                            <span class="ml-auto text-xs text-[#94a3b8]">{{ $e['when'] }}</span>
                        </div>
                        <div class="mt-1.5 rounded-xl border-l-[3px] border-[#f59e0b] bg-[#fbf6ec] px-4 py-2.5 text-sm text-[#334155] leading-relaxed">{!! $e['body'] !!}</div>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="text-xs font-semibold text-[#94a3b8]">Pinned</span>
                            @foreach($e['links'] as $lnk)<button type="button" class="text-xs font-semibold text-[#2563eb] hover:text-[#1d4ed8]">{{ $lnk }}</button>@endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-ui.panel>

    {{-- All notes & activity --}}
    <x-ui.panel>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-bold text-[#0f172a]">All Notes &amp; Activity</h3>
            <span class="text-xs font-medium text-[#94a3b8]">Newest first</span>
        </div>
        <div class="divide-y divide-card-border">
            @foreach($feed as $e)
                <div x-show="show('{{ $e['kind'] }}', '{{ $e['atype'] }}', {{ $e['pinned'] ? 'true' : 'false' }})" class="flex items-start gap-3 py-3.5">
                    {!! $authorAvatar($e) !!}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-bold text-[#0f172a]">{{ $e['name'] }}</span>
                            @if($e['atype'] === 'ai')<x-ui.pill variant="indigo" size="xs">AI</x-ui.pill>@else<x-ui.pill variant="blue" size="xs">{{ $e['arole'] ?? 'Human' }}</x-ui.pill>@endif
                            <x-ui.pill :variant="$e['tagTone']" size="xs">{{ $e['tag'] }}</x-ui.pill>
                            <span class="ml-auto text-xs text-[#94a3b8] whitespace-nowrap">{{ $e['when'] }}</span>
                        </div>
                        @if($e['tone'] === 'concern')
                            <div class="mt-1.5 rounded-xl border-l-[3px] border-[#ef4444] bg-[#fef2f2] px-4 py-2.5 text-sm text-[#334155] leading-relaxed">{!! $e['body'] !!}</div>
                        @elseif($e['tone'] === 'pin')
                            <div class="mt-1.5 rounded-xl border-l-[3px] border-[#f59e0b] bg-[#fbf6ec] px-4 py-2.5 text-sm text-[#334155] leading-relaxed">{!! $e['body'] !!}</div>
                        @else
                            <p class="text-sm text-[#475569] leading-relaxed mt-1">{!! $e['body'] !!}</p>
                        @endif
                        @if(count($e['links']))
                            <div class="flex items-center gap-3 mt-1.5">
                                @foreach($e['links'] as $lnk)<button type="button" class="text-xs font-semibold text-[#2563eb] hover:text-[#1d4ed8]">{{ $lnk }}</button>@endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-ui.panel>
</div>
