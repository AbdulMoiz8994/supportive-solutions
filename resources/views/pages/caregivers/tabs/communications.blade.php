@php
    $c = $caregiver;
    $comms = $c->communications->sortByDesc('occurred_at');
    $icon = fn($ch) => match($ch) {
        'Call' => '📞', 'SMS' => '💬', 'Email' => '✉️', 'Wellness' => '🩺', default => '📱',
    };
    $tagTone = fn($t) => str_contains((string)$t, 'AI') ? 'bg-green-50 text-green-600' : 'bg-blue-50 text-blue-600';
@endphp

{{-- Reach out bar --}}
<div class="bg-white rounded-[18px] border border-[#e2e8f0] px-5 py-4 mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[13px] font-bold text-blue-600 mr-1">Reach Out</span>
        @foreach(['Send SMS','Send email','Place call','App message'] as $action)
        <button class="px-3.5 py-1.5 bg-white border border-[#e2e8f0] rounded-lg text-[11px] font-bold text-[#475569] hover:bg-gray-50">{{ $action }}</button>
        @endforeach
    </div>
    <p class="text-[11px] text-[#94a3b8]">Outbound via RingCentral &amp; Google Workspace · AI Secretary speaks {{ $c->preferred_language ?? 'English' }}</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Log --}}
    <div class="lg:col-span-2 bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <h3 class="text-[15px] font-bold text-[#1e293b] mb-4">Communication Log</h3>
        <div class="space-y-3">
            @forelse($comms as $m)
            <div class="flex items-start gap-3 p-4 rounded-xl border border-[#f1f5f9] hover:bg-blue-50/30">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-[18px] shrink-0">{{ $icon($m->channel) }}</div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-[13px] font-bold text-[#1e293b]">{{ $m->title }}</p>
                        @if($m->direction)<span class="text-[10px] font-semibold text-[#94a3b8]">↗ {{ $m->direction }}</span>@endif
                        @if($m->tag)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $tagTone($m->tag) }}">{{ $m->tag }}</span>@endif
                    </div>
                    <p class="text-[12px] text-[#475569] mt-1 leading-relaxed">{{ $m->body }}</p>
                    <p class="text-[10px] text-[#94a3b8] mt-1.5">{{ $m->meta }} @if($m->meta) · @endif <span class="text-blue-600 font-bold cursor-pointer">Open thread</span></p>
                </div>
                <span class="text-[11px] text-[#94a3b8] shrink-0">{{ $m->occurred_at?->format('M j') }}</span>
            </div>
            @empty
            <p class="text-center text-[#94a3b8] italic py-8">No communications logged.</p>
            @endforelse
        </div>
    </div>

    {{-- Right column --}}
    <div class="space-y-5">
        <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
            <h3 class="text-[14px] font-bold text-[#1e293b] mb-4">Quick Contacts</h3>
            <div class="space-y-3">
                @foreach([
                    [$c->name, 'Caregiver · '.($c->preferred_language ?? 'English')],
                    [optional($servedClient)->first_name.' '.optional($servedClient)->last_name, 'Client he serves'],
                    ['SSHC Office', 'Agency line'],
                ] as [$name, $role])
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[11px] font-bold">{{ strtoupper(Str::substr($name,0,2)) }}</span>
                        <div><p class="text-[12.5px] font-bold text-[#1e293b]">{{ $name }}</p><p class="text-[10px] text-[#94a3b8]">{{ $role }}</p></div>
                    </div>
                    <div class="flex gap-1.5 text-[#94a3b8]">
                        <span class="w-7 h-7 rounded-lg border border-[#e2e8f0] flex items-center justify-center text-[12px] cursor-pointer">📞</span>
                        <span class="w-7 h-7 rounded-lg border border-[#e2e8f0] flex items-center justify-center text-[12px] cursor-pointer">💬</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
            <h3 class="text-[14px] font-bold text-[#1e293b] mb-4">Contact Preferences</h3>
            <div class="space-y-2.5 text-[12px]">
                <div class="flex justify-between"><span class="text-[#94a3b8]">Preferred language</span><span class="font-bold text-[#1e293b]">{{ $c->preferred_language ?? 'English' }}</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8]">Best channel</span><span class="font-bold text-[#1e293b]">Call / SMS</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8]">Wellness call</span><span class="font-bold text-[#1e293b]">End of month · AI</span></div>
                <div class="flex justify-between"><span class="text-[#94a3b8]">Consent to text</span><span class="font-bold text-[#1e293b]">Yes</span></div>
            </div>
        </div>

        <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
            <h3 class="text-[14px] font-bold text-[#1e293b] mb-3">Next scheduled</h3>
            <div class="flex items-center gap-3 bg-blue-50/60 rounded-xl px-4 py-3">
                <span class="w-9 h-9 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">🩺</span>
                <div><p class="text-[12.5px] font-bold text-[#1e293b]">{{ now()->format('M') }} wellness call</p><p class="text-[10px] text-[#94a3b8]">AI Secretary · {{ now()->endOfMonth()->format('M j') }}</p></div>
            </div>
        </div>
    </div>
</div>

