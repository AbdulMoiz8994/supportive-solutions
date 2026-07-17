{{-- Intake & Screening --}}
@php $updateUrl = route('clients.update', $client->id); @endphp
<div x-show="activeTab === 'intake'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

    {{-- Left column --}}
    <div class="space-y-4">
        <x-clients.edit-panel title="Referral" section="intake-referral" tab="intake" :action="$updateUrl">
            <div class="grid grid-cols-2 gap-x-4 gap-y-4 pt-1">
                <x-clients.efield label="How did they hear about us?" name="referral_source" type="select" :options="['Word of mouth (family / friend)', 'Doctor referral', 'Insurance referral', 'Internet search', 'Other']" :selected="$client->referral_source" placeholder="Select…" dropdown col="2" />
                <x-clients.efield label="Referral received" name="referral_received_date" type="date"
                    :value="$client->referral_received_date?->format('Y-m-d')"
                    :display="$client->referral_received_date?->format('F j, Y')" />
                <x-clients.efield label="Referred by" name="referred_by" :value="$client->referred_by" placeholder="Name or organization" />
                <x-clients.efield label="Currently receiving home care (at intake)" name="currently_receiving_care" type="select" :options="['No', 'Yes']" :selected="$client->currently_receiving_care" placeholder="Select…" />
                <x-clients.efield label="Intake taken by" name="intake_taken_by" :value="$client->intake_taken_by" placeholder="Staff member name" />
                <x-clients.efield label="Intake date" name="intake_date" type="date"
                    :value="$client->intake_date?->format('Y-m-d')"
                    :display="$client->intake_date?->format('F j, Y')" />
            </div>
        </x-clients.edit-panel>

        <x-clients.edit-panel title="Eligibility Screening" section="intake-screening" tab="intake" :action="$updateUrl">
            <div class="grid grid-cols-2 gap-x-4 gap-y-4 pt-1">
                <x-clients.efield label="Verified on" name="eligibility_verified_date" type="date"
                    :value="$client->eligibility_verified_date?->format('Y-m-d')"
                    :display="$client->eligibility_verified_date?->format('F j, Y')" />
                <x-clients.efield label="Result" name="eligibility_result" type="select"
                    :options="['Eligible', 'Ineligible', 'Pending verification']"
                    :selected="$client->eligibility_result ?? 'Eligible'" />
                <x-clients.efield label="Coverage type" :value="$client->coverageType?->name ?? 'Dual eligible (Medicaid + Medicare)'" />
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Program determined</div>
                    <div><x-ui.pill :variant="$program === 'DHS' ? 'indigo' : 'blue'">{{ $program }}</x-ui.pill></div>
                </div>
            </div>
        </x-clients.edit-panel>

        @php $selectedServices = $client->services_requested ?? []; @endphp
        <x-clients.edit-panel title="Services Requested" section="intake-services" tab="intake" :action="$updateUrl">
            {{-- Empty marker so unchecking every box persists as "none" rather than no-op. --}}
            <input type="hidden" name="services_requested[]" value="" x-bind:disabled="!editing">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-y-3 gap-x-3 pt-1">
                @foreach(['Eating','Bathing','Toileting','Dressing','Grooming','Mobility','Transferring','Meal Preparation','Housework','Laundry','Shopping (food / meds)','Taking Medication'] as $service)
                    <label class="flex items-center gap-2 text-sm font-medium text-[#334155] cursor-pointer">
                        <input type="checkbox" name="services_requested[]" value="{{ $service }}"
                            @checked(in_array($service, $selectedServices))
                            x-bind:disabled="!editing"
                            class="w-4 h-4 rounded-[5px] border-[#cbd5e1] text-[#2563eb] focus:ring-[#2563eb]/30 shrink-0">
                        {{ $service }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-[#94a3b8] mt-4 pt-4 border-t border-[#eef2f9] leading-relaxed">Only hands-on care (bathing, dressing, grooming, mobility, etc.) is billable — supervision is not.</p>
        </x-clients.edit-panel>
    </div>

    {{-- Right column --}}
    <div class="space-y-4">

        {{-- Onboarding / Screening Status — interactive --}}
        @php
            $base = $client->created_at ?? now();
            $savedSteps = $client->onboarding_steps ?? [];

            $stepDefs = [
                ['label' => 'Eligibility verified',
                 'sub'   => $base->format('M j, Y').' · dual eligible → '.$program],
                ['label' => 'Program determined & client chart created',
                 'sub'   => $base->format('M j, Y').' · status set to Pending Application'],
                ['label' => 'Client packet signed & verbal review completed',
                 'sub'   => $base->copy()->addDays(1)->format('M j, Y').' · all forms reviewed page-by-page'],
                ['label' => 'MCO contacted — Health Risk Assessment requested',
                 'sub'   => $base->copy()->addDays(3)->format('M j, Y').' · existing Case Coordinator '.($coordinator?->name ?? 'assigned')],
                ['label' => 'Prior Authorization received',
                 'sub'   => $authDetail ? \Carbon\Carbon::parse($authDetail->start_date)->format('M j, Y').' · '.$authDetail->billing_code : 'Pending'],
                ['label' => 'Caregiver linked & background checks cleared',
                 'sub'   => $caregiver ? $base->copy()->addDays(20)->format('M j, Y').' · '.$caregiver->first_name.' '.$caregiver->last_name.' · CHAMPS, ICHAT, SAM, OIG all clear' : 'Pending caregiver'],
                ['label' => 'Live-In Exemption filed & approved',
                 'sub'   => $base->copy()->addDays(22)->format('M j, Y').' · approved'],
                ['label' => 'Client activated',
                 'sub'   => $base->copy()->addDays(24)->format('M j, Y').' · services live'],
            ];

            // Merge saved state — first 2 default to complete, rest default to pending
            $steps = collect($stepDefs)->map(function($def, $i) use ($savedSteps) {
                $saved  = $savedSteps[$i] ?? null;
                $status = $saved['status'] ?? ($i < 2 ? 'complete' : 'pending');
                $note   = $saved['note'] ?? $def['sub'];
                $date   = $saved['date'] ?? null;
                return array_merge($def, ['idx' => $i, 'status' => $status, 'note' => $note, 'date' => $date]);
            });

            $allComplete    = $steps->every(fn($s) => $s['status'] === 'complete');
            $currentStepIdx = $steps->search(fn($s) => $s['status'] !== 'complete');
        @endphp

        <div x-data="onboardingStatus({
                steps: @js($steps->values()->all()),
                updateUrl: @js(route('clients.onboarding-steps.update', $client->id)),
                csrfToken: @js(csrf_token())
             })">
            <div class="rounded-[16px] border border-[#e6eef9] bg-white overflow-hidden">
                <div class="px-5 py-4 border-b border-[#eef2f9] flex items-center justify-between">
                    <h3 class="text-base font-bold text-[#0f172a]">Onboarding / Screening Status</h3>
                    <span :class="allComplete ? 'bg-[#ecfdf3] text-[#067647] border-[#d1fadf]' : 'bg-[#fff8eb] text-[#b54708] border-[#fdecc8]'"
                        class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5"
                        x-text="allComplete ? 'Complete' : 'In progress'">
                    </span>
                </div>

                <div class="px-5 py-4">
                    <div class="relative pl-1">
                        <template x-for="(s, i) in steps" :key="i">
                            <div class="relative flex gap-3.5" :class="i < steps.length - 1 ? 'pb-5' : ''">
                                {{-- Connector line --}}
                                <template x-if="i < steps.length - 1">
                                    <span class="absolute left-[5px] top-4 bottom-0 w-px transition-colors"
                                        :style="s.status === 'complete' ? 'background:#16a34a' : 'background:#e6eef9'"></span>
                                </template>

                                {{-- Step dot --}}
                                <span class="relative z-10 mt-1 w-[11px] h-[11px] rounded-full shrink-0 ring-4 ring-white transition-all cursor-pointer"
                                    :class="{
                                        'bg-[#16a34a]': s.status === 'complete',
                                        'bg-[#2563eb]': s.status === 'in_progress',
                                        'bg-[#cbd5e1]': s.status === 'pending'
                                    }"
                                    @click="cycleStep(i)"
                                    title="Click to toggle status"></span>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0 -mt-0.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="text-sm font-bold"
                                            :class="s.status === 'complete' ? 'text-[#0f172a]' : (s.status === 'in_progress' ? 'text-[#2563eb]' : 'text-[#94a3b8]')"
                                            x-text="s.label"></div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            {{-- Status pill --}}
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                                                :class="{
                                                    'bg-[#ecfdf3] text-[#067647] border-[#d1fadf]': s.status === 'complete',
                                                    'bg-[#eff4ff] text-[#2563eb] border-[#dbe6ff]': s.status === 'in_progress',
                                                    'bg-[#f8fafc] text-[#94a3b8] border-[#e2e8f0]': s.status === 'pending'
                                                }"
                                                x-text="s.status === 'complete' ? 'Done' : (s.status === 'in_progress' ? 'Active' : 'Pending')">
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-xs text-[#94a3b8] mt-0.5" x-text="s.note || s.sub"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <p class="text-xs text-[#94a3b8] mt-4 pt-3 border-t border-[#eef2f9]">Click any step dot to toggle its status (Pending → Active → Done). Changes save automatically.</p>
                </div>
            </div>
        </div>

        <x-clients.edit-panel title="Client Packet — Required Documents" section="intake-packet" editLabel="Manage">
            <div class="space-y-2 pt-1">
                @php
                    $onFileCount = $client->documents->count();
                    $packet = [
                        ['Supportive Solutions Client Application Form (optional)', false],
                        ['Copy of ID / SSN card', $onFileCount > 0],
                        ['Copy of Medicaid + Medicare cards', $onFileCount > 1],
                        ['MCO referral / Prior Authorization (Aetna)', (bool) $authDetail],
                        ['Health Risk Assessment (HRA)', $onFileCount > 2],
                    ];
                @endphp
                @foreach($packet as [$doc, $onFile])
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-[#eef2f9] bg-[#fafcff] px-3.5 py-2.5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <svg class="w-4 h-4 text-[#94a3b8] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="text-sm font-medium text-[#334155] truncate">{{ $doc }}</span>
                        </div>
                        @if($onFile)
                            <x-ui.pill variant="green">On file</x-ui.pill>
                        @else
                            <x-ui.pill variant="gray">Not provided</x-ui.pill>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-[#94a3b8] mt-3 leading-relaxed">DHS Home Help clients require a different packet: Client Application Form, DHS-390, MSA-4676 (Home Help Service Agreement), Medical Needs Form (MDHHS-6200), copy of ID/SSN, copy of Medicaid card.</p>
        </x-clients.edit-panel>

        <x-clients.edit-panel title="Initial Notes" section="intake-initialnotes" tab="intake" :action="$updateUrl">
            <x-clients.efield label="Notes" name="initial_notes" type="textarea" :rows="3"
                :value="$client->initial_notes"
                placeholder="No initial notes captured yet — add context from the intake call here."
                col="2" />
        </x-clients.edit-panel>
    </div>
</div>

@push('scripts')
<script>
function onboardingStatus({ steps, updateUrl, csrfToken }) {
    return {
        steps: steps,
        get allComplete() {
            return this.steps.every(s => s.status === 'complete');
        },
        cycleStep(i) {
            const order = ['pending', 'in_progress', 'complete'];
            const cur   = this.steps[i].status;
            const next  = order[(order.indexOf(cur) + 1) % order.length];
            this.steps[i].status = next;
            this.save(i);
        },
        async save(i) {
            try {
                await fetch(updateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        step_index: i,
                        status:     this.steps[i].status,
                        note:       this.steps[i].note || null,
                    })
                });
            } catch(e) {}
        }
    };
}
</script>
@endpush
