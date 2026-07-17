{{-- Program & Authorization --}}
<div x-show="activeTab === 'authorization'" x-cloak class="space-y-4"
     x-data="{ addAuth: {{ request('add_auth') ? 'true' : 'false' }} }"
     @keydown.escape.window="addAuth = false"
     x-init="@if(request('care_detail')) $nextTick(() => document.getElementById('care-detail-{{ (int) request('care_detail') }}')?.scrollIntoView({ block: 'center', behavior: 'smooth' })) @endif">

    @php
        $authRef = $authDetail ? $authDetail->authRefForProgram($program) : null;
        $daysLeft = $auth['days'] !== null ? max(0, $auth['days']) : null;
        $highlightTone = $auth['tone'] === 'red' ? 'red' : ($auth['tone'] === 'amber' ? 'amber' : 'blue');
        $borderClr = ['amber' => '#fdecc8', 'red' => '#fee4e2', 'blue' => '#dbe6ff'][$highlightTone];
        $bgClr     = ['amber' => '#fffaf0', 'red' => '#fef3f2', 'blue' => '#f3f8ff'][$highlightTone];
        $accentClr = ['amber' => '#b54708', 'red' => '#d92d20', 'blue' => '#2563eb'][$highlightTone];
    @endphp

    {{-- Auth type selector --}}
    <x-ui.panel bodyClass="px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">Authorization type</span>
                <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-[9px] border border-[#e2e8f0] bg-white text-sm font-semibold text-[#0f172a]">
                    <svg class="w-4 h-4 text-[#2563eb]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/></svg>
                    {{ $program === 'DHS' ? 'DHS Time/Task Sheet' : 'Prior Authorization (MICH)' }}
                </span>
            </div>
            <p class="text-sm text-[#94a3b8]">A DHS Time/Task Sheet has no expiry — reassessment every 6 months instead of renewal.</p>
        </div>
    </x-ui.panel>

    {{-- PA highlight card --}}
    <div class="rounded-2xl border-2 p-5" style="border-color: {{ $borderClr }}; background: {{ $bgClr }}">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                @if($daysLeft !== null && $program !== 'DHS')
                    <div class="text-center shrink-0">
                        <div class="text-4xl font-extrabold leading-none" style="color: {{ $accentClr }}">{{ $daysLeft }}</div>
                        <div class="text-xs font-semibold text-[#94a3b8] uppercase mt-1">Days left</div>
                    </div>
                @elseif($program === 'DHS' && $auth['days'] !== null)
                    <div class="text-center shrink-0">
                        <div class="text-4xl font-extrabold leading-none" style="color: {{ $accentClr }}">{{ $auth['days'] < 0 ? abs($auth['days']) : $auth['days'] }}</div>
                        <div class="text-xs font-semibold text-[#94a3b8] uppercase mt-1">{{ $auth['days'] < 0 ? 'Days overdue' : 'Days to reassess' }}</div>
                    </div>
                @endif
                <div>
                    <h3 class="text-base font-bold text-[#0f172a] flex items-center gap-2">
                        {{ $program === 'DHS' ? 'Time/Task Sheet' : 'Prior Authorization' }} · {{ $authRef ?? '—' }}
                        @if($program === 'DHS')
                            @if($authDetail && $authDetail->effectiveStatusForProgram($program) === 'Reassessment due')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-[#fffaeb] text-[#b54708] border border-[#fedf89]">Reassessment due</span>
                            @endif
                        @elseif($authDetail && $authDetail->needs_renewal)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-[#fef3f2] text-[#d92d20] border border-[#fee4e2]">Renewal due</span>
                        @elseif($authDetail && $authDetail->is_expired)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-[#fef2f2] text-[#b42318] border border-[#fecdca]">Expired · no billing</span>
                        @endif
                    </h3>
                    <p class="text-sm mt-0.5" style="color: {{ $accentClr }}">
                        @if($program === 'DHS')
                            {{ $authDetail?->effectiveStatusForProgram($program) ?? 'No authorization' }}@if($authDetail?->end_date) · reassess {{ \Carbon\Carbon::parse($authDetail->end_date)->format('F j, Y') }} · no expiry @endif
                        @else
                            {{ $authDetail?->effectiveStatusForProgram($program) ?? 'No authorization' }}@if($authDetail?->end_date) · expires {{ \Carbon\Carbon::parse($authDetail->end_date)->format('F j, Y') }}@endif · Aetna Better Health (via Availity)
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.btn variant="outline" size="sm" :href="route('clients.pa-letter.download', $client)">Download PA letter</x-ui.btn>
                <x-ui.btn variant="primary" size="sm">Start renewal</x-ui.btn>
            </div>
        </div>
        @if($authDetail)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5 pt-4 border-t" style="border-color: {{ $borderClr }}">
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">Service code</div>
                    <div class="text-sm font-bold text-[#0f172a] mt-1">{{ $authDetail->billing_code ?? '—' }} <span class="text-xs font-medium text-[#94a3b8]">· 15-min units</span></div>
                </div>
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">Authorized</div>
                    <div class="text-sm font-bold text-[#0f172a] mt-1">{{ $authDetail->total_units ?? '—' }} units <span class="text-xs font-medium text-[#94a3b8]">(≈{{ $authDetail->hours_per_week_value ?? '—' }} hrs/wk)</span></div>
                    @if($authDetail->hours_per_day !== null)
                        <div class="text-xs font-medium text-[#94a3b8] mt-0.5">≈{{ $authDetail->hours_per_day }} hrs/day · {{ $authDetail->hours_per_month }} hrs/mo</div>
                    @endif
                </div>
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">Effective</div>
                    <div class="text-sm font-bold text-[#0f172a] mt-1">{{ $authDetail->start_date ? \Carbon\Carbon::parse($authDetail->start_date)->format('M j, Y') : '—' }}</div>
                </div>
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">Expires</div>
                    <div class="text-sm font-bold mt-1" style="color: {{ $accentClr }}">{{ $authDetail->end_date ? \Carbon\Carbon::parse($authDetail->end_date)->format('M j, Y') : '—' }}</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Details + Renewal --}}
    @php
        // Units consumed: sum of billed schedules in current auth period
        $usedUnits = 0;
        if ($authDetail && $authDetail->start_date && $authDetail->end_date) {
            $usedUnits = $client->schedules
                ->where('start_time', '>=', $authDetail->start_date)
                ->where('start_time', '<=', $authDetail->end_date)
                ->sum(fn($s) => (int) ceil(
                    (\Carbon\Carbon::parse($s->start_time)->diffInMinutes(\Carbon\Carbon::parse($s->end_time))) / 15
                ));
        }
        $totalUnits     = $authDetail?->total_units ?? 0;
        $remainingUnits = max(0, $totalUnits - $usedUnits);
        $usedPct        = $totalUnits > 0 ? min(100, round(($usedUnits / $totalUnits) * 100)) : 0;
        $weeklyAvgUnits = $authDetail?->hours_per_week ? round($authDetail->hours_per_week * 4, 1) : null;
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
        <x-clients.edit-panel title="Authorization Details" section="auth-details" tab="authorization"
            :action="$authDetail ? route('clients.care-details.update', ['id' => $client->id, 'careDetail' => $authDetail->id]) : null">
            <div class="grid grid-cols-2 gap-x-4 gap-y-4 pt-1">
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Program</div>
                    <div><x-ui.pill :variant="$program === 'DHS' ? 'indigo' : 'blue'">{{ $program }}</x-ui.pill></div>
                </div>
                <x-clients.efield label="Authorization type" :value="$program === 'DHS' ? 'DHS Time/Task Sheet' : 'Prior Authorization (PA)'" />
                <x-clients.efield label="PA number" :value="$authRef" placeholder="—" />
                <x-clients.efield label="MCO" value="Aetna Better Health (Availity)" />
                <x-clients.efield label="Service code" name="billing_code" :value="$authDetail?->billing_code" placeholder="T1019" :required="(bool) $authDetail" />
                <x-clients.efield label="Authorized units / month" name="total_units" type="number"
                    :value="$authDetail?->total_units"
                    :display="$authDetail ? $authDetail->total_units.' units' : null"
                    placeholder="—" :required="(bool) $authDetail" />
                <x-clients.efield label="Weekly average"
                    :value="$weeklyAvgUnits ? $weeklyAvgUnits.' units  (≈'.$authDetail->hours_per_week.' hrs/wk)' : null" placeholder="—" />
                <x-clients.efield label="Effective date" name="start_date" type="date"
                    :value="$authDetail?->start_date ? \Carbon\Carbon::parse($authDetail->start_date)->format('Y-m-d') : null"
                    :display="$authDetail?->start_date ? \Carbon\Carbon::parse($authDetail->start_date)->format('F j, Y') : null"
                    :required="(bool) $authDetail" />
                <x-clients.efield label="{{ $program === 'DHS' ? 'Reassessment date' : 'Expiration date' }}" name="end_date" type="date"
                    :value="$authDetail?->end_date ? \Carbon\Carbon::parse($authDetail->end_date)->format('Y-m-d') : null"
                    :display="$authDetail?->end_date ? \Carbon\Carbon::parse($authDetail->end_date)->format('F j, Y') : null" />
            </div>

            {{-- Units remaining meter --}}
            @if($authDetail && $totalUnits > 0)
            <div class="mt-4 pt-4 border-t border-[#eef2f9]">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">Units remaining</span>
                    <span class="text-xs font-bold {{ $usedPct >= 90 ? 'text-[#d92d20]' : ($usedPct >= 70 ? 'text-[#b54708]' : 'text-[#067647]') }}">
                        {{ number_format($remainingUnits) }} / {{ number_format($totalUnits) }} ({{ 100 - $usedPct }}% left)
                    </span>
                </div>
                <div class="w-full bg-[#e2e8f0] rounded-full h-2.5">
                    <div class="h-2.5 rounded-full transition-all {{ $usedPct >= 90 ? 'bg-[#d92d20]' : ($usedPct >= 70 ? 'bg-[#f79009]' : 'bg-[#16a34a]') }}"
                        style="width: {{ $usedPct }}%"></div>
                </div>
                <p class="text-xs text-[#94a3b8] mt-1">{{ number_format($usedUnits) }} units used · {{ number_format($remainingUnits) }} remaining</p>
            </div>
            @endif
        </x-clients.edit-panel>

        <div class="space-y-4">
            <x-ui.panel>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-[#0f172a]">Renewal</h3>
                    <x-ui.pill variant="amber">Due soon</x-ui.pill>
                </div>
                <div class="rounded-xl border border-[#fdecc8] bg-[#fffaf0] px-4 py-3 mb-4 text-sm text-[#92400e] leading-relaxed">
                    Renewal request is due <span class="font-bold">2 weeks before the PA ends</span>. The Authorizations agent prepares the packet; it lands in your Approval Queue.
                </div>
                @php
                    $renewSteps = [
                        ['Renewal packet prepared by agent', 'Auto-assembled', 'green'],
                        ['Awaiting your approval to send', 'In Approval Queue', 'amber'],
                        ['Submitted to Aetna', 'Pending', 'gray'],
                        ['New PA received & uploaded', 'Pending', 'gray'],
                    ];
                @endphp
                <div class="relative pl-1">
                    @foreach($renewSteps as $rs)
                        <div class="relative flex gap-3.5 {{ ! $loop->last ? 'pb-4' : '' }}">
                            @if(! $loop->last)<span class="absolute left-[5px] top-4 bottom-0 w-px bg-[#e6eef9]"></span>@endif
                            <span class="relative z-10 mt-1 w-[11px] h-[11px] rounded-full shrink-0 ring-4 ring-white" style="background: {{ $rs[2] === 'green' ? '#16a34a' : ($rs[2] === 'amber' ? '#f79009' : '#cbd5e1') }}"></span>
                            <div class="-mt-0.5">
                                <div class="text-sm font-bold text-[#0f172a]">{{ $rs[0] }}</div>
                                <div class="text-xs text-[#94a3b8] mt-0.5">{{ $rs[1] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui.panel>

            <x-ui.panel title="If the PA lapses">
                <div class="rounded-xl border border-[#fdecc8] bg-[#fffaf0] px-4 py-3 text-sm text-[#92400e] leading-relaxed">
                    <span class="font-bold">PA expired + no renewal → service stops.</span> No billing on an expired authorization; the client moves to <span class="font-bold">On Hold</span> until a new PA arrives.
                </div>
            </x-ui.panel>
        </div>
    </div>

    {{-- All authorizations table --}}
    <x-ui.panel bodyClass="p-0">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <h3 class="text-base font-bold text-[#0f172a]">All Authorizations &amp; Documents</h3>
            <div class="flex items-center gap-2">
                <x-ui.btn variant="outline" size="sm" :href="route('clients.authorizations.export', $client)">Export</x-ui.btn>
                <x-ui.btn variant="primary" size="sm" x-on:click="addAuth = true">Add authorization</x-ui.btn>
            </div>
        </div>
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[760px] border-collapse">
                <thead>
                    <tr class="border-y border-[#eef2f9] bg-[#fafcff]">
                        @foreach([($program === 'DHS' ? 'Auth ref' : 'PA #'),'Period','Code',$program === 'DHS' ? 'Hrs/mo' : 'Units/mo','Status','Document'] as $col)
                            <th class="px-5 py-2.5 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse($client->careDetails->sortByDesc('end_date') as $cd)
                        @php $highlightPa = (int) request('care_detail') === $cd->id; @endphp
                        <tr id="care-detail-{{ $cd->id }}"
                            @class([
                            'hover:bg-[#f7faff] transition-colors',
                            'bg-[#eff6ff] ring-1 ring-inset ring-[#2563eb]/25' => $highlightPa,
                        ])>
                            <td class="px-5 py-3 text-sm font-bold text-[#0f172a] whitespace-nowrap">{{ $cd->authRefForProgram($program) }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b] whitespace-nowrap">{{ $cd->start_date ? \Carbon\Carbon::parse($cd->start_date)->format('M j') : '—' }} – {{ $cd->end_date ? \Carbon\Carbon::parse($cd->end_date)->format('M j, Y') : '—' }}</td>
                            <td class="px-5 py-3 text-sm font-medium text-[#1e293b]">{{ $cd->billing_code }}</td>
                            <td class="px-5 py-3 text-sm font-medium text-[#1e293b]">{{ $program === 'DHS' ? ($cd->hours_per_month ?? '—') : $cd->total_units }}</td>
                            @php
                                $rowStatus = $cd->effectiveStatusForProgram($program);
                                $rowTone = match ($rowStatus) {
                                    'Active' => 'green',
                                    'Reassessment due', 'Reassess soon', 'Expiring Soon' => 'amber',
                                    'Expired' => 'red',
                                    default => 'gray',
                                };
                            @endphp
                            <td class="px-5 py-3"><x-ui.pill :variant="$rowTone">{{ $rowStatus }}</x-ui.pill></td>
                            <td class="px-5 py-3 text-sm text-[#2563eb] font-semibold">{{ $cd->billing_code }}-{{ $cd->id }}.pdf</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-[#94a3b8] italic">No authorizations on record yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="m-5 rounded-xl border-2 border-dashed border-[#dbe6ff] bg-[#f7faff] px-5 py-4 text-center text-sm font-medium text-[#2563eb]">
            Drag a new PA letter here — or the agent uploads it automatically when Aetna returns it. The parser reads the dates & units and adds a new row.
        </div>
        <p class="px-5 pb-5 text-xs text-[#94a3b8]">Every prior authorization stays here with its attached letter for the 7-year record. Older periods appear as additional rows.</p>
    </x-ui.panel>

    {{-- Add authorization modal — wired to the real backend (clients.care-details.store) --}}
    <template x-teleport="body">
        <div x-show="addAuth" x-cloak class="fixed inset-0 z-[999999] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="addAuth = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6" @click.stop>
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-bold text-[#0f172a]">Log a new {{ $program === 'DHS' ? 'Time/Task Sheet' : 'Prior Authorization' }}</h3>
                    <button type="button" @click="addAuth = false" class="text-[#94a3b8] hover:text-[#475569] text-xl leading-none">&times;</button>
                </div>
                <form method="POST" action="{{ route('clients.care-details.store', $client->id) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Service code <span class="text-[#ef4444]">*</span></label>
                            <input type="text" name="billing_code" value="{{ old('billing_code', 'T1019') }}" required
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Authorized units / month <span class="text-[#ef4444]">*</span></label>
                            <input type="number" name="total_units" value="{{ old('total_units') }}" min="0" required placeholder="e.g. 112"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Effective date <span class="text-[#ef4444]">*</span></label>
                            <input type="date" name="start_date" value="{{ old('start_date') }}" required
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">{{ $program === 'DHS' ? 'Reassessment date' : 'Expiration date' }} <span class="text-[#ef4444]">*</span></label>
                            <input type="date" name="end_date" value="{{ old('end_date') }}" required
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Authorized by</label>
                        <input type="text" name="authorized_by" value="{{ old('authorized_by') }}" placeholder="MCO / coordinator name"
                            class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                    </div>
                    <p class="text-xs text-[#94a3b8]">Weekly hours are derived automatically from the authorized units (15-min T1019 units ÷ 4).</p>
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="addAuth = false"
                            class="px-4 py-2 text-sm font-semibold text-[#475569] border border-[#e2e8f0] rounded-[9px] hover:bg-gray-50 transition">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 text-sm font-semibold text-white bg-[#2563eb] border border-[#2563eb] rounded-[9px] hover:bg-[#1d4ed8] transition shadow-sm">Save authorization</button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
