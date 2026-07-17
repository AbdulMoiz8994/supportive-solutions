@php
    $c = $caregiver;
    $timeline = [
        ['Application & policies signed', $c->application_signed_at?->format('M j, Y') . ' · verbal review completed', 'blue'],
        ['Background checks kicked off', ($c->application_signed_at?->format('M j')) . ' · SAM & OIG clear; ICHAT submitted; CHAMPS enrolling', 'gray'],
        ['CHAMPS enrolled & associated to SSHC', $c->champs_association_date?->format('M j, Y') . ' · Provider ID ' . ($c->champs_provider_id ?? '—'), 'gray'],
        ['ICHAT cleared', optional($c->backgroundChecks->firstWhere('type','ICHAT'))->last_run?->format('M j, Y'), 'gray'],
        ['Live-In Attestation (BPHASA-2421) approved', ($c->attestation_expires_at ? 'EVV exempt through '.$c->attestation_expires_at->format('M Y') : '—'), 'gray'],
        ['Activated & linked to ' . ($servedClient->first_name ?? 'client') . ' ' . ($servedClient->last_name ?? ''), $c->activated_at?->format('M j, Y') . ' · approved by ' . ($c->onboarded_by ?? 'Owner'), 'gray'],
    ];
    $packet = [
        ['Employment Application + Policies Agreement','Signed','green'],
        ['MSA-204 — CHAMPS Enrollment Authorization','Complete','green'],
        ['I-9 — Employment Eligibility','Complete','green'],
        ['W-4 — Withholding','Complete','green'],
        ['Direct Deposit Agreement','Complete','green'],
        ['Insurance Coverage Waiver','Signed','green'],
        ['BPHASA-2421 — Live-In Attestation','Approved','green'],
        ['Copy of ID + SSN card','On file','green'],
    ];
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- Onboarding Summary --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Onboarding Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @include('pages.caregivers.tabs._kv', ['label'=>'How Recruited','value'=>$c->how_recruited])
            @include('pages.caregivers.tabs._kv', ['label'=>'Caregiver Type','value'=>$c->caregiver_type])
            @include('pages.caregivers.tabs._kv', ['label'=>'Application Signed','value'=>$c->application_signed_at?->format('F j, Y')])
            @include('pages.caregivers.tabs._kv', ['label'=>'Onboarded By','value'=>$c->onboarded_by])
            @include('pages.caregivers.tabs._kv', ['label'=>'Activated','value'=>$c->activated_at?->format('F j, Y')])
            <div class="space-y-1.5">
                <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">Status</label>
                <div class="px-4 py-2.5 bg-green-50 border border-green-200 rounded-xl text-[12px] font-bold text-green-700">{{ $c->status }}</div>
            </div>
        </div>
    </div>

    {{-- Enrollment timeline --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-[15px] font-bold text-[#1e293b]">Onboarding / Enrollment Status</h3>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700">Complete</span>
        </div>
        <div class="pl-2 space-y-5 relative before:content-[''] before:absolute before:left-[7px] before:top-2 before:bottom-2 before:w-[2px] before:bg-blue-100">
            @foreach($timeline as $i => [$title, $sub, $tone])
            <div class="relative flex items-start gap-4">
                <span class="w-3.5 h-3.5 rounded-full shrink-0 z-10 mt-1 {{ $i === 0 ? 'bg-blue-600' : 'bg-[#1e293b]' }}"></span>
                <div>
                    <p class="text-[13px] font-bold text-[#1e293b]">{{ $title }}</p>
                    <p class="text-[11px] text-[#94a3b8] mt-0.5">{{ $sub }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Eligibility & Disclosures --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Eligibility &amp; Disclosures</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @include('pages.caregivers.tabs._kv', ['label'=>'At Least 18','value'=>$c->is_18_plus ? 'Yes' : 'No'])
            @include('pages.caregivers.tabs._kv', ['label'=>'Work-Eligible (I-9)','value'=>$c->is_work_eligible ? 'Yes — verified' : 'No'])
            @include('pages.caregivers.tabs._kv', ['label'=>'Felony / Misdemeanor Disclosed','value'=>'No'])
            <div class="space-y-1.5">
                <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">Background-Check Consent</label>
                <div class="px-4 py-2.5 bg-green-50 border border-green-200 rounded-xl text-[12px] font-bold text-green-700">{{ $c->has_background_check ? 'Given' : 'Not given' }}</div>
            </div>
        </div>
    </div>

    {{-- Employment Packet --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Employment Packet</h3>
        <div class="space-y-2">
            @foreach($packet as [$doc, $st, $tone])
            <div class="flex items-center justify-between px-4 py-2.5 rounded-lg border border-[#f1f5f9]">
                <span class="text-[12.5px] font-semibold text-[#1e293b]">{{ $doc }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600">{{ $st }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Services they can provide --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6 lg:col-span-2">
        <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Services They Can Provide</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach(($c->services ?? []) as $svc)
            <label class="flex items-center gap-2.5 text-[13px] font-medium text-[#1e293b]">
                <span class="w-4 h-4 rounded bg-blue-600 flex items-center justify-center"><svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>
                {{ $svc }}
            </label>
            @endforeach
        </div>
        <p class="text-[11px] text-[#94a3b8] mt-4 bg-blue-50/60 rounded-lg px-4 py-2.5">Matches the services on {{ $servedClient->first_name ?? 'the client' }}'s authorization / Time &amp; Task. Hands-on tasks only.</p>
    </div>
</div>

