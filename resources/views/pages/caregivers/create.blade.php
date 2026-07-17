@extends('layouts.app')

@section('content')
@php
    $steps = [
        ['01', 'Caregiver Details', 'Personal info'],
        ['02', 'Eligibility & Checks', 'CHAMPS · ICHAT · SAM · OIG'],
        ['03', 'Employment & Services', 'Experience · tasks'],
        ['04', 'Assignment, Live-In & Pay', 'Wage · W-4 · deposit'],
        ['05', 'Documents & Verbal Review', 'Packet · acknowledgments'],
        ['06', 'Review & Create Profile', 'Pending onboarding'],
    ];
    $serviceOptions = ['Eating','Bathing','Toileting','Dressing','Grooming','Mobility','Transferring','Meal Preparation','Housework','Laundry','Shopping (food / meds)','Taking Medication'];
    $acks = [
        'Must be physically present during all billed hours; never leave the client unattended',
        'Cannot bill if client is in hospital / nursing / rehab (except discharge day)',
        'Only hands-on care is billable — supervision/reminders are not',
        'Hours logged accurately via EVV (unless approved live-in)',
        'Falsifying hours / billing unprovided services → termination + legal action',
        'Report changes (address, phone, employment) within 10 days',
        'Must be CHAMPS-approved before any work; submit service verifications',
        'Maintain confidentiality (HIPAA) & follow all agency / Medicaid policies',
    ];
@endphp

