@php $c = $caregiver; @endphp
<div x-data="{ editing: false }" x-on:edit-personal.window="editing = true">
    <form method="POST" action="{{ route('caregivers.update', $c->id) }}">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- Personal Information --}}
            <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-[15px] font-bold text-[#1e293b]">Personal Information</h3>
                    <button type="button" @click="editing = !editing" class="flex items-center gap-1.5 text-[12px] font-bold text-blue-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span x-text="editing ? 'Editing' : 'Edit'"></span>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" x-bind:class="editing || ''">
                    <template x-if="!editing">
                        <div class="contents">
                            @include('pages.caregivers.tabs._kv', ['label'=>'First Name *','value'=>$c->first_name])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Last Name *','value'=>$c->last_name])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Date of Birth *','value'=>$c->date_of_birth ? $c->date_of_birth->format('m / d / Y').' ('.$c->date_of_birth->age.')' : '—'])
                            @include('pages.caregivers.tabs._kv', ['label'=>'SSN *','value'=>$c->ssn_last4 ? '•••-••-'.$c->ssn_last4 : '—'])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Gender','value'=>$c->gender])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Phone Number *','value'=>$c->phone])
                            <div class="md:col-span-2">@include('pages.caregivers.tabs._kv', ['label'=>'Address','value'=>$c->address])</div>
                            @include('pages.caregivers.tabs._kv', ['label'=>'County','value'=>$c->county])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Email','value'=>$c->email])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Preferred Language','value'=>$c->preferred_language])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Requires Translator?','value'=>$c->preferred_language && $c->preferred_language !== 'English' ? 'Yes' : 'No'])
                        </div>
                    </template>
                    <template x-if="editing">
                        <div class="contents">
                            @include('pages.caregivers.tabs._edit', ['label'=>'First Name *','name'=>'first_name','value'=>$c->first_name])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Last Name *','name'=>'last_name','value'=>$c->last_name])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Phone Number *','name'=>'phone','value'=>$c->phone])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Gender','name'=>'gender','value'=>$c->gender])
                            <div class="md:col-span-2">@include('pages.caregivers.tabs._edit', ['label'=>'Address','name'=>'address','value'=>$c->address,'attrs'=>'data-gmaps'])</div>
                            @include('pages.caregivers.tabs._edit', ['label'=>'County','name'=>'county','value'=>$c->county])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Email','name'=>'email','value'=>$c->email])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Preferred Language','name'=>'preferred_language','value'=>$c->preferred_language])
                        </div>
                    </template>
                </div>
            </div>

            {{-- CHAMPS & Provider --}}
            <div class="bg-white rounded-[20px] border border-blue-200 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-[15px] font-bold text-[#1e293b]">CHAMPS &amp; Provider</h3>
                    <span class="text-[12px] font-bold text-blue-600">Editing</span>
                </div>
                <div class="space-y-4">
                    @include('pages.caregivers.tabs._kv', ['label'=>'CHAMPS Provider ID','value'=>$c->champs_provider_id])
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">CHAMPS Status</label>
                        <div class="px-4 py-2.5 bg-green-50 border border-green-200 rounded-xl text-[12px] font-bold text-green-700">{{ $c->champs_status ?? 'Pending' }}</div>
                    </div>
                    @include('pages.caregivers.tabs._kv', ['label'=>'Association Date','value'=>$c->champs_association_date?->format('F j, Y')])
                    @include('pages.caregivers.tabs._kv', ['label'=>'MILogin User ID','value'=>$c->milogin_user_id ? $c->milogin_user_id.' •••••' : '—'])
                    <div class="space-y-1.5">
                        <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">AI-Agent Access (Credentials)</label>
                        <div class="px-4 py-2.5 bg-blue-50 border border-blue-100 rounded-xl text-[12px] font-semibold text-blue-700">🔒 Linked for the CHAMPS agent · <span class="underline cursor-pointer">Manage on Apps &amp; Access ›</span></div>
                    </div>
                </div>
            </div>

            {{-- Employment --}}
            <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
                <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Employment</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <template x-if="!editing">
                        <div class="contents">
                            @include('pages.caregivers.tabs._kv', ['label'=>'Caregiver Type','value'=>$c->caregiver_type])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Employment Status','value'=>($c->classification ?? 'W-2 employee').' · '.$c->status])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Hire Date','value'=>$c->hire_date?->format('F j, Y')])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Hourly Wage','value'=>'$'.number_format((float)($c->hourly_wage ?? 0),2).' / hour'])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Pay Schedule','value'=>$c->pay_schedule])
                            @include('pages.caregivers.tabs._kv', ['label'=>'W-4 Filing Status','value'=>$c->w4_filing_status])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Direct Deposit','value'=>$c->direct_deposit_last4 ? '•••• '.$c->direct_deposit_last4.' · Routing on file' : '—'])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Insurance Coverage','value'=>$c->insurance_coverage])
                        </div>
                    </template>
                    <template x-if="editing">
                        <div class="contents">
                            @include('pages.caregivers.tabs._edit', ['label'=>'Caregiver Type','name'=>'caregiver_type','value'=>$c->caregiver_type])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Hourly Wage','name'=>'hourly_wage','value'=>$c->hourly_wage,'type'=>'number'])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Pay Schedule','name'=>'pay_schedule','value'=>$c->pay_schedule])
                            @include('pages.caregivers.tabs._edit', ['label'=>'W-4 Filing Status','name'=>'w4_filing_status','value'=>$c->w4_filing_status])
                            <div class="md:col-span-2">@include('pages.caregivers.tabs._edit', ['label'=>'Insurance Coverage','name'=>'insurance_coverage','value'=>$c->insurance_coverage])</div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Live-In & EVV + At a glance --}}
            <div class="space-y-5">
                <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
                    <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Live-In &amp; EVV</h3>
                    <div class="space-y-4">
                        @include('pages.caregivers.tabs._kv', ['label'=>'Lives With Client','value'=>$c->lives_with_client ? 'Yes — same address as '.($servedClient->first_name ?? 'client').' '.($servedClient->last_name ?? '') : 'No'])
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">Live-In Attestation (BPHASA-2421)</label>
                            <div class="px-4 py-2.5 bg-green-50 border border-green-200 rounded-xl text-[12px] font-bold text-green-700">{{ $c->attestation_status ?? '—' }}</div>
                        </div>
                        @include('pages.caregivers.tabs._kv', ['label'=>'EVV (HHAeXchange)','value'=>$c->evv_exempt ? 'Exempt — no clock-in / out' : 'Active'])
                    </div>
                </div>

                <div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/60 p-6">
                    <h3 class="text-[15px] font-bold text-[#1e293b] mb-4">At a glance</h3>
                    <div class="space-y-3 text-[12px] text-[#475569]">
                        <p>📋 <b>Serves:</b> {{ $servedClient->first_name ?? '—' }} {{ $servedClient->last_name ?? '' }} ({{ $assignment->relationship ?? '—' }} · {{ $assignment->program ?? 'MICH' }}) since {{ $c->hire_date?->format('M j, Y') ?? '—' }}</p>
                        <p>🛡️ <b>Background checks:</b> {{ $caregiver->backgroundChecks->whereIn('status',['Clear','On file','Exempted'])->count() }} current ({{ $caregiver->backgroundChecks->pluck('label')->take(4)->implode(', ') }})</p>
                        <p>🗓️ <b>Compliance ({{ now()->format('M') }}):</b> {{ optional($caregiver->complianceForms->firstWhere('status','Due'))->period_label ? 'form not yet submitted — wellness call scheduled' : 'on track' }}</p>
                        <p>💵 <b>Pay:</b> ${{ rtrim(rtrim(number_format((float)($c->hourly_wage ?? 0),2),'0'),'.') }}/hr · last paid {{ optional($caregiver->payRecords->firstWhere('status','Paid'))->period ?? '—' }} batch · no holds</p>
                    </div>
                </div>
            </div>

            {{-- Emergency Contact --}}
            <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6 lg:col-span-2">
                <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Emergency Contact</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <template x-if="!editing">
                        <div class="contents">
                            @include('pages.caregivers.tabs._kv', ['label'=>'Name','value'=>$c->emergency_contact_name])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Relationship','value'=>$c->emergency_contact_relationship])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Phone','value'=>$c->emergency_contact_phone])
                            @include('pages.caregivers.tabs._kv', ['label'=>'Email','value'=>$c->emergency_contact_email])
                        </div>
                    </template>
                    <template x-if="editing">
                        <div class="contents">
                            @include('pages.caregivers.tabs._edit', ['label'=>'Name','name'=>'emergency_contact_name','value'=>$c->emergency_contact_name])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Relationship','name'=>'emergency_contact_relationship','value'=>$c->emergency_contact_relationship])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Phone','name'=>'emergency_contact_phone','value'=>$c->emergency_contact_phone])
                            @include('pages.caregivers.tabs._edit', ['label'=>'Email','name'=>'emergency_contact_email','value'=>$c->emergency_contact_email,'type'=>'email'])
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div x-show="editing" x-cloak class="flex justify-end gap-3 mt-5">
            <button type="button" @click="editing=false" class="px-5 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[12px] font-bold text-[#475569]">Cancel</button>
            <button type="submit" class="px-6 py-2.5 bg-[#2563eb] text-white rounded-xl text-[12px] font-bold shadow-lg shadow-blue-100">Save changes</button>
        </div>
    </form>

    </div>
