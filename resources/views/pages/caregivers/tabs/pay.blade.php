@php
    $c = $caregiver;
    $pays = $c->payRecords->sortByDesc('id')->values();
    $paid = $pays->where('status', 'Paid');
    $ytd = $paid->sum('gross');
    $lastPaid = $paid->sortByDesc('paid_date')->first();
    $awaiting = $pays->firstWhere('status', 'Awaiting form');
@endphp

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-[18px] border border-[#e2e8f0] p-5"><p class="text-[11px] font-bold text-[#94a3b8]">Hourly wage</p><p class="text-[26px] font-black text-[#1e293b] mt-1.5">${{ number_format((float)($c->hourly_wage ?? 0), 2) }}</p><p class="text-[11px] text-[#94a3b8] mt-1">W-2 · per hour</p></div>
    <div class="bg-white rounded-[18px] border border-[#e2e8f0] p-5"><p class="text-[11px] font-bold text-[#94a3b8]">Gross paid YTD</p><p class="text-[26px] font-black text-green-600 mt-1.5">${{ number_format($ytd, 0) }}</p><p class="text-[11px] text-[#94a3b8] mt-1">{{ $paid->count() }} cycles</p></div>
    <div class="bg-white rounded-[18px] border border-[#e2e8f0] p-5"><p class="text-[11px] font-bold text-[#94a3b8]">Last pay</p><p class="text-[26px] font-black text-[#1e293b] mt-1.5">${{ $lastPaid ? number_format($lastPaid->gross, 0) : '—' }}</p><p class="text-[11px] text-[#94a3b8] mt-1">{{ $lastPaid ? $lastPaid->period.' · paid '.$lastPaid->paid_date?->format('M j') : '—' }}</p></div>
    <div class="bg-white rounded-[18px] border border-[#e2e8f0] p-5"><p class="text-[11px] font-bold text-[#94a3b8]">Next pay</p><p class="text-[22px] font-black text-[#1e293b] mt-2">{{ $awaiting->period ?? '—' }}</p><p class="text-[11px] text-[#94a3b8] mt-1">{{ $awaiting ? 'Awaiting form' : 'Up to date' }}</p></div>
</div>

{{-- Pay cycle banner --}}
<div class="bg-orange-50/50 border border-orange-200 rounded-[20px] p-6 mb-5">
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <span class="w-11 h-11 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center shrink-0"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
            <div>
                <h3 class="text-[15px] font-bold text-orange-700">Pay cycle — {{ $awaiting->period ?? now()->format('F Y') }}</h3>
                <p class="text-[12px] text-orange-600/80 mt-0.5">Pay follows the compliance form through the grace window into the AccountantsWorld batch.</p>
            </div>
        </div>
        <span class="text-[12px] font-bold text-orange-600">Awaiting {{ Str::before($awaiting->period ?? '', ' ') }} compliance form</span>
    </div>
    <div class="flex flex-wrap items-center gap-2 mt-5 text-[11px] font-bold text-[#64748b]">
        @foreach(['Compliance form received','~10-day grace window (anti-fraud hold)','Batch built in AccountantsWorld (1st Tue)','Paid that Friday'] as $i => $stepLabel)
            <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-full {{ $i===0 ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center">{{ $i===0 ? '✓' : $i+1 }}</span> {{ $stepLabel }}</span>
            @if(!$loop->last)<span class="text-[#cbd5e1]">›</span>@endif
        @endforeach
    </div>
    <div class="mt-4 bg-white/70 border border-orange-200 rounded-xl px-4 py-3 text-[11px] text-orange-700">
        ⏳ <b>The ~10-day grace window is intentional.</b> Pay isn't released the moment the form arrives — it's held so we don't pay for services that could later be denied. Late forms roll to the next week's batch.
    </div>
</div>

{{-- Pay history --}}
<div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6 mb-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-[15px] font-bold text-[#1e293b]">Pay History</h3>
        <span class="text-[11px] font-bold text-[#94a3b8]">Hours from the monthly compliance form · stored 7 years</span>
    </div>
    <table class="w-full text-[12px]">
        <thead><tr class="text-[10px] font-black text-[#94a3b8] uppercase border-b border-[#f1f5f9]">
            <th class="py-2 text-left">Pay Period</th><th class="py-2 text-left">Client</th><th class="py-2 text-left">Hours</th><th class="py-2 text-left">Rate</th><th class="py-2 text-left">Gross</th><th class="py-2 text-left">Status</th><th class="py-2 text-left">Paid</th><th class="py-2 text-right">Stub</th>
        </tr></thead>
        <tbody class="divide-y divide-[#f1f5f9]">
            @foreach($pays as $p)
            <tr>
                <td class="py-2.5 font-bold text-[#1e293b]">{{ $p->period }}</td>
                <td class="py-2.5 text-[#475569]">{{ optional($p->client)->first_name }} {{ optional($p->client)->last_name }}</td>
                <td class="py-2.5 text-[#475569]">{{ $p->hours ? (int)$p->hours : '—' }}</td>
                <td class="py-2.5 text-[#475569]">${{ number_format((float)$p->rate, 2) }}</td>
                <td class="py-2.5 font-bold text-[#1e293b]">{{ $p->gross ? '$'.number_format($p->gross, 2) : '—' }}</td>
                <td class="py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $p->status === 'Paid' ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500' }}">{{ $p->status }}</span></td>
                <td class="py-2.5 text-[#475569]">{{ $p->paid_date?->format('M j') ?? '—' }}</td>
                <td class="py-2.5 text-right">
                    @if($p->status === 'Paid' && $p->stub_path)
                        <span class="text-blue-600 font-bold cursor-pointer">View · ↓</span>
                    @elseif($p->id)
                        <a href="{{ route('payroll.show', $p) }}" class="text-blue-600 font-bold hover:underline">View</a>
                    @else
                        Pending
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p class="text-[11px] text-[#94a3b8] mt-3">April: 108 hrs (3 hospital days excluded) — matches the compliance form. Same hours bill on the client's claim at $30/hr.</p>
</div>

{{-- Pay setup --}}
<div class="bg-[#eff6ff] rounded-[20px] border border-blue-100/50 p-6">
    <h3 class="text-[15px] font-bold text-[#1e293b] mb-5">Pay Setup</h3>

    {{-- Payroll P4 — manual "Set up in payroll portal" checkoff --}}
    @php $portalSet = $c->payroll_portal_setup_at !== null; @endphp
    <div class="mb-5 rounded-2xl border p-4 {{ $portalSet ? 'bg-green-50/60 border-green-200' : 'bg-white border-[#e2e8f0]' }}">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $portalSet ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-400' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <div>
                    <h4 class="text-[13px] font-bold {{ $portalSet ? 'text-green-700' : 'text-[#1e293b]' }}">Set up in payroll portal {{ $portalSet ? '✓' : '' }}</h4>
                    @if($portalSet)
                        <p class="text-[11px] text-green-700/80 mt-0.5">Marked by {{ $c->payrollPortalSetupByUser?->name ?? 'staff' }} on {{ $c->payroll_portal_setup_at->format('M j, Y · g:i A') }} — filing status + direct deposit entered in the external payroll portal.</p>
                    @else
                        <p class="text-[11px] text-[#64748b] mt-0.5">Not yet set up. An AI agent or staff creates this caregiver in the external payroll portal (filing status + direct deposit), then checks this off here.</p>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('caregivers.payroll-portal-setup', $c->id) }}" class="shrink-0">
                @csrf
                @if($portalSet)
                    <input type="hidden" name="undo" value="1">
                    <button type="submit" class="text-[11px] font-semibold text-[#64748b] hover:text-[#475569] underline">Undo</button>
                @else
                    <button type="submit" class="inline-flex items-center h-8 px-3 text-[12px] font-semibold text-white bg-[#2563eb] rounded-lg hover:bg-[#1d4ed8] whitespace-nowrap">Mark set up ✓</button>
                @endif
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @include('pages.caregivers.tabs._kv', ['label'=>'Hourly Wage','value'=>'$'.number_format((float)($c->hourly_wage ?? 0),2).' / hour'])
        @include('pages.caregivers.tabs._kv', ['label'=>'Classification','value'=>$c->classification])
        @include('pages.caregivers.tabs._kv', ['label'=>'Pay Schedule','value'=>$c->pay_schedule])
        @include('pages.caregivers.tabs._kv', ['label'=>'Payroll System','value'=>$c->payroll_system])
        @include('pages.caregivers.tabs._kv', ['label'=>'Payroll Portal Setup','value'=> $c->payroll_portal_setup_at ? 'Set up ✓ · '.$c->payroll_portal_setup_at->format('M j, Y') : 'Not set up'])
        @include('pages.caregivers.tabs._kv', ['label'=>'W-4 Filing Status','value'=>$c->w4_filing_status])
        @include('pages.caregivers.tabs._kv', ['label'=>'Direct Deposit','value'=>$c->direct_deposit_last4 ? '•••• '.$c->direct_deposit_last4.' · Routing on file' : '—'])
        @include('pages.caregivers.tabs._kv', ['label'=>'Insurance','value'=>$c->insurance_coverage])
        @include('pages.caregivers.tabs._kv', ['label'=>'Pay-Eligibility Start','value'=>$c->pay_eligibility_start?->format('M j, Y').' (later of case start & CHAMPS assoc.)'])
    </div>
    <div class="mt-4 bg-blue-50/60 border border-blue-100 rounded-xl px-4 py-3 text-[11px] text-blue-700/90">⏳ Hours come straight from the Compliance Forms tab (no clock-in for live-in). Wage changes are logged to the Audit Trail and reflected on the next batch.</div>
</div>

