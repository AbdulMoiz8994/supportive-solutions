@extends('layouts.app')

@section('content')
@php
    $steps = [
        ['01', 'Basic Information',     'Name · DOB · Medicaid ID · SSN'],
        ['02', 'Contact & Address',     'Phone · Email · Address · County'],
        ['03', 'Program & Coverage',    'Coverage type · MCO · Insurance IDs'],
        ['04', 'Primary Care Physician','PCP · Phone · Fax · NPI'],
        ['05', 'Emergency Contact',     'Name · Relationship · Phone · Email'],
        ['06', 'Review & Enrol',        'Confirm details and create profile'],
    ];

    $coverageOptions   = $coverageTypes->pluck('name', 'id')->toArray();
    $genderOptions     = ['Male', 'Female', 'Other', 'Prefer not to say'];
    $languageOptions   = ['English', 'Arabic', 'Spanish', 'French', 'Other'];
    $translatorOptions = ['No', 'Yes'];
    // Directory → Payers / MCOs drives the insurer list (fallback until payers exist).
    $mcoOptions        = $mcoOptions ?? \App\Support\DirectoryMcoOptions::list();
    $relOptions        = ['Son', 'Daughter', 'Spouse', 'Parent', 'Sibling', 'Friend', 'Guardian', 'Other'];
    $michiganCounties  = ['Alcona','Alger','Allegan','Alpena','Antrim','Arenac','Baraga','Barry','Bay','Benzie','Berrien','Branch','Calhoun','Cass','Charlevoix','Cheboygan','Chippewa','Clare','Clinton','Crawford','Delta','Dickinson','Eaton','Emmet','Genesee','Gladwin','Gogebic','Grand Traverse','Gratiot','Hillsdale','Houghton','Huron','Ingham','Ionia','Iosco','Iron','Isabella','Jackson','Kalamazoo','Kalkaska','Kent','Keweenaw','Lake','Lapeer','Leelanau','Lenawee','Livingston','Luce','Mackinac','Macomb','Manistee','Marquette','Mason','Mecosta','Menominee','Midland','Missaukee','Monroe','Montcalm','Montmorency','Muskegon','Newaygo','Oakland','Oceana','Ogemaw','Ontonagon','Osceola','Oscoda','Otsego','Ottawa','Presque Isle','Roscommon','Saginaw','St. Clair','St. Joseph','Sanilac','Schoolcraft','Shiawassee','Tuscola','Van Buren','Washtenaw','Wayne','Wexford'];
@endphp

