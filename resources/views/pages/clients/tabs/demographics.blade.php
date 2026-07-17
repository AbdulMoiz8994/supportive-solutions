{{-- Demographics & Eligibility --}}
@php
    $updateUrl = route('clients.update', $client->id);

    $dobValue   = $client->dob ? \Carbon\Carbon::parse($client->dob)->format('Y-m-d') : '';
    $dobDisplay = $client->dob ? \Carbon\Carbon::parse($client->dob)->format('m / d / Y').($age ? '  ('.$age.')' : '') : '';

    $coverageOptions = $coverageTypes->pluck('name', 'id')->toArray();

    $genderOptions       = ['Male', 'Female', 'Other', 'Prefer not to say'];
    $languageOptions     = ['English', 'Arabic', 'Spanish', 'French', 'Other'];
    $translatorOptions   = ['No', 'Yes'];
    // MCO plans come from Directory → Payers / MCOs (fallback list until payers exist).
    $mcoOptions          = $mcoOptions ?? \App\Support\DirectoryMcoOptions::list();

    // Directory-driven pickers: MICH case coordinators + DHS ASW workers.
    $coordinatorOptions  = ($coordinators ?? collect())->pluck('name', 'id')->toArray();
    $aswOptions          = ($aswContacts ?? collect())->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->county ? ' · '.$c->county : '')])->toArray();
    $aswLinked           = $client->aswContact();
    $relationshipOptions = ['Son', 'Daughter', 'Spouse', 'Parent', 'Sibling', 'Friend', 'Guardian', 'Other'];
    $exemptionOptions    = ['Approved', 'Pending', 'Not exempt'];
    $evvOptions          = ['Active — caregiver clocks in/out via EVV', 'Exempt — live-in caregiver does not clock in/out'];
    $michiganCounties    = ['Alcona','Alger','Allegan','Alpena','Antrim','Arenac','Baraga','Barry','Bay','Benzie','Berrien','Branch','Calhoun','Cass','Charlevoix','Cheboygan','Chippewa','Clare','Clinton','Crawford','Delta','Dickinson','Eaton','Emmet','Genesee','Gladwin','Gogebic','Grand Traverse','Gratiot','Hillsdale','Houghton','Huron','Ingham','Ionia','Iosco','Iron','Isabella','Jackson','Kalamazoo','Kalkaska','Kent','Keweenaw','Lake','Lapeer','Leelanau','Lenawee','Livingston','Luce','Mackinac','Macomb','Manistee','Marquette','Mason','Mecosta','Menominee','Midland','Missaukee','Monroe','Montcalm','Montmorency','Muskegon','Newaygo','Oakland','Oceana','Ogemaw','Ontonagon','Osceola','Oscoda','Otsego','Ottawa','Presque Isle','Roscommon','Saginaw','St. Clair','St. Joseph','Sanilac','Schoolcraft','Shiawassee','Tuscola','Van Buren','Washtenaw','Wayne','Wexford'];

    $emergencyName = $emergency?->name ?? ($caregiver ? $caregiver->first_name.' '.$caregiver->last_name : '');
    $emergencyRelationship = $emergency?->pivot?->role
        ? preg_replace('/^emergency\s*·\s*/i', '', $emergency->pivot->role)
        : null;

    // SSN
    $ssnLast4    = $client->ssn_last4;
    $ssnMasked   = $ssnLast4 ? '•••-••-'.$ssnLast4 : '•••-••-••••';
    $ssnRevealUrl = route('clients.ssn.reveal', $client->id);

    // PCP — from directory (passed by controller); keyed by id with phone/fax
    $pcpList   = $pcpContacts ?? collect();
    $pcpMapJs  = $pcpContactsJson ?? '{}';

    // Assigned caregivers for the Household dropdown
    $assignedCaregivers = $client->employees ?? collect();

    // Live-in exemption
    $exemptionStatus      = $client->live_in_exemption_status ?? 'Not exempt';
    $exemptionSubmitted   = $client->live_in_exemption_submitted_at ? $client->live_in_exemption_submitted_at->format('M j, Y') : null;
    $exemptionApproved    = $client->live_in_exemption_approved_at ? $client->live_in_exemption_approved_at->format('M j, Y') : null;
    $exemptionExpires     = $client->live_in_exemption_expires_at  ? $client->live_in_exemption_expires_at->format('M j, Y')  : null;
    $evvStatusVal         = $client->evv_status ?? 'Active — caregiver clocks in/out via EVV';
