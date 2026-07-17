{{-- Communications — wired to unified Communication log --}}
@php
    $cgName = $caregiver ? $caregiver->first_name.' '.$caregiver->last_name : 'Caregiver';
    $coordName = $coordinator?->name ?? 'Case Coordinator';

    $iconPaths = [
        'fax'   => '<path d="M6 9V2h12v7"/><rect x="6" y="13" width="12" height="8"/><path d="M6 13H4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2"/><path d="M18 13h2a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'sms'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/>',
        'note'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    ];

    $channelIcon = fn ($icon) => $iconPaths[$icon] ?? $iconPaths['note'];
@endphp

<div x-show="activeTab === 'communications'" x-cloak class="space-y-4"
     x-data="{ filter: 'all', matches(channel) {
         if (this.filter === 'all') return true;
         if (this.filter === 'calls') return ['call', 'wellness'].includes(channel);
         return this.filter === channel;
     } }">

    <div class="rounded-2xl border border-card-border bg-card px-5 py-3.5 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="text-xs font-bold text-[#94a3b8] uppercase tracking-wider mr-1">Reach out</span>
            @can('send', \App\Models\Communication::class)
                <a href="{{ route('communications.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold rounded-[9px] px-3 py-1.5 border border-[#d8e2f0] text-[#2563eb] bg-[#eff4ff] hover:border-[#2563eb] transition">
                    Open Communications log
                </a>
            @endcan
        </div>
        <span class="text-xs text-[#94a3b8]">Outbound via RingCentral &amp; Google Workspace · synced from the unified log</span>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
        @foreach([['all','All'],['calls','Calls'],['sms','SMS'],['email','Email'],['fax','Fax'],['wellness','Wellness calls']] as [$f,$label])
            <button type="button" @click="filter = '{{ $f }}'"
                :class="filter === '{{ $f }}' ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-card-border hover:border-[#94a3b8]'"
                class="text-sm font-semibold rounded-full px-3.5 py-1.5 border transition">{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        <div class="lg:col-span-2">
            <x-ui.panel>
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-base font-bold text-[#0f172a]">Communication Log</h3>
                    <a href="{{ route('communications.index') }}" class="text-xs font-semibold text-[#2563eb] hover:underline">View all</a>
                </div>

                @if(($clientCommunications ?? collect())->isEmpty())
                    <p class="py-8 text-center text-sm text-[#94a3b8]">No communications logged for this client yet. Use <strong class="text-[#475569]">New message</strong> or <strong class="text-[#475569]">New eFax</strong> on the Communications page.</p>
                @else
                    <div class="divide-y divide-card-border">
                        @foreach($clientCommunications as $p)
                            @php
                                $filterKey = match(true) {
                                    $p->isWellnessCall() => 'wellness',
                                    $p->channelIcon() === 'fax' => 'fax',
                                    $p->channelIcon() === 'email' => 'email',
                                    $p->channelIcon() === 'sms' => 'sms',
                                    $p->channelIcon() === 'call' => 'call',
                                    default => 'note',
                                };
                                $badgeTone = match($p->handledTone()) {
                                    'green' => 'green',
                                    'amber', 'orange' => 'amber',
                                    default => 'gray',
                                };
                            @endphp
                            <div x-show="matches('{{ $filterKey }}')" class="flex items-start gap-3 py-3.5">
                                <span class="w-8 h-8 rounded-full bg-[#dbe7fa] text-[#2563eb] flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $channelIcon($p->channelIcon()) !!}</svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-bold text-[#0f172a]">{{ $p->partyName() }}</span>
                                        @if($p->partyContext())
                                            <span class="text-xs text-[#94a3b8]">({{ $p->partyContext() }})</span>
                                        @endif
                                        <span class="text-xs text-[#94a3b8]">{{ $p->profileDirectionLabel() }}</span>
                                        <x-ui.pill :variant="$badgeTone" size="xs">{{ $p->handledLabel() }}</x-ui.pill>
                                        @if($p->hasArabicTag())
                                            <x-ui.pill variant="blue" size="xs">AR</x-ui.pill>
                                        @endif
                                        <span class="ml-auto text-xs text-[#94a3b8] whitespace-nowrap">{{ $p->whenLabel() }}</span>
                                    </div>
                                    <p class="text-sm text-[#475569] leading-relaxed mt-1">{{ $p->summary() }}</p>
                                    <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                                        <span class="text-xs font-semibold text-[#64748b]">{{ $p->profileMetaLabel() }}</span>
                                        <a href="{{ route('communications.show', $p->communication()) }}" class="text-xs font-semibold text-[#2563eb] hover:text-[#1d4ed8]">Open in log</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.panel>
        </div>

        <div class="space-y-4">
            <x-ui.panel title="Quick Contacts">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-full bg-[#dbe7fa] text-[#2563eb] text-sm font-bold flex items-center justify-center shrink-0">{{ strtoupper(mb_substr($client->first_name,0,1).mb_substr($client->last_name,0,1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold text-[#0f172a] truncate">{{ $client->first_name }} {{ $client->last_name }}</div>
                            <div class="text-xs text-[#94a3b8] truncate">Client</div>
                        </div>
                    </div>
                    @if($caregiver ?? null)
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full bg-[#dbe7fa] text-[#2563eb] text-sm font-bold flex items-center justify-center shrink-0">{{ strtoupper(mb_substr($caregiver->first_name,0,1).mb_substr($caregiver->last_name,0,1)) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold text-[#0f172a] truncate">{{ $cgName }}</div>
                                <div class="text-xs text-[#94a3b8] truncate">Caregiver</div>
                            </div>
                        </div>
                    @endif
                    @if($coordinator ?? null)
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full bg-[#dbe7fa] text-[#2563eb] text-sm font-bold flex items-center justify-center shrink-0">{{ strtoupper(mb_substr($coordName,0,2)) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold text-[#0f172a] truncate">{{ $coordName }}</div>
                                <div class="text-xs text-[#94a3b8] truncate">Case Coordinator</div>
                            </div>
                        </div>
                    @endif
                </div>
            </x-ui.panel>
        </div>
    </div>
</div>
