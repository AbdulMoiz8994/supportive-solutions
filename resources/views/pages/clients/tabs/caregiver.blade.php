{{-- Caregiver Assignment --}}
<div x-show="activeTab === 'caregiver'" x-cloak class="space-y-4">
    @if($caregiver)
        @php
            $updateUrl = route('clients.update', $client->id);
            $assignment = $client->active_assignment;
            $cgName = trim($caregiver->first_name.' '.$caregiver->last_name);
            $assignedSince = $caregiver->hire_date ? \Carbon\Carbon::parse($caregiver->hire_date)->format('M j, Y') : ($caregiver->created_at?->format('M j, Y'));
            $champsDate = $caregiver->champs_association_date ? \Carbon\Carbon::parse($caregiver->champs_association_date)->format('M j, Y') : '—';
            $bgClear = (bool) ($caregiver->has_background_check ?? false);
            $availableCaregivers = \App\Models\Employee::where('position', 'Caregiver')
                ->where('status', 'Active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'caregiver_type', 'live_in']);
        @endphp

        {{-- Reassign Modal --}}
        <div x-data="{ reassignOpen: false }" @keydown.escape.window="reassignOpen = false">
            <template x-teleport="body">
            <div x-show="reassignOpen" x-cloak
                 class="fixed inset-0 z-[999999] flex items-center justify-center bg-black/50 p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-bold text-[#0f172a]">Reassign / Replace Caregiver</h3>
                        <button @click="reassignOpen = false" class="text-[#94a3b8] hover:text-[#475569] text-xl leading-none">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('clients.assign-caregiver', $client->id) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">New Caregiver <span class="text-[#ef4444]">*</span></label>
                            <div class="relative">
                                <select name="employee_id" required
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white appearance-none pr-9 outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                                    <option value="">Select caregiver…</option>
                                    @foreach($availableCaregivers as $cg)
                                        @if($cg->id !== $caregiver->id)
                                            <option value="{{ $cg->id }}">{{ $cg->first_name }} {{ $cg->last_name }}@if($cg->caregiver_type) ({{ $cg->caregiver_type }})@endif@if($cg->live_in) · Live-in@endif</option>
                                        @endif
                                    @endforeach
                                    @if($availableCaregivers->where('id', '!=', $caregiver->id)->isEmpty())
                                        <option disabled>No other active caregivers available</option>
                                    @endif
                                </select>
                                <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Relationship</label>
                                <div class="relative">
                                    <select name="relationship" class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white appearance-none pr-9 outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                                        <option value="">Select…</option>
                                        @foreach(['Mother','Father','Wife','Husband','Son','Daughter','Sibling','Friend','Neighbor','Professional','Other'] as $r)
                                            <option value="{{ $r }}">{{ $r }}</option>
                                        @endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Live-in?</label>
                                <div class="relative">
                                    <select name="live_in" class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white appearance-none pr-9 outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                                        <option value="0">No</option>
                                        <option value="1">Yes — same household</option>
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="reassignOpen = false"
                                class="px-4 py-2 text-sm font-semibold text-[#475569] border border-[#e2e8f0] rounded-[9px] hover:bg-gray-50 transition">Cancel</button>
                            <button type="submit"
                                class="px-5 py-2 text-sm font-semibold text-white bg-[#2563eb] border border-[#2563eb] rounded-[9px] hover:bg-[#1d4ed8] transition shadow-sm">Reassign →</button>
                        </div>
                    </form>
                </div>
            </div>
            </template>

        {{-- Caregiver header --}}
        <div class="rounded-2xl border border-[#e6eef9] bg-[#f3f8ff] p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="w-[58px] h-[58px] rounded-full overflow-hidden shrink-0 border-2 border-white shadow-sm">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($cgName) }}&background=2563eb&color=fff&bold=true&size=128" class="w-full h-full object-cover" alt="">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-lg font-bold text-[#0f172a]">{{ $cgName }}</h3>
                            <x-ui.pill variant="green">{{ $caregiver->status ?? 'Active' }}</x-ui.pill>
                            <x-ui.pill variant="blue">Live-in</x-ui.pill>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-1.5 text-sm text-[#475569]">
                            <span><span class="font-semibold text-[#0f172a]">Relationship</span> {{ $caregiver->position === 'Caregiver' ? 'Family' : ($caregiver->position ?? '—') }}</span>
                            <span><span class="font-semibold text-[#0f172a]">Employment</span> W-2</span>
                            <span><span class="font-semibold text-[#0f172a]">Assigned since</span> {{ $assignedSince ?? '—' }}</span>
                        </div>
                        <div class="mt-1.5 text-sm text-[#475569]">
                            <span class="font-semibold text-[#0f172a]">Background checks</span>
                            <x-ui.pill :variant="$bgClear ? 'green' : 'amber'">{{ $bgClear ? 'All clear' : 'Pending' }}</x-ui.pill>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.btn variant="outline" size="sm" x-on:click="reassignOpen = true">Reassign / Replace</x-ui.btn>
                    <x-ui.btn variant="primary" size="sm" :href="route('caregivers.show', $caregiver->id)">Open caregiver profile →</x-ui.btn>
                </div>
            </div>
        </div>

        {{-- Assignment + Live-in --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            <x-clients.edit-panel title="Assignment Details" section="cg-assignment" tab="caregiver"
                :action="$assignment ? route('clients.assignment.update', ['id' => $client->id, 'assignment' => $assignment->id]) : null">
                <div class="grid grid-cols-2 gap-x-4 gap-y-4 pt-1">
                    <x-clients.efield label="Caregiver" :value="$cgName" />
                    <x-clients.efield label="Relationship" name="relationship" type="select"
                        :options="['Family', 'Friend', 'Professional', 'Neighbor', 'Other']"
                        :selected="$assignment?->relationship ?? 'Family'" />
                    <x-clients.efield label="Employment" value="W-2 employee" />
                    <x-clients.efield label="Assigned since" :value="$assignedSince" />
                    <x-clients.efield label="Scheduled time" :value="$authDetail ? '≈'.$authDetail->hours_per_week.' hrs / month (matches PA)' : null" placeholder="—" col="2" />
                    <x-clients.efield label="Status" name="status" type="select"
                        :options="['Active', 'On Hold', 'Ended']"
                        :selected="$assignment?->status ?? 'Active'" />
                    <x-clients.efield label="Authorization linked"
                        :value="$authDetail ? 'PA-'.\Carbon\Carbon::parse($authDetail->start_date ?? now())->format('Y').'-'.str_pad($authDetail->id, 4, '0', STR_PAD_LEFT).' ('.$authDetail->billing_code.')' : null"
                        placeholder="—" />
                </div>
            </x-clients.edit-panel>

            <x-clients.edit-panel title="Live-In Exemption" section="cg-livein" tab="caregiver" :action="$updateUrl">
                <div class="grid grid-cols-2 gap-x-4 gap-y-4 pt-1">
                    <x-clients.efield label="Shares address with client"
                        :value="$client->lives_with_caregiver ? 'Yes — same household' : 'No'" col="2" />
                    <x-clients.efield label="Exemption status" name="live_in_exemption_status" type="select"
                        :options="['Approved', 'Pending', 'Submitted', 'Not required', 'Denied']"
                        :selected="$client->live_in_exemption_status" placeholder="Select status" col="2" />
                    <x-clients.efield label="Approved on" name="live_in_exemption_approved_at" type="date"
                        :value="$client->live_in_exemption_approved_at?->format('Y-m-d')"
                        :display="$client->live_in_exemption_approved_at?->format('F j, Y')" />
                    <x-clients.efield label="Expires / renew by" name="live_in_exemption_expires_at" type="date"
                        :value="$client->live_in_exemption_expires_at?->format('Y-m-d')"
                        :display="$client->live_in_exemption_expires_at?->format('F j, Y')" />
                    <x-clients.efield label="EVV (HHAeXchange)" name="evv_status" type="select"
                        :options="['Exempt — no clock in / out required', 'Required — clock in / out', 'Not set']"
                        :selected="$client->evv_status" placeholder="Select EVV status" col="2" />
                </div>
            </x-clients.edit-panel>
        </div>

        {{-- Pay + Background checks --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            <x-clients.edit-panel title="Pay Eligibility" section="cg-pay" tab="caregiver" :action="$updateUrl">
                <div class="grid grid-cols-2 gap-x-4 gap-y-4 pt-1">
                    <x-clients.efield label="Hourly pay rate" name="billing_rate" type="number" step="0.01" min="0"
                        :value="$client->billing_rate ?? 15"
                        :display="'$'.number_format($client->billing_rate ?? 15, 2).' / hour'" />
                    <x-clients.efield label="Pay type" value="W-2 · hourly" />
                    <x-clients.efield label="Case start date" :value="$authDetail?->start_date ? \Carbon\Carbon::parse($authDetail->start_date)->format('M j, Y').' (PA effective)' : null" placeholder="—" />
                    <x-clients.efield label="CHAMPS association date" :value="$champsDate !== '—' ? $champsDate : null" placeholder="—" />
                </div>
                <div class="mt-4 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm text-[#067647] leading-relaxed">
                    Pay-eligibility starts the later of case start &amp; CHAMPS association. A relative caregiver's prior service can be backdated if the dates support it.
                </div>
            </x-clients.edit-panel>

            <x-ui.panel title="Background Checks" link="#" linkLabel="Run all again">
                <div class="space-y-2.5 pt-1">
                    @php
                        $checks = [
                            ['CHAMPS', 'One-time at hiring + ongoing monitor', $champsDate],
                            ['ICHAT', 'Annual', $champsDate],
                            ['SAM.gov', 'Monthly', now()->format('M j, Y')],
                            ['OIG LEIE', 'Monthly', now()->format('M j, Y')],
                        ];
                    @endphp
                    @foreach($checks as [$name, $cadence, $date])
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-[#eef2f9] bg-[#fafcff] px-3.5 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <span class="w-5 h-5 rounded-full {{ $bgClear ? 'bg-[#16a34a]' : 'bg-[#cbd5e1]' }} text-white flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </span>
                                <div>
                                    <div class="text-sm font-bold text-[#0f172a]">{{ $name }}</div>
                                    <div class="text-xs text-[#94a3b8]">{{ $cadence }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-[#94a3b8]">{{ $date }}</span>
                                <x-ui.pill :variant="$bgClear ? 'green' : 'gray'">{{ $bgClear ? 'Clear' : '—' }}</x-ui.pill>
                                <x-ui.btn variant="outline" size="sm">Run</x-ui.btn>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 rounded-xl border border-[#fdecc8] bg-[#fffaf0] px-4 py-3 text-sm text-[#92400e] leading-relaxed">
                    If a re-check ever flags, it routes to you to verify same-person by address first. If confirmed, the caregiver is terminated &amp; replaced — service can't be billed under a disqualified caregiver.
                </div>
            </x-ui.panel>
        </div>

        {{-- Assignment history --}}
        <x-ui.panel title="Assignment History" bodyClass="px-0 pb-0">
            <div class="w-full overflow-x-auto no-scrollbar">
                <table class="w-full min-w-[620px] border-collapse">
                    <thead>
                        <tr class="border-y border-[#eef2f9] bg-[#fafcff]">
                            @foreach(['Caregiver','Relationship','Period','Status'] as $col)
                                <th class="px-5 py-2.5 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wider">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-5 py-3 text-sm font-bold text-[#0f172a]">{{ $cgName }}</td>
                            <td class="px-5 py-3 text-sm text-[#475569]">Family</td>
                            <td class="px-5 py-3 text-sm text-[#475569]">{{ $assignedSince ?? '—' }} – present</td>
                            <td class="px-5 py-3"><x-ui.pill variant="green">Current</x-ui.pill></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="px-5 py-3 text-xs text-[#94a3b8]">No prior caregivers — {{ $caregiver->first_name }} has been the caregiver since activation.</p>
        </x-ui.panel>
        </div>{{-- closes reassign x-data wrapper --}}
    @else
    @php
        $availableCaregivers = \App\Models\Employee::where('position', 'Caregiver')
            ->where('status', 'Active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'caregiver_type', 'live_in']);
    @endphp
        <x-ui.panel bodyClass="px-6 py-8" x-data="{ assignOpen: {{ old('employee_id') ? 'true' : 'false' }} }">
            <div class="text-center mb-6">
                <div class="w-12 h-12 rounded-full bg-[#eff4ff] flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-[#2563eb]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </div>
                <div class="text-sm font-bold text-[#0f172a]">No caregiver assigned yet</div>
                <div class="text-sm text-[#94a3b8] mt-1">Select a caregiver below to link authorization, pay eligibility, and background checks.</div>
                <div class="mt-4">
                    <x-ui.btn variant="primary" size="sm" x-on:click="assignOpen = true">Assign a caregiver</x-ui.btn>
                </div>
            </div>

            <form method="POST" action="{{ route('clients.assign-caregiver', $client->id) }}" class="max-w-md mx-auto space-y-4" x-show="assignOpen" x-cloak>
                @csrf
                <div>
                    <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Caregiver <span class="text-[#ef4444]">*</span></label>
                    <div class="relative">
                        <select name="employee_id" required
                            class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white appearance-none pr-9 outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                            <option value="">Select a caregiver…</option>
                            @foreach($availableCaregivers as $cg)
                                <option value="{{ $cg->id }}">{{ $cg->first_name }} {{ $cg->last_name }}@if($cg->caregiver_type) ({{ $cg->caregiver_type }})@endif@if($cg->live_in) · Live-in@endif</option>
                            @endforeach
                            @if($availableCaregivers->isEmpty())
                                <option disabled>No active caregivers — add via Caregiver Registry first</option>
                            @endif
                        </select>
                        <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Relationship</label>
                        <div class="relative">
                            <select name="relationship"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white appearance-none pr-9 outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                                <option value="">Select…</option>
                                @foreach(['Mother','Father','Wife','Husband','Son','Daughter','Sibling','Uncle','Aunt','Cousin','Friend','Neighbor','Professional','Other'] as $r)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endforeach
                            </select>
                            <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#94a3b8] uppercase tracking-wide mb-1.5">Live-in?</label>
                        <div class="relative">
                            <select name="live_in"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-medium text-[#0f172a] bg-white appearance-none pr-9 outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                                <option value="0">No</option>
                                <option value="1">Yes — same household</option>
                            </select>
                            <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('caregivers.create', ['client_id' => $client->id]) }}" class="text-sm font-semibold text-[#2563eb] hover:text-[#1d4ed8]">
                        + Onboard new caregiver for this client
                    </a>
                    <button type="submit"
                        class="px-6 py-2.5 bg-[#2563eb] text-white rounded-[9px] text-sm font-bold hover:bg-[#1d4ed8] transition shadow-[0_2px_8px_rgba(37,99,235,0.2)]">
                        Assign Caregiver →
                    </button>
                </div>
            </form>
        </x-ui.panel>
    @endif
</div>