<div class="w-full px-2 pb-20" x-data="clientWizard()"
     x-on:id-scanned.window="
        const d = $event.detail;
        if (d.first_name) form.first_name = d.first_name;
        if (d.last_name) form.last_name = d.last_name;
        const dob = window.idScanDob(d.date_of_birth); if (dob) form.dob = dob;
        const addr = window.idScanAddress(d); if (addr) form.address = addr;
        const g = window.idScanSex(d.sex); if (g) form.gender = g;
     ">

    {{-- Header --}}
    <div class="flex items-center justify-between pt-3 pb-4">
        <div>
            <h1 class="text-[26px] font-black text-[#1e293b] tracking-tight">Enrol Client</h1>
            <p class="text-[12px] font-medium text-[#64748b] mt-1">Direct enrolment workflow · step <span x-text="step"></span> of 6 — <span x-text="steps[step-1][2]"></span></p>
        </div>
        <a href="{{ route('clients.index') }}" class="px-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569] shadow-sm hover:bg-gray-50">Cancel</a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-2xl px-6 py-4 mb-6 text-[12px] font-bold text-red-700">
        Please fix: {{ implode(' · ', $errors->all()) }}
    </div>
    @endif

    {{-- Client-side validation errors --}}
    <div x-show="Object.keys(errors).length > 0" x-cloak
         class="bg-red-50 border border-red-200 rounded-2xl px-6 py-4 mb-6 text-[12px] font-bold text-red-700">
        <template x-for="(msg, field) in errors" :key="field">
            <div x-text="msg"></div>
        </template>
    </div>

    <form method="POST" action="{{ route('clients.store') }}" id="clientForm" autocomplete="off" x-ref="clientForm" @submit.prevent="submitForm()">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            {{-- Step rail --}}
            <div class="lg:col-span-1">
                <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-3 space-y-1 sticky top-4">
                    @foreach($steps as $i => [$num, $label, $sub])
                        <button type="button" @click="goto({{ $i+1 }})"
                            :class="step === {{ $i+1 }} ? 'bg-white shadow-sm border-blue-200' : 'border-transparent hover:bg-white/60'"
                            class="w-full text-left flex items-start gap-3 px-3 py-3 rounded-xl border transition-all">
                            <span class="w-6 h-6 shrink-0 rounded-full flex items-center justify-center text-[10px] font-black"
                                :class="step === {{ $i+1 }} ? 'bg-[#2563eb] text-white' : (step > {{ $i+1 }} ? 'bg-green-500 text-white' : 'bg-blue-100 text-blue-500')">
                                <template x-if="step > {{ $i+1 }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </template>
                                <template x-if="step <= {{ $i+1 }}">
                                    <span>{{ $num }}</span>
                                </template>
                            </span>
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

                {{-- ─── Step 1: Basic Information ─────────────────────────────── --}}
                <div x-show="step === 1">
                    <div class="bg-white rounded-[20px] border border-[#e6eef9] p-7 space-y-5">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-base font-bold text-[#0f172a]">Basic Information</h2>
                            @include('partials.id-scan')
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">First name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" x-model="form.first_name" required
                                    :class="inputClass('first_name')"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border text-sm outline-none focus:ring-2"
                                    placeholder="First name">
                                <p x-show="errors.first_name" x-cloak class="mt-1 text-xs font-medium text-[#d92d20]" x-text="errors.first_name"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Last name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" x-model="form.last_name" required
                                    :class="inputClass('last_name')"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border text-sm outline-none focus:ring-2"
                                    placeholder="Last name">
                                <p x-show="errors.last_name" x-cloak class="mt-1 text-xs font-medium text-[#d92d20]" x-text="errors.last_name"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Date of birth</label>
                                <input type="date" name="dob" x-model="form.dob"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Medicaid ID</label>
                                <input type="text" name="member_id" x-model="form.member_id"
                                    :class="inputClass('member_id')"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border text-sm outline-none focus:ring-2"
                                    placeholder="e.g. MD-100001" maxlength="20">
                                <p x-show="errors.member_id" x-cloak class="mt-1 text-xs font-medium text-[#d92d20]" x-text="errors.member_id"></p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Gender</label>
                                <div class="relative">
                                    <select name="gender" x-model="form.gender"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 bg-white appearance-none pr-9">
                                        <option value="">Select gender</option>
                                        @foreach($genderOptions as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">SSN <span class="text-[#94a3b8] normal-case font-normal">(stored securely)</span></label>
                                <input type="text" name="ssn" x-model="form.ssn"
                                    @input="formatSsn($event.target)"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-mono outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 tracking-widest"
                                    placeholder="###-##-####" maxlength="11">
                                <p class="text-xs text-[#94a3b8] mt-1">Only last 4 digits are stored and visible in profile.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Step 2: Contact & Address ──────────────────────────────── --}}
                <div x-show="step === 2">
                    <div class="bg-white rounded-[20px] border border-[#e6eef9] p-7 space-y-5">
                        <h2 class="text-base font-bold text-[#0f172a] mb-4">Contact & Address</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Phone</label>
                                <input type="tel" name="phone" x-model="form.phone"
                                    @input="window.formatPhone($event.target)"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="(313) 555-0000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Email</label>
                                <input type="email" name="email" x-model="form.email"
                                    :class="inputClass('email')"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border text-sm outline-none focus:ring-2"
                                    placeholder="client@email.com">
                                <p x-show="errors.email" x-cloak class="mt-1 text-xs font-medium text-[#d92d20]" x-text="errors.email"></p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Address</label>
                                <input type="text" name="address" x-model="form.address" id="addressAutocomplete" data-gmaps autocomplete="off"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="Start typing an address…">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">County</label>
                                <div class="relative">
                                    <select name="county" x-model="form.county"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 bg-white appearance-none pr-9">
                                        <option value="">Select county</option>
                                        @foreach($michiganCounties as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Preferred language</label>
                                <div class="relative">
                                    <select name="preferred_language" x-model="form.preferred_language"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 bg-white appearance-none pr-9">
                                        @foreach($languageOptions as $l)<option value="{{ $l }}">{{ $l }}</option>@endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Requires translator?</label>
                                <div class="relative">
                                    <select name="requires_translator" x-model="form.requires_translator"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 bg-white appearance-none pr-9">
                                        @foreach($translatorOptions as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Step 3: Program & Coverage ─────────────────────────────── --}}
                <div x-show="step === 3">
                    <div class="bg-white rounded-[20px] border border-[#e6eef9] p-7 space-y-5">
                        <h2 class="text-base font-bold text-[#0f172a] mb-4">Program & Coverage</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Coverage / Program <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <select name="coverage_type_id" x-model="form.coverage_type_id"
                                        :class="inputClass('coverage_type_id')"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border text-sm outline-none focus:ring-2 bg-white appearance-none pr-9">
                                        <option value="">Select coverage</option>
                                        @foreach($coverageTypes as $ct)
                                            <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                                        @endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                                <p x-show="errors.coverage_type_id" x-cloak class="mt-1 text-xs font-medium text-[#d92d20]" x-text="errors.coverage_type_id"></p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">MCO / Insurance</label>
                                <div class="relative">
                                    <select name="mco_name" x-model="form.mco_name"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 bg-white appearance-none pr-9">
                                        <option value="">Select insurer</option>
                                        @foreach($mcoOptions as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Medicaid ID</label>
                                <input type="text" name="member_id" x-model="form.member_id"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="e.g. MD-100001" maxlength="20">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Medicare ID <span class="text-[#94a3b8] normal-case font-normal">(dual only)</span></label>
                                <input type="text" name="medicare_id" x-model="form.medicare_id"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="e.g. 1AB-CD2-EF34">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Health Plan ID <span class="text-[#94a3b8] normal-case font-normal">(if applicable)</span></label>
                                <input type="text" name="health_plan_id" x-model="form.health_plan_id"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="e.g. AET-000000-00">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Step 4: Primary Care Physician ─────────────────────────── --}}
                <div x-show="step === 4" x-data="wizardPcp({ pcpMap: {{ $pcpContactsJson ?? '{}' }} })">
                    <div class="bg-white rounded-[20px] border border-[#e6eef9] p-7 space-y-5">
                        <h2 class="text-base font-bold text-[#0f172a] mb-4">Primary Care Physician</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">PCP — select from directory</label>
                                <div class="relative">
                                    <select name="pcp_contact_id" x-model="pcpId" @change="onSelect()"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 bg-white appearance-none pr-9">
                                        <option value="">Select PCP</option>
                                        @foreach($pcpContacts as $pcp)
                                            <option value="{{ $pcp->id }}">{{ $pcp->name }}@if($pcp->clinic_name) — {{ $pcp->clinic_name }}@endif</option>
                                        @endforeach
                                        @if($pcpContacts->isEmpty())
                                            <option disabled>No PCPs in directory — add via Directory first</option>
                                        @endif
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">PCP Phone <span class="text-[#94a3b8] normal-case font-normal">(auto-fills)</span></label>
                                <input type="tel" name="pcp_phone" x-model="pcpPhone"
                                    @input="window.formatPhone($event.target)"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="(000) 000-0000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">PCP Fax <span class="text-[#94a3b8] normal-case font-normal">(auto-fills)</span></label>
                                <input type="tel" name="pcp_fax" x-model="pcpFax"
                                    @input="window.formatPhone($event.target)"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="(000) 000-0000">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">NPI</label>
                                <input type="text" name="pcp_npi" x-model="pcpNpi"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="10-digit NPI" maxlength="10">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Medical conditions <span class="text-[#94a3b8] normal-case font-normal">(internal awareness)</span></label>
                                <textarea name="medical_conditions" rows="3"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="e.g. Osteoarthritis; hypertension; limited mobility"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Step 5: Emergency Contact ───────────────────────────────── --}}
                <div x-show="step === 5">
                    <div class="bg-white rounded-[20px] border border-[#e6eef9] p-7 space-y-5">
                        <h2 class="text-base font-bold text-[#0f172a] mb-4">Emergency Contact</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Name</label>
                                <input type="text" name="emergency_name" x-model="form.emergency_name"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="Full name">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Relationship</label>
                                <div class="relative">
                                    <select name="emergency_relationship" x-model="form.emergency_relationship"
                                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10 bg-white appearance-none pr-9">
                                        <option value="">Select relationship</option>
                                        @foreach($relOptions as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                                    </select>
                                    <svg class="w-4 h-4 text-[#94a3b8] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Phone</label>
                                <input type="tel" name="emergency_phone" x-model="form.emergency_phone"
                                    @input="window.formatPhone($event.target)"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="(313) 555-0000">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Email</label>
                                <input type="email" name="emergency_email" x-model="form.emergency_email"
                                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10"
                                    placeholder="email@example.com">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Step 6: Review & Enrol ─────────────────────────────────── --}}
                <div x-show="step === 6">
                    <div class="bg-white rounded-[20px] border border-[#e6eef9] p-7">
                        <h2 class="text-base font-bold text-[#0f172a] mb-5">Review & Enrol</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div class="text-xs font-bold text-[#94a3b8] uppercase tracking-wide">Client</div>
                                <div class="rounded-xl border border-[#eef2f9] bg-[#fafcff] px-4 py-3 space-y-2">
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">Name</span><span class="font-semibold text-[#0f172a]" x-text="(form.first_name || '—') + ' ' + (form.last_name || '')"></span></div>
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">DOB</span><span class="font-semibold text-[#0f172a]" x-text="form.dob || '—'"></span></div>
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">Medicaid ID</span><span class="font-semibold text-[#0f172a]" x-text="form.member_id || '—'"></span></div>
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">SSN</span><span class="font-semibold text-[#0f172a]" x-text="form.ssn ? '•••-••-' + (form.ssn.replace(/\D/g,'').slice(-4) || '••••') : '—'"></span></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="text-xs font-bold text-[#94a3b8] uppercase tracking-wide">Contact</div>
                                <div class="rounded-xl border border-[#eef2f9] bg-[#fafcff] px-4 py-3 space-y-2">
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">Phone</span><span class="font-semibold text-[#0f172a]" x-text="form.phone || '—'"></span></div>
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">Email</span><span class="font-semibold text-[#0f172a]" x-text="form.email || '—'"></span></div>
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">Address</span><span class="font-semibold text-[#0f172a]" x-text="form.address || '—'"></span></div>
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">County</span><span class="font-semibold text-[#0f172a]" x-text="form.county || '—'"></span></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="text-xs font-bold text-[#94a3b8] uppercase tracking-wide">Coverage</div>
                                <div class="rounded-xl border border-[#eef2f9] bg-[#fafcff] px-4 py-3 space-y-2">
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">MCO</span><span class="font-semibold text-[#0f172a]" x-text="form.mco_name || '—'"></span></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="text-xs font-bold text-[#94a3b8] uppercase tracking-wide">Emergency Contact</div>
                                <div class="rounded-xl border border-[#eef2f9] bg-[#fafcff] px-4 py-3 space-y-2">
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">Name</span><span class="font-semibold text-[#0f172a]" x-text="form.emergency_name || '—'"></span></div>
                                    <div class="flex gap-2 text-sm"><span class="text-[#94a3b8] w-24 shrink-0">Phone</span><span class="font-semibold text-[#0f172a]" x-text="form.emergency_phone || '—'"></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 p-4 rounded-xl bg-[#eff6ff] border border-blue-100">
                            <p class="text-sm text-[#1e40af] font-medium">By enrolling this client you confirm that consent has been obtained and all information is accurate to the best of your knowledge.</p>
                        </div>
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="flex items-center justify-between">
                    <button type="button" @click="prev()" x-show="step > 1"
                        class="px-5 py-2.5 rounded-xl border border-[#e2e8f0] bg-white text-sm font-bold text-[#475569] hover:bg-gray-50 transition">
                        ← Previous
                    </button>
                    <div x-show="step === 1" class="w-1"></div>

                    <div class="flex items-center gap-3">
                        <template x-for="i in 6" :key="i">
                            <div class="w-2 h-2 rounded-full transition-all"
                                :class="i === step ? 'bg-[#2563eb] w-4' : (i < step ? 'bg-[#2563eb]/40' : 'bg-[#e2e8f0]')"></div>
                        </template>
                    </div>

                    <button type="button" @click="next()" x-show="step < 6"
                        class="px-5 py-2.5 rounded-xl bg-[#2563eb] text-white text-sm font-bold hover:bg-[#1d4ed8] transition">
                        Next →
                    </button>
                    <button type="submit" x-show="step === 6"
                        class="px-7 py-2.5 rounded-xl bg-[#16a34a] text-white text-sm font-bold hover:bg-[#15803d] transition">
                        Enrol Client
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@include('partials.google-maps-autocomplete')
@endsection

@push('scripts')
<script>
function clientWizard() {
    return {
        step: 1,
        steps: @js(array_values($steps)),
        errors: {},
        form: {
            first_name: '', last_name: '', dob: '', member_id: '', gender: '', ssn: '',
            phone: '', email: '', address: '', county: '', preferred_language: 'English', requires_translator: 'No',
            coverage_type_id: '', mco_name: '', medicare_id: '', health_plan_id: '',
            emergency_name: '', emergency_relationship: '', emergency_phone: '', emergency_email: '',
        },
        goto(n) {
            if (n === this.step) return;
            if (n < this.step) {
                this.step = n;
                this.errors = {};
                window.scrollTo(0, 0);
                return;
            }
            if (!this.validateThrough(n - 1)) {
                window.scrollTo(0, 0);
                return;
            }
            this.step = n;
            this.errors = {};
            window.scrollTo(0, 0);
        },
        inputClass(field) {
            return this.errors[field]
                ? 'border-[#fda29b] focus:border-[#d92d20] focus:ring-[#d92d20]/10'
                : 'border-[#e2e8f0] focus:border-[#2563eb] focus:ring-[#2563eb]/10';
        },
        validateStep(n) {
            const e = {};
            if (n === 1) {
                if (!this.form.first_name.trim()) e.first_name = 'First name is required.';
                if (!this.form.last_name.trim())  e.last_name  = 'Last name is required.';
                if (this.form.member_id && !/^MD-\d{5}$/i.test(this.form.member_id.trim())) {
                    e.member_id = 'Medicaid ID must match MD-12345 format.';
                }
            }
            if (n === 2) {
                if (this.form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email.trim())) {
                    e.email = 'Email must be a valid address.';
                }
            }
            if (n === 3) {
                if (!this.form.coverage_type_id) e.coverage_type_id = 'Coverage / Program is required.';
            }
            return e;
        },
        validateThrough(lastStep) {
            let errors = {};
            for (let i = 1; i <= lastStep; i++) {
                const stepErrors = this.validateStep(i);
                if (Object.keys(stepErrors).length > 0) {
                    this.step = i;
                    errors = stepErrors;
                    break;
                }
            }
            this.errors = errors;
            return Object.keys(errors).length === 0;
        },
        next() {
            const stepErrors = this.validateStep(this.step);
            this.errors = stepErrors;
            if (Object.keys(stepErrors).length > 0) { window.scrollTo(0, 0); return; }
            if (this.step < 6) { this.step++; this.errors = {}; window.scrollTo(0,0); }
        },
        prev() { if (this.step > 1) { this.step--; this.errors = {}; window.scrollTo(0,0); } },
        submitForm() {
            if (!this.validateThrough(6)) {
                window.scrollTo(0, 0);
                return;
            }
            this.$refs.clientForm.submit();
        },
        formatSsn(input) {
            let raw = input.value.replace(/\D/g, '').substring(0, 9);
            if (raw.length > 5) {
                input.value = raw.substring(0,3) + '-' + raw.substring(3,5) + '-' + raw.substring(5);
            } else if (raw.length > 3) {
                input.value = raw.substring(0,3) + '-' + raw.substring(3);
            } else {
                input.value = raw;
            }
            this.form.ssn = input.value;
        }
    };
}

function wizardPcp({ pcpMap }) {
    return {
        pcpId: '',
        pcpPhone: '',
        pcpFax: '',
        pcpNpi: '',
        onSelect() {
            const pcp = pcpMap[this.pcpId];
            if (pcp) {
                this.pcpPhone = pcp.phone || '';
                this.pcpFax   = pcp.fax   || '';
                this.pcpNpi   = pcp.provider_id || '';
            }
        }
    };
}
</script>
@endpush