<div class="w-full px-2 pb-20"
    x-data="caregiverWizard({
        client_id: '{{ $fromClient->id ?? '' }}',
        address: @js($fromClient->address ?? ''),
        county: @js($fromClient->county ?? ''),
    })"
    x-on:id-scanned.window="
        const d = $event.detail;
        if (d.first_name) form.first_name = d.first_name;
        if (d.last_name) form.last_name = d.last_name;
        const dob = window.idScanDob(d.date_of_birth); if (dob) form.date_of_birth = dob;
        const addr = window.idScanAddress(d); if (addr) form.address = addr;
        const g = window.idScanSex(d.sex); if (g) form.gender = g;
    ">

    {{-- Header --}}
    <div class="flex items-center justify-between pt-3 pb-4">
        <div>
            <h1 class="text-[26px] font-black text-[#1e293b] tracking-tight">New Caregiver Onboarding</h1>
            <p class="text-[12px] font-medium text-[#64748b] mt-1">Step <span x-text="step"></span> of 6 — <span x-text="steps[step-1][2]"></span></p>
        </div>
        <button type="button" class="px-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm hover:bg-gray-50">Save draft &amp; exit</button>
    </div>

    @if($fromClient)
    <div class="bg-blue-50 border border-blue-200/70 rounded-2xl px-6 py-4 mb-6">
        <p class="text-[13px] font-bold text-blue-700">Continuing from client intake — {{ $fromClient->first_name }} {{ $fromClient->last_name }}</p>
        <p class="text-[12px] text-blue-600/80 mt-0.5">You're creating the caregiver profile linked to this client. Address &amp; program prefill from the client chart.</p>
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-2xl px-6 py-4 mb-6 text-[12px] font-bold text-red-700">
        Please fix: {{ implode(' ', $errors->all()) }}
    </div>
    @endif

    <form method="POST" action="{{ route('caregivers.store') }}" id="wizardForm" enctype="multipart/form-data">
        @csrf
        {{-- hidden booleans / arrays bound to Alpine --}}
        <input type="hidden" name="is_18_plus"            :value="form.is_18_plus ? 1 : 0">
        <input type="hidden" name="is_work_eligible"      :value="form.is_work_eligible ? 1 : 0">
        <input type="hidden" name="has_background_check"  :value="form.has_background_check ? 1 : 0">
        <input type="hidden" name="needs_accommodations"  :value="form.needs_accommodations ? 1 : 0">
        <input type="hidden" name="prior_experience"      :value="form.prior_experience ? 1 : 0">
        <input type="hidden" name="lives_with_client"     :value="form.lives_with_client ? 1 : 0">

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {{-- Step rail --}}
            <div class="lg:col-span-1">
                <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-3 space-y-1 sticky top-4">
                    @foreach($steps as $i => [$num, $label, $sub])
                        <button type="button" @click="goto({{ $i+1 }})"
                            :class="step === {{ $i+1 }} ? 'bg-white shadow-sm border-blue-200' : 'border-transparent hover:bg-white/60'"
                            class="w-full text-left flex items-start gap-3 px-3 py-3 rounded-xl border transition-all">
                            <span class="w-6 h-6 shrink-0 rounded-full flex items-center justify-center text-[10px] font-black"
                                :class="step === {{ $i+1 }} ? 'bg-[#2563eb] text-white' : (step > {{ $i+1 }} ? 'bg-green-500 text-white' : 'bg-blue-100 text-blue-500')">{{ $num }}</span>
                            <span>
                                <span class="block text-[12.5px] font-bold leading-tight" :class="step === {{ $i+1 }} ? 'text-[#2563eb]' : 'text-[#1e293b]'">{{ $label }}</span>
                                <span class="block text-[11px] text-[#94a3b8] mt-0.5">{{ $sub }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Step panels --}}
            <div class="lg:col-span-3 space-y-6">

                {{-- STEP 1 --}}
                <div x-show="step === 1" x-cloak class="space-y-6">
                    <div class="bg-blue-50/60 rounded-[20px] border border-blue-100/60 p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-[#2563eb] flex items-center justify-center text-white shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-[15px] font-bold text-[#1e293b]">Scan paperwork to auto-fill <span class="text-[#94a3b8] font-medium">(optional)</span></h3>
                                <p class="text-[12px] text-[#64748b] mt-1">Scan the signed application, the ID, and the SSN card — the parser fills the next steps. No application? Continue and enter manually.</p>
                                <div class="mt-4">@include('partials.id-scan')</div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
                                    @foreach(['Application Form'=>'Not always available','Photo ID'=>'Scanned ✓','SSN card'=>'Scanned ✓'] as $doc => $st)
                                    <div class="bg-white rounded-xl border border-[#e2e8f0] px-4 py-3 flex items-center justify-between">
                                        <div><p class="text-[12px] font-bold text-[#1e293b]">{{ $doc }}</p><p class="text-[11px] text-[#94a3b8]">{{ $st }}</p></div>
                                        <span class="text-[11px] font-bold text-blue-600">Scan</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b] mb-5">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            @include('pages.caregivers.partials.field', ['label'=>'First Name *','model'=>'first_name','name'=>'first_name'])
                            @include('pages.caregivers.partials.field', ['label'=>'Last Name *','model'=>'last_name','name'=>'last_name'])
                            @include('pages.caregivers.partials.field', ['label'=>'Date of Birth','model'=>'date_of_birth','name'=>'date_of_birth','type'=>'date'])
                            @include('pages.caregivers.partials.field', ['label'=>'SSN','model'=>'ssn_last4','name'=>'ssn_last4','placeholder'=>'•••-••-1234'])
                            @include('pages.caregivers.partials.select', ['label'=>'Gender','model'=>'gender','name'=>'gender','options'=>['Male','Female','Other']])
                            @include('pages.caregivers.partials.field', ['label'=>'Phone Number *','model'=>'phone','name'=>'phone','placeholder'=>'(313) 555-0167'])
                            <div class="md:col-span-2">@include('pages.caregivers.partials.field', ['label'=>'Address *','model'=>'address','name'=>'address','attrs'=>'data-gmaps'])</div>
                            @include('pages.caregivers.partials.field', ['label'=>'County (auto from address)','model'=>'county','name'=>'county'])
                            @include('pages.caregivers.partials.field', ['label'=>'Email','model'=>'email','name'=>'email','type'=>'email'])
                            @include('pages.caregivers.partials.select', ['label'=>'Preferred Language','model'=>'preferred_language','name'=>'preferred_language','options'=>['English','Arabic','Spanish','Bengali','Urdu']])
                            <div class="space-y-1.5">
                                <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Needs Accommodations?</label>
                                <div class="flex gap-2">
                                    <button type="button" @click="form.needs_accommodations=true" :class="form.needs_accommodations?'bg-[#2563eb] text-white':'bg-white text-[#475569] border border-[#e2e8f0]'" class="px-5 py-2 rounded-lg text-[12px] font-bold">Yes</button>
                                    <button type="button" @click="form.needs_accommodations=false" :class="!form.needs_accommodations?'bg-[#2563eb] text-white':'bg-white text-[#475569] border border-[#e2e8f0]'" class="px-5 py-2 rounded-lg text-[12px] font-bold">No</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b] mb-5">Emergency Contact</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            @include('pages.caregivers.partials.field', ['label'=>'Name *','model'=>'emergency_contact_name','name'=>'emergency_contact_name'])
                            @include('pages.caregivers.partials.field', ['label'=>'Phone','model'=>'emergency_contact_phone','name'=>'emergency_contact_phone'])
                            @include('pages.caregivers.partials.select', ['label'=>'Relationship','model'=>'emergency_contact_relationship','name'=>'emergency_contact_relationship','options'=>['Spouse (wife)','Spouse (husband)','Son','Daughter','Parent','Sibling','Other']])
                        </div>
                    </div>
                </div>

                {{-- STEP 2 --}}
                <div x-show="step === 2" x-cloak class="space-y-6">
                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">Work Eligibility &amp; Disclosures</h3>
                        <p class="text-[12px] text-[#94a3b8] mb-5">Section 3 of the application</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @include('pages.caregivers.partials.yesno', ['label'=>'At least 18 years old?','model'=>'is_18_plus'])
                            @include('pages.caregivers.partials.yesno', ['label'=>'Legally eligible to work in the U.S.? (I-9)','model'=>'is_work_eligible'])
                            @include('pages.caregivers.partials.yesno', ['label'=>'Ever convicted of a felony / misdemeanor?','model'=>'convicted'])
                            @include('pages.caregivers.partials.yesno', ['label'=>'Consent to criminal background check?','model'=>'has_background_check'])
                        </div>
                    </div>

                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">Background Checks — kicked off</h3>
                        <p class="text-[12px] text-[#94a3b8] mb-5">All four run automatically on consent. They run on different cadences after hiring.</p>
                        <div class="space-y-3">
                            @foreach([['CHAMPS enrolment','One-time at hiring + monitor · via MSA-204 + MILogin'],['ICHAT','Annual'],['SAM.gov','Monthly (free API)'],['OIG LEIE','Monthly (free download)']] as [$t,$sub])
                            <div class="bg-white rounded-xl border border-[#e2e8f0] px-5 py-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                                    <div><p class="text-[13.5px] font-bold text-[#1e293b]">{{ $t }}</p><p class="text-[11px] text-[#94a3b8]">{{ $sub }}</p></div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-orange-50 text-orange-500">Enrolling</span>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-5 bg-orange-50/70 border border-orange-200 rounded-xl px-5 py-4">
                            <p class="text-[12px] font-semibold text-orange-700">Cannot provide services yet. The caregiver must be CHAMPS-approved and associated to SSHC before any billable work. CHAMPS enrollment must be completed within 2 days.</p>
                        </div>
                    </div>
                </div>

                {{-- STEP 3 --}}
                <div x-show="step === 3" x-cloak class="space-y-6">
                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b] mb-5">Caregiver Type &amp; Experience</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            @include('pages.caregivers.partials.select', ['label'=>'Caregiver Type','model'=>'caregiver_type','name'=>'caregiver_type','options'=>['Family','Agency']])
                            @include('pages.caregivers.partials.select', ['label'=>'Relationship to Client','model'=>'relationship_to_client','name'=>'relationship_to_client','options'=>['Spouse (wife)','Spouse (husband)','Son','Daughter','Parent','Sibling','Other']])
                            <div class="space-y-1.5">
                                <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Prior Caregiving Experience?</label>
                                <div class="flex gap-2">
                                    <button type="button" @click="form.prior_experience=true" :class="form.prior_experience?'bg-[#2563eb] text-white':'bg-white text-[#475569] border border-[#e2e8f0]'" class="px-5 py-2 rounded-lg text-[12px] font-bold">Yes</button>
                                    <button type="button" @click="form.prior_experience=false" :class="!form.prior_experience?'bg-[#2563eb] text-white':'bg-white text-[#475569] border border-[#e2e8f0]'" class="px-5 py-2 rounded-lg text-[12px] font-bold">No</button>
                                </div>
                            </div>
                            @include('pages.caregivers.partials.select', ['label'=>'Years Caring For This Client','model'=>'years_experience','name'=>'years_experience','options'=>['< 1','1','2','3+']])
                            <div class="md:col-span-2 space-y-1.5">
                                <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Notes</label>
                                <textarea name="notes" x-model="form.notes" rows="3" class="w-full px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[13px] text-[#1e293b] outline-none focus:ring-2 focus:ring-blue-500/10 resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">Services They Can Provide</h3>
                        <p class="text-[12px] text-[#94a3b8] mb-5">Check all that apply. Non-skilled, hands-on tasks only.</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach($serviceOptions as $svc)
                            <label class="flex items-center gap-2.5 text-[13px] font-medium text-[#1e293b] cursor-pointer">
                                <input type="checkbox" name="services[]" value="{{ $svc }}" x-model="form.services" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                {{ $svc }}
                            </label>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-[#94a3b8] mt-5">These should match the services on the client's Time &amp; Task / authorization. Supervision-only tasks are not billable.</p>
                    </div>
                </div>

                {{-- STEP 4 --}}
                <div x-show="step === 4" x-cloak class="space-y-6">
                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b] mb-5">Client Assignment</h3>
                        <div class="space-y-5">
                            <div class="space-y-1.5">
                                <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Assigned Client</label>
                                <select name="client_id" x-model="form.client_id" class="w-full px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[13px] text-[#1e293b] outline-none focus:ring-2 focus:ring-blue-500/10">
                                    <option value="">— Select client —</option>
                                    @foreach($clients as $cl)
                                        <option value="{{ $cl->id }}">{{ $cl->first_name }} {{ $cl->last_name }} @if($cl->member_id) · {{ $cl->member_id }} @endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-1.5">
                                    <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Lives With Client (same address)?</label>
                                    <div class="flex gap-2">
                                        <button type="button" @click="form.lives_with_client=true" :class="form.lives_with_client?'bg-[#2563eb] text-white':'bg-white text-[#475569] border border-[#e2e8f0]'" class="px-5 py-2 rounded-lg text-[12px] font-bold">Yes</button>
                                        <button type="button" @click="form.lives_with_client=false" :class="!form.lives_with_client?'bg-[#2563eb] text-white':'bg-white text-[#475569] border border-[#e2e8f0]'" class="px-5 py-2 rounded-lg text-[12px] font-bold">No</button>
                                    </div>
                                </div>
                                <div class="space-y-1.5" x-show="form.lives_with_client">
                                    <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">Live-In Exemption</label>
                                    <div class="px-4 py-2.5 bg-orange-50 border border-orange-200 rounded-xl text-[12px] font-bold text-orange-600">BPHASA-2421 triggered</div>
                                </div>
                            </div>
                            <div x-show="form.lives_with_client" class="bg-green-50 border border-green-200 rounded-xl px-5 py-4 text-[12px] font-semibold text-green-700">
                                Same address as the client. The Live-In Caregiver Attestation (BPHASA-2421) is queued — once approved (renew yearly), the caregiver is EVV exempt (no HHAeXchange clock-in/out).
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">Pay &amp; Payroll Setup</h3>
                        <p class="text-[12px] text-[#94a3b8] mb-5">W-2 employee · paid through AccountantsWorld.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            @include('pages.caregivers.partials.field', ['label'=>'Hourly Wage *','model'=>'hourly_wage','name'=>'hourly_wage','placeholder'=>'15.00','type'=>'number'])
                            @include('pages.caregivers.partials.select', ['label'=>'Pay Type','model'=>'pay_type','name'=>'pay_type','options'=>['W-2 · hourly','1099 · contractor']])
                            @include('pages.caregivers.partials.select', ['label'=>'Pay Schedule','model'=>'pay_schedule','name'=>'pay_schedule','options'=>['Monthly · 1st Tue batch','Bi-weekly','Weekly']])
                            @include('pages.caregivers.partials.select', ['label'=>'W-4 Filing Status','model'=>'w4_filing_status','name'=>'w4_filing_status','options'=>['Single','Married filing jointly','Head of household']])
                            @include('pages.caregivers.partials.field', ['label'=>'Direct Deposit (last 4)','model'=>'direct_deposit_last4','name'=>'direct_deposit_last4','placeholder'=>'4821'])
                            @include('pages.caregivers.partials.select', ['label'=>'Insurance Coverage','model'=>'insurance_coverage','name'=>'insurance_coverage','options'=>['Waived (covered elsewhere)','Agency plan','Declined']])
                        </div>
                        <div class="mt-5 bg-orange-50/70 border border-orange-200 rounded-xl px-5 py-4">
                            <p class="text-[12px] font-semibold text-orange-700">Pay-eligibility start = the later of the client's case start date or the caregiver's CHAMPS Association Date. As a family caregiver, prior service can be backdated if dates support it.</p>
                        </div>
                    </div>
                </div>

                {{-- STEP 5 --}}
                <div x-show="step === 5" x-cloak class="space-y-6">
                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">Employment Packet</h3>
                        <p class="text-[12px] text-[#94a3b8] mb-5">Tracked here; the agent prepares/fills what it can and routes the rest for signature.</p>
                        <div class="space-y-2.5">
                            @foreach([
                                ['Caregiver Employment Application','+ Policies & Compliance Agreement (initialed)','Signed','green'],
                                ['MSA-204 — CHAMPS Enrollment Authorization','MILogin created · enrolling caregiver in CHAMPS','In progress','orange'],
                                ['I-9 — Employment Eligibility','ID + work authorization verified','Complete','green'],
                                ['W-4 — Withholding','Filing status from Step 4','Complete','green'],
                                ['Direct Deposit Agreement','Bank on file','Complete','green'],
                                ['Employee Insurance Coverage Waiver','Declined — covered elsewhere','Complete','green'],
                                ['BPHASA-2421 — Live-In Caregiver Attestation','Initial request · proofs of address attached','Enrolling','orange'],
                                ['Copy of ID + SSN card','Scanned at Step 1','On file','green'],
                            ] as [$doc,$sub,$st,$tone])
                            <div class="bg-white rounded-xl border border-[#e2e8f0] px-5 py-3.5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></span>
                                    <div><p class="text-[13px] font-bold text-[#1e293b]">{{ $doc }}</p><p class="text-[11px] text-[#94a3b8]">{{ $sub }}</p></div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold {{ $tone==='green'?'bg-green-50 text-green-600':'bg-orange-50 text-orange-500' }}">{{ $st }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">Caregiver Verbal Review — acknowledgments</h3>
                        <p class="text-[12px] text-[#94a3b8] mb-5">Walked through at onboarding; each item initialed.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                            @foreach($acks as $ack)
                            <label class="flex items-start gap-2.5 text-[12.5px] font-medium text-[#1e293b] cursor-pointer">
                                <input type="checkbox" x-model="form.acks" value="{{ $loop->index }}" class="w-4 h-4 mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                {{ $ack }}
                            </label>
                            @endforeach
                        </div>
                        <div class="mt-5 bg-green-50 border border-green-200 rounded-xl px-5 py-3 text-[12px] font-semibold text-green-700">
                            <span x-text="form.acks.length"></span> of 8 acknowledgments initialed · reviewed at onboarding. Exportable as a PDF.
                        </div>
                    </div>
                </div>

                {{-- STEP 6 --}}
                <div x-show="step === 6" x-cloak class="space-y-6">
                    <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">New caregiver — <span x-text="(form.first_name||'—') + ' ' + (form.last_name||'')"></span></h3>
                        <p class="text-[12px] text-[#94a3b8] mb-6">Review before creating the profile. Click any step on the left to edit.</p>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-5 pb-6 border-b border-blue-100/40">
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Personal Info</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="(form.first_name||'') + ' ' + (form.last_name||'')"></p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">DOB</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="form.date_of_birth||'—'"></p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Language</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="form.preferred_language||'—'"></p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Address</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="form.address||'—'"></p></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-5 py-6 border-b border-blue-100/40">
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Relationship · Type</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="(form.relationship_to_client||'—') + ' · ' + (form.caregiver_type||'—')"></p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Hourly Wage</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="form.hourly_wage ? ('$'+form.hourly_wage+' / hour') : '—'"></p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Live-in / EVV</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="form.lives_with_client ? 'Live-in → EVV exempt' : 'HHAeXchange'"></p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Services</p><p class="text-[13px] font-bold text-[#1e293b] mt-1" x-text="form.services.length + ' selected'"></p></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-5 pt-6">
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">CHAMPS</p><p class="text-[12px] font-bold text-orange-500 mt-1">Enrolling (MSA-204)</p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">ICHAT</p><p class="text-[12px] font-bold text-orange-500 mt-1">Submitted</p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">SAM.gov / OIG</p><p class="text-[12px] font-bold text-orange-500 mt-1">Clear</p></div>
                            <div><p class="text-[10px] font-black text-[#94a3b8] uppercase">Acknowledgments</p><p class="text-[12px] font-bold text-[#1e293b] mt-1"><span x-text="form.acks.length"></span> / 8 initialed</p></div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200/70 rounded-2xl px-6 py-4">
                        <p class="text-[12px] text-blue-700/90 leading-relaxed">This creates the caregiver profile only — it doesn't approve them to work. Status is set to <b>Pending onboarding</b>. The caregiver cannot provide billable services until CHAMPS enrollment is approved and associated to SSHC (and ICHAT clears). From there the agent tracks the checks and the live-in attestation, then the caregiver goes active and links to the client's chart.</p>
                    </div>

                    </div>

                {{-- Nav buttons --}}
                <div class="flex items-center justify-between pt-2">
                    <div>
                        <a href="{{ route('caregivers') }}" x-show="step===1" class="px-5 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm">‹ Cancel</a>
                        <button type="button" x-show="step>1" @click="prev()" class="px-5 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm">‹ Back</button>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" x-show="step===6" class="px-5 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm">Save as draft</button>
                        <button type="button" x-show="step<6" @click="next()" class="px-6 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 hover:bg-[#1d4ed8]" x-text="nextLabel"></button>
                        <button type="submit" x-show="step===6" class="px-6 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100 hover:bg-[#1d4ed8]">✓ Create Caregiver Profile</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>[x-cloak]{display:none!important}</style>
