{{-- Compliance Forms --}}
<div x-show="activeTab === 'compliance'" x-cloak class="space-y-4">

    {{-- Process strip --}}
    <x-ui.panel bodyClass="px-5 py-3.5">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
            @foreach([
                'End-of-month wellness call (AI/VA)',
                'Caregiver submits compliance form',
                'Verify hours/days met (prorate if short)',
                '~10-day pay grace window',
                'Payroll batch (1st Tue) + invoice',
            ] as $i => $step)
                <div class="flex items-center gap-2 shrink-0">
                    <span class="w-5 h-5 rounded-full bg-[#eff4ff] text-[#2563eb] text-xs font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                    <span class="text-sm font-medium text-[#475569] whitespace-nowrap">{{ $step }}</span>
                    @if(! $loop->last)<svg class="w-3.5 h-3.5 text-[#cbd5e1]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>@endif
                </div>
            @endforeach
        </div>
    </x-ui.panel>

    {{-- Month cards --}}
    @php $months = collect(range(-4, 1))->map(fn ($m) => now()->copy()->addMonths($m)); @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach($months as $m)
            @php $isCurrent = $m->isSameMonth(now()); $isPast = $m->lessThan(now()->startOfMonth()); @endphp
            <div class="rounded-xl border bg-white px-3.5 py-3 {{ $isCurrent ? 'border-[#2563eb] ring-1 ring-[#2563eb]/20' : 'border-[#e6eef9]' }}">
                <div class="text-sm font-bold text-[#0f172a]">{{ $m->format('M Y') }}</div>
                <div class="flex items-center gap-1.5 mt-1.5">
                    <span class="w-2 h-2 rounded-full {{ $isPast ? 'bg-[#16a34a]' : ($isCurrent ? 'bg-[#f79009]' : 'bg-[#cbd5e1]') }}"></span>
                    <span class="text-xs font-medium text-[#64748b]">{{ $isPast ? 'Services provided' : ($isCurrent ? 'Awaiting form' : '—') }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Visit log + compliance check --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        {{-- Daily visit log calendar --}}
        <x-ui.panel>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-[#0f172a]">{{ now()->format('F Y') }} — Daily Visit Log</h3>
                <x-ui.pill variant="green">Received</x-ui.pill>
            </div>
            @php
                $first = now()->copy()->startOfMonth();
                $daysInMonth = now()->daysInMonth;
                $startDow = (int) $first->format('w'); // 0=Sun
                $today = now()->day;
            @endphp
            <div class="grid grid-cols-7 gap-1.5 text-center">
                @foreach(['S','M','T','W','T','F','S'] as $d)
                    <div class="text-xs font-bold text-[#94a3b8] py-1">{{ $d }}</div>
                @endforeach
                @for($i = 0; $i < $startDow; $i++)<div></div>@endfor
                @for($day = 1; $day <= $daysInMonth; $day++)
                    @php $served = $day < $today; $off = in_array($day, [11, 12]); @endphp
                    <div class="aspect-square rounded-lg border flex items-center justify-center text-sm font-semibold
                        {{ $off ? 'border-[#fdecc8] bg-[#fffaf0] text-[#b54708]' : ($served ? 'border-[#d1fadf] bg-[#ecfdf3] text-[#067647]' : 'border-[#eef2f9] bg-[#fafcff] text-[#94a3b8]') }}">
                        {{ $day }}
                    </div>
                @endfor
            </div>
            <div class="flex items-center gap-4 mt-4 text-xs font-medium text-[#64748b]">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#16a34a]"></span> Served</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#f79009]"></span> Not worked</span>
            </div>
        </x-ui.panel>

        <div class="space-y-4">
            {{-- Compliance check --}}
            <x-ui.panel>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-[#0f172a]">Compliance Check — {{ now()->format('F') }}</h3>
                    <x-ui.pill variant="blue">{{ $program }} · hours-based</x-ui.pill>
                </div>
                @php
                    $authHrs = $authDetail?->hours_per_week ?? 120;
                    $delivered = round($authHrs * 0.9);
                @endphp
                <div class="rounded-xl border border-[#dbe6ff] bg-[#f3f8ff] px-4 py-3.5 space-y-2.5">
                    <div class="flex items-center justify-between text-sm"><span class="text-[#2563eb] font-medium">Authorized this month</span><span class="font-bold text-[#0f172a]">{{ $authHrs }} hrs</span></div>
                    <div class="flex items-center justify-between text-sm"><span class="text-[#2563eb] font-medium">Days not worked (excluded)</span><span class="font-bold text-[#0f172a]">2 · excluded</span></div>
                    <div class="flex items-center justify-between text-sm"><span class="text-[#2563eb] font-medium">Hours delivered & verified</span><span class="font-bold text-[#0f172a]">{{ $delivered }} hrs</span></div>
                    <div class="flex items-center justify-between text-sm pt-2 border-t border-[#dbe6ff]"><span class="text-[#2563eb] font-medium">Status</span><span class="font-bold text-[#067647]">Compliant</span></div>
                </div>
                <div class="mt-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm text-[#067647] leading-relaxed">
                    <span class="font-bold">MICH — meet the authorized hours.</span> A missed day is fine as long as the month's authorized hours are met (no proration). Hospitalized days are always excluded.
                </div>
            </x-ui.panel>

            {{-- HHAeXchange --}}
            <x-clients.edit-panel title="HHAeXchange Verification" section="comp-evv" tab="compliance" :action="$updateUrl" editLabel="Edit">
                <div class="grid grid-cols-1 gap-3 pt-1">
                    <x-clients.efield label="EVV status" name="evv_status" type="select"
                        :options="['Exempt — live-in caregiver (no clock-in / out)', 'Active — clock-in / out required', 'Not set']"
                        :selected="$client->evv_status" placeholder="Select EVV status" col="2" />
                    <x-clients.efield label="Last HHAeXchange check"
                        :value="now()->format('M j, Y · g:i A')" muted col="2" />
                    <x-clients.efield label="Clocked vs authorized hours" value="N/A (exempt) — hours confirmed via compliance form" muted col="2" />
                </div>
            </x-clients.edit-panel>
        </div>
    </div>

    {{-- Acknowledgments + wellness call --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        <x-ui.panel>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-bold text-[#0f172a]">Compliance Acknowledgments</h3>
                <x-ui.pill variant="green">All initialed</x-ui.pill>
            </div>
            <div class="divide-y divide-[#f1f5f9]">
                @foreach([
                    'I personally provided all services and was physically present the entire time; I did not delegate my duties.',
                    'All services followed the MDHHS-approved care plan (Time & Task); I won\'t be paid for hours outside that schedule.',
                    'I did not claim hours for any day the client was hospitalized, admitted, or otherwise unavailable.',
                    'I did not submit or request payment for services that were not performed.',
                    'All timesheets, electronic logs, and entries were submitted accurately and truthfully.',
                    'I reported any changes in the client\'s condition, hospitalizations, or address in a timely manner.',
                    'I understand false/fraudulent claims may result in termination, repayment, and legal prosecution.',
                    'HHAeXchange clock-in/out completed for every visit. (Exempt — live-in caregiver)',
                ] as $ack)
                    <div class="flex items-start gap-2.5 py-2.5">
                        <span class="w-5 h-5 rounded-md bg-[#ecfdf3] border border-[#d1fadf] text-[#067647] text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">{{ $caregiver ? strtoupper(substr($caregiver->first_name,0,1).substr($caregiver->last_name,0,1)) : 'YH' }}</span>
                        <span class="text-sm text-[#475569] leading-relaxed">{{ $ack }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.panel>

        <div class="space-y-4">
            <x-ui.panel>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-[#0f172a]">Wellness Call — {{ now()->format('F') }}</h3>
                    <x-ui.pill variant="green">Completed</x-ui.pill>
                </div>
                <div class="space-y-3 pt-1">
                    <x-clients.field label="Date · by" :value="now()->format('F j, Y').' · AI Secretary (VA)'" />
                    <div>
                        <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Summary</div>
                        <div class="rounded-[9px] border border-[#e6eef9] bg-[#fafcff] px-3.5 py-2.5 text-sm text-[#475569] leading-relaxed">Reached the caregiver and confirmed services for the month. Client is home; no travel and no change of address reported.</div>
                    </div>
                </div>
            </x-ui.panel>

            <x-ui.panel title="{{ now()->copy()->addMonth()->format('F Y') }} — pending">
                <div class="rounded-xl border border-[#fdecc8] bg-[#fffaf0] px-4 py-3 text-sm text-[#92400e] leading-relaxed">
                    End-of-month wellness call is scheduled. Once the caregiver submits next month's form, it prorates and enters the <span class="font-bold">~10-day pay grace window</span> before the next payroll batch (1st Tuesday).
                </div>
                <div class="flex items-center gap-2 mt-3">
                    <form action="{{ route('clients.wellness-call', $client->id) }}" method="POST">
                        @csrf
                        <x-ui.btn variant="outline" size="sm" type="submit">Trigger wellness call</x-ui.btn>
                    </form>
                    <x-ui.btn variant="primary" size="sm">Upload form</x-ui.btn>
                </div>
            </x-ui.panel>
        </div>
    </div>
</div>