@endphp

<div x-show="activeTab === 'demographics'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

    {{-- ── Left column ─────────────────────────────────────────────────────── --}}
    <div class="space-y-4">

        {{-- Personal Information --}}
        <x-clients.edit-panel title="Personal Information" :action="$updateUrl" section="personal" tab="demographics">
            <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                <x-clients.efield label="First name" name="first_name" :value="$client->first_name" required />
                <x-clients.efield label="Last name" name="last_name" :value="$client->last_name" required />
                <x-clients.efield label="Date of birth" name="dob" type="date" :value="$dobValue" :display="$dobDisplay" required />

                {{-- SSN — masked by default, eye toggle reveals full via AJAX --}}
                <div x-data="ssnField({
                        last4: @js($ssnLast4),
                        masked: @js($ssnMasked),
                        revealUrl: @js($ssnRevealUrl)
                     })"
                     x-init="orig = ssn; if (typeof panelId !== 'undefined') {
                        window.addEventListener('cl-cancel-' + panelId, () => { ssn = orig; revealed = false; });
                        window.addEventListener('cl-commit-' + panelId, () => { orig = ssn; });
                     }">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">SSN</span>
                    </div>

                    {{-- View mode --}}
                    <div x-show="!editing" class="relative">
                        <div class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a] flex items-center justify-between">
                            <span x-text="revealed ? fullSsn : masked" class="font-mono tracking-widest"></span>
                            <button type="button"
                                @click="toggleReveal()"
                                :title="revealed ? 'Hide SSN' : 'Show full SSN'"
                                class="text-[#94a3b8] hover:text-[#2563eb] transition-colors ml-2">
                                <svg x-show="!revealed" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg x-show="revealed" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Edit mode --}}
                    <div x-show="editing" x-cloak>
                        <input type="text" name="ssn"
                            x-model="ssn"
                            placeholder="###-##-####"
                            maxlength="11"
                            @input="formatSsn($event.target)"
                            class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-mono font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 placeholder-[#94a3b8] tracking-widest">
                        <p class="text-xs text-[#94a3b8] mt-1">9 digits · stored securely · only last 4 are displayed</p>
                    </div>
                </div>

                <x-clients.efield label="Gender" name="gender" type="select" :options="$genderOptions" :value="$client->gender" dropdown />
                <x-clients.efield label="Phone number" name="phone" type="tel" :value="$client->phone" />
                <x-clients.efield label="Address" name="address" :value="$client->address" col="2" data-gmaps autocomplete="off" />
                <x-clients.efield label="Home latitude (EVV)" name="home_latitude" type="number" :value="$client->home_latitude" step="any" />
                <x-clients.efield label="Home longitude (EVV)" name="home_longitude" type="number" :value="$client->home_longitude" step="any" />
                <x-clients.efield label="County" name="county" type="select" :options="$michiganCounties" :selected="$client->county" placeholder="Select county" dropdown />
                <x-clients.efield label="Email" name="email" type="email" :value="$client->email" />
                <x-clients.efield label="Preferred language" name="preferred_language" type="select" :options="$languageOptions" :value="$client->preferred_language" placeholder="Select language" dropdown />
                <x-clients.efield label="Requires translator?" name="requires_translator" type="select" :options="$translatorOptions" :value="$client->requires_translator" />
            </div>
        </x-clients.edit-panel>

        {{-- Primary Care Physician & Medical --}}
        <div x-data="pcpPanel({
                pcpMap: {{ $pcpMapJs }},
                clientPcp: @js($client->contacts->where('type', 'Primary Care Physician')->first()?->name)
             })">
            <x-clients.edit-panel title="Primary Care Physician & Medical" :action="$updateUrl" section="pcp" tab="demographics">
                <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                    {{-- PCP dropdown — populated from directory --}}
                    <div class="col-span-2">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide">PCP</span>
                            <span class="text-xs font-semibold text-[#94a3b8] inline-flex items-center gap-0.5">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>dropdown
                            </span>
                        </div>

                        {{-- View --}}
                        <div x-show="!editing" class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a]">
                            <span x-text="selectedPcpName || '—'"></span>
                        </div>

                        {{-- Edit --}}
                        <div x-show="editing" x-cloak class="relative">
                            <select name="pcp_contact_id" x-model="selectedId" @change="onPcpChange()"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 appearance-none pr-9 cursor-pointer">
                                <option value="">Select PCP</option>
                                @foreach($pcpList as $pcp)
                                    <option value="{{ $pcp->id }}">{{ $pcp->name }}@if($pcp->clinic_name) — {{ $pcp->clinic_name }}@endif</option>
                                @endforeach
                                @if($pcpList->isEmpty())
                                    <option disabled>No PCPs in directory — add via Directory</option>
                                @endif
                            </select>
                            <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>

                    {{-- PCP Phone — auto-fills when PCP selected --}}
                    <div>
                        <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">PCP Phone</div>
                        <div x-show="!editing" class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a]">
                            <span x-text="pcpPhone || '—'"></span>
                        </div>
                        <div x-show="editing" x-cloak>
                            <input type="tel" name="pcp_phone" x-model="pcpPhone"
                                placeholder="(000) 000-0000"
                                @input="window.formatPhone($event.target)"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 placeholder-[#94a3b8]">
                        </div>
                    </div>

                    {{-- PCP Fax — auto-fills when PCP selected --}}
                    <div>
                        <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">PCP Fax</div>
                        <div x-show="!editing" class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a]">
                            <span x-text="pcpFax || '—'"></span>
                        </div>
                        <div x-show="editing" x-cloak>
                            <input type="tel" name="pcp_fax" x-model="pcpFax"
                                placeholder="(000) 000-0000"
                                @input="window.formatPhone($event.target)"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 placeholder-[#94a3b8]">
                        </div>
                    </div>

                    <x-clients.efield label="NPI" name="pcp_npi" type="text" placeholder="10-digit NPI" :value="$client->contacts->where('type','Primary Care Physician')->first()?->provider_id" col="2" />
                    <x-clients.efield label="Medical conditions (internal awareness)" name="medical_conditions" type="textarea" :rows="2" placeholder="e.g. Osteoarthritis; hypertension; limited mobility" col="2" />
                </div>
            </x-clients.edit-panel>
        </div>

        {{-- Emergency Contact --}}
        <x-clients.edit-panel title="Emergency Contact" :action="$updateUrl" section="emergency" tab="demographics">
            <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                <x-clients.efield label="Name" name="emergency_name" type="text" :value="$emergencyName" />
                <x-clients.efield label="Relationship" name="emergency_relationship" type="select" :options="$relationshipOptions" :value="$emergencyRelationship" placeholder="Select relationship" dropdown />
                <x-clients.efield label="Phone" name="emergency_phone" type="tel" :value="$emergency?->phone" placeholder="(000) 000-0000" />
                <x-clients.efield label="Email" name="emergency_email" type="email" :value="$emergency?->email ?? $client->email" />
            </div>
        </x-clients.edit-panel>
    </div>

    {{-- ── Right column ────────────────────────────────────────────────────── --}}
    <div class="space-y-4">

        {{-- Eligibility & Insurance --}}
        <x-clients.edit-panel title="Eligibility & Insurance" :action="$updateUrl" section="eligibility" tab="demographics">
            <div x-data="{ msg: 'verified {{ now()->format('M j, Y') }} via insurance portal' }"
                 class="flex items-center justify-between gap-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-full bg-[#16a34a] text-white flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="text-sm font-semibold text-[#067647]">Eligible — <span x-text="msg"></span></span>
                </div>
                <button type="button" @click="msg = 're-verified just now'"
                    class="inline-flex items-center justify-center font-semibold rounded-[9px] text-xs px-2.5 py-1.5 bg-white text-[#475569] border border-[#d8e2f0] hover:border-[#94a3b8] hover:text-[#1e293b] transition-all">
                    Verify
                </button>
            </div>

            <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                <x-clients.efield label="Program" name="coverage_type_id" type="select"
                    :options="$coverageOptions" :selected="$client->coverage_type_id"
                    :display="$client->coverageType?->name ?? ($program.' — Medicaid')" placeholder="Select program" dropdown col="2" />
                <x-clients.efield label="Insurance name" name="mco_name" type="select" :options="$mcoOptions"
                    :value="$client->mco_name" placeholder="Select insurer" dropdown col="2" />
                <x-clients.efield label="Medicaid ID number" name="member_id" type="text" :value="$client->member_id" />
                <x-clients.efield label="Health plan ID (if applicable)" name="health_plan_id" type="text" placeholder="e.g. AET-000000-00" />
                <x-clients.efield label="Medicare ID (dual only)" name="medicare_id" type="text" placeholder="e.g. 1AB-CD2-EF34" />
                <x-clients.efield label="Case coordinator" name="coordinator_contact_id" type="select"
                    :options="$coordinatorOptions" :selected="$coordinator && ($coordinator->type === \App\Models\Contact::TYPE_CASE_COORDINATOR || str_contains(strtolower($coordinator->pivot->role ?? ''), 'coordinator')) ? $coordinator->id : null"
                    :display="$coordinator?->name" placeholder="Select coordinator from Directory" dropdown col="2" />
                <x-clients.efield label="DHS ASW (Adult Services Worker)" name="asw_contact_id" type="select"
                    :options="$aswOptions" :selected="$aswLinked?->id"
                    :display="$aswLinked ? $aswLinked->name.($aswLinked->email ? ' · '.$aswLinked->email : '') : null"
                    placeholder="Select ASW from Directory" dropdown col="2" />
            </div>
        </x-clients.edit-panel>

        {{-- Household & Live-In --}}
        <x-clients.edit-panel title="Household & Live-In" :action="$updateUrl" section="household" tab="demographics">
            <div class="space-y-4"
                 x-data="{
                    livesWithCaregiver: @js((bool)$client->lives_with_caregiver),
                    caregiverId: '',
                    evv: @js($evvStatusVal),
                    exemption: @js($exemptionStatus),
                    orig: {}
                 }"
                 x-init="orig = { livesWithCaregiver, evv, exemption };
                          if (typeof panelId !== 'undefined') {
                             window.addEventListener('cl-cancel-'+panelId, () => { livesWithCaregiver = orig.livesWithCaregiver; evv = orig.evv; exemption = orig.exemption; });
                             window.addEventListener('cl-commit-'+panelId, () => { orig = { livesWithCaregiver, evv, exemption }; });
                          }">

                {{-- Lives with caregiver --}}
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Lives with Caregiver</div>

                    {{-- View --}}
                    <div x-show="!editing" class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a]">
                        <span x-text="livesWithCaregiver ? 'Yes' : 'No'"></span>
                        @if($caregiver)
                            <span class="text-[#64748b]"> — {{ $caregiver->first_name }} {{ $caregiver->last_name }}</span>
                        @endif
                    </div>

                    {{-- Edit --}}
                    <div x-show="editing" x-cloak class="space-y-2">
                        <div class="relative">
                            <select name="lives_with_caregiver" x-model="livesWithCaregiver"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 appearance-none pr-9 cursor-pointer">
                                <option value="0">No</option>
                                @foreach($assignedCaregivers as $cg)
                                    <option value="{{ $cg->id }}">Yes — {{ $cg->first_name }} {{ $cg->last_name }}</option>
                                @endforeach
                                @if($assignedCaregivers->isEmpty())
                                    <option disabled>No caregiver assigned yet</option>
                                @endif
                            </select>
                            <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                    </div>
                </div>

                {{-- Live-in exemption --}}
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">Live-in Exemption</div>

                    {{-- View --}}
                    <div x-show="!editing" class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            @if($exemptionStatus === 'Approved')
                                <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5 bg-[#ecfdf3] text-[#067647] border-[#d1fadf]">Approved</span>
                                @if($exemptionExpires)<span class="text-sm text-[#64748b]">through {{ $exemptionExpires }}</span>@endif
                            @elseif($exemptionStatus === 'Pending')
                                <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5 bg-[#fff8eb] text-[#b54708] border-[#fdecc8]">Pending</span>
                            @else
                                <span class="text-sm text-[#94a3b8]">Not exempt</span>
                            @endif
                        </div>
                        @if($exemptionSubmitted || $exemptionApproved || $exemptionExpires)
                            <div class="text-xs text-[#94a3b8] space-y-0.5">
                                @if($exemptionSubmitted)<div>Submitted: <span class="text-[#475569] font-medium">{{ $exemptionSubmitted }}</span></div>@endif
                                @if($exemptionApproved)<div>Approved: <span class="text-[#475569] font-medium">{{ $exemptionApproved }}</span></div>@endif
                                @if($exemptionExpires)<div>Expires: <span class="text-[#475569] font-medium">{{ $exemptionExpires }}</span></div>@endif
                            </div>
                        @endif
                    </div>

                    {{-- Edit --}}
                    <div x-show="editing" x-cloak class="space-y-2">
                        <div class="relative">
                            <select name="live_in_exemption_status" x-model="exemption"
                                class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 appearance-none pr-9 cursor-pointer">
                                @foreach($exemptionOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                            </select>
                            <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <div x-show="exemption !== 'Not exempt'" class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="text-xs font-semibold text-[#94a3b8] block mb-1">Submitted</label>
                                <input type="date" name="live_in_exemption_submitted_at" value="{{ $client->live_in_exemption_submitted_at?->format('Y-m-d') }}"
                                    class="w-full px-2.5 py-2 rounded-[8px] border border-card-border bg-white text-xs font-medium text-[#0f172a] outline-none focus:border-[#2563eb]">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-[#94a3b8] block mb-1">Approved</label>
                                <input type="date" name="live_in_exemption_approved_at" value="{{ $client->live_in_exemption_approved_at?->format('Y-m-d') }}"
                                    class="w-full px-2.5 py-2 rounded-[8px] border border-card-border bg-white text-xs font-medium text-[#0f172a] outline-none focus:border-[#2563eb]">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-[#94a3b8] block mb-1">Expires</label>
                                <input type="date" name="live_in_exemption_expires_at" value="{{ $client->live_in_exemption_expires_at?->format('Y-m-d') }}"
                                    class="w-full px-2.5 py-2 rounded-[8px] border border-card-border bg-white text-xs font-medium text-[#0f172a] outline-none focus:border-[#2563eb]">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- EVV --}}
                <div>
                    <div class="text-xs font-semibold text-[#94a3b8] uppercase tracking-wide mb-1.5">EVV (HHAeXchange)</div>

                    {{-- View --}}
                    <div x-show="!editing" class="px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a]">
                        <span x-text="evv || '—'"></span>
                    </div>

                    {{-- Edit --}}
                    <div x-show="editing" x-cloak class="relative">
                        <select name="evv_status" x-model="evv"
                            class="w-full px-3.5 py-2.5 rounded-[9px] border border-card-border bg-white text-sm font-medium text-[#0f172a] outline-none transition focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 appearance-none pr-9 cursor-pointer">
                            @foreach($evvOptions as $opt)<option value="{{ $opt }}">{{ $opt }}</option>@endforeach
                        </select>
                        <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                </div>
            </div>
        </x-clients.edit-panel>

        {{-- At a glance --}}
        <x-clients.edit-panel title="At a glance" section="glance">
            <div class="space-y-3 text-sm text-[#475569]">
                @php
                    $glanceRows = [
                        ['icon' => '<rect x="4" y="3" width="16" height="18" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/>', 'label' => 'Authorization:', 'v' => ($authDetail ? ($authDetail->billing_code ?: 'PA').' · '.$auth['label'].($authDetail?->end_date ? ' · expires '.\Carbon\Carbon::parse($authDetail->end_date)->format('M j, Y') : '') : 'No active authorization')],
                        ['icon' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>', 'label' => 'Compliance (May):', 'v' => 'form not yet received — wellness call scheduled'],
                        ['icon' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>', 'label' => 'Billing:', 'v' => ($client->billings->count() ? 'last claim paid in full · no outstanding balance' : 'no invoices on record yet')],
                        ['icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'label' => 'Caregiver checks:', 'v' => ($caregiver ? 'all current (CHAMPS, ICHAT, SAM, OIG)' : 'no caregiver assigned')],
                    ];
                @endphp
                @foreach($glanceRows as $row)
                    <div class="flex gap-2">
                        <svg class="w-4 h-4 text-[#94a3b8] shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $row['icon'] !!}</svg>
                        <div class="flex-1 min-w-0">
                            <span class="font-semibold text-[#0f172a]">{{ $row['label'] }}</span>
                            <span class="text-[#475569]"> {{ $row['v'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-clients.edit-panel>
    </div>
</div>

{{-- Alpine components for SSN and PCP --}}
@push('scripts')
<script>
function ssnField({ last4, masked, revealUrl }) {
    return {
        ssn: last4 ? '' : '',
        orig: '',
        masked: masked,
        fullSsn: '',
        revealed: false,

        formatSsn(input) {
            let raw = input.value.replace(/\D/g, '').substring(0, 9);
            if (raw.length > 5) {
                input.value = raw.substring(0,3) + '-' + raw.substring(3,5) + '-' + raw.substring(5);
            } else if (raw.length > 3) {
                input.value = raw.substring(0,3) + '-' + raw.substring(3);
            } else {
                input.value = raw;
            }
            this.ssn = input.value;
        },

        async toggleReveal() {
            if (this.revealed) {
                this.revealed = false;
                this.fullSsn = '';
                return;
            }
            try {
                const resp = await fetch(revealUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await resp.json();
                if (data.ssn) {
                    this.fullSsn = data.ssn;
                    this.revealed = true;
                    // Auto-hide after 8 seconds
                    setTimeout(() => { this.revealed = false; this.fullSsn = ''; }, 8000);
                }
            } catch(e) {}
        }
    };
}

function pcpPanel({ pcpMap, clientPcp }) {
    return {
        selectedId: '',
        selectedPcpName: clientPcp || '',
        pcpPhone: '',
        pcpFax: '',

        onPcpChange() {
            if (!this.selectedId) {
                this.selectedPcpName = '';
                this.pcpPhone = '';
                this.pcpFax = '';
                return;
            }
            const pcp = pcpMap[this.selectedId];
            if (pcp) {
                this.selectedPcpName = pcp.name;
                this.pcpPhone = pcp.phone || '';
                this.pcpFax   = pcp.fax   || '';
            }
        }
    };
}
</script>
@endpush
