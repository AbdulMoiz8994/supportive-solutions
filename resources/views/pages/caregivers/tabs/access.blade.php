@php $c = $caregiver; @endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    {{-- CHAMPS / MILogin credentials --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-[15px] font-bold text-[#1e293b]">CHAMPS / MILogin</h3>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700">Linked</span>
        </div>
        <div class="space-y-4">
            @include('pages.caregivers.tabs._kv', ['label'=>'MILogin User ID','value'=>$c->milogin_user_id])
            <div class="space-y-1.5">
                <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">Password</label>
                <div class="px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[13px] font-semibold text-[#1e293b] flex items-center justify-between">
                    <span>••••••••••</span><span class="text-[11px] font-bold text-blue-600 cursor-pointer">🔒 In vault</span>
                </div>
            </div>
            @include('pages.caregivers.tabs._kv', ['label'=>'CHAMPS Provider ID','value'=>$c->champs_provider_id])
            <div class="bg-blue-50/60 border border-blue-100 rounded-xl px-4 py-3 text-[12px] text-blue-700/90">
                The CHAMPS agent signs in with these stored credentials (RPA) to submit enrollment, pull approval letters, and re-run monitoring. Every credential use is logged to the Audit Trail.
            </div>
        </div>
    </div>

    {{-- App access --}}
    <div class="bg-white rounded-[20px] border border-[#e2e8f0] p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-[15px] font-bold text-[#1e293b]">SSHC Caregiver App</h3>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-green-100 text-green-700">Active</span>
        </div>
        <div class="space-y-3">
            @foreach([
                ['App invite', 'Sent '.($c->activated_at?->format('M j, Y') ?? '—'), 'Accepted'],
                ['Language', $c->preferred_language ?? 'English', 'Set'],
                ['Clock-in / out (EVV)', $c->evv_exempt ? 'N/A — live-in exempt' : 'HHAeXchange', $c->evv_exempt ? 'Exempt' : 'On'],
                ['Compliance form submission', 'Monthly via app', 'Enabled'],
                ['Pay stubs / My Pay', 'Direct deposit '.($c->direct_deposit_last4 ? '••••'.$c->direct_deposit_last4 : '—'), 'On'],
                ['Push + SMS notifications', 'RingCentral · '.($c->preferred_language ?? 'English'), 'On'],
            ] as [$item, $sub, $st])
            <div class="flex items-center justify-between px-4 py-3 rounded-lg border border-[#f1f5f9]">
                <div><p class="text-[12.5px] font-bold text-[#1e293b]">{{ $item }}</p><p class="text-[11px] text-[#94a3b8]">{{ $sub }}</p></div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-600">{{ $st }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