<script>
function caregiverWizard(prefill) {
    return {
        step: 1,
        steps: @json($steps),
        form: {
            first_name: '', last_name: '', date_of_birth: '', ssn_last4: '', gender: '',
            phone: '', address: prefill.address || '', county: prefill.county || '', email: '',
            preferred_language: '', needs_accommodations: false,
            emergency_contact_name: '', emergency_contact_phone: '', emergency_contact_relationship: '',
            is_18_plus: true, is_work_eligible: true, convicted: false, has_background_check: true,
            caregiver_type: 'Family', relationship_to_client: '', prior_experience: true,
            years_experience: '', notes: '', services: [],
            client_id: prefill.client_id || '', lives_with_client: true,
            hourly_wage: '', pay_type: 'W-2 · hourly', pay_schedule: 'Monthly · 1st Tue batch',
            w4_filing_status: 'Single', direct_deposit_last4: '', insurance_coverage: 'Waived (covered elsewhere)',
            acks: [],
        },
        get nextLabel() {
            return ['Continue to Eligibility ›','Continue to Employment ›','Continue to Assignment ›','Continue to Documents ›','Continue to Review ›'][this.step-1] || 'Continue ›';
        },
        goto(n) { this.step = n; window.scrollTo({top:0,behavior:'smooth'}); },
        next() { if (this.step < 6) this.goto(this.step + 1); },
        prev() { if (this.step > 1) this.goto(this.step - 1); },
    };
}
</script>
@include('partials.google-maps-autocomplete')
@endsection
