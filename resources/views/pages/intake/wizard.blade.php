@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="New Intake — Scan First" />

    <div x-data="intakeWizard()" class="max-w-4xl mx-auto">
        <div class="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03] shadow-theme-xs">

            <div class="px-8 pt-6 pb-5 border-b border-gray-100 dark:border-white/[0.05]">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Scan-first intake</h3>
                        <p class="text-sm text-gray-500 font-medium mt-1">Upload the referral packet first — scan reads the fields, eligibility picks the program, then the client chart is created.</p>
                    </div>
                    <a href="{{ route('intakes.index') }}" class="text-sm font-medium text-gray-400 hover:text-gray-600">Cancel</a>
                </div>
                <div class="flex items-center gap-2 mt-5">
                    <template x-for="(label, i) in steps" :key="i">
                        <div class="flex-1">
                            <div class="h-1.5 rounded-full" :class="step >= i ? 'bg-brand-500' : 'bg-gray-100 dark:bg-white/[0.05]'"></div>
                            <p class="mt-1.5 text-[10px] font-black uppercase tracking-widest truncate"
                               :class="step >= i ? 'text-brand-500' : 'text-gray-400'" x-text="label"></p>
                        </div>
                    </template>
                </div>
            </div>

            <form action="{{ route('intakes.store') }}" method="POST" class="p-8">
                @csrf
                <input type="hidden" name="from_wizard" value="1">
                <input type="hidden" name="scan_data" :value="scanData ? JSON.stringify(scanData) : ''">
                <input type="hidden" name="scanned_documents" :value="scannedDocuments.length ? JSON.stringify(scannedDocuments) : ''">
                <input type="hidden" name="eligibility_status" :value="eligibility.status">
                <input type="hidden" name="eligibility_note" :value="eligibility.note">
                <input type="hidden" name="eligibility_checked_at" :value="eligibility.checked_at">
                <input type="hidden" name="recommended_program" :value="form.recommended_program">
                <input type="hidden" name="program_track" :value="form.program_track">
                <input type="hidden" name="source" :value="form.source">

                {{-- Step 1 · Scan referral packet --}}
                <div x-show="step === 0" x-cloak class="space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Upload each document from the referral packet. The AI reads identity fields — confirm before anything is saved.</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach([
                            ['key' => 'medicaid', 'label' => 'Medicaid card', 'hint' => 'Member ID + plan'],
                            ['key' => 'id', 'label' => 'Photo ID', 'hint' => 'Name, DOB, address'],
                            ['key' => 'referral', 'label' => 'Referral form', 'hint' => 'Hospital / discharge'],
                        ] as $slot)
                        <div class="rounded-2xl border-2 border-dashed p-5 text-center"
                             :class="hasScan('{{ $slot['key'] }}') ? 'border-green-200 bg-green-50/40' : 'border-gray-200 dark:border-white/[0.08]'">
                            <p class="text-xs font-black text-gray-400 uppercase tracking-widest">{{ $slot['label'] }}</p>
                            <p class="mt-2 text-[11px] text-gray-500">{{ $slot['hint'] }}</p>
                            <input type="file" class="hidden" x-ref="{{ $slot['key'] }}File" accept="image/jpeg,image/png,image/webp,application/pdf"
                                   @change="scanDocument('{{ $slot['key'] }}', $event)">
                            <button type="button" @click="$refs.{{ $slot['key'] }}File.click()" :disabled="scanningKey === '{{ $slot['key'] }}'"
                                class="mt-4 px-4 py-2 text-xs font-semibold text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-60">
                                <span x-show="scanningKey !== '{{ $slot['key'] }}'">Scan Doc</span>
                                <span x-show="scanningKey === '{{ $slot['key'] }}'" x-cloak>Reading…</span>
                            </button>
                            <p x-show="hasScan('{{ $slot['key'] }}')" x-cloak class="mt-2 text-[11px] font-semibold text-green-600">✓ Captured</p>
                        </div>
                        @endforeach
                    </div>
                    <p x-show="scanError" x-cloak class="text-xs font-medium text-amber-600" x-text="scanError"></p>
                    <div class="flex justify-end">
                        <button type="button" @click="step = 1" class="text-sm font-medium text-gray-500 hover:text-gray-700">Skip scan — enter manually →</button>
                    </div>
                </div>

                {{-- Step 2 · Verify pre-filled details --}}
                <div x-show="step === 1" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2 flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/[0.02] px-4 py-3">
                        <p class="text-xs text-gray-500">Fields below were read from your scans — fix anything that looks wrong.</p>
                        @include('partials.id-scan')
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">First name *</label>
                        <input type="text" name="first_name" x-model="form.first_name" required class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Last name *</label>
                        <input type="text" name="last_name" x-model="form.last_name" required class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Date of birth</label>
                        <input type="date" name="dob" x-model="form.dob" class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Phone</label>
                        <input type="text" name="phone" x-model="form.phone" class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Email</label>
                        <input type="email" name="email" x-model="form.email" class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Medicaid ID</label>
                        <input type="text" name="member_id" x-model="form.member_id" placeholder="MD-00000" class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Address</label>
                        <input type="text" name="address" x-model="form.address" class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">MCO / insurance plan</label>
                        <select name="mco_name" x-model="form.mco_name" class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                            <option value="">None — straight Medicaid (DHS)</option>
                            @foreach($mcoOptions as $mco)
                                <option value="{{ $mco }}">{{ $mco }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Referral source</label>
                        <input type="text" x-model="form.source" placeholder="Hospital, discharge planner, website…" class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                    </div>
                </div>

                {{-- Step 3 · Eligibility → program --}}
                <div x-show="step === 2" x-cloak class="space-y-5">
                    <div class="flex items-center justify-between rounded-2xl border border-gray-100 dark:border-white/[0.05] p-5">
                        <div>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">Medicaid eligibility check</p>
                            <p class="text-xs text-gray-400 mt-0.5">Availity / portal verification — the result determines DHS vs MICH/ICO/DAAA.</p>
                        </div>
                        <button type="button" @click="runEligibility()" :disabled="checking"
                            class="px-4 py-2.5 text-sm font-semibold text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-60">
                            <span x-show="!checking">Check eligibility</span>
                            <span x-show="checking" x-cloak>Checking…</span>
                        </button>
                    </div>
                    <div x-show="eligibility.status" x-cloak class="rounded-2xl p-5 border"
                         :class="{
                            'bg-green-50 border-green-100': eligibility.status === 'eligible',
                            'bg-amber-50 border-amber-100': eligibility.status === 'needs_verification',
                            'bg-red-50 border-red-100': eligibility.status === 'ineligible',
                         }">
                        <p class="text-sm font-bold" x-text="eligibilityLabel()"></p>
                        <p class="text-xs text-gray-600 mt-1" x-text="eligibility.note"></p>
                        <p x-show="eligibility.live" x-cloak class="text-[10px] font-bold text-green-700 mt-2 uppercase tracking-widest">Live Availity verification</p>
                    </div>
                    <div x-show="recommendation.program" x-cloak class="rounded-2xl border border-brand-100 bg-brand-50/30 p-5">
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Program selected by eligibility</p>
                        <p class="text-lg font-bold text-gray-800 dark:text-white/90 mt-1" x-text="recommendation.program"></p>
                        <p class="text-xs text-gray-500 mt-1" x-text="recommendation.reason"></p>
                        <input type="hidden" name="coverage_type_id" :value="form.coverage_type_id">
                    </div>
                </div>

                {{-- Step 4 · Program-specific path --}}
                <div x-show="step === 3" x-cloak class="space-y-5">
                    <div x-show="form.program_track === 'dhs'" x-cloak class="rounded-2xl border border-gray-100 p-5 space-y-4">
                        <p class="text-sm font-bold text-gray-800">DHS Home Help — Time/Task path</p>
                        <p class="text-xs text-gray-500">No prior authorization. Capture weekly hours for the Time/Task sheet; reassessment every 6 months.</p>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Hours per week</label>
                            <input type="number" name="hours_per_week" x-model="form.hours_per_week" min="1" max="168" step="0.5"
                                class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                        </div>
                    </div>
                    <div x-show="form.program_track !== 'dhs' && form.program_track" x-cloak class="rounded-2xl border border-gray-100 p-5 space-y-4">
                        <p class="text-sm font-bold text-gray-800" x-text="(recommendation.program || 'Managed care') + ' — Prior Authorization path'"></p>
                        <p class="text-xs text-gray-500">MCO prior auth required. Request units now — the Authorizations Agent submits after approval.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">MCO plan *</label>
                                <select name="mco_name" x-model="form.mco_name" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                                    <option value="">Select MCO…</option>
                                    @foreach($mcoOptions as $mco)
                                        <option value="{{ $mco }}">{{ $mco }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">PA units (15-min) *</label>
                                <input type="number" name="pa_units" x-model="form.pa_units" min="1" max="9999"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                                <p class="text-[10px] text-gray-400 mt-1" x-show="form.pa_units">≈ <span x-text="Math.round(form.pa_units / 4)"></span> hrs/mo</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Assign caregiver (optional)</label>
                            <select name="assigned_employee_id" x-model="form.assigned_employee_id"
                                class="w-full px-4 py-3 rounded-xl border border-gray-100 dark:bg-white/[0.03] dark:text-white outline-none focus:ring-4 focus:ring-brand-500/10">
                                <option value="">Decide later</option>
                                @foreach($caregivers as $cg)
                                    <option value="{{ $cg->id }}">{{ $cg->first_name }} {{ $cg->last_name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-gray-400 mt-1">If selected, a background-check verification task is queued for the agent.</p>
                        </div>
                    </div>
                </div>

                {{-- Step 5 · Review & create client --}}
                <div x-show="step === 4" x-cloak class="space-y-4">
                    <div class="rounded-2xl border border-gray-100 dark:border-white/[0.05] divide-y divide-gray-50 dark:divide-white/[0.03]">
                        <template x-for="row in reviewRows()" :key="row[0]">
                            <div class="flex items-center justify-between px-5 py-3">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest" x-text="row[0]"></span>
                                <span class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="row[1] || '—'"></span>
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-gray-500">Finishing creates the client in the registry, marks the intake <strong>Converted</strong>, and queues agent follow-ups (compliance documents<span x-show="form.program_track !== 'dhs'">, PA submission</span><span x-show="form.assigned_employee_id">, background check</span>).</p>
                </div>

                <div class="flex items-center justify-between gap-3 mt-8 pt-6 border-t border-gray-50 dark:border-white/[0.05]">
                    <button type="button" x-show="step > 0" @click="step--"
                        class="px-5 py-3 text-sm font-semibold text-gray-500 bg-gray-50 dark:bg-white/[0.02] rounded-xl hover:bg-gray-100">Back</button>
                    <span x-show="step === 0"></span>
                    <button type="button" x-show="step < 4" x-cloak @click="next()"
                        class="px-6 py-3 text-sm font-semibold text-white bg-brand-500 rounded-xl hover:bg-brand-600 ml-auto">Continue</button>
                    <button type="submit" x-show="step === 4" x-cloak
                        class="px-6 py-3 text-sm font-semibold text-white bg-brand-500 rounded-xl hover:bg-brand-600 shadow-lg shadow-brand-500/20 ml-auto">
                        Create client from intake
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function intakeWizard() {
            return {
                step: 0,
                steps: ['Scan', 'Verify', 'Eligibility', 'Program', 'Create'],
                scanningKey: '',
                scanError: '',
                scanData: null,
                scannedDocuments: [],
                checking: false,
                eligibility: { status: '', note: '', checked_at: '', live: false },
                recommendation: { program: '', program_track: '', reason: '', billing_mode: '' },
                form: {
                    first_name: '', last_name: '', dob: '', phone: '', email: '',
                    member_id: '', address: '', mco_name: '', source: '',
                    coverage_type_id: '', recommended_program: '', program_track: 'dhs',
                    hours_per_week: 28, pa_units: 480, assigned_employee_id: '',
                },

                init() {
                    window.addEventListener('id-scanned', (e) => this.applyScanFields(e.detail || {}));
                },

                hasScan(key) {
                    return this.scannedDocuments.some(d => d.slot === key);
                },

                applyScanFields(fields) {
                    if (fields.first_name) this.form.first_name = fields.first_name;
                    if (fields.last_name) this.form.last_name = fields.last_name;
                    if (fields.date_of_birth) this.form.dob = window.idScanDob(fields.date_of_birth);
                    if (fields.id_number && !this.form.member_id) this.form.member_id = fields.id_number;
                    const addr = window.idScanAddress(fields);
                    if (addr) this.form.address = addr;
                },

                mergeFieldsFromResult(result) {
                    const f = result?.fields || result?.extracted || {};
                    this.applyScanFields(f);
                    if (f.member_id) this.form.member_id = f.member_id;
                    if (result?.mco_name) this.form.mco_name = result.mco_name;
                },

                async scanDocument(slotKey, event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    this.scanningKey = slotKey;
                    this.scanError = '';
                    const body = new FormData();
                    body.append(slotKey === 'referral' ? 'file' : 'image', file);
                    const url = slotKey === 'referral' ? @js($recognizeDocumentUrl) : @js($scanIdUrl);
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
                            body,
                        });
                        const data = await res.json();
                        if (!res.ok || !data.ok) {
                            this.scanError = (data.error || 'Scan failed for ' + slotKey) + '. You can enter details manually.';
                            return;
                        }
                        const result = data.result || {};
                        this.scannedDocuments.push({ slot: slotKey, filename: file.name, captured_at: new Date().toISOString(), result });
                        this.scanData = result;
                        this.mergeFieldsFromResult(result);
                        if (this.step === 0 && this.scannedDocuments.length >= 1) {
                            this.step = 1;
                        }
                    } catch (e) {
                        this.scanError = 'Scan failed — check your connection or enter manually.';
                    } finally {
                        this.scanningKey = '';
                        if (event.target) event.target.value = '';
                    }
                },

                async runEligibility() {
                    this.checking = true;
                    try {
                        const res = await fetch(@js(route('intakes.check-eligibility')), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': @js(csrf_token()),
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                dob: this.form.dob,
                                member_id: this.form.member_id,
                                mco_name: this.form.mco_name,
                            }),
                        });
                        const data = await res.json();
                        if (res.ok && data.ok) {
                            this.eligibility = data.eligibility;
                            this.recommendation = data.recommendation;
                            this.form.recommended_program = data.recommendation.program || '';
                            this.form.program_track = data.recommendation.program_track || 'dhs';
                            if (data.recommendation.coverage_type_id) {
                                this.form.coverage_type_id = String(data.recommendation.coverage_type_id);
                            }
                            if (this.form.program_track === 'dhs') {
                                this.form.mco_name = '';
                            }
                        }
                    } finally {
                        this.checking = false;
                    }
                },

                eligibilityLabel() {
                    return { eligible: 'Eligible', needs_verification: 'Needs verification', ineligible: 'Not eligible' }[this.eligibility.status] || '';
                },

                next() {
                    if (this.step === 1 && (!this.form.first_name || !this.form.last_name)) {
                        alert('First and last name are required.');
                        return;
                    }
                    if (this.step === 2) {
                        if (!this.eligibility.status) {
                            this.runEligibility();
                        } else if (this.eligibility.status === 'ineligible') {
                            alert('Applicant is ineligible — you cannot continue.');
                            return;
                        }
                    }
                    if (this.step === 3) {
                        if (this.form.program_track !== 'dhs' && (!this.form.mco_name || !this.form.pa_units)) {
                            alert('MCO plan and PA units are required for managed-care programs.');
                            return;
                        }
                    }
                    this.step++;
                },

                reviewRows() {
                    const path = this.form.program_track === 'dhs'
                        ? 'Time/Task · ' + (this.form.hours_per_week || 28) + ' hrs/wk · no PA'
                        : (this.recommendation.program || 'Managed care') + ' · ' + (this.form.pa_units || '—') + ' PA units';
                    return [
                        ['Documents scanned', this.scannedDocuments.length + ' file(s)'],
                        ['Name', (this.form.first_name + ' ' + this.form.last_name).trim()],
                        ['Medicaid ID', this.form.member_id],
                        ['Eligibility', this.eligibilityLabel()],
                        ['Program', this.recommendation.program || this.form.recommended_program],
                        ['Authorization path', path],
                        ['Source', this.form.source],
                    ];
                },
            };
        }
    </script>
@endsection
